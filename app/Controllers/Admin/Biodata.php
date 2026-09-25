<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\BiodataForm;
use App\Libraries\BiodataLaporan;
use App\Libraries\BiodataVerifikasi;
use App\Models\AuditModel;
use App\Models\BiodataIsianModel;
use App\Models\KelasModel;
use App\Models\SettingModel;
use App\Models\SiswaModel;
use CodeIgniter\HTTP\RedirectResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Isian Biodata Siswa — sisi admin.
 *
 *   index     : saklar buka/tutup + tautan untuk dibagikan, angka progres,
 *               kotak masuk per status, daftar belum mengisi, rekap per kelas
 *   detail    : bandingkan Master Siswa (lama) vs isian siswa (baru)
 *   setujui   : tulis isian ke Master Siswa (satu / massal)
 *   kembalikan: minta siswa memperbaiki, dengan catatan
 *   hapus     : buang isian agar siswa mengisi dari nol
 *   laporan   : Excel siap cetak — kelengkapan biodata per kelas
 *
 * Semua aksi yang mengubah data memakai POST (tidak menambah utang CSRF-GET).
 */
class Biodata extends BaseController
{
    private const TAB = ['menunggu', 'perbaikan', 'disetujui', 'belum', 'kelas'];

    private const PER = 50;

    /** Batas satu kali "setujui massal" agar aman dari batas waktu eksekusi hosting. */
    private const MAKS_MASSAL = 300;

    private BiodataIsianModel $isian;
    private AuditModel $audit;

    public function __construct()
    {
        $this->isian = new BiodataIsianModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $tab = (string) $this->request->getGet('tab');
        if (! in_array($tab, self::TAB, true)) {
            $tab = 'menunggu';
        }
        $kelasId = (int) $this->request->getGet('kelas_id');
        $q       = trim((string) $this->request->getGet('q'));
        $page    = max(1, (int) $this->request->getGet('page'));
        $setting = (new SettingModel())->get();

        $rows  = [];
        $total = 0;
        if (in_array($tab, ['menunggu', 'perbaikan', 'disetujui'], true)) {
            [$rows, $total] = $this->isian->daftar($tab, $kelasId, $q, self::PER, $page);
        } elseif ($tab === 'belum') {
            // Satu kelas dipilih → tampilkan semua (untuk pesan WA); tanpa kelas → berhalaman.
            [$rows, $total] = $this->isian->belumMengisi($kelasId, $q, $kelasId > 0 ? 0 : self::PER, $page);
        }

        $pager = null;
        if ($total > self::PER && ! ($tab === 'belum' && $kelasId > 0)) {
            $pager = service('pager');
            $pager->store('default', $page, self::PER, $total);
        }

        $kelasOpts = (new KelasModel())->options();

        return view('admin/biodata/index', [
            'title'      => 'Isian Biodata Siswa',
            'tab'        => $tab,
            'kelasId'    => $kelasId,
            'kelasNama'  => $kelasOpts[$kelasId] ?? '',
            'q'          => $q,
            'rows'       => $rows,
            'total'      => $total,
            'pager'      => $pager,
            'mulai'      => ($page - 1) * self::PER,
            'ringkas'    => $this->isian->ringkasan(),
            'perKelas'   => $tab === 'kelas' ? $this->isian->perKelas() : [],
            'kelasOpts'  => $kelasOpts,
            'setting'    => $setting,
            'terbuka'    => BiodataIsianModel::formTerbuka($setting),
            'tautan'     => $this->tautanSubdomain(),
            'tautanAlt'  => site_url('biodata'),
            'maksMassal' => self::MAKS_MASSAL,
        ]);
    }

    /** POST — saklar buka/tutup + batas waktu. */
    public function pengaturan(): RedirectResponse
    {
        $buka   = $this->request->getPost('biodata_open') ? 1 : 0;
        $isian  = trim((string) $this->request->getPost('biodata_tutup'));
        $tutup  = null;
        if ($isian !== '') {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $isian);
            if ($dt === false) {
                return redirect()->back()->with('error', 'Format batas waktu tidak dikenali.');
            }
            $tutup = $dt->format('Y-m-d H:i:00');
        }

        (new SettingModel())->store(['biodata_open' => $buka, 'biodata_tutup' => $tutup]);
        $this->audit->record('update', 'settings', 1, 'Isian biodata: ' . ($buka ? 'DIBUKA' : 'DITUTUP')
            . ($tutup !== null ? ' s.d. ' . $tutup : ''));

        $pesan = $buka ? 'Form isian biodata DIBUKA.' : 'Form isian biodata DITUTUP.';
        if ($buka && $tutup !== null && strtotime($tutup) <= time()) {
            return redirect()->back()->with('error', 'Tersimpan, tetapi batas waktunya sudah lewat sehingga form tetap TERTUTUP bagi siswa. Kosongkan atau mundurkan batas waktunya.');
        }

        return redirect()->back()->with('success', $pesan);
    }

    /** @param int|string $id */
    public function detail($id)
    {
        $row = $this->isian->find((int) $id);
        if ($row === null) {
            return redirect()->to(site_url('admin/biodata'))->with('error', 'Isian tidak ditemukan.');
        }

        $siswa = (new SiswaModel())->withDeleted()->withRelations()->where('siswa.id', $row['siswa_id'])->first();
        $data  = BiodataIsianModel::decode($row);

        // Kolom pembanding "lama": untuk isian yang sudah disetujui, Master Siswa
        // sudah sama dengan isian — tampilkan potret SEBELUM disetujui.
        $sudahDisetujui = $row['status'] === 'disetujui';
        $lama           = $sudahDisetujui
            ? (json_decode((string) $row['data_sebelum'], true) ?: [])
            : array_intersect_key($siswa ?? [], array_flip(BiodataIsianModel::KOLOM));

        $bagian = [];
        $hitung = ['baru' => 0, 'ubah' => 0];
        foreach (BiodataForm::BAGIAN as $judul => $kolom) {
            foreach ($kolom as $k) {
                $vLama = $this->tampil($k, $lama[$k] ?? null);
                $vBaru = $this->tampil($k, $data[$k] ?? null);
                $tetap = $vBaru === '' && in_array($k, BiodataVerifikasi::KOSONG_JANGAN_TIMPA, true);
                $jenis = match (true) {
                    $vLama === $vBaru, $tetap => 'sama',
                    $vLama === ''             => 'baru',
                    default                   => 'ubah',
                };
                if ($jenis !== 'sama') {
                    $hitung[$jenis]++;
                }
                $bagian[$judul][] = [
                    'label' => BiodataForm::LABEL[$k],
                    'lama'  => $vLama,
                    'baru'  => $vBaru,
                    'jenis' => $jenis,
                    'tetap' => $tetap && $vLama !== '',
                ];
            }
        }

        $admin = null;
        if (! empty($row['diverifikasi_oleh'])) {
            $admin = db_connect()->table('admins')->select('full_name, username')
                ->where('id', (int) $row['diverifikasi_oleh'])->get()->getRowArray();
        }

        return view('admin/biodata/detail', [
            'title'     => 'Periksa Biodata',
            'row'       => $row,
            'siswa'     => $siswa,
            'bagian'    => $bagian,
            'hitung'    => $hitung,
            'peringatan' => $siswa !== null && ! $sudahDisetujui ? $this->peringatan($row, $data, $siswa) : [],
            'admin'     => $admin,
            'kelasId'   => (int) $this->request->getGet('kelas_id'),
        ]);
    }

    /** @param int|string $id */
    public function setujui($id): RedirectResponse
    {
        $id      = (int) $id;
        $kelasId = (int) $this->request->getPost('kelas_id');
        $hasil   = (new BiodataVerifikasi())->setujui($id, $this->adminId());
        if (! $hasil['ok']) {
            return redirect()->to($this->urlDetail($id, $kelasId))->with('error', 'Gagal menyetujui: ' . $hasil['pesan']);
        }
        master_data_changed('siswa');
        $this->audit->record('update', 'siswa', null, 'Setujui biodata: ' . $hasil['nama'] . ' (isian #' . $id . ')');

        return $this->keBerikutnya($id, $kelasId, 'Biodata ' . $hasil['nama'] . ' disetujui dan masuk Master Siswa.');
    }

    /** @param int|string $id */
    public function kembalikan($id): RedirectResponse
    {
        $id      = (int) $id;
        $kelasId = (int) $this->request->getPost('kelas_id');
        $hasil   = (new BiodataVerifikasi())->kembalikan($id, (string) $this->request->getPost('catatan'));
        if (! $hasil['ok']) {
            return redirect()->to($this->urlDetail($id, $kelasId))->with('error', $hasil['pesan']);
        }
        $this->audit->record('update', 'biodata_isian', $id, 'Kembalikan isian biodata untuk diperbaiki');

        return $this->keBerikutnya($id, $kelasId, $hasil['pesan']);
    }

    /** @param int|string $id */
    public function hapus($id): RedirectResponse
    {
        $id    = (int) $id;
        $hasil = (new BiodataVerifikasi())->hapus($id);
        if ($hasil['ok']) {
            $this->audit->record('delete', 'biodata_isian', $id, 'Hapus isian biodata');
        }

        return redirect()->to(site_url('admin/biodata'))->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    /**
     * GET — laporan kelengkapan biodata (Excel siap cetak): lembar REKAP per
     * kelas + satu lembar daftar nama per kelas. ?kelas_id= untuk satu kelas.
     */
    public function laporan(): void
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        $kelas   = BiodataLaporan::data($kelasId);
        $ss      = BiodataLaporan::excel($kelas, (new SettingModel())->get());

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . BiodataLaporan::namaBerkas($kelas, $kelasId) . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /** POST — setujui banyak isian: mode=selected (ids[]) atau mode=all (sesuai saringan). */
    public function setujuiMassal(): RedirectResponse
    {
        $kelasId = (int) $this->request->getPost('kelas_id');
        $q       = trim((string) $this->request->getPost('q'));
        if ($this->request->getPost('mode') === 'all') {
            $ids = $this->isian->idMenunggu($kelasId, $q, self::MAKS_MASSAL);
        } else {
            $ids = array_slice(array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids')))), 0, self::MAKS_MASSAL);
        }
        $kembali = site_url('admin/biodata') . '?' . http_build_query(array_filter(['tab' => 'menunggu', 'kelas_id' => $kelasId ?: '', 'q' => $q]));
        if ($ids === []) {
            return redirect()->to($kembali)->with('error', 'Tidak ada isian yang dipilih.');
        }

        $verif = new BiodataVerifikasi();
        $ok    = 0;
        $gagal = [];
        foreach ($ids as $id) {
            $h = $verif->setujui($id, $this->adminId());
            if ($h['ok']) {
                $ok++;
            } else {
                $siswa   = db_connect()->table('biodata_isian b')->select('s.nama')->join('siswa s', 's.id = b.siswa_id')
                    ->where('b.id', $id)->get()->getRowArray();
                $gagal[] = ($siswa['nama'] ?? '#' . $id) . ' — ' . $h['pesan'];
            }
        }
        if ($ok > 0) {
            master_data_changed('siswa');
            $this->audit->record('update', 'siswa', null, "Setujui massal biodata: {$ok} disetujui, " . count($gagal) . ' gagal');
        }

        $sisa  = $this->isian->ringkasan()['menunggu'];
        $pesan = "{$ok} biodata disetujui." . ($sisa > 0 ? " Masih ada {$sisa} isian menunggu." : '');
        if ($gagal !== []) {
            return redirect()->to($kembali)->with('error', $pesan . ' ' . count($gagal) . ' gagal: ' . implode('; ', array_slice($gagal, 0, 5)) . (count($gagal) > 5 ? '; …' : ''));
        }

        return redirect()->to($kembali)->with('success', $pesan);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** Tautan subdomain yang dibagikan ke siswa (skema mengikuti baseURL). */
    private function tautanSubdomain(): string
    {
        $skema = parse_url(config('App')->baseURL, PHP_URL_SCHEME) ?: 'https';

        return $skema . '://' . config('Biodata')->host;
    }

    /** Nilai siap tampil (tanggal Indonesia, JK lengkap, kosong = ''). */
    private function tampil(string $k, mixed $v): string
    {
        $v = trim((string) ($v ?? ''));
        if ($v === '') {
            return '';
        }
        if ($k === 'jenis_kelamin') {
            return ['L' => 'Laki-laki', 'P' => 'Perempuan'][$v] ?? $v;
        }
        if (in_array($k, ['tanggal_lahir', 'diterima_tanggal'], true) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

            return (int) $m[3] . ' ' . $bulan[(int) $m[2]] . ' ' . $m[1];
        }

        return $v;
    }

    /**
     * Hal yang perlu diperhatikan sebelum menyetujui.
     *
     * @return list<string>
     */
    private function peringatan(array $row, array $data, array $siswa): array
    {
        $out = [];
        $db  = db_connect();
        if (! empty($data['nisn'])) {
            $lain = $db->table('siswa')->select('nama')->where('nisn', $data['nisn'])
                ->where('id !=', (int) $siswa['id'])->where('deleted_at', null)->get()->getRowArray();
            if ($lain !== null) {
                $out[] = 'NISN ' . $data['nisn'] . ' sudah tercatat atas nama ' . $lain['nama'] . ' di Master Siswa — persetujuan akan DITOLAK sampai salah satunya dibetulkan.';
            }
        }
        if (! empty($data['nis'])) {
            $lain = $db->table('siswa')->select('nama')->where('nis', $data['nis'])
                ->where('id !=', (int) $siswa['id'])->where('deleted_at', null)->get()->getRowArray();
            if ($lain !== null) {
                $out[] = 'NIS ' . $data['nis'] . ' sudah tercatat atas nama ' . $lain['nama'] . ' di Master Siswa — persetujuan akan DITOLAK sampai salah satunya dibetulkan.';
            }
        }
        if (mb_strtolower(trim((string) ($data['nama'] ?? ''))) !== mb_strtolower(trim((string) $siswa['nama']))) {
            $out[] = 'Nama di isian berbeda dengan nama di Master Siswa ("' . $siswa['nama'] . '"). Pastikan siswa tidak salah memilih nama temannya.';
        }
        if (($data['jenis_kelamin'] ?? '') !== '' && ($siswa['jenis_kelamin'] ?? '') !== '' && $data['jenis_kelamin'] !== $siswa['jenis_kelamin']) {
            $out[] = 'Jenis kelamin di isian berbeda dengan data sekolah — kemungkinan salah pilih nama.';
        }
        if ((int) $row['kirim_ke'] > 1) {
            $out[] = 'Ini kiriman ke-' . (int) $row['kirim_ke'] . ' (perbaikan dari siswa).';
        }

        return $out;
    }

    /** Setelah memutuskan satu isian, langsung buka antrean berikutnya. */
    private function keBerikutnya(int $id, int $kelasId, string $pesan): RedirectResponse
    {
        $next = $this->isian->menungguBerikutnya($id, $kelasId);
        if ($next !== null) {
            return redirect()->to($this->urlDetail((int) $next['id'], $kelasId))->with('success', $pesan . ' Berikut isian selanjutnya.');
        }

        return redirect()->to(site_url('admin/biodata') . ($kelasId > 0 ? '?kelas_id=' . $kelasId : ''))
            ->with('success', $pesan . ' Antrean menunggu sudah habis.');
    }

    private function urlDetail(int $id, int $kelasId): string
    {
        return site_url('admin/biodata/' . $id) . ($kelasId > 0 ? '?kelas_id=' . $kelasId : '');
    }

    private function adminId(): ?int
    {
        $id = session('admin')['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }
}
