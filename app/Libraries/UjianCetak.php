<?php

namespace App\Libraries;

use App\Models\JurusanModel;
use App\Models\MataPelajaranModel;
use App\Models\SettingModel;
use App\Models\UjianJadwalModel;
use App\Models\UjianPengawasModel;
use App\Models\UjianPeriodeModel;
use App\Models\UjianSusulanModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pembuat berkas cetak modul Ujian — satu mesin, dipakai bersama oleh:
 *  - web   : App\Controllers\Admin\LaporanUjian & Admin\UjianBerkas
 *  - mobile: App\Controllers\Api\Admin\UjianCetak
 *
 * Library ini HANYA menghasilkan isi berkas (string biner / objek
 * Spreadsheet). Urusan header HTTP, nama unduhan, dan cara mengirim
 * diserahkan ke controller masing-masing, karena web memakai streaming
 * `exit` sedangkan API mengembalikan ResponseInterface.
 *
 * Alasan dipisah: sebelum ada berkas ini, satu-satunya cara memberi fitur
 * cetak ke aplikasi Android adalah menyalin seluruh perakitan PDF/Excel —
 * dua salinan yang pasti lekas berbeda isinya. Angka agregatnya sendiri
 * datang dari UjianReport, jadi layar, PDF, dan Excel mustahil beda.
 */
class UjianCetak
{
    // =================================================================
    // Definisi kolom berkas jadwal (dipakai template, ekspor, DAN impor)
    // =================================================================

    /**
     * Urutan kunci di sini = urutan kolom di Excel. Satu definisi dipakai
     * bertiga supaya template yang diunduh selalu cocok dengan parser impor.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function kolomJadwal(): array
    {
        return [
            ['key' => 'mapel',       'label' => 'Kode Mapel',           'type' => 'datalist', 'required' => true, 'width' => 140],
            ['key' => 'tingkat',     'label' => 'Tingkat',              'type' => 'select', 'options' => UjianJadwalModel::TINGKAT, 'required' => true, 'width' => 90],
            ['key' => 'jurusan',     'label' => 'Kode Jurusan',         'type' => 'datalist', 'width' => 120],
            ['key' => 'shift',       'label' => 'Shift',                'type' => 'select', 'options' => UjianJadwalModel::SHIFT, 'width' => 100],
            ['key' => 'tanggal',     'label' => 'Tanggal (YYYY-MM-DD)', 'type' => 'text', 'required' => true, 'width' => 150],
            ['key' => 'jam_mulai',   'label' => 'Jam Mulai',            'type' => 'text', 'width' => 100],
            ['key' => 'jam_selesai', 'label' => 'Jam Selesai',          'type' => 'text', 'width' => 100],
            ['key' => 'ruang',       'label' => 'Ruang',                'type' => 'text', 'width' => 120],
            ['key' => 'keterangan',  'label' => 'Keterangan',           'type' => 'text', 'width' => 180],
        ];
    }

    /** Lebar kolom lembar jadwal, sejajar dengan kolomJadwal(). */
    private static function lebarJadwal(): array
    {
        return ['A' => 16, 'B' => 10, 'C' => 14, 'D' => 10, 'E' => 20, 'F' => 12, 'G' => 12, 'H' => 16, 'I' => 26];
    }

    // =================================================================
    // Berkas jadwal
    // =================================================================

    /** Template impor — sengaja POLOS tanpa kop agar mudah diparse ulang. */
    public static function templateJadwal(): Spreadsheet
    {
        $ss    = new Spreadsheet();
        $sheet = self::lembarBerjudul($ss, 'Jadwal Ujian', array_column(self::kolomJadwal(), 'label'), self::lebarJadwal());

        // Dua baris contoh memakai kode mapel yang benar-benar ada.
        $contoh = (new MataPelajaranModel())->orderBy('kode_mapel', 'ASC')->findAll(2);
        $k1     = $contoh[0]['kode_mapel'] ?? '10MTK';
        $k2     = $contoh[1]['kode_mapel'] ?? '11MTK';
        $sheet->fromArray([
            [$k1, 'X', '', 'pagi', date('Y-m-d'), '07:30', '09:00', 'R1', 'contoh — hapus baris ini'],
            [$k2, 'XII', 'TJKT', 'siang', date('Y-m-d'), '13:00', '14:30', 'R2', 'kosongkan jurusan bila semua jurusan'],
        ], null, 'A2', true);

        return $ss;
    }

    /** Ekspor jadwal satu periode (kop dipasang oleh pemanggil bila perlu). */
    public static function jadwalSpreadsheet(array $periode): Spreadsheet
    {
        $ss    = new Spreadsheet();
        $sheet = self::lembarBerjudul($ss, 'Jadwal Ujian', array_column(self::kolomJadwal(), 'label'), self::lebarJadwal());

        $baris = [];
        foreach ((new UjianJadwalModel())->untukPeriode((int) $periode['id'])->findAll() as $r) {
            $baris[] = [
                $r['kode_mapel'] ?? '',
                $r['tingkat'],
                $r['jurusan_kode'] ?? '',
                $r['shift'],
                $r['tanggal'],
                $r['jam_mulai'] ? substr((string) $r['jam_mulai'], 0, 5) : '',
                $r['jam_selesai'] ? substr((string) $r['jam_selesai'], 0, 5) : '',
                $r['ruang'] ?? '',
                $r['keterangan'] ?? '',
            ];
        }
        if ($baris !== []) {
            $sheet->fromArray($baris, null, 'A2', true);
        }

        return $ss;
    }

    // =================================================================
    // Rekap periode
    // =================================================================

    public static function rekapHtml(array $periode): string
    {
        return view('pdf/ujian_rekap', [
            'periode' => $periode,
            'label'   => (new UjianPeriodeModel())->label($periode),
            'panjang' => UjianPeriodeModel::JENIS_PANJANG[$periode['jenis']] ?? '',
            'setting' => (new SettingModel())->get(),
        ] + UjianReport::hitung((int) $periode['id']));
    }

    public static function rekapSpreadsheet(array $periode): Spreadsheet
    {
        $d     = UjianReport::hitung((int) $periode['id']);
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Ujian');

        $baris = 1;
        $judul = static function (string $teks) use ($sheet, &$baris) {
            $sheet->setCellValue('A' . $baris, $teks);
            $sheet->getStyle('A' . $baris)->getFont()->setBold(true)->setSize(12);
            $baris += 1;
        };
        $kepala = static function (array $kolom) use ($sheet, &$baris) {
            $sheet->fromArray($kolom, null, 'A' . $baris, true);
            $akhir = chr(ord('A') + count($kolom) - 1);
            $range = 'A' . $baris . ':' . $akhir . $baris;
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $baris += 1;
        };
        $isi = static function (array $data) use ($sheet, &$baris) {
            if ($data !== []) {
                $sheet->fromArray($data, null, 'A' . $baris, true);
                $baris += count($data);
            }
            $baris += 1;
        };

        $judul((new UjianPeriodeModel())->label($periode));
        $sheet->setCellValue('A' . $baris, 'Semester ' . $periode['semester']
            . ' · Pelaksanaan ' . self::tgl($periode['tanggal_mulai']) . ' s.d. ' . self::tgl($periode['tanggal_selesai']));
        $baris += 2;

        $judul('RINGKASAN');
        $kepala(['Keterangan', 'Jumlah']);
        $isi([
            ['Sesi ujian terjadwal', $d['jadwalTotal']],
            ['Penugasan pengawas', $d['pengawasTotal']],
            ['Siswa tidak hadir', $d['takHadirTotal']],
            ['Susulan belum dijadwalkan', $d['perStatus']['belum']],
            ['Susulan sudah dijadwalkan', $d['perStatus']['dijadwalkan']],
            ['Susulan selesai', $d['perStatus']['selesai']],
            ['Susulan batal', $d['perStatus']['batal']],
            ['Alasan: sakit', $d['perAlasan']['sakit']],
            ['Alasan: izin', $d['perAlasan']['izin']],
            ['Alasan: alpa', $d['perAlasan']['alpa']],
            ['Alasan: lainnya', $d['perAlasan']['lainnya']],
        ]);

        $judul('REKAP PER KELAS');
        $kepala(['Kelas', 'Tingkat', 'Tidak Hadir', 'Belum', 'Dijadwalkan', 'Selesai', 'Batal']);
        $isi(array_map(static fn ($r) => [
            $r['nama_kelas'] ?? '—', $r['tingkat'] ?? '—',
            (int) $r['total'], (int) $r['belum'], (int) $r['dijadwalkan'], (int) $r['selesai'], (int) $r['batal'],
        ], $d['perKelas']));

        $judul('REKAP PER MATA PELAJARAN');
        $kepala(['Kode', 'Mata Pelajaran', 'Tidak Hadir', 'Belum', 'Dijadwalkan', 'Selesai', 'Batal']);
        $isi(array_map(static fn ($r) => [
            $r['kode_mapel'] ?? '—', $r['nama_mapel'] ?? '—',
            (int) $r['total'], (int) $r['belum'], (int) $r['dijadwalkan'], (int) $r['selesai'], (int) $r['batal'],
        ], $d['perMapel']));

        $judul('DAFTAR SISWA TIDAK HADIR & SUSULANNYA');
        $kepala(['NIS', 'Nama', 'Kelas', 'Mapel', 'Tgl Ujian', 'Alasan', 'Status', 'Tgl Susulan', 'Ruang', 'Pengawas']);
        $isi(array_map(static fn ($r) => [
            $r['nis'] ?? '—',
            $r['siswa_nama'] ?? '—',
            $r['nama_kelas'] ?? '—',
            $r['nama_mapel'] ?? '—',
            self::tgl($r['tanggal_ujian']),
            $r['alasan'],
            UjianSusulanModel::STATUS_LABEL[$r['status']] ?? $r['status'],
            self::tgl($r['tanggal_susulan']),
            $r['ruang_susulan'] ?? '—',
            $r['pengawas_nama'] ?? '—',
        ], self::daftarSusulan((int) $periode['id'])));

        foreach (range('A', 'J') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }
        kop_excel_prepend($sheet, 'J');

        return $ss;
    }

    // =================================================================
    // Cetakan per sesi ujian
    // =================================================================

    public static function daftarHadirHtml(array $periode, array $jadwal): string
    {
        $jadwalModel = new UjianJadwalModel();

        return view('pdf/ujian_daftar_hadir', [
            'periode'  => $periode,
            'label'    => (new UjianPeriodeModel())->label($periode),
            'jadwal'   => $jadwal,
            'kelompok' => UjianReport::pesertaSesi($jadwalModel->kelasSasaran($jadwal)),
            'pengawas' => (new UjianPengawasModel())->untukJadwal((int) $jadwal['id']),
        ]);
    }

    public static function beritaAcaraHtml(array $periode, array $jadwal): string
    {
        $jadwalModel = new UjianJadwalModel();
        $sasaran     = $jadwalModel->kelasSasaran($jadwal);
        $kelompok    = UjianReport::pesertaSesi($sasaran);

        $jumlahPeserta = 0;
        foreach ($kelompok as $k) {
            $jumlahPeserta += count($k['siswa']);
        }

        $takHadir = (new UjianSusulanModel())->withRelations()
            ->where('ujian_susulan.jadwal_id', (int) $jadwal['id'])
            ->orderBy('kelas.nama_kelas', 'ASC')->orderBy('siswa.nama', 'ASC')
            ->findAll();

        return view('pdf/ujian_berita_acara', [
            'periode'       => $periode,
            'label'         => (new UjianPeriodeModel())->label($periode),
            'panjang'       => UjianPeriodeModel::JENIS_PANJANG[$periode['jenis']] ?? '',
            'jadwal'        => $jadwal,
            'sasaran'       => $sasaran,
            'jumlahPeserta' => $jumlahPeserta,
            'takHadir'      => $takHadir,
            'pengawas'      => (new UjianPengawasModel())->untukJadwal((int) $jadwal['id']),
            'nomor'         => self::nomorBeritaAcara($periode, $jadwal),
            'setting'       => (new SettingModel())->get(),
        ]);
    }

    /**
     * Nomor berita acara DITURUNKAN dari data, tidak disimpan — stabil tiap
     * kali dicetak tanpa perlu tabel penomoran tersendiri.
     */
    public static function nomorBeritaAcara(array $periode, array $jadwal): string
    {
        return 'BA/' . strtoupper($periode['jenis']) . '/'
            . str_replace('/', '-', $periode['tahun_ajaran']) . '/'
            . str_pad((string) $jadwal['id'], 4, '0', STR_PAD_LEFT);
    }

    // =================================================================
    // Perenderan
    // =================================================================

    /** HTML → berkas PDF (A4 portrait) sebagai string biner. */
    public static function pdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** Spreadsheet → berkas .xlsx sebagai string biner. */
    public static function xlsx(Spreadsheet $ss): string
    {
        // php://memory, bukan php://output: pemanggil yang menentukan cara
        // mengirim (streaming web vs ResponseInterface API).
        $aliran = fopen('php://memory', 'r+');
        (new Xlsx($ss))->save($aliran);
        rewind($aliran);
        $isi = (string) stream_get_contents($aliran);
        fclose($aliran);

        return $isi;
    }

    /** Nama berkas yang aman dipakai di header Content-Disposition. */
    public static function namaAman(string $nama): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $nama) ?? 'berkas';
    }

    // =================================================================
    // Util internal
    // =================================================================

    /** Buat lembar berjudul dengan baris header berwarna (pola BaseMaster). */
    private static function lembarBerjudul(Spreadsheet $ss, string $judul, array $headers, array $lebar = [])
    {
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle($judul);
        $sheet->fromArray($headers, null, 'A1', true);

        $last  = Coordinate::stringFromColumnIndex(count($headers));
        $range = 'A1:' . $last . '1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($lebar as $kolom => $w) {
            $sheet->getColumnDimension($kolom)->setWidth($w);
        }

        return $sheet;
    }

    /** Daftar susulan satu periode, lengkap dengan relasinya. */
    private static function daftarSusulan(int $periodeId): array
    {
        return (new UjianSusulanModel())->withRelations()
            ->where('ujian_susulan.periode_id', $periodeId)
            ->orderBy('kelas.nama_kelas', 'ASC')
            ->orderBy('siswa.nama', 'ASC')
            ->orderBy('ujian_susulan.tanggal_ujian', 'ASC')
            ->findAll();
    }

    private static function tgl(?string $d): string
    {
        return $d ? date('d/m/Y', strtotime($d)) : '—';
    }

    /** Opsi datalist untuk pratinjau impor (kode mapel & kode jurusan). */
    public static function opsiKolomImpor(): array
    {
        $kolom = self::kolomJadwal();
        foreach ($kolom as &$k) {
            if ($k['key'] === 'mapel') {
                $k['options'] = array_values(array_column(
                    (new MataPelajaranModel())->select('kode_mapel')->orderBy('kode_mapel', 'ASC')->findAll(),
                    'kode_mapel'
                ));
            }
            if ($k['key'] === 'jurusan') {
                $k['options'] = array_values(array_column(
                    (new JurusanModel())->select('kode')->orderBy('kode', 'ASC')->findAll(),
                    'kode'
                ));
            }
        }
        unset($k);

        return $kolom;
    }
}
