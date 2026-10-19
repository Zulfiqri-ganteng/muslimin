<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel buku piket keterlambatan — PERSIS format sekolah formatdatasekolah/KETERLAMBATAN SISWA DI GURU PIKET.xlsx (berkas itu
 * tidak ikut repo, jadi tata letaknya ditulis di sini): judul 3 baris digabung A:F (BUKU PIKET KETERLAMBATAN SISWA / nama sekolah +
 * kabupaten / TAHUN PELAJARAN), HARI / TANGGAL & GURU PIKET, kepala tabel baris 8 (NO | HARI/TANGGAL | NAMA | KELAS | SANKSI |
 * KETERANGAN), minimal 50 baris bernomor bergaris tipis (sisa baris kosong untuk ditulis tangan), Times New Roman 11, kertas
 * Legal tegak, margin & lebar kolom sama. Satu lembar = satu hari; unduhan rentang = satu lembar per hari yang ada catatannya.
 * Bedanya dengan contoh: SEMUA baris data Times New Roman tinggi 14 (di contoh baris 36–58 tak sengaja Calibri tinggi bawaan), dan
 * isian panjang dilipat (tinggi baris ikut) agar tidak terpotong saat dicetak.
 *
 * + Excel rekap (lembar "Per siswa" & "Per kelas") untuk halaman Rekap Keterlambatan.
 */
final class KeterlambatanExcel
{
    public const BARIS_MIN = 50;

    private const HURUF  = 'Times New Roman';
    private const LEBAR  = ['A' => 5, 'B' => 15.7265625, 'C' => 31.26953125, 'D' => 15.1796875, 'E' => 15.1796875, 'F' => 16.54296875];
    private const KEPALA = ['NO', 'HARI/TANGGAL', 'NAMA', 'KELAS', 'SANKSI', 'KETERANGAN'];
    // Perkiraan jumlah huruf Times 11 yang muat per kolom (untuk melipat isian panjang).
    private const MUAT   = ['B' => 17, 'C' => 36, 'D' => 17, 'E' => 17, 'F' => 18];
    private const KOSONG = ': _____________________________';

    /** "SMK BINA NUSA (BINUS)" + kota "Bekasi" → "SMK BINA NUSA KABUPATEN BEKASI" (sama dengan judul Excel Honor). */
    public static function namaSekolah(): string
    {
        $set     = db_connect()->table('settings')->select('school_name, city')->get()->getRowArray() ?? [];
        $sekolah = trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', (string) ($set['school_name'] ?? 'SEKOLAH')));
        $kota    = trim((string) ($set['city'] ?? ''));
        $wilayah = $kota === '' ? '' : (preg_match('/^(kab|kota)/i', $kota) ? $kota : 'Kabupaten ' . $kota);

        return mb_strtoupper(trim($sekolah . ' ' . $wilayah));
    }

    /** "2026/2027" → "2026-2027": tahun ajaran terbanyak di catatan, cadangan Pengaturan Sekolah. */
    public static function tahunPelajaran(array $rows): string
    {
        $hitung = array_count_values(array_filter(array_map(static fn (array $r): string => (string) ($r['tahun_ajaran'] ?? ''), $rows)));
        arsort($hitung);
        $ta = (string) (array_key_first($hitung) ?? Keterlambatan::tahunAjaran());

        return str_replace('/', '-', $ta);
    }

    /**
     * @param list<array{tanggal: string, rows: list<array<string, mixed>>, guru: string}> $hari urut tanggal; minimal satu
     */
    public static function bukuPiket(array $hari): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()->setCreator('Sistem Akademik')->setTitle('Buku Piket Keterlambatan Siswa');
        $sekolah = self::namaSekolah();
        foreach (array_values($hari) as $i => $h) {
            $ws = $i === 0 ? $ss->getActiveSheet() : $ss->createSheet();
            $ws->setTitle(date('d-m-Y', strtotime($h['tanggal'])));
            self::lembar($ws, $h['tanggal'], $h['rows'], $h['guru'], $sekolah);
        }
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    private static function lembar(Worksheet $ws, string $tanggal, array $rows, string $guru, string $sekolah): void
    {
        $n    = max(self::BARIS_MIN, count($rows));
        $akhir = 8 + $n;
        foreach (self::LEBAR as $kol => $w) {
            $ws->getColumnDimension($kol)->setWidth($w);
        }
        foreach ([1 => 19.5, 2 => 19.5, 3 => 19.5, 4 => 14, 5 => 25, 6 => 25, 7 => 14, 8 => 22] as $b => $t) {
            $ws->getRowDimension($b)->setRowHeight($t);
        }
        $ws->getStyle('A1:F' . $akhir)->getFont()->setName(self::HURUF)->setSize(11);

        // Judul
        foreach ([1 => 'BUKU PIKET KETERLAMBATAN SISWA', 2 => $sekolah, 3 => 'TAHUN PELAJARAN ' . self::tahunPelajaran($rows)] as $b => $teks) {
            $ws->mergeCells("A{$b}:F{$b}");
            $ws->setCellValue("A{$b}", $teks);
        }
        $ws->getStyle('A1:F3')->getFont()->setBold(true);
        $ws->getStyle('A1:F3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Hari / tanggal & guru piket
        $ws->mergeCells('A5:B5');
        $ws->mergeCells('A6:B6');
        $ws->setCellValue('A5', 'HARI / TANGGAL');
        $ws->setCellValue('A6', 'GURU PIKET ');
        $ws->setCellValueExplicit('C5', ': ' . Keterlambatan::hariTanggal($tanggal), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->setCellValueExplicit('C6', $guru !== '' ? ': ' . $guru : self::KOSONG, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->getStyle('A5:B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('A5:F6')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        if (mb_strlen($guru) > 80) {
            // Banyak guru piket: lipat di C6:F6 agar tidak keluar kertas.
            $ws->mergeCells('C6:F6');
            $ws->getStyle('C6')->getAlignment()->setWrapText(true);
            $ws->getRowDimension(6)->setRowHeight(max(25, 14 * (int) ceil(mb_strlen($guru) / 85)));
        }

        // Kepala tabel
        $ws->fromArray(self::KEPALA, null, 'A8');
        $ws->getStyle('A8:F8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Baris data (+ baris kosong bernomor sampai 50)
        $rows = array_values($rows);
        for ($i = 0; $i < $n; $i++) {
            $b = 9 + $i;
            $ws->setCellValue("A{$b}", $i + 1);
            $tinggi = 1;
            if (isset($rows[$i])) {
                $r   = $rows[$i];
                $isi = [
                    'B' => self::HARI_SINGKAT[(int) date('N', strtotime((string) $r['tanggal']))] . ', ' . date('d-m-Y', strtotime((string) $r['tanggal'])),
                    'C' => (string) $r['nama_siswa'],
                    'D' => (string) ($r['nama_kelas'] ?? ''),
                    'E' => (string) ($r['sanksi'] ?? ''),
                    'F' => (string) ($r['keterangan'] ?? ''),
                ];
                foreach ($isi as $kol => $teks) {
                    $ws->setCellValueExplicit("{$kol}{$b}", $teks, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $tinggi = max($tinggi, (int) ceil(mb_strlen($teks) / self::MUAT[$kol]));
                }
                if ($tinggi > 1) {
                    $ws->getStyle("B{$b}:F{$b}")->getAlignment()->setWrapText(true);
                }
            }
            $ws->getRowDimension($b)->setRowHeight(14 * $tinggi);
        }
        $ws->getStyle('A9:A' . $akhir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('A9:F' . $akhir)->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);
        $ws->getStyle('A8:F' . $akhir)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Cetak: Legal tegak, margin contoh, tanpa garis bantu.
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
        $ws->getPageMargins()->setTop(0.46)->setBottom(0.43)->setLeft(0.32)->setRight(0.41)->setHeader(0.3)->setFooter(0.3);
        $ws->setPrintGridlines(false);
        $ws->setSelectedCell('A1');
    }

    private const HARI_SINGKAT = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu', 'Minggu'];

    /**
     * Excel rekap: lembar "Per siswa" (urut jumlah terbanyak) & "Per kelas".
     *
     * @param array{siswa: list<array<string, mixed>>, kelas: list<array<string, mixed>>, total: int} $rekap Keterlambatan::rekap()
     */
    public static function rekap(array $rekap, string $mulai, string $selesai, string $kelas): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()->setCreator('Sistem Akademik')->setTitle('Rekap Keterlambatan Siswa');
        $periode = 'Periode ' . IsianBantu::tanggalIndo($mulai) . ' s.d. ' . IsianBantu::tanggalIndo($selesai) . ($kelas !== '' ? ' · Kelas ' . $kelas : '');

        $lembar = [
            ['Per siswa', ['NO', 'NAMA', 'NIS', 'KELAS', 'JUMLAH TERLAMBAT', 'TANGGAL'], [5, 32, 14, 14, 12, 60],
                array_map(static fn (array $s): array => [(string) $s['nama'], ! empty($s['manual']) ? 'belum terdaftar' : (string) $s['nis'], (string) $s['kelas'], (int) $s['jumlah'],
                    implode(', ', array_map(static fn (string $t): string => date('d/m', strtotime($t)), $s['tanggal']))], $rekap['siswa'])],
            ['Per kelas', ['NO', 'KELAS', 'JUMLAH TERLAMBAT', 'JUMLAH SISWA'], [5, 22, 14, 14],
                array_map(static fn (array $k): array => [(string) $k['kelas'], (int) $k['jumlah'], (int) $k['siswa']], $rekap['kelas'])],
        ];
        foreach ($lembar as $i => [$judul, $kepala, $lebar, $isi]) {
            $ws  = $i === 0 ? $ss->getActiveSheet() : $ss->createSheet();
            $kol = range('A', chr(ord('A') + count($kepala) - 1));
            $end = end($kol);
            $ws->setTitle($judul);
            foreach ($kol as $j => $k) {
                $ws->getColumnDimension($k)->setWidth($lebar[$j]);
            }
            foreach ([1 => 'REKAP KETERLAMBATAN SISWA', 2 => self::namaSekolah(), 3 => $periode] as $b => $teks) {
                $ws->mergeCells("A{$b}:{$end}{$b}");
                $ws->setCellValue("A{$b}", $teks);
            }
            $ws->getStyle("A1:{$end}2")->getFont()->setBold(true);
            $ws->getStyle("A1:{$end}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->fromArray($kepala, null, 'A5');
            $ws->getStyle("A5:{$end}5")->getFont()->setBold(true);
            $ws->getStyle("A5:{$end}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $b = 6;
            foreach ($isi as $no => $baris) {
                $ws->setCellValue("A{$b}", $no + 1);
                foreach ($baris as $j => $v) {
                    $sel = $kol[$j + 1] . $b;
                    is_int($v) ? $ws->setCellValue($sel, $v) : $ws->setCellValueExplicit($sel, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $b++;
            }
            $akhir = max(5, $b - 1);
            $ws->getStyle("A1:{$end}{$akhir}")->getFont()->setName(self::HURUF)->setSize(11);
            $ws->getStyle("A5:{$end}{$akhir}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $ws->getStyle("A6:A{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getStyle("A6:{$end}{$akhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $ws->getPageSetup()->setOrientation($i === 0 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
                ->setPaperSize(PageSetup::PAPERSIZE_LEGAL)->setFitToWidth(1)->setFitToHeight(0);
            $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);
            $ws->setSelectedCell('A1');
        }
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    public static function namaBerkasHari(string $tanggal): string
    {
        return 'Buku Piket Keterlambatan ' . $tanggal . '.xlsx';
    }

    public static function namaBerkasRentang(string $mulai, string $selesai): string
    {
        return 'Buku Piket Keterlambatan ' . $mulai . ($selesai !== $mulai ? ' sd ' . $selesai : '') . '.xlsx';
    }

    public static function namaBerkasRekap(string $mulai, string $selesai): string
    {
        return 'Rekap Keterlambatan ' . $mulai . ' sd ' . $selesai . '.xlsx';
    }
}
