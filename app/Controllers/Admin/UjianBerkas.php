<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UjianCetak;
use App\Models\AuditModel;
use App\Models\JurusanModel;
use App\Models\MataPelajaranModel;
use App\Models\UjianJadwalModel;
use App\Models\UjianPeriodeModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Impor & ekspor berkas Excel untuk modul Ujian.
 *
 * Dipisah dari `Admin\Ujian` (yang mengurus tampilan halaman) mengikuti pola
 * yang sudah ada di repo: pembuatan berkas punya controller sendiri, seperti
 * `Admin\Export` dan `Admin\Cetak`.
 *
 * Template sengaja DIBUAT POLOS tanpa kop sekolah supaya gampang diparse
 * ulang saat diunggah; kop hanya dipasang pada berkas ekspor.
 */
class UjianBerkas extends BaseController
{
    protected UjianPeriodeModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new UjianPeriodeModel();
        $this->audit = new AuditModel();
    }


    /** Unduh template Excel kosong (tanpa kop, agar mudah diparse ulang). */
    public function templateJadwal(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }

        $this->kirimXlsx(UjianCetak::templateJadwal(), 'template-jadwal-ujian-' . $slug);
    }

    /** Ekspor jadwal periode yang sedang dilihat (pakai kop sekolah). */
    public function exportJadwal(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug    = UjianPeriodeModel::keSlug($jenis);
        $periode = $this->periodeTerpilih($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))->with('error', 'Periode ujian tidak ditemukan.');
        }

        $nama = 'jadwal-ujian-' . $slug . '-' . str_replace('/', '-', $periode['tahun_ajaran']);

        $this->kirimXlsx(UjianCetak::jadwalSpreadsheet($periode), $nama, 'I');
    }

    /** Baca berkas unggahan lalu tampilkan pratinjau yang bisa disunting. */
    public function importPreviewJadwal(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug    = UjianPeriodeModel::keSlug($jenis);
        $periode = $this->periodeTerpilih($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))->with('error', 'Periode ujian tidak ditemukan.');
        }
        $kembali = $this->urlTab($slug, 'jadwal', $periode['tahun_ajaran']);

        $rows = $this->bacaUnggahan();
        if ($rows === null) {
            return redirect()->to($kembali);       // pesan sudah diset bacaUnggahan()
        }
        if ($rows === []) {
            return redirect()->to($kembali)->with('error', 'File tidak berisi data.');
        }

        // Tandai baris yang akan menimpa jadwal yang sudah ada.
        $model = new UjianJadwalModel();
        foreach ($rows as &$r) {
            $petak       = $this->petakanBarisImpor($r, (int) $periode['id']);
            $r['_status'] = ($petak['data'] !== null && $this->jadwalSeragam($model, $petak['data']) !== null)
                ? 'perbarui' : 'baru';
        }
        unset($r);

        $kolom = UjianCetak::opsiKolomImpor();

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor Jadwal Ujian',
            'subtitle'  => 'Jadwal ' . $this->model->label($periode),
            'cols'      => $kolom,
            'rows'      => $rows,
            'commitUrl' => site_url('admin/ujian/' . $slug . '/jadwal/import-commit')
                . '?tp=' . rawurlencode($periode['tahun_ajaran']),
            'backUrl'   => $kembali,
        ]);
    }

    /** Simpan hasil pratinjau impor ke database. */
    public function importCommitJadwal(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug    = UjianPeriodeModel::keSlug($jenis);
        $periode = $this->periodeTerpilih($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))->with('error', 'Periode ujian tidak ditemukan.');
        }
        $kembali = $this->urlTab($slug, 'jadwal', $periode['tahun_ajaran']);

        $rows = (array) $this->request->getPost('rows');
        if ($rows === []) {
            return redirect()->to($kembali)->with('error', 'Tidak ada data untuk disimpan.');
        }

        $model  = new UjianJadwalModel();
        $baru   = 0;
        $ubah   = 0;
        $lewat  = 0;
        $galat  = [];

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        foreach ($rows as $i => $row) {
            $nomor = $i + 1;
            $petak = $this->petakanBarisImpor((array) $row, (int) $periode['id']);

            if ($petak['data'] === null) {
                $lewat++;
                $galat[] = "Baris {$nomor}: " . $petak['pesan'];
                continue;
            }
            $data = $petak['data'];

            // Baris kembar (mapel+tingkat+jurusan+shift+ruang sama) diperbarui,
            // bukan ditumpuk jadi baris baru — supaya berkas boleh diunggah ulang.
            $ada     = $this->jadwalSeragam($model, $data);
            $bentrok = $model->bentrok($data, $ada['id'] ?? null);
            if ($bentrok !== []) {
                $lewat++;
                $b       = $bentrok[0];
                $galat[] = "Baris {$nomor}: bentrok dengan " . ($b['nama_mapel'] ?? 'jadwal lain')
                    . ' pada ' . date('d/m/Y', strtotime((string) $b['tanggal']));
                continue;
            }

            if ($ada) {
                $model->update($ada['id'], $data);
                $ubah++;
            } else {
                $model->insert($data);
                $baru++;
            }
        }

        $db->transComplete();

        master_data_changed('ujian_jadwal');
        $this->audit->record(
            'import',
            'ujian_jadwal',
            null,
            'Impor jadwal ' . $this->model->label($periode) . ": +{$baru} baru, {$ubah} diperbarui, {$lewat} dilewati"
        );

        $pesan = "Impor selesai: {$baru} baru, {$ubah} diperbarui, {$lewat} dilewati.";
        if ($galat !== []) {
            return redirect()->to($kembali)
                ->with('error', $pesan)
                ->with('errors', array_slice($galat, 0, 8));
        }

        return redirect()->to($kembali)->with('success', $pesan);
    }

    /** Baca berkas Excel yang diunggah jadi array assoc sesuai UjianCetak::kolomJadwal(). */
    private function bacaUnggahan(): ?array
    {
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            session()->setFlashdata('error', 'File tidak valid.');

            return null;
        }
        if (! in_array(strtolower((string) $file->getExtension()), ['xlsx', 'xls'], true)) {
            session()->setFlashdata('error', 'File harus berformat Excel (.xlsx / .xls).');

            return null;
        }

        try {
            $sheet = IOFactory::load($file->getTempName())->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'Gagal membaca file: ' . $e->getMessage());

            return null;
        }

        $keys = array_column(UjianCetak::kolomJadwal(), 'key');
        $rows = [];
        foreach ($data as $i => $row) {
            if ($i === 0) {
                continue; // baris header
            }
            $assoc = [];
            foreach ($keys as $c => $k) {
                $assoc[$k] = trim((string) ($row[$c] ?? ''));
            }
            if (implode('', $assoc) === '') {
                continue; // baris kosong
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * Ubah satu baris impor jadi data siap simpan.
     *
     * @return array{data: array|null, pesan: string}
     */
    private function petakanBarisImpor(array $row, int $periodeId): array
    {
        $tolak = static fn (string $p) => ['data' => null, 'pesan' => $p];

        $kodeMapel = trim((string) ($row['mapel'] ?? ''));
        if ($kodeMapel === '') {
            return $tolak('kode mapel kosong.');
        }
        $mapel = (new MataPelajaranModel())->where('kode_mapel', $kodeMapel)->first();
        if (! $mapel) {
            return $tolak("kode mapel \"{$kodeMapel}\" tidak terdaftar.");
        }

        $tingkat = strtoupper(trim((string) ($row['tingkat'] ?? '')));
        if (! in_array($tingkat, UjianJadwalModel::TINGKAT, true)) {
            return $tolak("tingkat \"{$tingkat}\" tidak dikenal (isi X, XI, atau XII).");
        }

        $jurusanId  = null;
        $kodeJurusan = trim((string) ($row['jurusan'] ?? ''));
        if ($kodeJurusan !== '') {
            $jurusan = (new JurusanModel())->where('kode', $kodeJurusan)->first();
            if (! $jurusan) {
                return $tolak("kode jurusan \"{$kodeJurusan}\" tidak terdaftar.");
            }
            $jurusanId = (int) $jurusan['id'];
        }

        $shift = strtolower(trim((string) ($row['shift'] ?? '')));
        if ($shift === '') {
            $shift = 'semua';
        }
        if (! in_array($shift, UjianJadwalModel::SHIFT, true)) {
            return $tolak("shift \"{$shift}\" tidak dikenal (isi pagi, siang, atau semua).");
        }

        $tanggal = $this->tanggalImpor((string) ($row['tanggal'] ?? ''));
        if ($tanggal === false) {
            return $tolak('format tanggal tidak dikenali (pakai YYYY-MM-DD).');
        }
        if ($tanggal === null) {
            return $tolak('tanggal kosong.');
        }

        $mulai = $this->jamImpor((string) ($row['jam_mulai'] ?? ''));
        if ($mulai === false) {
            return $tolak('format jam mulai tidak dikenali (pakai HH:MM).');
        }
        $selesai = $this->jamImpor((string) ($row['jam_selesai'] ?? ''));
        if ($selesai === false) {
            return $tolak('format jam selesai tidak dikenali (pakai HH:MM).');
        }
        if ($selesai !== null && $mulai === null) {
            return $tolak('jam selesai diisi tapi jam mulai kosong.');
        }
        if ($mulai !== null && $selesai !== null && $selesai <= $mulai) {
            return $tolak('jam selesai harus lebih besar dari jam mulai.');
        }

        $ruang      = trim((string) ($row['ruang'] ?? ''));
        $keterangan = trim((string) ($row['keterangan'] ?? ''));

        return ['data' => [
            'periode_id'  => $periodeId,
            'mapel_id'    => (int) $mapel['id'],
            'tingkat'     => $tingkat,
            'jurusan_id'  => $jurusanId,
            'shift'       => $shift,
            'tanggal'     => $tanggal,
            'jam_mulai'   => $mulai,
            'jam_selesai' => $selesai,
            'ruang'       => $ruang !== '' ? mb_substr($ruang, 0, 100) : null,
            'keterangan'  => $keterangan !== '' ? mb_substr($keterangan, 0, 255) : null,
        ], 'pesan' => ''];
    }

    /**
     * Cari jadwal yang identitasnya sama persis: periode + mapel + tingkat +
     * jurusan + shift + ruang. Ruang ikut dihitung supaya ujian paralel di
     * dua ruang tetap jadi dua baris terpisah saat berkas diunggah ulang.
     */
    private function jadwalSeragam(UjianJadwalModel $model, array $data): ?array
    {
        $b = $model->where('periode_id', $data['periode_id'])
            ->where('mapel_id', $data['mapel_id'])
            ->where('tingkat', $data['tingkat'])
            ->where('shift', $data['shift']);

        $b = $data['jurusan_id'] === null
            ? $b->where('jurusan_id', null)
            : $b->where('jurusan_id', $data['jurusan_id']);

        $b = $data['ruang'] === null
            ? $b->where('ruang', null)
            : $b->where('ruang', $data['ruang']);

        return $b->first();
    }

    /** Tanggal dari sel Excel: null = kosong, false = format tak dikenali. */
    private function tanggalImpor(string $v)
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v)) {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);

            return ($d && $d->format('Y-m-d') === $v) ? $v : false;
        }

        // Format Indonesia d/m/Y atau d-m-Y.
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])
                : false;
        }

        // Nomor seri Excel (sel bertipe tanggal tapi berformat General).
        if (is_numeric($v) && (float) $v > 1 && (float) $v < 100000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /** Jam dari sel Excel: null = kosong, false = format tak dikenali. */
    private function jamImpor(string $v)
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?$/', $v, $m)) {
            $j = (int) $m[1];
            $i = (int) $m[2];

            return ($j <= 23 && $i <= 59) ? sprintf('%02d:%02d:00', $j, $i) : false;
        }

        // Pecahan hari (sel bertipe jam yang terbaca sebagai angka).
        if (is_numeric($v) && (float) $v >= 0 && (float) $v < 1) {
            $detik = (int) round((float) $v * 86400);

            return sprintf('%02d:%02d:00', intdiv($detik, 3600), intdiv($detik % 3600, 60));
        }

        return false;
    }

    /** Kirim berkas .xlsx sebagai unduhan (isi $kopKolomAkhir untuk memasang kop). */
    private function kirimXlsx(Spreadsheet $ss, string $namaBerkas, ?string $kopKolomAkhir = null): void
    {
        if ($kopKolomAkhir !== null) {
            kop_excel_prepend($ss->getActiveSheet(), $kopKolomAkhir);
        }

        $isi = UjianCetak::xlsx($ss);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . UjianCetak::namaAman($namaBerkas . '.xlsx') . '"');
        header('Content-Length: ' . strlen($isi));
        header('Cache-Control: max-age=0');
        echo $isi;
        exit;
    }

    /** Periode yang sedang dilihat (aturannya ada di model, dipakai web & API). */
    private function periodeTerpilih(string $jenis): ?array
    {
        return $this->model->untukTahun($jenis, $this->request->getGet('tp'));
    }

    /** URL satu tab lengkap dengan tahun pelajaran yang sedang dilihat. */
    private function urlTab(string $slug, string $tab, string $tahun): string
    {
        return site_url('admin/ujian/' . $slug . ($tab === 'periode' ? '' : '/' . $tab))
            . '?tp=' . rawurlencode($tahun);
    }
}
