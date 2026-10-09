<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel "KOREKSI NILAI" — ceklis pembagian lembar jawaban ke guru per rombel — dari ceklis Koreksi sebuah honor.
 *
 * Tata letak meniru berkas sekolah (KOREKSI NILAI.xlsx): judul 3 baris; header 2 baris (kelompok kelas Pagi/Siang di atas,
 * nama kelas di bawah); satu blok per guru (No., Nama, Kode Guru 3A/3B…, Mata Pelajaran, satu kolom per kelas, total di
 * "Keterangan", nama diulang di kolom terakhir); baris hitungan di bawah. Sel BIRU berisi jumlah lembar = guru mengoreksi
 * kelas itu; sel ABU-ABU = tidak mengampu (termasuk baris mapel yang belum punya kelas). Total guru (SUM),
 * hitungan per kelas (COUNT), dan total semua adalah RUMUS HIDUP. Pembanding otomatis: php spark dev:uji-koreksi-excel.
 */
final class HonorKoreksiCetak
{
    public const JUDUL = 'CEKLIS PEMBAGIAN LEMBAR JAWABAN KE GURU (PER ROMBEL)';

    private const WARNA_HEADER = 'D9D9D9';
    private const WARNA_TIDAK  = 'C0C0C0';
    private const WARNA_ISI    = '00B0F0';

    /** Nama berkas aman untuk header unduhan. */
    public static function namaBerkas(array $periode): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', 'KOREKSI-NILAI-' . $periode['jenis'] . '-' . str_replace('/', '-', (string) $periode['tahun_ajaran']) . '.xlsx') ?? 'koreksi-nilai.xlsx';
    }

    /** Isi berkas .xlsx (biner). */
    public static function xlsx(Spreadsheet $ss): string
    {
        return HonorCetak::xlsx($ss);
    }

    /** Catatan di bawah judul (sama dengan berkas sekolah; nama sekolah & tahun mengikuti data). */
    public static function catatan(string $sekolah, string $tahunAjaran): string
    {
        return "Struktur baris (Nama Guru / Kode Guru / Mata Pelajaran) dan rombel yang diampu mengikuti Lampiran 3 SK Pembagian Tugas Mengajar (sheet 'SKBM "
            . str_replace('/', '-', $tahunAjaran) . "' pada file Jadwal_" . str_replace(' ', '_', $sekolah) . '). Beri tanda pada kolom rombel saat lembar jawaban sudah diserahkan. Sel abu-abu = guru TIDAK mengampu rombel tersebut.';
    }

    /**
     * @param array<string,mixed> $m       hasil HonorKoreksi::muat()
     * @param array<string,mixed> $periode baris ujian_periode
     */
    public static function spreadsheet(array $m, array $periode): Spreadsheet
    {
        $set     = db_connect()->table('settings')->select('school_name')->get()->getRowArray() ?? [];
        $sekolah = trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', (string) ($set['school_name'] ?? 'SEKOLAH')));
        $ss      = new Spreadsheet();
        $ss->getProperties()->setTitle(self::JUDUL)->setCreator('Sistem Akademik')->setSubject('TAHUN PELAJARAN ' . $periode['tahun_ajaran']);
        $ws = $ss->getActiveSheet();
        $ws->setTitle('KOREKSI');
        self::isi($ws, $m, $periode, $sekolah);

        return $ss;
    }

    private static function col(int $i): string
    {
        return Coordinate::stringFromColumnIndex($i);
    }

    private static function isi(Worksheet $ws, array $m, array $periode, string $sekolah): void
    {
        $kelas = $m['kelas'];
        $n     = count($kelas);
        $c0    = 5;                       // kolom kelas pertama (E)
        $cN    = $c0 + $n - 1;            // kolom kelas terakhir
        $cAU   = $cN + 1;                 // Keterangan (total guru)
        $cAV   = $cN + 2;                 // nama diulang
        $L     = static fn (int $i): string => self::col($i);
        $kolPertama = $L($c0);
        $kolTerakhir = $L($cN);
        $tipis = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]];

        $ws->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $ws->getSheetView()->setZoomScale(70);

        // ---- judul
        $ws->setCellValue('A1', self::JUDUL);
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->setCellValue('A2', mb_strtoupper($sekolah) . ' - TAHUN PELAJARAN ' . $periode['tahun_ajaran']);
        $ws->setCellValue('A3', self::catatan(HonorCetak::kapitalNama($sekolah), (string) $periode['tahun_ajaran']));
        $ws->getStyle('A3')->getFont()->setItalic(true)->setSize(9);
        $ws->getRowDimension(1)->setRowHeight(18.5);

        // ---- header (baris 5-6)
        $ws->setCellValue('A5', 'No.');
        $ws->setCellValue('B5', 'Nama Guru');
        $ws->setCellValue('C5', 'Kode Guru');
        $ws->setCellValue('D5', 'Mata Pelajaran');
        $ws->setCellValue($L($cAU) . '5', 'Keterangan');
        foreach (['A', 'B', 'C', 'D', $L($cAU)] as $c) {
            $ws->mergeCells("{$c}5:{$c}6");
        }
        foreach ($m['grup'] as $g) {
            $ws->setCellValue($L($c0 + $g['dari']) . '5', $g['judul']);
            if ($g['sampai'] > $g['dari']) {
                $ws->mergeCells($L($c0 + $g['dari']) . '5:' . $L($c0 + $g['sampai']) . '5');
            }
        }
        foreach ($kelas as $i => $k) {
            $ws->setCellValue($L($c0 + $i) . '6', $k['label']);
        }
        $ws->getStyle('A5:' . $L($cAU) . '6')->applyFromArray($tipis + [
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::WARNA_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getStyle($kolPertama . '6:' . $kolTerakhir . '6')->getFont()->setSize(8);
        $ws->getRowDimension(5)->setRowHeight(18);
        $ws->getRowDimension(6)->setRowHeight(26.15);

        // ---- blok guru
        $idxKelas = [];
        foreach ($kelas as $i => $k) {
            $idxKelas[$k['id']] = $i;
        }
        $r = 7;
        foreach ($m['guru'] as $g) {
            $awal = $r;
            $jml  = count($g['mapel']);
            foreach ($g['mapel'] as $x) {
                $ws->setCellValue("C{$r}", $x['kode']);
                $ws->setCellValue("D{$r}", $x['nama']);
                foreach ($x['sel'] as $kid => $jumlah) {
                    if (isset($idxKelas[$kid])) {
                        $ws->setCellValue($L($c0 + $idxKelas[$kid]) . $r, (int) $jumlah);
                    }
                }
                if ($jml === 1) {
                    $ws->getRowDimension($r)->setRowHeight(26); // total berhuruf 20 butuh tinggi
                }
                $r++;
            }
            $akhir = $r - 1;
            $ws->setCellValue("A{$awal}", $g['no']);
            $ws->setCellValue("B{$awal}", $g['nama']);
            $ws->setCellValue($L($cAU) . $awal, "=SUM({$kolPertama}{$awal}:{$kolTerakhir}{$akhir})");
            $ws->setCellValue($L($cAV) . $awal, $g['nama']);
            if ($akhir > $awal) {
                foreach (['A', 'B', $L($cAU), $L($cAV)] as $c) {
                    $ws->mergeCells("{$c}{$awal}:{$c}{$akhir}");
                }
            }
        }
        $akhirData = $r - 1;
        $awalData  = 7;

        if ($akhirData >= $awalData) {
            $area = "A{$awalData}:" . $L($cAV) . $akhirData;
            $ws->getStyle($area)->applyFromArray($tipis + ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
            $ws->getStyle("B{$awalData}:B{$akhirData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_GENERAL);
            $ws->getStyle("D{$awalData}:D{$akhirData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_GENERAL)->setVertical(Alignment::VERTICAL_BOTTOM);
            // semua sel kelas abu-abu (= tidak mengampu) dulu; sel yang diisi dibiru-kan
            $ws->getStyle("{$kolPertama}{$awalData}:{$kolTerakhir}{$akhirData}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_TIDAK);
            $ws->getStyle($L($cAU) . "{$awalData}:" . $L($cAU) . $akhirData)->getFont()->setSize(20)->setBold(true);
            $rr = $awalData;
            foreach ($m['guru'] as $g) {
                foreach ($g['mapel'] as $x) {
                    foreach (array_keys($x['sel']) as $kid) {
                        if (isset($idxKelas[$kid])) {
                            $ws->getStyle($L($c0 + $idxKelas[$kid]) . $rr)->getFill()->getStartColor()->setRGB(self::WARNA_ISI);
                        }
                    }
                    $rr++;
                }
            }
        }

        // ---- baris hitungan di bawah tabel
        $rt = $akhirData + 1;
        for ($i = 0; $i < $n; $i++) {
            $c = $L($c0 + $i);
            $ws->setCellValue("{$c}{$rt}", "=COUNT({$c}{$awalData}:{$c}{$akhirData})");
        }
        $ws->setCellValue($L($cAU) . $rt, '=SUM(' . $L($cAU) . "{$awalData}:" . $L($cAU) . "{$akhirData})");
        $ws->getStyle("{$kolPertama}{$rt}:" . $L($cAU) . $rt)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle($L($cAU) . $rt)->getFont()->setSize(20);
        $ws->getRowDimension($rt)->setRowHeight(26);

        // ---- lebar kolom & cetak
        foreach (['A' => 5.54, 'B' => 24.54, 'C' => 9.54, 'D' => 53.54] as $c => $w) {
            $ws->getColumnDimension($c)->setWidth($w);
        }
        for ($i = $c0; $i <= $cN; $i++) {
            $ws->getColumnDimension($L($i))->setWidth(7.18);
        }
        $ws->getColumnDimension($L($cAU))->setWidth(20.54);
        $ws->getColumnDimension($L($cAV))->setWidth(25.91);
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageMargins()->setLeft(0.7)->setRight(0.7)->setTop(0.75)->setBottom(0.75)->setHeader(0.3)->setFooter(0.3);
    }
}
