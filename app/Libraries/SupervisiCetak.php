<?php

namespace App\Libraries;

use App\Models\SettingModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Berkas cetak Supervisi Guru (docs/DESAIN-SUPERVISI.md §6) — SATU pembuat untuk web (Admin\SupervisiCetak, Admin\SupervisiRekap) dan
 * API Android (Api\Supervisi). Library ini hanya menghasilkan isi berkas (objek Spreadsheet / HTML untuk Dompdf); header unduhan
 * diurus controller. Klien belum punya format sendiri → tata letak di sini (bila format sekolah datang, ubah berkas ini saja).
 *
 *   1. Excel "PROGRAM SUPERVISI AKADEMIK" — jadwal kunjungan satu semester (A4 mendatar, Times New Roman 11) + TTD Kepala Sekolah.
 *   2. PDF "LEMBAR HASIL SUPERVISI AKADEMIK" — satu kunjungan per halaman (bisa digabung): kop, identitas, tabel skor per instrumen,
 *      temuan & tindak lanjut, ringkasan nilai, TTD guru · supervisor · kepala sekolah (view pdf/supervisi_lembar, A4 tegak).
 *   3. Excel "REKAP HASIL SUPERVISI AKADEMIK" — lembar "Rekap" (guru × instrumen) & "Ringkasan" (per predikat + belum disupervisi).
 */
final class SupervisiCetak
{
    /** PDF gabungan: paling banyak kunjungan per unduhan (Dompdf lambat untuk ratusan halaman). */
    public const MAKS_GABUNGAN = 120;

    private const HURUF = 'Times New Roman';

    /** @return array{kota: string, kepala: string, nip: string} */
    public static function penandatangan(): array
    {
        $s = (new SettingModel())->get();

        return ['kota' => trim((string) ($s['city'] ?? '')), 'kepala' => trim((string) ($s['headmaster_name'] ?? '')), 'nip' => trim((string) ($s['headmaster_nip'] ?? ''))];
    }

    /** "Perencanaan Pembelajaran (Modul Ajar)" → "Perencanaan Pembelajaran". */
    public static function namaPendek(string $nama): string
    {
        return trim((string) preg_replace('/\s*\(.*\)$/', '', $nama));
    }

    // =================================================================
    // 1. Excel Program Supervisi
    // =================================================================

    /** @param list<array<string, mixed>> $rows Supervisi::daftarJadwal (urut tanggal) */
    public static function program(array $rows, string $ta, string $semester): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()->setCreator('Sistem Akademik')->setTitle('Program Supervisi Akademik');
        $ws = $ss->getActiveSheet();
        $ws->setTitle('Program Supervisi');
        $lebar  = ['A' => 5, 'B' => 22, 'C' => 8, 'D' => 30, 'E' => 24, 'F' => 14, 'G' => 28, 'H' => 34, 'I' => 18];
        $kepala = ['NO', 'HARI/TANGGAL', 'JAM', 'NAMA GURU', 'MATA PELAJARAN', 'KELAS', 'SUPERVISOR', 'INSTRUMEN', 'KET'];
        foreach ($lebar as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }
        self::judul($ws, 'I', ['PROGRAM SUPERVISI AKADEMIK', KeterlambatanExcel::namaSekolah(), self::teksPeriode($ta, $semester)]);
        $ws->fromArray($kepala, null, 'A5');
        $ws->getStyle('A5:I5')->getFont()->setBold(true);
        $ws->getStyle('A5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getRowDimension(5)->setRowHeight(20);

        $b = 6;
        foreach (array_values($rows) as $i => $r) {
            $ket = array_filter([
                $r['status'] === 'selesai' ? 'Selesai' . ($r['nilai_akhir'] !== null ? ' (' . SupervisiPenilaian::teksNilai($r['nilai_akhir']) . ')' : '') : null,
                $r['status'] === 'batal' ? 'Batal' : null,
                (string) ($r['catatan'] ?? '') !== '' ? (string) $r['catatan'] : null,
            ]);
            $isi = [
                'B' => Keterlambatan::hariTanggal((string) $r['tanggal']),
                'C' => $r['jam'] !== null ? substr((string) $r['jam'], 0, 5) : '',
                'D' => (string) $r['nama_guru'] . (($r['nip_guru'] ?? '') !== '' ? "\nNIP " . $r['nip_guru'] : ''),
                'E' => (string) ($r['nama_mapel'] ?? ''),
                'F' => (string) ($r['nama_kelas'] ?? ''),
                'G' => (string) $r['nama_supervisor'],
                'H' => implode("\n", array_map([self::class, 'namaPendek'], $r['instrumen_nama'])),
                'I' => implode('. ', $ket),
            ];
            $ws->setCellValue("A{$b}", $i + 1);
            foreach ($isi as $kol => $teks) {
                $ws->setCellValueExplicit("{$kol}{$b}", $teks, DataType::TYPE_STRING);
            }
            $b++;
        }
        if ($rows === []) {
            $ws->mergeCells("A{$b}:I{$b}");
            $ws->setCellValue("A{$b}", 'Belum ada jadwal supervisi pada periode ini.');
            $ws->getStyle("A{$b}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $b++;
        }
        $akhir = $b - 1;
        $ws->getStyle("A5:I{$akhir}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("A6:I{$akhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $ws->getStyle("A6:A{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("C6:C{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ttdAkhir = self::ttdKepala($ws, $akhir + 2, 'G', 'I');
        $ws->getStyle("A1:I{$ttdAkhir}")->getFont()->setName(self::HURUF)->setSize(11);
        $ws->getStyle('A1:I2')->getFont()->setBold(true);
        self::kertas($ws, PageSetup::ORIENTATION_LANDSCAPE);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);

        return $ss;
    }

    // =================================================================
    // 2. PDF Lembar Hasil
    // =================================================================

    /**
     * HTML lembar hasil untuk Dompdf — satu atau beberapa kunjungan (tiap kunjungan mulai halaman baru).
     *
     * @param list<array<string, mixed>> $lembar hasil SupervisiPenilaian::lembar()
     */
    public static function lembarHtml(array $lembar): string
    {
        return view('pdf/supervisi_lembar', ['daftar' => array_values($lembar), 'ttd' => self::penandatangan()], ['saveData' => false]);
    }

    /** PDF A4 tegak sebagai string biner. */
    public static function pdf(string $html): string
    {
        return HonorCetak::pdf($html, 'a4-portrait');
    }

    // =================================================================
    // 3. Excel Rekap Hasil
    // =================================================================

    /** @param array<string, mixed> $rekap SupervisiPenilaian::rekap() */
    public static function rekap(array $rekap): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()->setCreator('Sistem Akademik')->setTitle('Rekap Hasil Supervisi Akademik');
        $periode = self::teksPeriode($rekap['ta'], $rekap['semester']) . ($rekap['predikat'] !== '' ? ' · PREDIKAT ' . mb_strtoupper($rekap['predikat']) : '');

        // --- Lembar "Rekap": guru × instrumen.
        $ws     = $ss->getActiveSheet();
        $ws->setTitle('Rekap');
        $ins    = $rekap['instrumen'];
        $kepala = array_merge(['NO', 'NAMA GURU', 'NIP', 'MATA PELAJARAN', 'KUNJUNGAN'], array_map(static fn (array $i): string => mb_strtoupper(self::namaPendek($i['nama'])), $ins),
            ['RATA-RATA', 'PREDIKAT', 'TINDAK LANJUT']);
        $kol    = [];
        for ($i = 1; $i <= count($kepala); $i++) {
            $kol[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        }
        $end   = end($kol);
        $lebar = array_merge([5, 30, 20, 26, 11], array_fill(0, count($ins), 14), [11, 12, 34]);
        foreach ($kol as $j => $k) {
            $ws->getColumnDimension($k)->setWidth($lebar[$j]);
        }
        self::judul($ws, $end, ['REKAP HASIL SUPERVISI AKADEMIK', KeterlambatanExcel::namaSekolah(), $periode]);
        $ws->fromArray($kepala, null, 'A5');
        $ws->getStyle("A5:{$end}5")->getFont()->setBold(true);
        $ws->getStyle("A5:{$end}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getRowDimension(5)->setRowHeight(30);
        $b = 6;
        foreach ($rekap['baris'] as $no => $r) {
            $isi = array_merge(
                [$no + 1, (string) $r['nama_guru'], (string) ($r['nip_guru'] ?? ''), implode(', ', $r['mapel']), count($r['kunjungan'])],
                array_map(static fn (array $i) => $r['nilai'][$i['kunci']] ?? null, $ins),
                [$r['rata_rata'], (string) ($r['predikat'] ?? ''), implode(', ', SupervisiPenilaian::labelTindakLanjut($r['tindak_lanjut']))],
            );
            foreach ($isi as $j => $v) {
                $sel = $kol[$j] . $b;
                if ($v === null) {
                    continue;
                }
                is_string($v) ? $ws->setCellValueExplicit($sel, $v, DataType::TYPE_STRING) : $ws->setCellValue($sel, $v);
            }
            $b++;
        }
        if ($rekap['baris'] === []) {
            $ws->mergeCells("A{$b}:{$end}{$b}");
            $ws->setCellValue("A{$b}", 'Belum ada kunjungan supervisi yang selesai dinilai' . ($rekap['predikat'] !== '' ? ' dengan predikat ' . $rekap['predikat'] : '') . '.');
            $ws->getStyle("A{$b}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $b++;
        }
        $akhir   = $b - 1;
        $kolNilaiAwal = $kol[5] ?? $kol[4];
        $kolRata = $kol[5 + count($ins)];
        $ws->getStyle("A5:{$end}{$akhir}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("A6:{$end}{$akhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $ws->getStyle("A6:A{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("E6:E{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("{$kolNilaiAwal}6:{$kolRata}{$akhir}")->getNumberFormat()->setFormatCode('0.00');
        $ws->getStyle("{$kolNilaiAwal}6:{$kol[6 + count($ins)]}{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("{$kolRata}6:{$kolRata}{$akhir}")->getFont()->setBold(true);
        $ttdKiri  = $kol[max(0, count($kol) - 3)];
        $ttdAkhir = self::ttdKepala($ws, $akhir + 2, $ttdKiri, $end);
        $ws->getStyle("A1:{$end}{$ttdAkhir}")->getFont()->setName(self::HURUF)->setSize(11);
        $ws->getStyle("A1:{$end}2")->getFont()->setBold(true);
        $ws->getStyle("A5:{$end}5")->getFont()->setBold(true);
        self::kertas($ws, PageSetup::ORIENTATION_LANDSCAPE);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);

        // --- Lembar "Ringkasan": per predikat + guru yang belum disupervisi.
        $ws = $ss->createSheet();
        $ws->setTitle('Ringkasan');
        foreach (['A' => 5, 'B' => 36, 'C' => 30] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }
        self::judul($ws, 'C', ['RINGKASAN HASIL SUPERVISI AKADEMIK', KeterlambatanExcel::namaSekolah(), self::teksPeriode($rekap['ta'], $rekap['semester'])]);
        $rk = $rekap['ringkas'];
        $ws->fromArray(['NO', 'PREDIKAT', 'JUMLAH GURU'], null, 'A5');
        $b = 6;
        foreach (SupervisiPenilaian::PREDIKAT as $batas => $label) {
            $ws->fromArray([$b - 5, $label . ' (' . self::rentangPredikat((int) $batas) . ')', (int) ($rk['per_predikat'][$label] ?? 0)], null, "A{$b}");
            $b++;
        }
        foreach ([
            ['Jumlah guru yang sudah dinilai', (int) $rk['guru_dinilai']],
            ['Jumlah kunjungan selesai', (int) $rk['kunjungan_selesai']],
            ['Rata-rata nilai sekolah', $rk['rata_rata'] !== null ? (float) $rk['rata_rata'] : '—'],
            ['Predikat rata-rata', (string) ($rk['rata_predikat'] ?? '—')],
            ['Guru terjadwal, belum dinilai', (int) $rk['terjadwal']],
            ['Guru belum dijadwalkan', (int) $rk['belum_dijadwalkan']],
        ] as [$label, $v]) {
            $ws->mergeCells("A{$b}:B{$b}");
            $ws->setCellValue("A{$b}", $label);
            is_string($v) ? $ws->setCellValueExplicit("C{$b}", $v, DataType::TYPE_STRING) : $ws->setCellValue("C{$b}", $v);
            if (is_float($v)) {
                $ws->getStyle("C{$b}")->getNumberFormat()->setFormatCode('0.00');
            }
            $ws->getStyle("A{$b}")->getFont()->setBold(true);
            $b++;
        }
        $akhirA = $b - 1;
        $ws->getStyle("A5:C{$akhirA}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("A5:C5")->getFont()->setBold(true);
        $ws->getStyle("A5:C5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("A6:A{$akhirA}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("C6:C{$akhirA}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $b += 1;
        $ws->setCellValue("A{$b}", 'GURU YANG BELUM DISUPERVISI');
        $ws->getStyle("A{$b}")->getFont()->setBold(true);
        $b++;
        $awalB = $b;
        $ws->fromArray(['NO', 'NAMA GURU', 'KETERANGAN'], null, "A{$b}");
        $ws->getStyle("A{$b}:C{$b}")->getFont()->setBold(true);
        $ws->getStyle("A{$b}:C{$b}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $b++;
        foreach ($rekap['belum'] as $no => $g) {
            $ws->setCellValue("A{$b}", $no + 1);
            $ws->setCellValueExplicit("B{$b}", (string) $g['nama'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit("C{$b}", $g['status'] === 'terjadwal' ? 'Terjadwal ' . IsianBantu::tanggalIndo((string) $g['tanggal']) : 'Belum dijadwalkan', DataType::TYPE_STRING);
            $b++;
        }
        if ($rekap['belum'] === []) {
            $ws->mergeCells("A{$b}:C{$b}");
            $ws->setCellValue("A{$b}", 'Nihil — semua guru sudah disupervisi.');
            $ws->getStyle("A{$b}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $b++;
        }
        $akhirB = $b - 1;
        $ws->getStyle("A{$awalB}:C{$akhirB}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle('A' . ($awalB + 1) . ":A{$akhirB}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("A{$awalB}:C{$akhirB}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $ttdAkhir = self::ttdKepala($ws, $akhirB + 2, 'C', 'C');
        $ws->getStyle("A1:C{$ttdAkhir}")->getFont()->setName(self::HURUF)->setSize(11);
        $ws->getStyle('A1:C2')->getFont()->setBold(true);
        $ws->getStyle("A5:C5")->getFont()->setBold(true);
        self::kertas($ws, PageSetup::ORIENTATION_PORTRAIT);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    /** "91–100" · "76–90,99" · … · "0–60,99". */
    public static function rentangPredikat(int $batas): string
    {
        $batasan = array_keys(SupervisiPenilaian::PREDIKAT); // menurun
        $i       = array_search($batas, $batasan, true);
        $atas    = $i === 0 || $i === false ? '100' : ($batasan[$i - 1] - 1) . ',99';

        return $batas . '–' . $atas;
    }

    // =================================================================
    // Nama berkas
    // =================================================================

    public static function namaBerkasProgram(string $ta, string $semester): string
    {
        return 'Program Supervisi ' . str_replace('/', '-', $ta) . ' ' . (Supervisi::SEMESTER[$semester] ?? $semester) . '.xlsx';
    }

    public static function namaBerkasRekap(string $ta, string $semester): string
    {
        return 'Rekap Supervisi ' . str_replace('/', '-', $ta) . ' ' . (Supervisi::SEMESTER[$semester] ?? $semester) . '.xlsx';
    }

    public static function namaBerkasLembar(array $jadwal): string
    {
        $nama = trim((string) preg_replace('/[^A-Za-z0-9 .-]+/', '', (string) preg_replace('/,.*$/', '', (string) $jadwal['nama_guru'])));

        return 'Lembar Supervisi ' . ($nama !== '' ? $nama . ' ' : '') . $jadwal['tanggal'] . '.pdf';
    }

    public static function namaBerkasGabungan(string $ta, string $semester): string
    {
        return 'Lembar Hasil Supervisi ' . str_replace('/', '-', $ta) . ' ' . (Supervisi::SEMESTER[$semester] ?? $semester) . '.pdf';
    }

    // =================================================================
    // Bantu tata letak
    // =================================================================

    /** "SEMESTER GANJIL TAHUN PELAJARAN 2026-2027". */
    private static function teksPeriode(string $ta, string $semester): string
    {
        return 'SEMESTER ' . mb_strtoupper(Supervisi::SEMESTER[$semester] ?? $semester) . ' TAHUN PELAJARAN ' . str_replace('/', '-', $ta);
    }

    /** @param list<string> $baris tiga baris judul (digabung A:$end, tengah) */
    private static function judul(Worksheet $ws, string $end, array $baris): void
    {
        foreach (array_values($baris) as $i => $teks) {
            $b = $i + 1;
            $ws->mergeCells("A{$b}:{$end}{$b}");
            $ws->setCellValueExplicit("A{$b}", $teks, DataType::TYPE_STRING);
            $ws->getRowDimension($b)->setRowHeight(18);
        }
        $ws->getStyle("A1:{$end}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Blok tanda tangan Kepala Sekolah di kolom $kiri:$kanan mulai baris $b: "Kota, tanggal hari ini" / "Kepala Sekolah" / 4 baris
     * kosong / nama (tebal, garis bawah) / NIP. Mengembalikan baris terakhir.
     */
    private static function ttdKepala(Worksheet $ws, int $b, string $kiri, string $kanan): int
    {
        $t     = self::penandatangan();
        $baris = [
            [($t['kota'] !== '' ? $t['kota'] . ', ' : '') . IsianBantu::tanggalIndo(date('Y-m-d')), false],
            ['Kepala Sekolah', false],
            ['', false], ['', false], ['', false],
            [$t['kepala'] !== '' ? $t['kepala'] : '(..............................)', true],
            [$t['nip'] !== '' ? 'NIP. ' . $t['nip'] : '', false],
        ];
        foreach ($baris as $i => [$teks, $tebal]) {
            $r = $b + $i;
            if ($kiri !== $kanan) {
                $ws->mergeCells("{$kiri}{$r}:{$kanan}{$r}");
            }
            $ws->setCellValueExplicit("{$kiri}{$r}", $teks, DataType::TYPE_STRING);
            $ws->getStyle("{$kiri}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if ($tebal) {
                $ws->getStyle("{$kiri}{$r}")->getFont()->setBold(true)->setUnderline(true);
            }
        }

        return $b + count($baris) - 1;
    }

    private static function kertas(Worksheet $ws, string $arah): void
    {
        $ws->getPageSetup()->setOrientation($arah)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setFitToPage(true);
        $ws->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4)->setHeader(0.3)->setFooter(0.3);
        $ws->setPrintGridlines(false);
        $ws->setSelectedCell('A1');
    }
}
