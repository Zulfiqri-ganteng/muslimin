<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel "Status PKL Siswa" (halaman PKL → Status Siswa, tombol "Unduh Excel sesuai saringan"; Admin\PklBerkas::siswaExcel).
 * Dirapikan 2026-10-21 (keluhan user: kolom "No" selebar judul, tanpa garis, riwayat tanpa alasan):
 *   - judul & keterangan digabung selebar tabel, saringan yang dipakai ikut tertulis, ringkasan = tabel kecil 4 baris;
 *   - lebar kolom TETAP (tidak lagi "otomatis" yang ikut menghitung judul), teks panjang dilipat + tinggi baris ikut, rata atas;
 *   - garis tipis seluruh tabel, kepala tabel tebal berwarna + saringan (filter) Excel, dibekukan & diulang di tiap halaman cetak;
 *   - kolom Status berwarna lembut per tahap; Legal mendatar, muat satu halaman lebar, nomor halaman di kaki;
 *   - kolom Riwayat: satu kejadian per baris "tanggal · kode perusahaan — keterangan: alasan" (PklKeluar::teksTolakLengkap).
 * Semua huruf Times New Roman (seperti keluaran PKL lain).
 */
final class PklStatusExcel
{
    public const LABEL = [
        'belum' => 'Belum mengisi', 'ditolak' => 'Ditolak (perlu ajukan ulang)', 'menunggu' => 'Menunggu keputusan Hubin', 'perbaikan' => 'Perlu perbaikan',
        'belum_mulai' => 'Disetujui, belum mulai', 'sedang' => 'Sedang PKL', 'selesai' => 'Selesai PKL', 'disetujui' => 'Disetujui',
    ];

    /** Kolom tabel: [judul, lebar, lipat?, rata tengah?]. */
    private const KOLOM = [
        'A' => ['No', 5, false, true],
        'B' => ['Nama', 30, true, false],
        'C' => ['Kelas', 11, true, false],
        'D' => ['Status', 22, true, false],
        'E' => ['Perusahaan', 32, true, false],
        'F' => ['Kota', 18, true, false],
        'G' => ['Disetujui oleh', 20, true, false],
        'H' => ['Waktu ACC', 16, false, true],
        'I' => ['No. Bukti', 11, false, true],
        'J' => ['Nomor Surat', 23, true, false],
        'K' => ['Riwayat Ditolak / Dikeluarkan', 62, true, false],
    ];

    /** Warna lembut kolom Status per tahap. */
    private const WARNA = [
        'ditolak' => 'FDE2E1', 'menunggu' => 'FEF3C7', 'perbaikan' => 'FEF3C7',
        'belum_mulai' => 'DCFCE7', 'sedang' => 'DCFCE7', 'selesai' => 'DCFCE7', 'disetujui' => 'DCFCE7',
    ];

    private const HURUF  = 'Times New Roman';
    private const BARIS  = 15; // tinggi satu baris teks Times 11 (pt)
    private const KEPALA = 'E4DCF2'; // lilac muda (warna sekolah)

    /**
     * @param list<array<string, mixed>>                 $rows    PklPengajuanModel::statusSiswa()
     * @param array<string, int>                         $r       PklPengajuanModel::ringkasanSiswa()
     * @param array<int, list<array<string, mixed>>>     $tolak   PklKeluar::tolakSiswa() (siswa_id => riwayat)
     * @param array<string, mixed>                       $setting settings
     * @param list<string>                               $tingkat
     * @param list<string>                               $saring  keterangan saringan yang dipakai (kosong = semua)
     */
    public static function buat(array $rows, array $r, array $tolak, array $setting, array $tingkat, array $saring = []): Spreadsheet
    {
        $x = new Spreadsheet();
        $x->getDefaultStyle()->getFont()->setName(self::HURUF)->setSize(11);
        $x->getProperties()->setCreator('Sistem Informasi Akademik Sekolah')->setTitle('Status PKL Siswa');
        $ws = $x->getActiveSheet()->setTitle('Status PKL Siswa');
        foreach (self::KOLOM as $k => [, $lebar]) {
            $ws->getColumnDimension($k)->setWidth($lebar);
        }

        // Judul (digabung selebar tabel).
        $baris = [
            1 => ['STATUS PKL SISWA — ' . mb_strtoupper(trim((string) ($setting['school_name'] ?? ''))), true, 14],
            2 => ['Tahun Pelajaran ' . ($setting['academic_year'] ?? '') . ' · tingkat ' . implode('/', $tingkat) . ' · dicetak ' . date('d-m-Y H:i'), false, 11],
            3 => ['Saringan: ' . ($saring !== [] ? implode(' · ', $saring) : 'semua siswa'), false, 11],
        ];
        foreach ($baris as $b => [$teks, $tebal, $ukuran]) {
            $ws->mergeCells("A{$b}:K{$b}");
            $ws->setCellValueExplicit("A{$b}", $teks, DataType::TYPE_STRING);
            $ws->getStyle("A{$b}")->getFont()->setBold($tebal)->setSize($ukuran);
            $ws->getStyle("A{$b}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $ws->getRowDimension(1)->setRowHeight(20);

        // Ringkasan: tabel kecil (label B, nilai C:D).
        $ring = [
            ['Sudah mengisi', sprintf('%d dari %d siswa (%d%%)', $r['sudah_isi'] ?? 0, $r['total'] ?? 0, $r['persen_isi'] ?? 0)],
            ['Belum mengisi', sprintf('%d siswa', $r['belum_isi'] ?? 0)],
            ['Sudah PKL (disetujui)', sprintf('%d siswa (%d%%)', $r['sudah_pkl'] ?? 0, $r['persen_pkl'] ?? 0)],
            ['Belum PKL', sprintf('%d siswa', $r['belum_pkl'] ?? 0)],
        ];
        foreach ($ring as $i => [$label, $nilai]) {
            $b = 5 + $i;
            $ws->setCellValueExplicit("B{$b}", $label, DataType::TYPE_STRING);
            $ws->mergeCells("C{$b}:D{$b}");
            $ws->setCellValueExplicit("C{$b}", $nilai, DataType::TYPE_STRING);
        }
        $ws->getStyle('B5:D8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle('B5:B8')->getFont()->setBold(true);
        $ws->getStyle('B5:B8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::KEPALA);

        // Kepala tabel.
        $kepala = 10;
        foreach (self::KOLOM as $k => [$judul]) {
            $ws->setCellValueExplicit("{$k}{$kepala}", $judul, DataType::TYPE_STRING);
        }
        $ws->getStyle("A{$kepala}:K{$kepala}")->getFont()->setBold(true);
        $ws->getStyle("A{$kepala}:K{$kepala}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::KEPALA);
        $ws->getStyle("A{$kepala}:K{$kepala}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getRowDimension($kepala)->setRowHeight(self::BARIS * 2);

        // Isi.
        $b = $kepala + 1;
        foreach (array_values($rows) as $i => $s) {
            $fase = (string) ($s['fase'] ?? '');
            $isi  = [
                'A' => $i + 1,
                'B' => (string) ($s['nama'] ?? ''),
                'C' => (string) ($s['nama_kelas'] ?? ''),
                'D' => self::LABEL[$fase] ?? $fase,
                'E' => IsianBantu::rapikan((string) ($s['perusahaan_nama'] ?? '')),
                'F' => IsianBantu::rapikan((string) ($s['perusahaan_kota'] ?? '')),
                'G' => (string) ($s['acc_nama'] ?? ''),
                'H' => ! empty($s['acc_at']) ? date('d-m-Y H:i', strtotime((string) $s['acc_at'])) : '',
                'I' => ! empty($s['ajuan_id']) ? PklPengajuanModel::kode((int) $s['ajuan_id']) : '',
                'J' => (string) ($s['nomor_surat'] ?? ''),
                'K' => implode("\n", array_map([PklKeluar::class, 'teksTolakLengkap'], $tolak[(int) ($s['id'] ?? 0)] ?? [])),
            ];
            $tinggi = 1;
            foreach ($isi as $k => $v) {
                is_int($v) ? $ws->setCellValue("{$k}{$b}", $v) : $ws->setCellValueExplicit("{$k}{$b}", $v, DataType::TYPE_STRING);
                if (is_string($v) && $v !== '' && self::KOLOM[$k][2]) {
                    $tinggi = max($tinggi, self::jumlahBaris($v, self::KOLOM[$k][1]));
                }
            }
            if ($tinggi > 1) {
                $ws->getRowDimension($b)->setRowHeight(self::BARIS * $tinggi);
            }
            if (isset(self::WARNA[$fase])) {
                $ws->getStyle("D{$b}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA[$fase]);
            }
            $b++;
        }
        $akhir = max($kepala + 1, $b - 1);
        if ($rows === []) {
            $ws->mergeCells("A{$akhir}:K{$akhir}");
            $ws->setCellValue("A{$akhir}", 'Tidak ada siswa yang cocok dengan saringan ini.');
            $ws->getStyle("A{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $tabel = "A{$kepala}:K{$akhir}";
        $ws->getStyle($tabel)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle('A' . ($kepala + 1) . ":K{$akhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        foreach (self::KOLOM as $k => [, , $lipat, $tengah]) {
            $gaya = $ws->getStyle("{$k}" . ($kepala + 1) . ":{$k}{$akhir}")->getAlignment();
            $gaya->setWrapText($lipat);
            if ($tengah) {
                $gaya->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        if ($rows !== []) {
            $ws->setAutoFilter($tabel);
        }
        $ws->freezePane('C' . ($kepala + 1)); // kepala tabel + kolom No & Nama tetap terlihat saat digulir

        // Cetak: Legal mendatar, muat satu halaman lebar, kepala tabel diulang, nomor halaman.
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LEGAL)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($kepala, $kepala);
        $ws->getPageSetup()->setFitToPage(true);
        $ws->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setHeader(0.3)->setFooter(0.3);
        $ws->getHeaderFooter()->setOddFooter('&L&8Status PKL Siswa — dicetak ' . date('d-m-Y H:i') . '&R&8Halaman &P dari &N');
        $ws->setSelectedCell('A1');

        return $x;
    }

    /** Perkiraan jumlah baris teks terlipat di kolom selebar $lebar (Times 11 ≈ 1,1 huruf per satuan lebar). */
    public static function jumlahBaris(string $teks, float $lebar): int
    {
        $muat = max(1, (int) floor($lebar * 1.1));
        $n    = 0;
        foreach (explode("\n", $teks) as $baris) {
            $n += max(1, (int) ceil(mb_strlen($baris) / $muat));
        }

        return $n;
    }
}
