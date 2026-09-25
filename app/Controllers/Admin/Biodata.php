<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\BiodataLaporan;
use App\Libraries\BiodataPesan;
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
        $kelasNama = $kelasOpts[$kelasId] ?? '';

        return view('admin/biodata/index', [
            'title'        => 'Isian Biodata Siswa',
            'tab'          => $tab,
            'kelasId'      => $kelasId,
            'kelasNama'    => $kelasNama,
            'q'            => $q,
            'rows'         => $rows,
            'total'        => $total,
            'pager'        => $pager,
            'mulai'        => ($page - 1) * self::PER,
            'ringkas'      => $this->isian->ringkasan(),
            'perKelas'     => $tab === 'kelas' ? $this->isian->perKelas() : [],
            'kelasOpts'    => $kelasOpts,
            'setting'      => $setting,
            'terbuka'      => BiodataIsianModel::formTerbuka($setting),
            'tautan'       => BiodataPesan::tautan(),
            'tautanAlt'    => BiodataPesan::tautanCadangan(),
            'batasTeks'    => BiodataPesan::batasTeks($setting['biodata_tutup'] ?? null),
            'pesanBagikan' => BiodataPesan::bagikan($setting),
            // Daftar nama belum mengisi hanya berarti bila satu kelas dipilih.
            'pesanBelum'   => $tab === 'belum' && $kelasId > 0 && $rows !== []
                ? BiodataPesan::belumMengisi($kelasNama, array_column($rows, 'nama'))
                : '',
            'maksMassal'   => self::MAKS_MASSAL,
        ]);
    }

    /** POST — saklar buka/tutup + batas waktu. */
    public function pengaturan(): RedirectResponse
    {
        $hasil = BiodataVerifikasi::simpanPengaturan(
            (bool) $this->request->getPost('biodata_open'),
            (string) $this->request->getPost('biodata_tutup')
        );
        if (! $hasil['ok']) {
            return redirect()->back()->with('error', $hasil['pesan']);
        }
        $this->audit->record('update', 'settings', 1, 'Isian biodata: ' . ($hasil['buka'] ? 'DIBUKA' : 'DITUTUP')
            . ($hasil['tutup'] !== null ? ' s.d. ' . $hasil['tutup'] : ''));

        return redirect()->back()->with($hasil['lewat'] ? 'error' : 'success', $hasil['pesan']);
    }

    /** @param int|string $id */
    public function detail($id)
    {
        $row = $this->isian->find((int) $id);
        if ($row === null) {
            return redirect()->to(site_url('admin/biodata'))->with('error', 'Isian tidak ditemukan.');
        }

        $siswa     = (new SiswaModel())->withDeleted()->withRelations()->where('siswa.id', $row['siswa_id'])->first();
        $banding   = BiodataVerifikasi::bandingkan($row, $siswa);
        $admin     = null;
        if (! empty($row['diverifikasi_oleh'])) {
            $admin = db_connect()->table('admins')->select('full_name, username')
                ->where('id', (int) $row['diverifikasi_oleh'])->get()->getRowArray();
        }

        return view('admin/biodata/detail', [
            'title'      => 'Periksa Biodata',
            'row'        => $row,
            'siswa'      => $siswa,
            'judulLama'  => $banding['judul_lama'],
            'bagian'     => $banding['bagian'],
            'hitung'     => $banding['hitung'],
            'peringatan' => BiodataVerifikasi::peringatan($row, $siswa),
            'admin'      => $admin,
            'kelasId'    => (int) $this->request->getGet('kelas_id'),
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

        ['ok' => $ok, 'gagal' => $gagal] = (new BiodataVerifikasi())->setujuiBanyak($ids, $this->adminId());
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
