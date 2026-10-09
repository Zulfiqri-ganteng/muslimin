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

    private static function lembarRekap(Worksheet $ws, array $b): void
    {
        $ws->setTitle('REKAP HONOR');
        $ws->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

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
        $n      = count($b['baris']);
        $r0     = 7;                 // baris data pertama
        $rLast  = $r0 + $n - 1;
        $rJum   = $rLast + 1;

        // judul 3 baris
        foreach ([1 => $b['judul'], 2 => $b['sekolah'], 3 => $b['tahun']] as $r => $t) {
            $ws->mergeCells("A{$r}:{$akhir}{$r}");
            $ws->setCellValue("A{$r}", $t);
            $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize($r === 1 ? 13 : 11);
            $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        // baris 4: nomor kolom
        for ($i = 1; $i <= $cTtd; $i++) {
            $ws->setCellValueExplicit(self::huruf($i) . '4', (string) $i, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        }
        $ws->getStyle("A4:{$akhir}4")->applyFromArray(['font' => ['size' => 8, 'color' => ['rgb' => '64748B']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        // baris 5–6: judul kolom + tarif
        foreach (['A' => 'NO', 'B' => 'NAMA', 'C' => 'JABATAN'] as $col => $t) {
            $ws->mergeCells("{$col}5:{$col}6");
            $ws->setCellValue("{$col}5", $t);
        }
        foreach ($kolom as $kol) {
            $k = $kol['k'];
            if ($kol['qty'] === null) {
                $col = self::huruf($kol['rp']);
                $ws->mergeCells("{$col}5:{$col}6");
                $ws->setCellValue("{$col}5", mb_strtoupper($k['nama']));
            } else {
                $a = self::huruf($kol['qty']);
                $z = self::huruf($kol['rp']);
                $ws->mergeCells("{$a}5:{$z}5");
                $ws->setCellValue("{$a}5", mb_strtoupper($k['nama']));
                $ws->mergeCells("{$a}6:{$z}6");
                $ws->setCellValue("{$a}6", (int) $k['tarif']);                   // sel TARIF — rumus di bawah memakainya
                $ws->getStyle("{$a}6")->getNumberFormat()->setFormatCode('"Rp. "#,##0');
            }
        }
        $tot = self::huruf($cTotal);
        $ttd = self::huruf($cTtd);
        $ws->mergeCells("{$tot}5:{$tot}6");
        $ws->setCellValue("{$tot}5", 'TOTAL');
        $ws->mergeCells("{$ttd}5:{$ttd}6");
        $ws->setCellValue("{$ttd}5", 'TTD');
        $ws->getStyle("A5:{$akhir}6")->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5EAF2']],
        ]);

        // data
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
                    $ws->setCellValue($cell, "={$q}{$r}*\${$q}\$6");               // jumlah × tarif (sel tarif, bukan angka tertulis)
                }
                $rupiah[] = $cell;
            }
            $ws->setCellValue("{$tot}{$r}", '=SUM(' . implode(',', $rupiah) . ')');
            $ws->setCellValue("{$ttd}{$r}", $br['no']);
            $ws->getStyle("{$ttd}{$r}")->getAlignment()->setHorizontal($br['no'] % 2 === 1 ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER);
        }

        // JUMLAH — menjumlah SEMUA baris data
        $ws->mergeCells("A{$rJum}:C{$rJum}");
        $ws->setCellValue("A{$rJum}", 'Jumlah');
        for ($col = 4; $col <= $cTotal; $col++) {
            $h = self::huruf($col);
            $ws->setCellValue("{$h}{$rJum}", "=SUM({$h}{$r0}:{$h}{$rLast})");
        }
        $ws->getStyle("A{$rJum}:{$akhir}{$rJum}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']]]);
        $ws->getStyle("A{$rJum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // bingkai, rata, format angka
        $ws->getStyle("A5:{$akhir}{$rJum}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("D{$r0}:{$tot}{$rJum}")->getNumberFormat()->setFormatCode('#,##0');
        $ws->getStyle("D{$r0}:{$tot}{$rJum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle("{$tot}{$r0}:{$tot}{$rJum}")->getFont()->setBold(true);
        $ws->getStyle("A{$r0}:A{$rLast}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // lebar kolom
        $ws->getColumnDimension('A')->setWidth(4.5);
        $ws->getColumnDimension('B')->setWidth(34);
        $ws->getColumnDimension('C')->setWidth(24);
        foreach ($kolom as $kol) {
            if ($kol['qty'] === null) {
                $ws->getColumnDimension(self::huruf($kol['rp']))->setWidth(16);
            } else {
                $ws->getColumnDimension(self::huruf($kol['qty']))->setWidth(9);
                $ws->getColumnDimension(self::huruf($kol['rp']))->setWidth(14);
            }
        }
        $ws->getColumnDimension($tot)->setWidth(16);
        $ws->getColumnDimension($ttd)->setWidth(10);

        // tanda tangan
        $t   = $rJum + 2;
        $kiriA = 'B';
        $kiriZ = 'C';
        $kananA = self::huruf(max(4, $cTotal - 3));
        $tengahA = self::huruf(max(4, intdiv($cTotal, 2) - 1));
        $tengahZ = self::huruf(min($cTotal, intdiv($cTotal, 2) + 2));
        $blok = static function (string $a, string $z, int $r, string $teks, bool $tebal = false, bool $garis = false) use ($ws): void {
            $ws->mergeCells("{$a}{$r}:{$z}{$r}");
            $ws->setCellValue("{$a}{$r}", $teks);
            $st = $ws->getStyle("{$a}{$r}");
            $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $st->getFont()->setBold($tebal)->setUnderline($garis ? \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE : \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_NONE);
        };
        $blok($kiriA, $kiriZ, $t, 'Mengetahui,');
        $blok($kiriA, $kiriZ, $t + 1, 'Ketua');
        $blok($kiriA, $kiriZ, $t + 5, $b['ketua'] !== '' ? $b['ketua'] : '(........................)', true, true);
        $blok($kananA, $akhir, $t, trim($b['tempat'] . ($b['tanggal'] !== '' ? ', ' . $b['tanggal'] : ', ..................')));
        $blok($kananA, $akhir, $t + 1, 'Bendahara');
        $blok($kananA, $akhir, $t + 5, $b['bendahara'] !== '' ? $b['bendahara'] : '(........................)', true, true);
        $blok($tengahA, $tengahZ, $t + 7, 'Menyetujui,');
        $blok($tengahA, $tengahZ, $t + 8, 'Kepala ' . $b['namaSekolah']);
        $blok($tengahA, $tengahZ, $t + 12, $b['kepsek'] !== '' ? $b['kepsek'] : '(........................)', true, true);
        $rAkhir = $t + 12;

        // cetak: landscape Folio, 1 halaman lebar, judul 1–6 diulang
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_FOLIO)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ps->setRowsToRepeatAtTopByStartAndEnd(1, 6);
        $ps->setPrintArea("A1:{$akhir}{$rAkhir}");
        $ps->setHorizontalCentered(true);
        $ws->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $ws->getHeaderFooter()->setOddFooter('&L&8' . $b['judul'] . ($b['status'] === 'draf' ? '&C&8DRAF - belum final' : '') . '&R&8Halaman &P dari &N');
        $ws->freezePane('D7');
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
        foreach ($b['baris'] as $i => $br) {
            $awal = $r;
            foreach ([['SLIP HONOR', true, 13], [$b['judul'], true, 10], [$b['sekolah'] . ' — ' . $b['tahun'], false, 9]] as [$t, $tebal, $uk]) {
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
            $d->setPaper('F4', 'landscape');
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
