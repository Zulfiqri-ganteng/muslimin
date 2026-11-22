<?php

namespace App\Libraries;

use App\Models\SettingModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cetak JADWAL PENGAWAS — Excel + PDF yang meniru berkas contoh klien "jadwal pengawas.xlsx" (F5 cetak jadwal, 2026-11-22).
 * Data dari tab Jadwal Pengawas (JadwalPengawas: sesi = slot Jadwal Ujian, isi = kode guru per ruang).
 *
 * Bentuk (hasil bedah contoh):
 *  - lembar "Jadwal Pengawas Pagi" & "Jadwal Pengawas Siang" (Legal mendatar): judul 5 baris (JADWAL PENGAWAS / ASESMEN … /
 *    SEKOLAH / TAHUN PELAJARAN / PAGI), kepala magenta #EF57D9: NO | HARI/TGL | JAM KE- | WAKTU | RUANG 1..N; isi = KODE pengawas;
 *    baris Istirahat (miring) digabung selebar ruang; sesi beruntun berpengawas sama digabung vertikal; garis luar & pemisah hari
 *    tebal mulai kolom B; blok tanda tangan Kepala Sekolah & Ketua Panitia di bawah tabel — DI LUAR area cetak (persis contoh:
 *    area cetak B1 s/d satu baris di bawah tabel; bila tanda tangan ikut dicetak, lembar pagi tidak muat satu halaman Legal);
 *  - lembar "KODE PENGAWAS" (Legal tegak): Kode | Nama (Times New Roman tebal) + kolom bantu angka 1 dan jumlahnya.
 * PDF: halaman Pagi & Siang = tampilan area cetak; daftar kode = PDF tersendiri (dompdf tak bisa mencampur arah kertas).
 */
class JadwalPengawasCetak
{
    private const MAGENTA = 'EF57D9';

    /** Ukuran per lembar meniru contoh (tinggi judul baris 1–5, tinggi baris tabel, margin inci atas/bawah/kiri/kanan, zoom). */
    private const TATA = [
        'pagi'  => ['judul' => [17, 17, 17, 17, 17], 'shiftUkuran' => 13, 'baris' => 22.0, 'margin' => [0.27, 0.31, 1.05, 0.12], 'zoom' => 100],
        'siang' => ['judul' => [17, 17, 17, 17, 15.5], 'shiftUkuran' => 12, 'baris' => 21.5, 'margin' => [0.27, 0.33, 1.05, 0.23], 'zoom' => 85],
    ];

    private const LEBAR = ['A' => 5.6, 'B' => 16.6, 'C' => 7.6, 'D' => 13.6, 'ruang' => 4.9];

    // =================================================================
    //  Data
    // =================================================================

    /** Ada isi jadwal pengawas di periode ini? */
    public static function adaIsi(array $periode): bool
    {
        return JadwalPengawas::jumlahTerisi((int) $periode['id']) > 0;
    }

    /**
     * Data satu shift: hari → baris (sesi/istirahat) dengan kode per ruang dan rentang gabung vertikal.
     *
     * @return array{shift: string, nRuang: int, hari: list<array{tanggal: string, baris: list<array{jenis: string, jamKe: ?int, waktu: string, kode: array<int, string>, rentang: array<int, int>}>}>}
     */
    public static function data(array $periode, string $shift): array
    {
        $pid     = (int) $periode['id'];
        $nRuang  = \App\Models\UjianPeriodeModel::jumlahRuang($periode, $shift);
        $kodeDari = [];
        foreach (JadwalPengawas::semuaGuru() as $g) {
            $kodeDari[$g['id']] = $g['kode'];
        }
        $tugas = JadwalPengawas::penugasan($pid, $shift);

        $hari = [];
        foreach (JadwalPengawas::sesi($periode, $shift) as $h) {
            $baris = [];
            foreach ($h['baris'] as $b) {
                $kode = [];
                if ($b['jenis'] === 'sesi') {
                    foreach ($tugas[$b['kunci']] ?? [] as $ruang => $gid) {
                        if ($ruang >= 1 && $ruang <= $nRuang) {
                            $kode[$ruang] = $kodeDari[$gid] ?? '';
                        }
                    }
                }
                $baris[] = ['jenis' => $b['jenis'], 'jamKe' => $b['jamKe'], 'waktu' => $b['waktu'], 'kode' => $kode, 'rentang' => []];
            }
            // Gabung vertikal: sesi beruntun (tanpa istirahat di antaranya) dengan kode sama di ruang yang sama.
            for ($ruang = 1; $ruang <= $nRuang; $ruang++) {
                $i = 0;
                while ($i < count($baris)) {
                    $kode = $baris[$i]['kode'][$ruang] ?? '';
                    if ($baris[$i]['jenis'] !== 'sesi' || $kode === '') {
                        $i++;
                        continue;
                    }
                    $j = $i + 1;
                    while ($j < count($baris) && $baris[$j]['jenis'] === 'sesi' && ($baris[$j]['kode'][$ruang] ?? '') === $kode) {
                        $j++;
                    }
                    $baris[$i]['rentang'][$ruang] = $j - $i;
                    for ($k = $i + 1; $k < $j; $k++) {
                        $baris[$k]['rentang'][$ruang] = 0; // tertutup gabungan di atasnya
                    }
                    $i = $j;
                }
            }
            $hari[] = ['tanggal' => $h['tanggal'], 'baris' => $baris];
        }

        return ['shift' => $shift, 'nRuang' => $nRuang, 'hari' => $hari];
    }

    /** Lima baris judul lembar pengawas. @return list<string> */
    public static function judul(array $periode, string $shift): array
    {
        [$j1, $sekolah, $tp] = JadwalUjianCetak::judul($periode);

        return ['JADWAL PENGAWAS', preg_replace('/^JADWAL\s+/', '', $j1), $sekolah, $tp, mb_strtoupper($shift)];
    }

    /** Dua blok tanda tangan: [jabatan kiri, nama kiri, jabatan kanan, nama kanan]. */
    public static function tandaTangan(array $periode): array
    {
        $set     = (new SettingModel())->get();
        $kata    = preg_split('/\s+/', trim((string) ($set['school_name'] ?? 'Sekolah')));
        $sekolah = implode(' ', array_map(static fn (string $w): string => mb_strlen($w) <= 3 && mb_strtoupper($w) === $w ? $w : mb_convert_case(mb_strtolower($w), MB_CASE_TITLE), $kata));
        $singkat = ['ASTS1' => 'ASTS', 'ASAS' => 'ASAS', 'ASTS2' => 'ASTS', 'ASAT' => 'ASAT'][$periode['jenis']] ?? $periode['jenis'];

        return ['Kepala Sekolah ' . $sekolah, trim((string) ($set['headmaster_name'] ?? '')), 'Ketua Panitia ' . $singkat, trim((string) ($periode['ketua_panitia'] ?? ''))];
    }

    /** Pengawas terdaftar untuk lembar KODE PENGAWAS (urut kode). @return list<array{kode: string, nama: string}> */
    public static function daftarKode(array $periode): array
    {
        $pilih = array_flip(JadwalPengawas::guruTerpilih((int) $periode['id']));
        $out   = [];
        foreach (JadwalPengawas::semuaGuru() as $g) {
            if (isset($pilih[$g['id']]) && $g['kode'] !== '') {
                $out[] = ['kode' => $g['kode'], 'nama' => $g['nama']];
            }
        }

        return $out;
    }

    // =================================================================
    //  Excel
    // =================================================================

    public static function spreadsheet(array $periode): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $ss->removeSheetByIndex(0);
        foreach (JadwalUjianCetak::shiftAda() as $shift) {
            $ws = new Worksheet($ss, 'Jadwal Pengawas ' . ucfirst($shift));
            $ss->addSheet($ws);
            self::isiLembar($ws, $periode, self::data($periode, $shift));
        }
        $ws = new Worksheet($ss, 'KODE PENGAWAS');
        $ss->addSheet($ws);
        self::isiKode($ws, self::daftarKode($periode));
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    /** Angka bila kode berupa angka saja (contoh: 27), selain itu teks ("12A"). */
    private static function nilaiKode(string $kode): int|string
    {
        return ctype_digit($kode) ? (int) $kode : $kode;
    }

    private static function isiLembar(Worksheet $ws, array $periode, array $d): void
    {
        $t     = self::TATA[$d['shift']];
        $n     = max(1, $d['nRuang']);
        $akhir = Coordinate::stringFromColumnIndex(4 + $n);
        $kol   = static fn (int $ruang): string => Coordinate::stringFromColumnIndex(4 + $ruang);
        $ws->getSheetView()->setZoomScale($t['zoom']);
        foreach (['A', 'B', 'C', 'D'] as $c) {
            $ws->getColumnDimension($c)->setWidth(self::LEBAR[$c]);
        }
        for ($r = 1; $r <= $n; $r++) {
            $ws->getColumnDimension($kol($r))->setWidth(self::LEBAR['ruang']);
        }

        // Judul 5 baris (B..akhir digabung, tebal 13; baris shift siang 12)
        foreach (self::judul($periode, $d['shift']) as $i => $teks) {
            $r = $i + 1;
            $ws->mergeCells("B{$r}:{$akhir}{$r}");
            $ws->setCellValue("B{$r}", $teks);
            $ws->getRowDimension($r)->setRowHeight($t['judul'][$i]);
        }
        $ws->getStyle("B1:{$akhir}5")->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_BOTTOM]]);
        $ws->getStyle('B5')->getFont()->setSize($t['shiftUkuran']);

        // Kepala (baris 7–8)
        foreach (['A7:A8' => 'NO', 'B7:B8' => 'HARI/TGL', 'C7:C8' => 'JAM KE-', 'D7:D8' => 'WAKTU'] as $rentang => $teks) {
            $ws->mergeCells($rentang);
            $ws->setCellValue(explode(':', $rentang)[0], $teks);
        }
        $ws->mergeCells("E7:{$akhir}7");
        $ws->setCellValue('E7', 'RUANG');
        for ($r = 1; $r <= $n; $r++) {
            $ws->setCellValue($kol($r) . '8', $r);
        }
        $ws->getRowDimension(7)->setRowHeight($t['baris']);
        $ws->getRowDimension(8)->setRowHeight($t['baris']);
        $ws->getStyle("A7:{$akhir}8")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::MAGENTA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Isi per hari
        $r        = 9;
        $awalBlok = [];
        foreach ($d['hari'] as $no => $h) {
            $awal = $r;
            $awalBlok[] = $awal;
            $jml  = count($h['baris']);
            foreach ($h['baris'] as $b) {
                $ws->getRowDimension($r)->setRowHeight($t['baris']);
                $ws->setCellValue("D{$r}", $b['waktu']);
                if ($b['jenis'] === 'istirahat') {
                    $ws->mergeCells("E{$r}:{$akhir}{$r}");
                    $ws->setCellValue("E{$r}", 'Istirahat');
                    $ws->getStyle("E{$r}")->getFont()->setItalic(true);
                } else {
                    $ws->setCellValue("C{$r}", (int) $b['jamKe']);
                    for ($ru = 1; $ru <= $n; $ru++) {
                        $rentang = $b['rentang'][$ru] ?? 1;
                        if ($rentang === 0) {
                            continue;
                        }
                        $kode = $b['kode'][$ru] ?? '';
                        if ($kode !== '') {
                            $ws->setCellValue($kol($ru) . $r, self::nilaiKode($kode));
                        }
                        if ($rentang > 1) {
                            $ws->mergeCells($kol($ru) . $r . ':' . $kol($ru) . ($r + $rentang - 1));
                        }
                    }
                }
                $r++;
            }
            $akhirHari = $r - 1;
            $ws->setCellValue("A{$awal}", $no + 1);
            $ws->setCellValue("B{$awal}", JadwalUjianCetak::namaHari($h['tanggal']));
            if ($jml > 1) {
                $ws->mergeCells("A{$awal}:A{$akhirHari}");
                if ($jml > 2) {
                    $ws->mergeCells('B' . ($awal + 1) . ":B{$akhirHari}");
                }
                JadwalUjianCetak::selTanggal($ws, 'B' . ($awal + 1), $h['tanggal']);
            } else {
                $ws->setCellValue("B{$awal}", JadwalUjianCetak::namaHari($h['tanggal']) . ' ' . JadwalUjianCetak::tanggalTeks($h['tanggal']));
            }
        }
        $akhirTabel = max(8, $r - 1);

        if ($akhirTabel >= 9) {
            $ws->getStyle("A9:{$akhir}{$akhirTabel}")->applyFromArray(['font' => ['size' => 11], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
            $ws->getStyle("B9:B{$akhirTabel}")->getFont()->setBold(true)->setSize(10);
            $ws->getStyle("D9:D{$akhirTabel}")->getFont()->setSize(10);
            foreach ($awalBlok as $baris) {
                $ws->getStyle("A{$baris}")->getFont()->setBold(true);
            }
        }

        // Garis: tipis di dalam (termasuk kolom NO); luar tebal mulai kolom B, garis atas kepala & tiap pergantian hari tebal.
        $ws->getStyle("A7:{$akhir}{$akhirTabel}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("B7:{$akhir}{$akhirTabel}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        foreach ($awalBlok as $baris) {
            $ws->getStyle("B{$baris}:{$akhir}{$baris}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        }

        // Tanda tangan (di luar area cetak, persis contoh)
        [$jabKiri, $namaKiri, $jabKanan, $namaKanan] = self::tandaTangan($periode);
        $kolKanan = Coordinate::stringFromColumnIndex(max(5, 4 + $n - 7)); // contoh 21 ruang: kolom R
        $ws->setCellValue('B' . ($akhirTabel + 3), 'Mengetahui,');
        $ws->setCellValue('B' . ($akhirTabel + 4), $jabKiri);
        $ws->setCellValue($kolKanan . ($akhirTabel + 4), $jabKanan);
        $ws->setCellValue('B' . ($akhirTabel + 11), $namaKiri);
        $ws->setCellValue($kolKanan . ($akhirTabel + 11), $namaKanan);

        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LEGAL)->setScale(100, false)
            ->setFitToPage(false)->setPrintArea("B1:{$akhir}" . ($akhirTabel + 1));
        [$atas, $bawah, $kiri, $kanan] = $t['margin'];
        $ws->getPageMargins()->setTop($atas)->setBottom($bawah)->setLeft($kiri)->setRight($kanan);
    }

    private static function isiKode(Worksheet $ws, array $daftar): void
    {
        $ws->getColumnDimension('C')->setWidth(10.6);
        $ws->getColumnDimension('D')->setWidth(34.1);
        $ws->setCellValue('C1', 'Kode Pengawas');
        $ws->setCellValue('D1', 'Nama Pengawas');
        $ws->getRowDimension(1)->setRowHeight(31);
        $ws->getStyle('C1:D1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::MAGENTA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('C1')->getAlignment()->setWrapText(true);
        $r = 2;
        foreach ($daftar as $p) {
            $ws->setCellValue("C{$r}", self::nilaiKode($p['kode']));
            $ws->setCellValue("D{$r}", $p['nama']);
            $ws->setCellValue("E{$r}", 1);
            $ws->getRowDimension($r)->setRowHeight(17.5);
            $r++;
        }
        $akhir = $r - 1;
        if ($akhir >= 2) {
            $ws->getStyle("C2:D{$akhir}")->applyFromArray(['font' => ['name' => 'Times New Roman', 'bold' => true, 'size' => 12], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
            $ws->getStyle("C2:C{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $ws->setCellValue("E{$r}", '=SUM(E2:E' . max(2, $akhir) . ')');
        $ws->getStyle('C1:D' . max(1, $akhir))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_LEGAL)->setScale(100, false);
        $ws->getPageMargins()->setTop(0.35)->setBottom(0.32)->setLeft(1.01)->setRight(0.70);
    }

    // =================================================================
    //  PDF
    // =================================================================

    /** PDF Jadwal Pengawas: satu halaman Legal mendatar per shift (tampilan area cetak contoh). */
    public static function pdf(array $periode): string
    {
        $shifts  = JadwalUjianCetak::shiftAda();
        $isi     = '';
        for ($kecil = 1.0; $kecil >= 0.7; $kecil -= 0.05) {
            $halaman = [];
            foreach ($shifts as $shift) {
                $halaman[] = self::htmlLembar($periode, self::data($periode, $shift), $kecil);
            }
            [$isi, $jumlah] = JadwalUjianCetak::renderPdf(self::bungkus(implode('', $halaman), 'legal landscape'), 'legal', 'landscape');
            if ($jumlah <= max(1, count($shifts))) {
                break;
            }
        }

        return $isi;
    }

    /** PDF daftar KODE PENGAWAS (Legal tegak). */
    public static function pdfKode(array $periode): string
    {
        $pt     = static fn (float $lebar): float => round(((int) floor($lebar * 7 + 5)) * 0.75, 2);
        $tipis  = '0.75pt solid #000';
        $baris  = '';
        $daftar = self::daftarKode($periode);
        foreach ($daftar as $p) {
            $baris .= '<tr style="height:16.6pt"><td></td><td></td>'
                . '<td style="border:' . $tipis . ';text-align:center;font-family:\'Times New Roman\',Times,serif;font-weight:bold;font-size:12pt">' . esc($p['kode']) . '</td>'
                // Tak dilipat (di Excel sel nama tidak dilipat; nama panjang tetap satu baris).
                . '<td style="border:' . $tipis . ';font-family:\'Times New Roman\',Times,serif;font-weight:bold;font-size:12pt;padding-left:2pt;white-space:nowrap;overflow:hidden">' . esc($p['nama']) . '</td>'
                . '<td style="text-align:right;font-size:11pt;padding-right:2pt">1</td></tr>';
        }
        $kolom = '<tr>' . implode('', array_map(static fn (float $w): string => '<td style="width:' . $w . 'pt;height:0;padding:0;border:0;font-size:0;line-height:0"></td>', [$pt(8.43), $pt(8.43), $pt(10.6), $pt(34.1), $pt(8.43)])) . '</tr>';
        $html  = '<div style="padding:' . round(0.35 * 72, 1) . 'pt ' . round(0.70 * 72, 1) . 'pt 0 ' . round(1.01 * 72, 1) . 'pt">'
            . '<table style="border-collapse:collapse;line-height:1.0">' . $kolom
            . '<tr style="height:30pt"><td></td><td></td>'
            . '<td style="border:' . $tipis . ';background:#' . self::MAGENTA . ';text-align:center;font-weight:bold;font-size:12pt">Kode<br>Pengawas</td>'
            . '<td style="border:' . $tipis . ';background:#' . self::MAGENTA . ';text-align:center;font-weight:bold;font-size:12pt">Nama Pengawas</td><td></td></tr>'
            . $baris
            . '<tr style="height:14pt"><td></td><td></td><td></td><td></td><td style="text-align:right;font-size:11pt;padding-right:2pt">' . count($daftar) . '</td></tr>'
            . '</table></div>';

        return JadwalUjianCetak::renderPdf(self::bungkus($html, 'legal portrait'), 'legal', 'portrait')[0];
    }

    private static function bungkus(string $isi, string $ukuran): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page { size: ' . $ukuran . '; margin: 0; } body { margin: 0; font-family: Calibri, "DejaVu Sans", sans-serif; color: #000; }'
            . '.hal { page-break-after: always; } .hal:last-child { page-break-after: auto; }'
            . 'td { vertical-align: middle; padding: 0 1pt; }'
            . '</style></head><body>' . $isi . '</body></html>';
    }

    private static function htmlLembar(array $periode, array $d, float $kecil): string
    {
        $t = self::TATA[$d['shift']];
        $n = max(1, $d['nRuang']);
        [$atas, $bawah, $kiri, $kanan] = $t['margin'];
        $lebarPt = static fn (float $lebar): float => ((int) floor($lebar * 7 + 5)) * 0.75;

        $jmlBaris = 0;
        foreach ($d['hari'] as $h) {
            $jmlBaris += count($h['baris']);
        }
        $lebarMentah  = $lebarPt(self::LEBAR['B']) + $lebarPt(self::LEBAR['C']) + $lebarPt(self::LEBAR['D']) + $n * $lebarPt(self::LEBAR['ruang']);
        $tinggiMentah = array_sum($t['judul']) + 15 + 2 * $t['baris'] + $jmlBaris * $t['baris'];
        $s = min(1.0, (612 - ($atas + $bawah) * 72 - 6) / $tinggiMentah, (1008 - ($kiri + $kanan) * 72 - 4) / $lebarMentah) * $kecil;

        $pt     = static fn (float $lebar): float => round($lebarPt($lebar) * $s, 2);
        $tipis  = round(0.75 * $s, 2) . 'pt solid #000';
        $tebal  = round(1.5 * $s, 2) . 'pt solid #000';
        $f      = static fn (float $u): string => 'font-size:' . round($u * $s, 2) . 'pt;';
        $tg     = static fn (float $h): string => 'height:' . round(max(1, $h * $s - 0.9 * $s), 2) . 'pt;';
        $garis  = static fn (string $a, string $b, string $k, string $ka): string => "border-top:{$a};border-bottom:{$b};border-left:{$k};border-right:{$ka};";
        $sel    = static fn (string $isi, array $gaya, int $cs = 1, int $rs = 1): string => '<td' . ($cs > 1 ? ' colspan="' . $cs . '"' : '') . ($rs > 1 ? ' rowspan="' . $rs . '"' : '') . ' style="text-align:center;' . implode('', $gaya) . '">' . $isi . '</td>';
        $lebar  = array_merge([$pt(self::LEBAR['B']), $pt(self::LEBAR['C']), $pt(self::LEBAR['D'])], array_fill(0, $n, $pt(self::LEBAR['ruang'])));
        $kanan1 = static fn (int $i) => $i === $n ? $tebal : $tipis;
        $magenta = 'background:#' . self::MAGENTA . ';';

        $h = '<div class="hal" style="padding:' . round($atas * 72, 1) . 'pt ' . round($kanan * 72, 1) . 'pt 0 ' . round($kiri * 72, 1) . 'pt;">'
            . '<table style="border-collapse:collapse;line-height:1.0;width:' . array_sum($lebar) . 'pt;"><tr>';
        foreach ($lebar as $w) {
            $h .= '<td style="width:' . $w . 'pt;height:0;padding:0;border:0;font-size:0;line-height:0"></td>';
        }
        $h .= '</tr>';
        foreach (self::judul($periode, $d['shift']) as $i => $teks) {
            $h .= '<tr>' . $sel(esc($teks), ['font-weight:bold;', $f($i === 4 ? $t['shiftUkuran'] : 13), $tg($t['judul'][$i]), 'vertical-align:bottom;'], 3 + $n) . '</tr>';
        }
        $h .= '<tr>' . $sel('&nbsp;', [$tg(15)], 3 + $n) . '</tr>';
        $h .= '<tr>' . $sel('HARI/TGL', ['font-weight:bold;', $f(11), $magenta, $garis($tebal, $tebal, $tebal, $tipis)], 1, 2)
            . $sel('JAM KE-', ['font-weight:bold;', $f(11), $magenta, $garis($tebal, $tebal, $tipis, $tipis)], 1, 2)
            . $sel('WAKTU', ['font-weight:bold;', $f(11), $magenta, $garis($tebal, $tebal, $tipis, $tipis)], 1, 2)
            . $sel('RUANG', ['font-weight:bold;', $f(11), $magenta, $tg($t['baris']), $garis($tebal, $tipis, $tipis, $tebal)], $n) . '</tr><tr>';
        for ($r = 1; $r <= $n; $r++) {
            $h .= $sel((string) $r, ['font-weight:bold;', $f(11), $magenta, $tg($t['baris']), $garis($tipis, $tebal, $tipis, $kanan1($r))]);
        }
        $h .= '</tr>';

        foreach ($d['hari'] as $hari) {
            $jml = count($hari['baris']);
            foreach ($hari['baris'] as $j => $b) {
                $atasG  = $j === 0 ? $tebal : $tipis;
                $bawahG = $j === $jml - 1 ? $tebal : $tipis;
                $h .= '<tr>';
                if ($j === 0) {
                    $isiHari = esc(JadwalUjianCetak::namaHari($hari['tanggal'])) . ($jml === 1 ? ' ' . esc(JadwalUjianCetak::tanggalTeks($hari['tanggal'])) : '');
                    $h .= $sel($isiHari, ['font-weight:bold;', $f(10), $tg($t['baris']), $garis($tebal, $jml === 1 ? $tebal : $tipis, $tebal, $tipis)]);
                } elseif ($j === 1) {
                    $h .= $sel(esc(JadwalUjianCetak::tanggalTeks($hari['tanggal'])), ['font-weight:bold;', $f(10), $garis($tipis, $tebal, $tebal, $tipis)], 1, $jml - 1);
                }
                $h .= $sel($b['jamKe'] !== null ? (string) (int) $b['jamKe'] : '', [$f(11), $tg($t['baris']), $garis($atasG, $bawahG, $tipis, $tipis)]);
                $h .= $sel(esc($b['waktu']), [$f(10), $tg($t['baris']), $garis($atasG, $bawahG, $tipis, $tipis)]);
                if ($b['jenis'] === 'istirahat') {
                    $h .= $sel('Istirahat', ['font-style:italic;', $f(11), $garis($atasG, $bawahG, $tipis, $tebal)], $n);
                } else {
                    for ($r = 1; $r <= $n; $r++) {
                        $rentang = $b['rentang'][$r] ?? 1;
                        if ($rentang === 0) {
                            continue;
                        }
                        $bawahSel = ($j + $rentang - 1) === $jml - 1 ? $tebal : $tipis;
                        $h .= $sel(esc($b['kode'][$r] ?? ''), [$f(11), $garis($atasG, $bawahSel, $tipis, $kanan1($r))], 1, $rentang);
                    }
                }
                $h .= '</tr>';
            }
        }

        return $h . '</table></div>';
    }
}
