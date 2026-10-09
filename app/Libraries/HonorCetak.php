<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Keluaran Honor Ujian (Fase 4): REKAP HONOR + SLIP dalam Excel, serta PDF Rekap dan PDF Slip.
 *
 * SATU sumber data: bahan() — turunan HonorDokumen::muat() (fungsi hitung yang sama dengan layar). Excel dan PDF
 * hanya memformat bahan itu, jadi angka tidak mungkin berbeda antar keluaran. Di Excel, kolom rupiah, TOTAL, dan
 * baris JUMLAH adalah RUMUS HIDUP (jumlah × tarif yang ada di sel tarif; JUMLAH menjumlah SEMUA baris), jadi bila
 * seseorang mengubah angka di Excel hasil unduhan, totalnya ikut benar — tidak seperti Excel lama yang JUMLAH-nya
 * hanya menjumlah sebagian baris dan slipnya rusak.
 *
 * Format mengikuti rekap sekolah: judul 3 baris, header bernomor, baris tarif di bawah judul komponen, tanda tangan
 * Ketua / Bendahara / Kepala Sekolah; cetak landscape Folio, judul diulang di tiap halaman.
 */
final class HonorCetak
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    // =================================================================
    // Pembantu murni
    // =================================================================

    /** "2026-09-25" → "25 September 2026"; tak sah/kosong → "". */
    public static function tanggalIndo(?string $d): string
    {
        $t = $d ? strtotime($d) : false;

        return $t ? (int) date('j', $t) . ' ' . self::BULAN[(int) date('n', $t)] . ' ' . date('Y', $t) : '';
    }

    /** 1500000 → "satu juta lima ratus ribu" (huruf kecil, tanpa kata "rupiah"). */
    public static function terbilang(int $n): string
    {
        if ($n === 0) {
            return 'nol';
        }
        $s = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $f = static function (int $n) use (&$f, $s): string {
            return match (true) {
                $n < 12         => $s[$n],
                $n < 20         => $s[$n - 10] . ' belas',
                $n < 100        => $s[intdiv($n, 10)] . ' puluh ' . $s[$n % 10],
                $n < 200        => 'seratus ' . $f($n - 100),
                $n < 1000       => $s[intdiv($n, 100)] . ' ratus ' . $f($n % 100),
                $n < 2000       => 'seribu ' . $f($n - 1000),
                $n < 1000000    => $f(intdiv($n, 1000)) . ' ribu ' . $f($n % 1000),
                $n < 1000000000 => $f(intdiv($n, 1000000)) . ' juta ' . $f($n % 1000000),
                default         => $f(intdiv($n, 1000000000)) . ' miliar ' . $f($n % 1000000000),
            };
        };

        return trim((string) preg_replace('/\s+/', ' ', $f($n)));
    }

    public static function rp(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    /** Nama berkas aman untuk header unduhan. */
    public static function namaBerkas(array $periode, string $jenis, string $ekstensi): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', 'Honor-' . $periode['jenis'] . '-' . str_replace('/', '-', $periode['tahun_ajaran']) . '-' . $jenis . '.' . $ekstensi) ?? 'honor.' . $ekstensi;
    }

    // =================================================================
    // Bahan bersama
    // =================================================================

    /**
     * Semua yang dibutuhkan keluaran, dari HonorDokumen::muat().
     *
     * @param array<string,mixed> $m       hasil HonorDokumen::muat()
     * @param array<string,mixed> $periode baris ujian_periode
     *
     * @return array<string,mixed>
     */
    public static function bahan(array $m, array $periode): array
    {
        $dok = $m['dokumen'];
        $set = db_connect()->table('settings')->select('school_name, city, headmaster_name')->get()->getRowArray() ?? [];
        $sekolah = trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', (string) ($set['school_name'] ?? 'SEKOLAH')));
        $kota    = trim((string) ($set['city'] ?? ''));
        $wilayah = $kota === '' ? '' : (preg_match('/^(kab|kota)/i', $kota) ? $kota : 'Kabupaten ' . $kota);
        $tempat  = trim((string) ($dok['tempat'] ?? '')) !== '' ? (string) $dok['tempat'] : $kota;

        $baris = [];
        foreach ($m['baris'] as $i => $b) {
            $sel = [];
            foreach ($m['komponen'] as $k) {
                $kid = (int) $k['id'];
                $sel[$kid] = ['n' => (int) ($b['nilai'][$kid]['nilai'] ?? 0), 'rp' => (int) ($b['per'][$kid] ?? 0)];
            }
            $baris[] = ['no' => $i + 1, 'id' => (int) $b['id'], 'nama' => (string) $b['nama'], 'jabatan' => (string) ($b['jabatan'] ?? ''), 'sel' => $sel, 'total' => (int) $b['total']];
        }

        return [
            'status'     => (string) ($dok['status'] ?? 'draf'),
            'judul'      => (string) ($dok['judul'] ?: 'HONOR UJIAN'),
            'sekolah'    => mb_strtoupper($sekolah . ($wilayah !== '' ? ' ' . $wilayah : '')),
            'namaSekolah' => $sekolah,
            'namaSekolahJudul' => self::kapitalNama($sekolah),
            // "Bekasi, 25 September 2026"; bila tanggal belum diisi: "Bekasi, Oktober 2026" (gaya rekap sekolah)
            'tanggal_ttd' => trim($tempat . ', ' . (self::tanggalIndo($dok['tanggal'] ?? null) !== '' ? self::tanggalIndo($dok['tanggal'] ?? null) : self::BULAN[(int) date('n')] . ' ' . date('Y')), ' ,'),
            'tahun'      => 'TAHUN PELAJARAN ' . str_replace('/', '-', (string) $periode['tahun_ajaran']),
            'tempat'     => $tempat,
            'tanggal'    => self::tanggalIndo($dok['tanggal'] ?? null),
            'ketua'      => (string) ($dok['ketua_nama'] ?? ''),
            'bendahara'  => (string) ($dok['bendahara_nama'] ?? ''),
            'kepsek'     => (string) ($dok['kepsek_nama'] ?? ($set['headmaster_name'] ?? '')),
            'komponen'   => $m['komponen'],
            'baris'      => $baris,
            'total_komponen' => $m['total_komponen'],
            'total_jumlah'   => $m['total_jumlah'],
            'total'      => (int) $m['total'],
        ];
    }

    // =================================================================
    // Excel
    // =================================================================

    public static function xlsx(Spreadsheet $ss): string
    {
        $aliran = fopen('php://memory', 'r+');
        (new Xlsx($ss))->save($aliran);
        rewind($aliran);
        $isi = (string) stream_get_contents($aliran);
        fclose($aliran);

        return $isi;
    }

    /** Buku kerja: lembar "REKAP HONOR" (rumus hidup) + lembar "SLIP" (satu slip per penerima). */
    public static function spreadsheet(array $m, array $periode): Spreadsheet
    {
        $b  = self::bahan($m, $periode);
        $ss = new Spreadsheet();
        $ss->getProperties()->setTitle($b['judul'])->setCreator('Sistem Akademik')->setSubject($b['tahun']);
        self::lembarRekap($ss->getActiveSheet(), $b);
        self::lembarSlip($ss->createSheet(), $b);
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    private static function huruf(int $c): string
    {
        return Coordinate::stringFromColumnIndex($c);
    }

    /** Judul kolom di cetakan: judul_cetak bila diisi, selain itu NAMA KOMPONEN huruf besar. */
    public static function judulKolom(array $k): string
    {
        $j = trim((string) ($k['judul_cetak'] ?? ''));

        return $j !== '' ? $j : mb_strtoupper((string) $k['nama']);
    }

    /** "SMK BINA NUSA" → "SMK Bina Nusa" (singkatan ≤ 3 huruf tetap kapital). */
    public static function kapitalNama(string $s): string
    {
        $kata = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(static fn (string $w): string => mb_strlen($w) <= 3 ? mb_strtoupper($w) : mb_convert_case($w, MB_CASE_TITLE), $kata));
    }

    /** Format Akuntansi Excel dengan "Rp" di kiri dan "-" untuk nol (persis rekap sekolah). */
    private const AKUNTANSI = '_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"_);_(@_)';

    /** Lebar kolom jumlah & rupiah menurut kode komponen (angka dari rekap Excel sekolah). */
    private const LEBAR_QTY = ['soal' => 5.73, 'transport' => 4.73, 'pengawas' => 4.73, 'koreksi' => 6.73, 'rapot' => 5.73];
    private const LEBAR_RP  = ['soal' => 13.73, 'transport' => 13.73, 'pengawas' => 13.73, 'koreksi' => 14.73, 'rapot' => 15.73];

    /**
     * Lebar kolom PDF dalam poin (urutan sama dengan Excel): kolom angka secukupnya, sisanya untuk NAMA dan JABATAN
     * supaya nama panjang dan "Waka. Humas & Hubungan Industri" tidak membungkus (kolom jabatan Excel sempit dan
     * membungkus; di PDF dibuat longgar agar rekap muat dalam jumlah halaman wajar).
     *
     * @param list<array<string,mixed>> $komponen
     *
     * @return list<float>
     */
    public static function lebarKolomPdf(array $komponen): array
    {
        $w = [20.0, 175.0, 150.0];
        foreach ($komponen as $k) {
            if ($k['tipe'] === 'tetap') {
                $w[] = 62.0;
            } else {
                $w[] = 24.0;
                $w[] = 56.0;
            }
        }
        $w[] = 66.0;
        $w[] = 30.0;

        return $w;
    }

    /**
     * Lebar relatif semua kolom rekap Excel (NO, NAMA, JABATAN, komponen…, TOTAL, TTD) — angka dari rekap sekolah.
     *
     * @param list<array<string,mixed>> $komponen
     *
     * @return list<float>
     */
    public static function lebarKolom(array $komponen): array
    {
        $w = [4.18, 33.45, 16.18];
        foreach ($komponen as $k) {
            $kode = (string) $k['kode'];
            if ($k['tipe'] === 'tetap') {
                $w[] = 14.73;
            } else {
                $w[] = self::LEBAR_QTY[$kode] ?? 6.73;
                $w[] = self::LEBAR_RP[$kode] ?? 14.73;
            }
        }
        $w[] = 15.73;
        $w[] = 19.45;

        return $w;
    }

    /**
     * Lembar "REKAP HONOR" — meniru berkas sekolah (HONOR ASTS.xlsx): Calibri 12, nama Times New Roman, header
     * JABATAN & kolom jumlah Koreksi berwarna kuning, format akuntansi "Rp", tinggi baris 30, garis ganda di bawah
     * header, tanda tangan Ketua (kiri) / Bendahara (kanan) / Kepala Sekolah (bawah tengah), zoom 70, cetak landscape.
     * Rumus: rupiah = jumlah × tarif (tarif tertulis di rumus seperti rekap sekolah), TOTAL = SUM rupiah, JUMLAH = SUM
     * SEMUA baris data.
     */
    private static function lembarRekap(Worksheet $ws, array $b): void
    {
        $ws->setTitle('REKAP HONOR');
        $ws->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(12);
        $ws->getDefaultRowDimension()->setRowHeight(30);
        $ws->getSheetView()->setZoomScale(70);

        // peta kolom
        $kolom = [];
        $c = 4;
        foreach ($b['komponen'] as $k) {
            if ($k['tipe'] === 'tetap') {
                $kolom[] = ['k' => $k, 'qty' => null, 'rp' => $c];
                $c++;
            } else {
                $kolom[] = ['k' => $k, 'qty' => $c, 'rp' => $c + 1];
                $c += 2;
            }
        }
        $cTotal = $c;
        $cTtd   = $c + 1;
        $akhir  = self::huruf($cTtd);
        $tot    = self::huruf($cTotal);
        $ttd    = self::huruf($cTtd);
        $n      = count($b['baris']);
        $r0     = 7;
        $rLast  = max($r0, $r0 + $n - 1);
        $rJum   = $rLast + 1;
        $tipis  = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $tengah = Alignment::HORIZONTAL_CENTER;

        // ---- judul 3 baris (tinggi 15)
        foreach ([1 => $b['judul'], 2 => $b['sekolah'], 3 => $b['tahun']] as $r => $t) {
            $ws->mergeCells("A{$r}:{$akhir}{$r}");
            $ws->setCellValue("A{$r}", $t);
            $ws->getRowDimension($r)->setRowHeight(15);
        }
        $ws->getStyle("A1:{$akhir}3")->applyFromArray(['font' => ['bold' => true], 'alignment' => ['horizontal' => $tengah, 'vertical' => Alignment::VERTICAL_BOTTOM]]);
        $ws->getStyle("A3:{$akhir}3")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

        // ---- baris 4: nomor kolom
        for ($i = 1; $i <= $cTtd; $i++) {
            $ws->setCellValue(self::huruf($i) . '4', $i);
        }
        $ws->getRowDimension(4)->setRowHeight(15);
        $ws->getStyle("A4:{$akhir}4")->applyFromArray($tipis + ['font' => ['bold' => true], 'alignment' => ['horizontal' => $tengah, 'vertical' => Alignment::VERTICAL_CENTER]]);

        // ---- baris 5–6: judul kolom + tarif
        $ws->getRowDimension(5)->setRowHeight(15);
        $ws->getRowDimension(6)->setRowHeight(15);
        foreach (['A' => 'NO', 'B' => 'NAMA', 'C' => 'JABATAN'] as $col => $t) {
            $ws->mergeCells("{$col}5:{$col}6");
            $ws->setCellValue("{$col}5", $t);
        }
        foreach ($kolom as $kol) {
            $k = $kol['k'];
            if ($kol['qty'] === null) {
                $col = self::huruf($kol['rp']);
                $ws->mergeCells("{$col}5:{$col}6");
                $ws->setCellValue("{$col}5", self::judulKolom($k));
            } else {
                $a = self::huruf($kol['qty']);
                $z = self::huruf($kol['rp']);
                $ws->mergeCells("{$a}5:{$z}5");
                $ws->setCellValue("{$a}5", self::judulKolom($k));
                $ws->mergeCells("{$a}6:{$z}6");
                $ws->setCellValue("{$a}6", 'Rp. ' . number_format((int) $k['tarif'], 0, ',', '.'));   // teks, persis rekap sekolah
            }
        }
        $ws->mergeCells("{$tot}5:{$tot}6");
        $ws->setCellValue("{$tot}5", 'TOTAL');
        $ws->mergeCells("{$ttd}5:{$ttd}6");
        $ws->setCellValue("{$ttd}5", 'TTD');
        $ws->getStyle("A5:{$akhir}6")->applyFromArray($tipis + [
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => $tengah, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
        ]);
        // Teks header membungkus hanya di kolom JABATAN dan kolom komponen (seperti rekap sekolah).
        $ws->getStyle('C5:' . self::huruf($cTotal - 1) . '6')->getAlignment()->setWrapText(true);
        $ws->getStyle("{$ttd}5:{$ttd}6")->getFont()->setBold(false);
        $ws->getStyle('B5:B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('C5:C6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
        $ws->getStyle("A6:{$akhir}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        // ---- data
        foreach ($b['baris'] as $i => $br) {
            $r = $r0 + $i;
            $ws->setCellValue("A{$r}", $br['no']);
            $ws->setCellValue("B{$r}", $br['nama']);
            $ws->setCellValue("C{$r}", $br['jabatan']);
            $rupiah = [];
            foreach ($kolom as $kol) {
                $kid = (int) $kol['k']['id'];
                $sel = $br['sel'][$kid];
                if ($kol['qty'] === null) {
                    $cell = self::huruf($kol['rp']) . $r;
                    $ws->setCellValue($cell, $sel['n']);
                } else {
                    $q = self::huruf($kol['qty']);
                    $ws->setCellValue($q . $r, $sel['n']);
                    $cell = self::huruf($kol['rp']) . $r;
                    $ws->setCellValue($cell, "={$q}{$r}*" . (int) $kol['k']['tarif']);
                }
                $rupiah[] = $cell;
            }
            $ws->setCellValue("{$tot}{$r}", '=SUM(' . implode(',', $rupiah) . ')');
            $ws->setCellValue("{$ttd}{$r}", $br['no']);
        }
        $ws->getStyle("A{$r0}:{$akhir}{$rLast}")->applyFromArray($tipis + ['alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r0}:{$akhir}{$r0}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_NONE); // garis ganda header tampil utuh
        for ($r = $r0; $r <= $rLast; $r++) {
            $ws->getRowDimension($r)->setRowHeight(30);
        }
        $ws->getRowDimension($rJum)->setRowHeight(30);
        $ws->getStyle("A{$r0}:A{$rLast}")->getAlignment()->setHorizontal($tengah);
        $ws->getStyle("B{$r0}:B{$rLast}")->getFont()->setName('Times New Roman');
        $ws->getStyle("B{$r0}:C{$rLast}")->getAlignment()->setWrapText(true);
        $ws->getStyle("C{$r0}:C{$rLast}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach ($kolom as $kol) {
            $rpH = self::huruf($kol['rp']);
            $ws->getStyle("{$rpH}{$r0}:{$rpH}{$rLast}")->getNumberFormat()->setFormatCode(self::AKUNTANSI);
            if ($kol['qty'] !== null) {
                $qH = self::huruf($kol['qty']);
                $ws->getStyle("{$qH}{$r0}:{$qH}{$rLast}")->getAlignment()->setHorizontal($tengah);
                if (($kol['k']['sumber'] ?? '') === 'koreksi') {
                    $ws->getStyle("{$qH}{$r0}:{$qH}{$rLast}")->getAlignment()->setWrapText(true);
                    $ws->getStyle("{$qH}{$r0}:{$qH}{$rLast}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
                }
            }
        }
        $ws->getStyle("{$tot}{$r0}:{$tot}{$rLast}")->getNumberFormat()->setFormatCode(self::AKUNTANSI);
        foreach ($b['baris'] as $i => $br) {
            $ws->getStyle("{$ttd}" . ($r0 + $i))->getAlignment()->setHorizontal($br['no'] % 2 === 1 ? Alignment::HORIZONTAL_LEFT : $tengah);
        }

        // ---- JUMLAH: menjumlah SEMUA baris data
        $ws->mergeCells("A{$rJum}:C{$rJum}");
        $ws->setCellValue("A{$rJum}", 'Jumlah');
        for ($col = 4; $col <= $cTotal; $col++) {
            $h = self::huruf($col);
            $ws->setCellValue("{$h}{$rJum}", "=SUM({$h}{$r0}:{$h}{$rLast})");
        }
        $ws->getStyle("A{$rJum}:{$akhir}{$rJum}")->applyFromArray($tipis + [
            'alignment' => ['horizontal' => $tengah, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getStyle("D{$rJum}:{$tot}{$rJum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $ws->getStyle("A{$rJum}")->getFont()->setName('Arial Narrow')->setBold(true);
        foreach ($kolom as $kol) {
            $ws->getStyle(self::huruf($kol['rp']) . $rJum)->getNumberFormat()->setFormatCode(self::AKUNTANSI);
        }
        $ws->getStyle("{$tot}{$rJum}")->getNumberFormat()->setFormatCode(self::AKUNTANSI);

        // ---- lebar kolom (angka dari rekap sekolah)
        $ws->getColumnDimension('A')->setWidth(4.18);
        $ws->getColumnDimension('B')->setWidth(33.45);
        $ws->getColumnDimension('C')->setWidth(16.18);
        foreach ($kolom as $kol) {
            $kode = (string) $kol['k']['kode'];
            if ($kol['qty'] === null) {
                $ws->getColumnDimension(self::huruf($kol['rp']))->setWidth(14.73);
            } else {
                $ws->getColumnDimension(self::huruf($kol['qty']))->setWidth(self::LEBAR_QTY[$kode] ?? 6.73);
                $ws->getColumnDimension(self::huruf($kol['rp']))->setWidth(self::LEBAR_RP[$kode] ?? 14.73);
            }
        }
        $ws->getColumnDimension($tot)->setWidth(15.73);
        $ws->getColumnDimension($ttd)->setWidth(19.45);

        // ---- tanda tangan (posisi relatif sama dengan rekap sekolah: JUMLAH + 2 …)
        $t = $rJum + 2;
        for ($r = $t - 1; $r <= $t + 11; $r++) {
            $ws->getRowDimension($r)->setRowHeight(15);
        }
        $kiri   = static function (string $sel, string $teks, bool $tebal = false) use ($ws): void {
            $ws->setCellValue($sel, $teks);
            $ws->getStyle($sel)->applyFromArray(['font' => ['bold' => $tebal], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]]);
        };
        $kosongNama = '(........................)';
        $kiri("C{$t}", 'Mengetahui,');
        $kiri('C' . ($t + 1), 'Ketua');
        $kiri('C' . ($t + 5), $b['ketua'] !== '' ? $b['ketua'] : $kosongNama, true);
        $aR = self::huruf(max(4, $cTotal - 3));
        $kiri("{$aR}{$t}", $b['tanggal_ttd']);
        $kiri($aR . ($t + 1), 'Bendahara');
        $ws->mergeCells($aR . ($t + 5) . ':' . $tot . ($t + 5));
        $kiri($aR . ($t + 5), $b['bendahara'] !== '' ? $b['bendahara'] : $kosongNama, true);
        $ws->getStyle($aR . ($t + 5))->getFont()->setName('Arial')->setSize(10);
        $cA = 'F';
        $cZ = self::huruf(min(8, max(6, $cTotal)));
        foreach ([[$t + 6, 'Menyetujui,', false], [$t + 7, 'Kepala ' . $b['namaSekolahJudul'], false], [$t + 11, $b['kepsek'] !== '' ? $b['kepsek'] : $kosongNama, true]] as [$r, $teks, $tebal]) {
            $ws->mergeCells("{$cA}{$r}:{$cZ}{$r}");
            $ws->setCellValue("{$cA}{$r}", $teks);
            $ws->getStyle("{$cA}{$r}")->applyFromArray(['font' => ['bold' => $tebal], 'alignment' => ['horizontal' => $tengah, 'vertical' => Alignment::VERTICAL_BOTTOM]]);
        }

        // ---- cetak: landscape Folio, 1 halaman lebar, judul baris 1–6 diulang, margin & rata tengah seperti aslinya
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_FOLIO)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ps->setRowsToRepeatAtTopByStartAndEnd(1, 6);
        $ps->setPrintArea("A1:{$akhir}" . ($t + 11));
        $ps->setHorizontalCentered(true);
        $ws->getPageMargins()->setTop(0.748)->setBottom(0.748)->setLeft(0.197)->setRight(0.236)->setHeader(0.315)->setFooter(0.315);
        $ws->getHeaderFooter()->setOddFooter(($b['status'] === 'draf' ? '&C&8DRAF - belum final' : '') . '&R&8Halaman &P dari &N');
    }

    /** Daftar komponen bernilai (≠ 0) satu penerima untuk slip. @return list<array<string,mixed>> */
    public static function itemSlip(array $b, array $komponen, array $baris): array
    {
        $item = [];
        foreach ($komponen as $k) {
            $s = $baris['sel'][(int) $k['id']] ?? ['n' => 0, 'rp' => 0];
            if ($s['rp'] <= 0) {
                continue;
            }
            $item[] = ['nama' => (string) $k['nama'], 'jumlah' => $k['tipe'] === 'satuan' ? $s['n'] : null, 'satuan' => (string) ($k['satuan'] ?? ''), 'tarif' => $k['tipe'] === 'satuan' ? (int) $k['tarif'] : null, 'rp' => $s['rp']];
        }

        return $item;
    }

    private static function lembarSlip(Worksheet $ws, array $b): void
    {
        $ws->setTitle('SLIP');
        foreach (['A' => 4.5, 'B' => 32, 'C' => 11, 'D' => 14, 'E' => 17] as $c => $w) {
            $ws->getColumnDimension($c)->setWidth($w);
        }
        $r = 1;
        $ukuran = [];
        foreach ($b['baris'] as $i => $br) {
            $awal = $r;
            foreach ([['SLIP HONOR', true, 13], [$b['judul'], true, 10], [$b['sekolah'] . ' — ' . $b['tahun'], false, 9]] as [$t, $tebal, $uk]) {
                $ukuran[$r] = $uk;
                $ws->mergeCells("A{$r}:E{$r}");
                $ws->setCellValue("A{$r}", $t);
                $ws->getStyle("A{$r}")->getFont()->setBold($tebal)->setSize($uk);
                $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $r++;
            }
            $r++;
            foreach (['Nama' => $br['nama'], 'Jabatan' => $br['jabatan'] !== '' ? $br['jabatan'] : '-'] as $lbl => $v) {
                $ws->setCellValue("A{$r}", $lbl);
                $ws->mergeCells("A{$r}:B{$r}");
                $ws->setCellValue("C{$r}", ': ' . $v);
                $ws->mergeCells("C{$r}:E{$r}");
                $ws->getStyle("C{$r}")->getFont()->setBold($lbl === 'Nama');
                $r++;
            }
            $r++;
            foreach (['No', 'Komponen', 'Jumlah', 'Tarif (Rp)', 'Rupiah'] as $j => $t) {
                $ws->setCellValue(self::huruf($j + 1) . $r, $t);
            }
            $ws->getStyle("A{$r}:E{$r}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5EAF2']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
            $hdr = $r;
            $r++;
            $item = self::itemSlip($b, $b['komponen'], $br);
            if ($item === []) {
                $ws->mergeCells("A{$r}:E{$r}");
                $ws->setCellValue("A{$r}", 'Tidak ada honor pada periode ini.');
                $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $r++;
            }
            foreach ($item as $no => $it) {
                $ws->setCellValue("A{$r}", $no + 1);
                $ws->setCellValue("B{$r}", $it['nama']);
                if ($it['jumlah'] !== null) {
                    $ws->setCellValue("C{$r}", $it['jumlah'] . ' ' . $it['satuan']);
                    $ws->setCellValue("D{$r}", $it['tarif']);
                }
                $ws->setCellValue("E{$r}", $it['rp']);
                $r++;
            }
            $ws->mergeCells("A{$r}:D{$r}");
            $ws->setCellValue("A{$r}", 'JUMLAH PENDAPATAN');
            $ws->setCellValue("E{$r}", $br['total']);
            $ws->getStyle("A{$r}:E{$r}")->getFont()->setBold(true);
            $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("D" . ($hdr + 1) . ":E{$r}")->getNumberFormat()->setFormatCode('#,##0');
            $ws->getStyle("C" . ($hdr + 1) . ":C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("A{$hdr}:E{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $r++;
            $ws->mergeCells("A{$r}:E{$r}");
            $ws->setCellValue("A{$r}", 'Terbilang: ' . ucfirst(self::terbilang($br['total'])) . ' rupiah');
            $ws->getStyle("A{$r}")->getFont()->setItalic(true);
            $ws->getStyle("A{$r}")->getAlignment()->setWrapText(true);
            $ws->getRowDimension($r)->setRowHeight(26);
            $r += 2;
            $ws->mergeCells("A{$r}:B{$r}");
            $ws->setCellValue("A{$r}", 'Penerima,');
            $ws->mergeCells("C{$r}:E{$r}");
            $ws->setCellValue("C{$r}", trim($b['tempat'] . ($b['tanggal'] !== '' ? ', ' . $b['tanggal'] : '')));
            $r++;
            $ws->mergeCells("C{$r}:E{$r}");
            $ws->setCellValue("C{$r}", 'Bendahara,');
            $r += 3;
            $ws->mergeCells("A{$r}:B{$r}");
            $ws->setCellValue("A{$r}", '(' . $br['nama'] . ')');
            $ws->mergeCells("C{$r}:E{$r}");
            $ws->setCellValue("C{$r}", $b['bendahara'] !== '' ? $b['bendahara'] : '(........................)');
            $ws->getStyle("A" . ($r - 4) . ":E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $r++;
            if (($i + 1) % 2 === 0 && $i + 1 < count($b['baris'])) {
                $ws->setBreak("A{$r}", Worksheet::BREAK_ROW);   // dua slip per halaman
                $r++;
            } else {
                $r += 2;
            }
            unset($awal);
        }
        // Lembar slip memakai Arial 10 (bukan Calibri 12 milik lembar rekap); ukuran judul dikembalikan.
        $ws->getStyle('A1:E' . max(1, $r))->getFont()->setName('Arial')->setSize(10);
        foreach ($ukuran as $baris => $uk) {
            $ws->getStyle("A{$baris}")->getFont()->setSize($uk);
        }
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ps->setHorizontalCentered(true);
        $ws->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.6)->setRight(0.6);
    }

    // =================================================================
    // PDF
    // =================================================================

    /** HTML → PDF string. $kertas: 'f4-landscape' (rekap) atau 'a4-portrait' (slip). */
    public static function pdf(string $html, string $kertas = 'f4-landscape'): string
    {
        $o = new Options();
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('isRemoteEnabled', false);
        $d = new Dompdf($o);
        $d->loadHtml($html);
        if ($kertas === 'a4-portrait') {
            $d->setPaper('A4', 'portrait');
        } else {
            // F4/Folio = 215 × 330 mm. Nama 'F4' TIDAK dikenal Dompdf (diam-diam jadi Letter), jadi ukurannya ditulis eksplisit (pt).
            $d->setPaper([0, 0, 609.45, 935.43], 'landscape');
        }
        $d->render();

        return (string) $d->output();
    }

    public static function rekapHtml(array $m, array $periode): string
    {
        return view('pdf/honor_rekap', ['b' => self::bahan($m, $periode)]);
    }

    /**
     * Slip PDF: semua penerima, atau satu bila $barisId diberikan. null bila baris itu tak ada.
     */
    public static function slipHtml(array $m, array $periode, ?int $barisId = null): ?string
    {
        $b = self::bahan($m, $periode);
        if ($barisId !== null) {
            $b['baris'] = array_values(array_filter($b['baris'], static fn (array $x): bool => $x['id'] === $barisId));
            if ($b['baris'] === []) {
                return null;
            }
        }
        foreach ($b['baris'] as &$br) {
            $br['item']      = self::itemSlip($b, $b['komponen'], $br);
            $br['terbilang'] = ucfirst(self::terbilang($br['total'])) . ' rupiah';
        }
        unset($br);

        return view('pdf/honor_slip', ['b' => $b]);
    }
}
