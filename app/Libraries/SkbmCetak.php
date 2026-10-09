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
 * Excel SKBM — "Daftar Lampiran 3 Surat Keputusan Kepala Sekolah tentang Pembagian Tugas Mengajar" — dari SKBM satu tahun ajaran;
 * bila tahun itu belum diisi yang keluar adalah TEMPLATE (tabel kosong siap diisi).
 *
 * Tata letak meniru lembar "SKBM 2026-2027" di berkas jadwal sekolah (bagian yang TAMPAK di sana; blok kolom kiri yang disembunyikan
 * sekolah tidak ditiru): judul 4 baris (DAFTAR LAMPIRAN 3 / SURAT KEPUTUSAN KEPALA <SEKOLAH> / TAHUN PELAJARAN / Nomor : <nomor SK>);
 * header ungu 3 baris (NO · NAMA GURU · KODE GURU · MATA PELAJARAN, lalu 42 kolom kelas: KELAS PAGI/SIANG → tingkat → nama kelas
 * ditulis miring 90°, lalu JUMLAH JP · JUMLAH TOTAL JP · JUMLAH KOREKSI); NO dan NAMA hanya di baris pertama tiap guru; JUMLAH TOTAL JP
 * dan JUMLAH KOREKSI digabung per guru; baris TOTAL JP / KELAS dan TOTAL JP SELURUHNYA; nama kepala sekolah + NRKS di bawah;
 * Legal landscape skala 95 %, baris 6–8 diulang di tiap halaman. Semua jumlah adalah RUMUS HIDUP.
 * Pembanding otomatis terhadap berkas sekolah: php spark dev:uji-skbm-excel.
 *
 * Berkas ini juga DAPAT DIIMPOR KEMBALI (Libraries\SkbmImpor membaca tata letak yang sama): unduh → ubah di Excel → impor.
 * Sel kelas yang menyala tetapi JP-nya belum diisi diberi tanda "?" (kuning; dilewati impor — isi angka sebelum mengimpor kembali).
 */
final class SkbmCetak
{
    public const JUDUL = 'DAFTAR LAMPIRAN 3';

    private const UNGU        = 'CC66FF';
    private const KOL_KELAS   = 5;   // kolom E: kelas pertama (A NO, B NAMA GURU, C KODE GURU, D MATA PELAJARAN)
    private const BARIS_HEADER = 6;  // header 3 baris: 6–8
    private const BARIS_DATA  = 9;
    private const BARIS_KOSONG = 30; // jumlah baris kosong pada template

    /** Nama berkas aman untuk header unduhan. */
    public static function namaBerkas(string $tahun, bool $template = false): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', 'SKBM-' . ($template ? 'TEMPLATE-' : '') . str_replace('/', '-', $tahun) . '.xlsx') ?? 'skbm.xlsx';
    }

    /** Isi berkas .xlsx (biner). */
    public static function xlsx(Spreadsheet $ss): string
    {
        return HonorCetak::xlsx($ss);
    }

    /**
     * Bahan judul & tanda tangan: nama sekolah, nama kepala sekolah dan nomor induknya (Pengaturan Sekolah), nomor SK tahun itu.
     *
     * @return array{sekolah:string, nomor:string, kepsek:string, nrks:string}
     */
    public static function info(string $tahun): array
    {
        $db  = db_connect();
        $set = $db->table('settings')->select('school_name, headmaster_name, headmaster_nip')->get()->getRowArray() ?? [];
        $nip = trim((string) ($set['headmaster_nip'] ?? ''));
        if ($nip === '-' || $nip === '') {
            $nip = '';
        } elseif (preg_match('/^(NRKS|NIP|NUPTK)\b/i', $nip) !== 1) {
            $nip = 'NRKS. ' . $nip;
        }

        return [
            'sekolah' => mb_strtoupper(trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', (string) ($set['school_name'] ?? 'SEKOLAH')))),
            'nomor'   => (new Skbm($db))->nomorSk($tahun),
            'kepsek'  => trim((string) ($set['headmaster_name'] ?? '')),
            'nrks'    => $nip,
        ];
    }

    /**
     * @param array<string,mixed>                                                $m    hasil Skbm::muat()
     * @param array{sekolah?:string, nomor?:string, kepsek?:string, nrks?:string} $info lihat info()
     */
    public static function spreadsheet(array $m, array $info = []): Spreadsheet
    {
        $tahun  = (string) $m['tahun'];
        $kelas  = $m['kelas'];
        $nK     = count($kelas);
        $nomor  = trim((string) ($info['nomor'] ?? ''));
        $kolJp  = self::KOL_KELAS + $nK;      // JUMLAH JP
        $kolTot = $kolJp + 1;                 // JUMLAH TOTAL JP
        $kolKor = $kolJp + 2;                 // JUMLAH KOREKSI
        $kolAkhirKelas = $kolJp - 1;
        $hAwal  = Coordinate::stringFromColumnIndex(self::KOL_KELAS);
        $hAkhir = Coordinate::stringFromColumnIndex($kolAkhirKelas);
        $hJp    = Coordinate::stringFromColumnIndex($kolJp);
        $hTot   = Coordinate::stringFromColumnIndex($kolTot);
        $hr     = self::BARIS_HEADER;

        $ss = new Spreadsheet();
        $ss->getProperties()->setTitle(self::JUDUL . ' — SKBM ' . $tahun)->setCreator('Sistem Akademik')->setSubject('TAHUN PELAJARAN ' . $tahun);
        $ws = $ss->getActiveSheet();
        $ws->setTitle('SKBM ' . str_replace('/', '-', $tahun));
        $ws->getTabColor()->setRGB('B3A2C7');

        // ---- judul (4 baris, rata kiri seperti berkas sekolah)
        $ws->setCellValue('A1', self::JUDUL);
        $ws->setCellValue('A2', 'SURAT KEPUTUSAN  KEPALA ' . ($info['sekolah'] ?? 'SEKOLAH'));
        $ws->setCellValue('A3', 'TAHUN PELAJARAN ' . $tahun);
        $ws->setCellValue('A4', 'Nomor : ' . ($nomor !== '' ? $nomor : '..........................................'));
        $ws->getStyle('A1:A4')->applyFromArray(['font' => ['name' => 'Arial Narrow', 'size' => 12, 'bold' => true], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);

        // ---- header 3 baris
        $tipis = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $ungu  = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::UNGU]]];
        $tengah = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
        foreach (['A' => 'NO', 'B' => 'NAMA GURU', 'C' => 'KODE GURU', 'D' => 'MATA PELAJARAN'] as $kol => $teks) {
            $ws->setCellValue($kol . $hr, $teks);
            $ws->mergeCells($kol . $hr . ':' . $kol . ($hr + 2));
        }
        $ws->getStyle('A' . $hr . ':D' . ($hr + 2))->applyFromArray($tipis + $ungu + $tengah + ['font' => ['name' => 'Arial', 'size' => 10, 'bold' => true]]);

        foreach ($m['grup'] as $g) {
            $a     = self::KOL_KELAS + $g['dari'];
            $b     = self::KOL_KELAS + $g['sampai'];
            $shift = (string) ($kelas[$g['dari']]['shift'] ?? '');
            $ws->setCellValue([$a, $hr], 'KELAS' . ($shift !== '' ? ' ' . mb_strtoupper($shift) : ''));
            $ws->setCellValue([$a, $hr + 1], (string) ($kelas[$g['dari']]['tingkat'] ?? ''));
            if ($b > $a) {
                $ws->mergeCells([$a, $hr, $b, $hr]);
                $ws->mergeCells([$a, $hr + 1, $b, $hr + 1]);
            }
            // kelompok & tingkat: rata tengah tanpa pembungkus teks (seperti berkas sekolah)
            $ws->getStyle([$a, $hr, $b, $hr + 1])->applyFromArray($tipis + $ungu + ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]] + ['font' => ['name' => 'Arial Narrow', 'size' => 12, 'bold' => true]]);
        }
        foreach ($kelas as $i => $k) {
            $c     = self::KOL_KELAS + $i;
            $label = str_replace('.', ' ', (string) $k['label']);
            $ws->setCellValue([$c, $hr + 2], $label);
            $ws->getStyle([$c, $hr + 2])->applyFromArray($tipis + $ungu + $tengah + ['font' => ['name' => 'Arial Narrow', 'size' => mb_strlen($label) > 6 ? 7 : 8, 'bold' => true]]);
            $ws->getStyle([$c, $hr + 2])->getAlignment()->setTextRotation(90);
        }
        foreach ([[$kolJp, 'JUMLAH JP'], [$kolTot, 'JUMLAH TOTAL JP']] as [$c, $teks]) {
            $ws->setCellValue([$c, $hr], $teks);
            $ws->mergeCells([$c, $hr, $c, $hr + 2]);
            $ws->getStyle([$c, $hr, $c, $hr + 2])->applyFromArray($tipis + $ungu + $tengah + ['font' => ['name' => 'Arial Narrow', 'size' => 10, 'bold' => true]]);
        }
        $ws->setCellValue([$kolKor, $hr], 'JUMLAH KOREKSI');
        $ws->mergeCells([$kolKor, $hr, $kolKor, $hr + 2]);
        $ws->getStyle([$kolKor, $hr, $kolKor, $hr + 2])->applyFromArray($tengah + ['font' => ['name' => 'Calibri', 'size' => 11], 'borders' => ['left' => ['borderStyle' => Border::BORDER_THIN]]]);
        $ws->getRowDimension($hr)->setRowHeight(51);
        $ws->getRowDimension($hr + 1)->setRowHeight(16.5);
        $ws->getRowDimension($hr + 2)->setRowHeight(33);

        // ---- isi: satu baris per (guru, mapel); NO & NAMA hanya di baris pertama guru
        $r = self::BARIS_DATA;
        foreach ($m['guru'] as $g) {
            $pertama = $r;
            foreach ($g['mapel'] as $j => $x) {
                if ($j === 0) {
                    $ws->setCellValue('A' . $r, (int) $g['no']);
                    $ws->setCellValue('B' . $r, (string) $g['nama']);
                }
                $ws->setCellValue('C' . $r, (string) $x['kode']);
                $ws->setCellValue('D' . $r, (string) $x['nama']);
                foreach ($kelas as $i => $k) {
                    if (! array_key_exists($k['id'], $x['sel'])) {
                        continue;
                    }
                    $jp = $x['sel'][$k['id']];
                    $c  = self::KOL_KELAS + $i;
                    $ws->setCellValue([$c, $r], $jp === null ? '?' : (int) $jp);
                    if ($jp === null) {
                        $ws->getStyle([$c, $r])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE68A');
                    }
                }
                $ws->setCellValue([$kolJp, $r], '=SUM(' . $hAwal . $r . ':' . $hAkhir . $r . ')');
                $r++;
            }
            $terakhir = $r - 1;
            $ws->setCellValue([$kolTot, $pertama], '=SUM(' . $hJp . $pertama . ':' . $hJp . $terakhir . ')');
            $ws->setCellValue([$kolKor, $pertama], '=COUNT(' . $hAwal . $pertama . ':' . $hAkhir . $terakhir . ')');
            if ($terakhir > $pertama) {
                $ws->mergeCells([$kolTot, $pertama, $kolTot, $terakhir]);
                $ws->mergeCells([$kolKor, $pertama, $kolKor, $terakhir]);
            }
        }
        if ($r === self::BARIS_DATA) { // template: baris kosong siap diisi
            for ($i = 0; $i < self::BARIS_KOSONG; $i++) {
                $ws->setCellValue([$kolJp, $r], '=SUM(' . $hAwal . $r . ':' . $hAkhir . $r . ')');
                $r++;
            }
        }
        $akhir = $r - 1;
        self::gayaIsi($ws, $kolJp, $kolTot, $kolKor, $akhir);

        // ---- baris hitungan: satu baris kosong, TOTAL JP / KELAS, TOTAL JP SELURUHNYA
        $ws->getRowDimension($akhir + 1)->setRowHeight(15.5);
        $f1 = $akhir + 2;
        $f2 = $f1 + 1;
        $ws->setCellValue('A' . $f1, 'TOTAL JP / KELAS');
        $ws->mergeCells('A' . $f1 . ':D' . $f1);
        $ws->setCellValue('A' . $f2, 'TOTAL JP SELURUHNYA');
        $ws->mergeCells('A' . $f2 . ':D' . $f2);
        $ws->getStyle('A' . $f1 . ':D' . $f2)->applyFromArray(['font' => ['name' => 'Arial Narrow', 'size' => 11], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_BOTTOM]]);
        $ws->getStyle('A' . $f1 . ':D' . $f1)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        for ($c = self::KOL_KELAS; $c <= $kolAkhirKelas; $c++) {
            $h = Coordinate::stringFromColumnIndex($c);
            $ws->setCellValue([$c, $f1], '=SUM(' . $h . self::BARIS_DATA . ':' . $h . $akhir . ')');
        }
        $ws->setCellValue([$kolJp, $f1], '=SUM(' . $hJp . self::BARIS_DATA . ':' . $hJp . $akhir . ')');
        $ws->setCellValue([$kolTot, $f1], '=SUM(' . $hTot . self::BARIS_DATA . ':' . $hTot . $akhir . ')');
        $ws->setCellValue([self::KOL_KELAS, $f2], '=SUM(' . $hAwal . $f1 . ':' . $hAkhir . $f1 . ')');
        $ws->mergeCells([self::KOL_KELAS, $f2, $kolAkhirKelas, $f2]);
        $ws->mergeCells([$kolJp, $f1, $kolJp, $f2]);
        $ws->mergeCells([$kolTot, $f1, $kolTot, $f2]);
        $ws->getStyle([self::KOL_KELAS, $f1, $kolTot, $f1])->applyFromArray($tipis + ['font' => ['name' => 'Calibri', 'size' => 11], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle([self::KOL_KELAS, $f2, $kolAkhirKelas, $f2])->applyFromArray($tipis + $ungu + ['font' => ['name' => 'Calibri', 'size' => 11], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle([$kolJp, $f1, $kolTot, $f2])->applyFromArray($tipis + $ungu);

        // ---- tanda tangan: nama kepala sekolah + nomor induknya, empat baris di bawah total
        $s1 = $f2 + 5;
        $s2 = $s1 + 1;
        $ws->setCellValue('B' . $s1, (string) ($info['kepsek'] ?? ''));
        $ws->setCellValue('B' . $s2, (string) ($info['nrks'] ?? ''));
        $ws->getStyle('B' . $s1)->applyFromArray(['font' => ['name' => 'Arial Narrow', 'size' => 12, 'bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]]);
        $ws->getStyle('B' . $s2)->applyFromArray(['font' => ['name' => 'Arial Narrow', 'size' => 11], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]]);
        $ws->getRowDimension($s2)->setRowHeight(28);

        // ---- lebar kolom (sama dengan berkas sekolah), cetak Legal landscape skala 95 %, ulang header di tiap halaman
        foreach (['A' => 4.73, 'B' => 26, 'C' => 4.27, 'D' => 20.27] as $kol => $lebar) {
            $ws->getColumnDimension($kol)->setWidth($lebar);
        }
        for ($c = self::KOL_KELAS; $c <= $kolAkhirKelas; $c++) {
            $ws->getColumnDimensionByColumn($c)->setWidth(2.73);
        }
        $ws->getColumnDimensionByColumn($kolJp)->setWidth(5.36);
        $ws->getColumnDimensionByColumn($kolTot)->setWidth(5.36);
        $ws->getColumnDimensionByColumn($kolKor)->setWidth(9.18);
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LEGAL)->setScale(95);
        $ps->setPrintArea('A1:' . $hTot . ($s2));
        $ps->setRowsToRepeatAtTopByStartAndEnd($hr, $hr + 2);
        $ws->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.39)->setRight(0.0);

        return $ss;
    }

    /** Gaya baris data (huruf, rata, garis): sama dengan berkas sekolah. */
    private static function gayaIsi(Worksheet $ws, int $kolJp, int $kolTot, int $kolKor, int $akhir): void
    {
        $awal  = self::BARIS_DATA;
        $tipis = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $tgh   = Alignment::HORIZONTAL_CENTER;
        $kiri  = Alignment::HORIZONTAL_LEFT;
        $tgv   = Alignment::VERTICAL_CENTER;
        $ws->getStyle('A' . $awal . ':A' . $akhir)->applyFromArray($tipis + ['font' => ['name' => 'Arial', 'size' => 9], 'alignment' => ['horizontal' => $tgh, 'vertical' => $tgv]]);
        $ws->getStyle('B' . $awal . ':B' . $akhir)->applyFromArray($tipis + ['font' => ['name' => 'Arial', 'size' => 9], 'alignment' => ['horizontal' => $kiri, 'vertical' => $tgv]]);
        $ws->getStyle('C' . $awal . ':C' . $akhir)->applyFromArray($tipis + ['font' => ['name' => 'Arial', 'size' => 9], 'alignment' => ['horizontal' => $tgh, 'vertical' => $tgv]]);
        $ws->getStyle('D' . $awal . ':D' . $akhir)->applyFromArray($tipis + ['font' => ['name' => 'Arial', 'size' => 8], 'alignment' => ['horizontal' => $kiri, 'vertical' => $tgv, 'wrapText' => true]]);
        $ws->getStyle([self::KOL_KELAS, $awal, $kolJp - 1, $akhir])->applyFromArray($tipis + [
            'font'      => ['name' => 'Calibri', 'size' => 11, 'bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => $tgh, 'vertical' => $tgv],
        ]);
        $ws->getStyle([$kolJp, $awal, $kolJp, $akhir])->applyFromArray($tipis + ['font' => ['name' => 'Calibri', 'size' => 11, 'bold' => true], 'alignment' => ['horizontal' => $tgh, 'vertical' => $tgv]]);
        $ws->getStyle([$kolTot, $awal, $kolTot, $akhir])->applyFromArray($tipis + ['font' => ['name' => 'Calibri', 'size' => 11], 'alignment' => ['horizontal' => $tgh, 'vertical' => $tgv]]);
        $ws->getStyle([$kolKor, $awal, $kolKor, $akhir])->applyFromArray($tipis + ['font' => ['name' => 'Calibri', 'size' => 11], 'alignment' => ['horizontal' => $tgh, 'vertical' => $tgv]]);
    }
}
