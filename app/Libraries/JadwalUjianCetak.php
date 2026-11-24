<?php

namespace App\Libraries;

use App\Models\SettingModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cetak JADWAL UJIAN berbentuk matriks — Excel + PDF yang meniru PERSIS berkas contoh klien "jadwal ujian.xlsx"
 * (lembar "JADWAL ASAS 1 PAGI" & "JADWAL ASAS 1 SIANG"; permintaan user 2026-11-22, fase F2). Berlaku untuk ASTS 1, ASAS,
 * ASTS 2, ASAT. Satu sumber data (matriks()) dipakai Excel DAN PDF supaya isinya mustahil beda.
 *
 * Bentuk (hasil bedah contoh):
 *  - satu lembar per shift (pagi, siang) yang punya kelas; judul 3 baris; kepala NO | HARI / TANGGAL | WAKTU | <SHIFT>/KELAS/
 *    kolom kelompok kelas (tingkat + label jurusan, mis. "X MPLB"); isi = nama mapel (Master Mapel "Nama di jadwal ujian");
 *  - baris = hari (sel HARI di baris pertama, tanggal d-mmm-yy digabung di baris sisanya) × sesi jam; baris ISTIRAHAT kuning
 *    dari pengaturan admin (tab Jadwal Ujian → Istirahat) atau otomatis dari jeda waktu antar sesi (IstirahatUjian); baris penutup
 *    "UJIAN SUSULAN" (tanggal & jam dari Periode);
 *  - Calibri, garis luar & pemisah hari tebal (medium), dalam tipis; kepala kuning #FFE699; A4 mendatar; area cetak
 *    TANPA kolom NO dan TANPA blok susulan (persis contoh) — PDF = tampilan cetak itu.
 */
class JadwalUjianCetak
{
    /** Judul baris 1 per jenis (contoh: "JADWAL ASESMEN SUMATIF AKHIR SEMESTER (ASAS) GANJIL"). */
    private const JUDUL = [
        'ASTS1' => 'ASESMEN SUMATIF TENGAH SEMESTER (ASTS)',
        'ASAS'  => 'ASESMEN SUMATIF AKHIR SEMESTER (ASAS)',
        'ASTS2' => 'ASESMEN SUMATIF TENGAH SEMESTER (ASTS)',
        'ASAT'  => 'ASESMEN SUMATIF AKHIR TAHUN (ASAT)',
    ];

    /** Nama lembar (contoh "JADWAL ASAS 1 PAGI"; lembar lama sekolah: "JADWAL ASAT PAGI"). */
    private const LEMBAR = ['ASTS1' => 'ASTS 1', 'ASAS' => 'ASAS 1', 'ASTS2' => 'ASTS 2', 'ASAT' => 'ASAT'];

    private const HARI = [1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU', 4 => 'KAMIS', 5 => "JUM'AT", 6 => 'SABTU', 7 => 'MINGGU'];

    /** Singkatan bulan seperti tampilan d-mmm-yy di Excel klien (contoh: "1-Dec-26", bukan "1-Des-26") — audit 2026-11-23. */
    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /**
     * Ukuran per lembar meniru contoh (lebar kolom Excel, tinggi baris pt, skala cetak %, margin inci atas/bawah/kiri/kanan).
     * Lebar kolom kelompok: ≤ 4 kolom 23.7 (contoh siang), selebihnya 19.5 (contoh pagi).
     */
    private const TATA = [
        'pagi'  => ['A' => 3.8, 'B' => 14.2, 'C' => 11.3, 'baris' => 16.0, 'judul' => 15.5, 'jarak' => 10.0, 'kelas' => 11, 'skala' => 95, 'zoom' => 70, 'margin' => [0.66, 0.75, 0.38, 0.43]],
        'siang' => ['A' => 5.2, 'B' => 14.2, 'C' => 11.5, 'baris' => 15.0, 'judul' => 15.0, 'jarak' => 15.0, 'kelas' => 10, 'skala' => 100, 'zoom' => 85, 'margin' => [0.39, 0.39, 0.30, 0.39]],
    ];

    private const KUNING_KEPALA = 'FFE699';
    private const KUNING_ISTIRAHAT = 'FFFF00';
    private const SUSULAN_BAWAAN = ['07:00:00', '12:00:00'];

    // =================================================================
    //  Data matriks
    // =================================================================

    /** Shift yang punya kelas (urutan pagi → siang). @return list<string> */
    public static function shiftAda(): array
    {
        return array_values(array_filter(['pagi', 'siang'], static fn (string $s): bool => self::kolom($s) !== []));
    }

    /**
     * Kolom kelompok kelas satu shift: tingkat + label jurusan (label_ujian, kosong = kode), diurutkan tingkat lalu
     * urutan jurusan (lembar siang: urutan_ujian_siang bila diisi, selain itu urutan_ujian; kosong = paling kanan) lalu label.
     * Urutan siang terpisah (2026-11-23): contoh sekolah pagi "XII AKL, XII TKJ" tetapi siang "XI TKJ, XI AKL".
     *
     * @return list<array{tingkat: string, label: string, judul: string}>
     */
    public static function kolom(string $shift): array
    {
        $rows = db_connect()->table('kelas k')
            ->select('k.tingkat, j.kode, j.label_ujian, j.urutan_ujian, j.urutan_ujian_siang')
            ->join('jurusan j', 'j.id = k.jurusan_id AND j.deleted_at IS NULL', 'left')
            ->where('k.deleted_at', null)->where('k.shift', $shift)
            ->get()->getResultArray();

        $grup = [];
        foreach ($rows as $r) {
            $label = self::labelJurusan($r['label_ujian'] ?? null, $r['kode'] ?? null);
            $kunci = $r['tingkat'] . '|' . $label;
            $urutX = $shift === 'siang' && ($r['urutan_ujian_siang'] ?? null) !== null ? $r['urutan_ujian_siang'] : $r['urutan_ujian'];
            $urut  = $urutX !== null ? (int) $urutX : 9999;
            if (! isset($grup[$kunci]) || $urut < $grup[$kunci]['urut']) {
                $grup[$kunci] = ['tingkat' => (string) $r['tingkat'], 'label' => $label, 'judul' => trim($r['tingkat'] . ' ' . $label), 'urut' => $urut];
            }
        }
        $posTingkat = ['X' => 1, 'XI' => 2, 'XII' => 3];
        usort($grup, static fn (array $a, array $b): int => [$posTingkat[$a['tingkat']] ?? 9, $a['urut'], $a['label']] <=> [$posTingkat[$b['tingkat']] ?? 9, $b['urut'], $b['label']]);

        return array_map(static fn (array $g): array => ['tingkat' => $g['tingkat'], 'label' => $g['label'], 'judul' => $g['judul']], $grup);
    }

    /**
     * Matriks jadwal satu shift.
     *
     * @return array{shift: string, kolom: list<array>, hari: list<array{tanggal: string, baris: list<array{jenis: string, waktu: string, sel: list<string>}>}>, susulan: array{tanggal: string, waktu: string}|null}
     */
    public static function matriks(array $periode, string $shift): array
    {
        $kolom = self::kolom($shift);
        $rows  = db_connect()->table('ujian_jadwal uj')
            ->select('uj.tingkat, uj.jurusan_id, uj.tanggal, uj.jam_mulai, uj.jam_selesai, m.nama_mapel, m.nama_ujian, j.kode AS jur_kode, j.label_ujian AS jur_label')
            ->join('mata_pelajaran m', 'm.id = uj.mapel_id', 'left')
            ->join('jurusan j', 'j.id = uj.jurusan_id', 'left')
            ->where('uj.periode_id', (int) $periode['id'])->where('uj.deleted_at', null)
            ->whereIn('uj.shift', [$shift, 'semua'])->where('uj.tanggal IS NOT NULL', null, false)
            ->orderBy('uj.tanggal', 'ASC')->orderBy('uj.jam_mulai', 'ASC')->orderBy('uj.jam_selesai', 'ASC')
            ->get()->getResultArray();

        // tanggal → "mulai|selesai" → indeks kolom → daftar nama mapel
        $peta = [];
        foreach ($rows as $r) {
            $labelBaris = $r['jurusan_id'] !== null ? self::labelJurusan($r['jur_label'], $r['jur_kode']) : null;
            $nama       = trim((string) ($r['nama_ujian'] ?? '')) !== '' ? trim((string) $r['nama_ujian']) : (string) ($r['nama_mapel'] ?? '-');
            foreach ($kolom as $i => $k) {
                if ($k['tingkat'] !== $r['tingkat'] || ($labelBaris !== null && $labelBaris !== $k['label'])) {
                    continue;
                }
                $slot = ($r['jam_mulai'] ?? '') . '|' . ($r['jam_selesai'] ?? '');
                $peta[$r['tanggal']][$slot][$i][$nama] = true;
            }
        }

        // Istirahat: diatur admin per shift + hari, atau (belum diatur) otomatis dari jeda jam — IstirahatUjian::susun (2026-11-24).
        $atur = $peta !== [] ? IstirahatUjian::pengaturan((int) $periode['id'])[$shift] ?? [] : [];
        $hari = [];
        foreach ($peta as $tanggal => $slots) {
            $baris = [];
            foreach (IstirahatUjian::susun(array_keys($slots), $atur[$tanggal] ?? null) as $b) {
                if ($b['jenis'] === 'istirahat') {
                    $baris[] = ['jenis' => 'istirahat', 'waktu' => self::waktu($b['mulai'], $b['selesai']), 'mulai' => $b['mulai'], 'selesai' => $b['selesai'], 'sel' => array_fill(0, count($kolom), 'ISTIRAHAT')];

                    continue;
                }
                $isi = $slots[$b['slot']];
                $sel = [];
                foreach (array_keys($kolom) as $i) {
                    $sel[] = isset($isi[$i]) ? implode(' / ', array_keys($isi[$i])) : '';
                }
                // mulai/selesai mentah ("07:00:00") dipakai Jadwal Pengawas (JadwalPengawas::sesi) — sesi keduanya pasti sama.
                $baris[] = ['jenis' => 'sesi', 'waktu' => self::waktu($b['mulai'], $b['selesai']), 'mulai' => $b['mulai'], 'selesai' => $b['selesai'], 'sel' => $sel];
            }
            $hari[] = ['tanggal' => (string) $tanggal, 'baris' => $baris];
        }

        $susulan = null;
        if (! empty($periode['susulan_mulai'])) {
            $susulan = [
                'tanggal' => (string) $periode['susulan_mulai'],
                'waktu'   => self::waktu($periode['susulan_jam_mulai'] ?: self::SUSULAN_BAWAAN[0], $periode['susulan_jam_selesai'] ?: self::SUSULAN_BAWAAN[1]),
            ];
        }

        return ['shift' => $shift, 'kolom' => $kolom, 'hari' => $hari, 'susulan' => $susulan];
    }

    /** Apakah periode ini punya isi jadwal di shift mana pun (untuk pesan "belum ada jadwal"). */
    public static function adaIsi(array $periode): bool
    {
        foreach (self::shiftAda() as $s) {
            if (self::matriks($periode, $s)['hari'] !== []) {
                return true;
            }
        }

        return false;
    }

    /** Tiga baris judul. @return array{0: string, 1: string, 2: string} */
    public static function judul(array $periode): array
    {
        $set     = (new SettingModel())->get();
        $sekolah = mb_strtoupper(trim((string) ($set['school_name'] ?? 'SEKOLAH')));

        return [
            'JADWAL ' . (self::JUDUL[$periode['jenis']] ?? $periode['jenis']) . ' ' . mb_strtoupper((string) $periode['semester']),
            trim($sekolah . ' ' . self::wilayah($set)),
            'TAHUN PELAJARAN ' . $periode['tahun_ajaran'],
        ];
    }

    // =================================================================
    //  Excel
    // =================================================================

    public static function spreadsheet(array $periode): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $ss->removeSheetByIndex(0);
        $judul = self::judul($periode);

        foreach (self::shiftAda() as $shift) {
            $ws = new Worksheet($ss, mb_substr('JADWAL ' . (self::LEMBAR[$periode['jenis']] ?? $periode['jenis']) . ' ' . mb_strtoupper($shift), 0, 31));
            $ss->addSheet($ws);
            self::isiLembar($ws, $judul, self::matriks($periode, $shift));
        }
        if ($ss->getSheetCount() === 0) {
            $ss->addSheet(new Worksheet($ss, 'JADWAL'));
        }
        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    private static function isiLembar(Worksheet $ws, array $judul, array $m): void
    {
        $t      = self::TATA[$m['shift']];
        $n      = max(1, count($m['kolom']));
        $lebarK = $n <= 4 ? 23.7 : 19.5;
        $akhirC = Coordinate::stringFromColumnIndex(3 + $n); // kolom kelompok terakhir
        $ws->getDefaultRowDimension()->setRowHeight($t['baris']);
        $ws->getSheetView()->setZoomScale($t['zoom']);
        foreach (['A' => $t['A'], 'B' => $t['B'], 'C' => $t['C']] as $c => $w) {
            $ws->getColumnDimension($c)->setWidth($w);
        }
        for ($i = 0; $i < $n; $i++) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex(4 + $i))->setWidth($lebarK);
        }

        // Judul (B1:..1 s/d B3:..3)
        foreach ($judul as $i => $teks) {
            $r = $i + 1;
            $ws->mergeCells("B{$r}:{$akhirC}{$r}");
            $ws->setCellValue("B{$r}", $teks);
            $ws->getRowDimension($r)->setRowHeight($t['judul']);
        }
        $ws->getStyle("B1:{$akhirC}3")->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getRowDimension(4)->setRowHeight($t['jarak']); // baris jarak di bawah judul (contoh: 10)

        // Kepala (baris 5–7)
        foreach (['A5:A7' => 'NO', 'B5:B7' => 'HARI / TANGGAL', 'C5:C7' => 'WAKTU'] as $rentang => $teks) {
            $ws->mergeCells($rentang);
            $ws->setCellValue(explode(':', $rentang)[0], $teks);
        }
        $ws->mergeCells("D5:{$akhirC}5");
        $ws->setCellValue('D5', mb_strtoupper($m['shift']));
        $ws->mergeCells("D6:{$akhirC}6");
        $ws->setCellValue('D6', 'KELAS');
        foreach ($m['kolom'] as $i => $k) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex(4 + $i) . '7', $k['judul']);
        }
        foreach ([5, 6, 7] as $r) {
            $ws->getRowDimension($r)->setRowHeight($t['baris']);
        }
        $ws->getStyle("A5:{$akhirC}7")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::KUNING_KEPALA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('D5')->getFont()->setSize(12);
        $ws->getStyle('D6')->getFont()->setSize($t['kelas']);
        $ws->getStyle("D5:{$akhirC}7")->getAlignment()->setWrapText(true);

        // Isi per hari
        $r       = 8;
        $awalBlok = [];
        foreach ($m['hari'] as $no => $h) {
            $awal = $r;
            $awalBlok[] = $awal;
            $jml  = count($h['baris']);
            foreach ($h['baris'] as $b) {
                $ws->setCellValue("C{$r}", $b['waktu']);
                $baris = 1;
                foreach ($b['sel'] as $i => $teks) {
                    $koor = Coordinate::stringFromColumnIndex(4 + $i) . $r;
                    $ws->setCellValue($koor, $teks);
                    if ($b['jenis'] === 'istirahat') {
                        $ws->getStyle($koor)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::KUNING_ISTIRAHAT);
                    }
                    $baris = max($baris, self::jumlahBarisTeks($teks, $lebarK));
                }
                if ($baris > 1) {
                    $ws->getRowDimension($r)->setRowHeight(13.0 * $baris);
                }
                $r++;
            }
            $akhir = $r - 1;
            $ws->setCellValue("A{$awal}", $no + 1);
            $ws->setCellValue("B{$awal}", self::HARI[(int) date('N', strtotime($h['tanggal']))]);
            if ($jml > 1) {
                $ws->mergeCells("A{$awal}:A{$akhir}");
                if ($jml > 2) {
                    $ws->mergeCells('B' . ($awal + 1) . ":B{$akhir}");
                }
                self::selTanggal($ws, 'B' . ($awal + 1), $h['tanggal']);
            } else {
                $ws->setCellValue("B{$awal}", self::HARI[(int) date('N', strtotime($h['tanggal']))] . "\n" . self::tanggalTeks($h['tanggal']));
                $ws->getStyle("B{$awal}")->getAlignment()->setWrapText(true);
                $ws->getRowDimension($awal)->setRowHeight(26.0);
            }
        }
        $akhirIsi = max(7, $r - 1); // batas area cetak (tanpa blok susulan, persis contoh)

        // Blok UJIAN SUSULAN (3 baris)
        if ($m['susulan'] !== null) {
            $awal = $r;
            $awalBlok[] = $awal;
            $akhir = $r + 2;
            $ws->mergeCells("A{$awal}:A{$akhir}");
            $ws->setCellValue("A{$awal}", count($m['hari']) + 1);
            $ws->setCellValue("B{$awal}", self::HARI[(int) date('N', strtotime($m['susulan']['tanggal']))]);
            $ws->mergeCells('B' . ($awal + 1) . ":B{$akhir}");
            self::selTanggal($ws, 'B' . ($awal + 1), $m['susulan']['tanggal']);
            $ws->mergeCells("C{$awal}:C{$akhir}");
            $ws->setCellValue("C{$awal}", $m['susulan']['waktu']);
            $ws->mergeCells("D{$awal}:{$akhirC}{$akhir}");
            $ws->setCellValue("D{$awal}", 'UJIAN SUSULAN');
            $ws->getStyle("D{$awal}:{$akhirC}{$akhir}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::KUNING_KEPALA]],
            ]);
            // Persis contoh: tiga baris susulan DISEMBUNYIKAN (ada di berkas, tidak tampil & tidak tercetak) — audit 2026-11-23.
            for ($b = $awal; $b <= $akhir; $b++) {
                $ws->getRowDimension($b)->setVisible(false);
            }
            $r = $akhir + 1;
        }
        $akhirTabel = max(7, $r - 1);

        // Gaya isi: Calibri 10, rata tengah; NO & HARI tebal; kolom kelompok dilipat.
        if ($akhirTabel >= 8) {
            $isi = $ws->getStyle("A8:{$akhirC}{$akhirTabel}");
            $isi->getFont()->setSize(10);
            $isi->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getStyle("A8:B{$akhirTabel}")->getFont()->setBold(true);
            $ws->getStyle("D8:{$akhirC}{$akhirTabel}")->getAlignment()->setWrapText(true);
            if ($m['susulan'] !== null) {
                $ws->getStyle('D' . ($akhirTabel - 2))->getFont()->setSize(12);
            }
        }

        // Garis: dalam tipis; luar, bawah kepala, dan pemisah hari tebal (medium).
        $ws->getStyle("A5:{$akhirC}{$akhirTabel}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("A5:{$akhirC}{$akhirTabel}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        $ws->getStyle("A7:{$akhirC}7")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        foreach ($awalBlok as $baris) {
            $ws->getStyle("A{$baris}:{$akhirC}{$baris}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        }

        // Cetak: A4 mendatar, skala & margin contoh, rata tengah mendatar, area cetak B1 s/d isi (tanpa NO & susulan).
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setScale($t['skala'], false)
            ->setFitToPage(false)->setHorizontalCentered(true)->setPrintArea("B1:{$akhirC}{$akhirIsi}");
        [$atas, $bawah, $kiri, $kanan] = $t['margin'];
        $ws->getPageMargins()->setTop($atas)->setBottom($bawah)->setLeft($kiri)->setRight($kanan);
    }

    /** Tulis tanggal sebagai angka tanggal Excel berformat d-mmm-yy (dipakai juga JadwalPengawasCetak). */
    public static function selTanggal(Worksheet $ws, string $koor, string $tanggal): void
    {
        $ws->setCellValue($koor, TanggalExcel::PHPToExcel(new \DateTime($tanggal . ' 00:00:00')));
        $ws->getStyle($koor)->getNumberFormat()->setFormatCode('d-mmm-yy');
    }

    // =================================================================
    //  PDF (tampilan cetak contoh: tanpa kolom NO & blok susulan)
    // =================================================================

    /** @param float $kecil pengecil tambahan (1 = ukuran contoh) — dipakai pdf() bila hasil render melebihi 1 halaman per shift. */
    public static function html(array $periode, float $kecil = 1.0): string
    {
        $judul   = self::judul($periode);
        $halaman = [];
        foreach (self::shiftAda() as $shift) {
            $halaman[] = self::htmlLembar($judul, self::matriks($periode, $shift), $kecil);
        }
        $css = '@page { size: A4 landscape; margin: 0; }'
            . 'body { margin: 0; font-family: Calibri, "DejaVu Sans", sans-serif; color: #000; }'
            . '.hal { page-break-after: always; } .hal:last-child { page-break-after: auto; }'
            // TANPA table-layout:fixed — pada mode itu dompdf membagi kolom sama rata dan mengabaikan lebar (teruji 2026-11-22).
            . 'table { border-collapse: collapse; margin: 0 auto; }'
            . 'td { text-align: center; vertical-align: middle; padding: 0 1pt; overflow: hidden; word-wrap: break-word; }'
            . '.j { font-weight: bold; }';

        return '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>'
            . implode('', $halaman) . '</body></html>';
    }

    private static function htmlLembar(array $judul, array $m, float $kecil = 1.0): string
    {
        $t      = self::TATA[$m['shift']];
        $n      = max(1, count($m['kolom']));
        $lebarK = $n <= 4 ? 23.7 : 19.5;
        [$atas, $bawah, $kiri, $kanan] = $t['margin'];

        // Tinggi tiap baris isi (pt, sebelum skala) — sama dengan perhitungan lembar Excel.
        $tinggiIsi = [];
        foreach ($m['hari'] as $hr) {
            foreach ($hr['baris'] as $b) {
                $baris = 1;
                foreach ($b['sel'] as $teks) {
                    $baris = max($baris, self::jumlahBarisTeks($teks, $lebarK));
                }
                $tinggiIsi[] = $baris > 1 ? 13.0 * $baris : (count($hr['baris']) === 1 ? 26.0 : $t['baris']);
            }
        }
        // Skala: seperti contoh (95%/100%), diperkecil otomatis bila tak muat SATU halaman A4 mendatar (mis. jadwal sangat panjang).
        $lebarPt = static fn (float $lebar): float => ((int) floor($lebar * 7 + 5)) * 0.75; // lebar kolom Excel → pt
        $lebarMentah  = $lebarPt($t['B']) + $lebarPt($t['C']) + $n * $lebarPt($lebarK);
        $tinggiMentah = 3 * $t['judul'] + $t['jarak'] + 3 * $t['baris'] + array_sum($tinggiIsi);
        $s = min($t['skala'] / 100, (595.28 - ($atas + $bawah) * 72 - 6) / $tinggiMentah, (841.89 - ($kiri + $kanan) * 72 - 4) / $lebarMentah) * $kecil;

        $pt     = static fn (float $lebar): float => round($lebarPt($lebar) * $s, 2);
        $tipis  = round(0.75 * $s, 2) . 'pt solid #000';
        $tebal  = round(1.5 * $s, 2) . 'pt solid #000';
        $f      = static fn (float $ukuran): string => 'font-size:' . round($ukuran * $s, 2) . 'pt;';

        // Kolom: B (hari/tanggal), C (waktu), kelompok. Area cetak contoh tak memuat kolom NO.
        $lebar = array_merge([$pt($t['B']), $pt($t['C'])], array_fill(0, $n, $pt($lebarK)));
        $total = array_sum($lebar);
        $sel   = static function (string $isi, array $gaya, int $colspan = 1, int $rowspan = 1): string {
            return '<td' . ($colspan > 1 ? ' colspan="' . $colspan . '"' : '') . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '')
                . ' style="' . implode('', $gaya) . '">' . $isi . '</td>';
        };
        $garis = static fn (string $atasG, string $bawahG, string $kiriG, string $kananG): string => "border-top:{$atasG};border-bottom:{$bawahG};border-left:{$kiriG};border-right:{$kananG};";
        // Tinggi sel dikurangi tebal garis: dompdf menambahkan garis di luar tinggi sel (teruji 2026-11-22).
        $tinggi = static fn (float $ptTinggi): string => 'height:' . round(max(1, $ptTinggi * $s - 0.9 * $s), 2) . 'pt;';

        $h  = '<div class="hal" style="padding:' . round($atas * 72, 1) . 'pt ' . round($kanan * 72, 1) . 'pt 0 ' . round($kiri * 72, 1) . 'pt;">';
        // line-height 13 pt per baris teks = tinggi baris Excel (2 baris = 26, 3 baris = 39).
        // Jarak antarbaris = ukuran huruf: lebih besar dari itu membuat dompdf menambah ±2,6 pt di SETIAP baris (teruji).
        $h .= '<table style="width:' . $total . 'pt;line-height:1.0;">';
        // Baris pengatur lebar (tak terlihat): dompdf mengabaikan <col>, lebar kolom diambil dari sel baris pertama.
        $h .= '<tr>';
        foreach ($lebar as $w) {
            $h .= '<td style="width:' . $w . 'pt;height:0;padding:0;border:0;font-size:0;line-height:0;"></td>';
        }
        $h .= '</tr>';
        foreach ($judul as $teks) {
            $h .= '<tr>' . $sel(esc($teks), ['font-weight:bold;', $f(12), $tinggi($t['judul'])], 2 + $n) . '</tr>';
        }
        $h .= '<tr>' . $sel('&nbsp;', [$tinggi($t['jarak'])], 2 + $n) . '</tr>';

        $kuning = 'background:#' . self::KUNING_KEPALA . ';';
        $kanan1 = static fn (int $i) => $i === $n - 1 ? $tebal : $tipis;
        $h .= '<tr>' . $sel('HARI / TANGGAL', ['font-weight:bold;', $f(10), $kuning, $garis($tebal, $tebal, $tipis, $tipis)], 1, 3)
            . $sel('WAKTU', ['font-weight:bold;', $f(10), $kuning, $garis($tebal, $tebal, $tipis, $tipis)], 1, 3)
            . $sel(esc(mb_strtoupper($m['shift'])), ['font-weight:bold;', $f(12), $kuning, $tinggi($t['baris']), $garis($tebal, $tipis, $tipis, $tebal)], $n) . '</tr>';
        $h .= '<tr>' . $sel('KELAS', ['font-weight:bold;', $f($t['kelas']), $kuning, $tinggi($t['baris']), $garis($tipis, $tipis, $tipis, $tebal)], $n) . '</tr>';
        $h .= '<tr>';
        foreach ($m['kolom'] as $i => $k) {
            $h .= $sel(esc($k['judul']), ['font-weight:bold;', $f(10), $kuning, $tinggi($t['baris']), $garis($tipis, $tebal, $tipis, $kanan1($i))]);
        }
        $h .= '</tr>';

        $urutBaris = 0;
        foreach ($m['hari'] as $h2) {
            $jml = count($h2['baris']);
            foreach ($h2['baris'] as $j => $b) {
                $atasG  = $j === 0 ? $tebal : $tipis;
                $tg     = $tinggi($tinggiIsi[$urutBaris++]);
                $h .= '<tr>';
                if ($j === 0) {
                    $hari = esc(self::HARI[(int) date('N', strtotime($h2['tanggal']))]) . ($jml === 1 ? '<br>' . esc(self::tanggalTeks($h2['tanggal'])) : '');
                    $h .= $sel($hari, ['font-weight:bold;', $f(10), $tg, $garis($tebal, $jml === 1 ? $tebal : $tipis, $tipis, $tipis)]);
                } elseif ($j === 1) {
                    $h .= $sel(esc(self::tanggalTeks($h2['tanggal'])), ['font-weight:bold;', $f(10), $garis($tipis, $tebal, $tipis, $tipis)], 1, $jml - 1);
                }
                $h .= $sel(esc($b['waktu']), [$f(10), $tg, $garis($atasG, $j === $jml - 1 ? $tebal : $tipis, $tipis, $tipis)]);
                foreach ($b['sel'] as $i => $teks) {
                    $isiGaya = [$f(10), $tg, $garis($atasG, $j === $jml - 1 ? $tebal : $tipis, $tipis, $kanan1($i))];
                    if ($b['jenis'] === 'istirahat') {
                        $isiGaya[] = 'background:#' . self::KUNING_ISTIRAHAT . ';';
                    }
                    $h .= $sel(esc($teks), $isiGaya);
                }
                $h .= '</tr>';
            }
        }

        return $h . '</table></div>';
    }

    /** HTML → PDF A4 mendatar, huruf Calibri digantikan Carlito (ukuran sama persis, lisensi OFL). */
    public static function pdf(array $periode): string
    {
        // Jaminan 1 halaman per shift: bila render melebihi itu (isi sangat panjang / beda ukur huruf), perkecil 5% lalu ulangi.
        $halaman = max(1, count(self::shiftAda()));
        $isi     = '';
        for ($kecil = 1.0; $kecil >= 0.7; $kecil -= 0.05) {
            [$isi, $jumlah] = self::renderPdf(self::html($periode, $kecil));
            if ($jumlah <= $halaman) {
                break;
            }
        }

        return $isi;
    }

    public static function pdfDariHtml(string $html): string
    {
        return self::renderPdf($html)[0];
    }

    /**
     * Render HTML → PDF dengan huruf Carlito sebagai "Calibri". Dipakai juga JadwalPengawasCetak (kertas Legal).
     *
     * @return array{0: string, 1: int} [isi PDF, jumlah halaman]
     */
    public static function renderPdf(string $html, string $kertas = 'A4', string $arah = 'landscape'): array
    {
        $dirHuruf = APPPATH . 'ThirdParty/fonts/carlito/';
        $cache    = WRITEPATH . 'cache/dompdf-huruf/';
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('fontDir', $cache);
        $options->set('fontCache', $cache);
        $options->setChroot([realpath($dirHuruf) ?: $dirHuruf, realpath(FCPATH) ?: FCPATH]);

        $dompdf = new Dompdf($options);
        $fm     = $dompdf->getFontMetrics();
        // Miring dipakai "Istirahat" di Jadwal Pengawas — tanpa Carlito-Italic dompdf memakai DejaVu (huruf lain, lebih besar).
        foreach ([['normal', 'normal', 'Carlito-Regular.ttf'], ['bold', 'normal', 'Carlito-Bold.ttf'], ['normal', 'italic', 'Carlito-Italic.ttf']] as [$tebal, $gaya, $berkas]) {
            if (is_file($dirHuruf . $berkas)) {
                $fm->registerFont(['family' => 'Calibri', 'weight' => $tebal, 'style' => $gaya], $dirHuruf . $berkas);
            }
        }
        $dompdf->loadHtml($html);
        $dompdf->setPaper($kertas, $arah);
        $dompdf->render();

        return [(string) $dompdf->output(), (int) $dompdf->getCanvas()->get_page_count()];
    }

    // =================================================================
    //  Pembantu
    // =================================================================

    /** "MPLB" dari label_ujian, kosong = kode jurusan. */
    /** "SENIN" … "JUM'AT" dari tanggal TTTT-BB-HH. */
    public static function namaHari(string $tanggal): string
    {
        return self::HARI[(int) date('N', strtotime($tanggal))];
    }

    private static function labelJurusan(?string $label, ?string $kode): string
    {
        $label = trim((string) $label);

        return $label !== '' ? mb_strtoupper($label) : mb_strtoupper(trim((string) $kode));
    }

    /** "07.00 - 08.30" (jam kosong → "-"). */
    public static function waktu(?string $mulai, ?string $selesai): string
    {
        $j = static fn (?string $x): string => $x !== null && $x !== '' ? str_replace(':', '.', substr($x, 0, 5)) : '';
        $a = $j($mulai);
        $b = $j($selesai);

        return $a === '' && $b === '' ? '-' : trim($a . ' - ' . $b, ' -');
    }

    /** "30-Nov-26" / "1-Dec-26" (sama dengan tampilan sel tanggal d-mmm-yy di Excel contoh sekolah). */
    public static function tanggalTeks(string $tanggal): string
    {
        $t = strtotime($tanggal);

        return date('j', $t) . '-' . self::BULAN[(int) date('n', $t)] . '-' . date('y', $t);
    }

    /** "KABUPATEN BEKASI" dari settings.city (+ deteksi "Kab."/"Kota" di alamat). */
    private static function wilayah(array $set): string
    {
        $kota = trim((string) ($set['city'] ?? ''));
        if ($kota === '') {
            return '';
        }
        if (preg_match('/^(kab(upaten)?\.?|kota)\s+/i', $kota)) {
            return mb_strtoupper(preg_replace('/^kab(upaten)?\.?\s+/i', 'KABUPATEN ', $kota));
        }
        $alamat = (string) ($set['address'] ?? '');

        return (preg_match('/\bkota\s+' . preg_quote($kota, '/') . '/i', $alamat) ? 'KOTA ' : 'KABUPATEN ') . mb_strtoupper($kota);
    }

    /** Lebar huruf Calibri/Carlito per 1000 em (metrik asli fon) — untuk memperkirakan teks terlipat. */
    private const LEBAR_HURUF = [
        'a' => 479, 'b' => 525, 'c' => 423, 'd' => 525, 'e' => 498, 'f' => 305, 'g' => 471, 'h' => 525, 'i' => 229, 'j' => 239, 'k' => 455,
        'l' => 229, 'm' => 799, 'n' => 525, 'o' => 527, 'p' => 525, 'q' => 525, 'r' => 349, 's' => 391, 't' => 335, 'u' => 525, 'v' => 452,
        'w' => 715, 'x' => 433, 'y' => 453, 'z' => 395,
        'A' => 579, 'B' => 544, 'C' => 533, 'D' => 615, 'E' => 488, 'F' => 459, 'G' => 631, 'H' => 623, 'I' => 252, 'J' => 319, 'K' => 520,
        'L' => 420, 'M' => 855, 'N' => 646, 'O' => 662, 'P' => 517, 'Q' => 673, 'R' => 543, 'S' => 459, 'T' => 487, 'U' => 642, 'V' => 567,
        'W' => 890, 'X' => 519, 'Y' => 487, 'Z' => 468,
        ' ' => 226, '.' => 252, ',' => 250, '-' => 306, '&' => 682, '(' => 303, ')' => 303, '/' => 386, ':' => 268, "'" => 221, '"' => 401,
    ];

    /**
     * Perkiraan jumlah baris teks terlipat di sel ber-lebar Excel $lebar (Calibri 10 pt = 13,33 px; lebar sel = trunc(lebar×7+5) px
     * dikurangi padding). Teruji sama dengan contoh: "Komunikasi di Tempat Kerja" @19.5 = 2, "Pengelolaan Sumberdaya Manusia (SDM)"
     * = 3, "Kreativitas, Inovasi, dan Kewirausahaan" = 2, "TEKNOLOGI JARINGAN KABEL DAN NIRKABEL" @23.7 = 2, "PRAKTER AKUNTANSI
     * PERUSAHAAN JASA, DAGANG DAN MANUFAKTUR" = 3, "BAHASA INDONESIA" = 1.
     */
    public static function jumlahBarisTeks(string $teks, float $lebar): int
    {
        $teks = trim($teks);
        if ($teks === '') {
            return 1;
        }
        $muat  = floor($lebar * 7 + 5) - 6;
        $px    = 13.333 / 1000;
        $ukur  = static function (string $kata) use ($px): float {
            $w = 0;
            foreach (preg_split('//u', $kata, -1, PREG_SPLIT_NO_EMPTY) as $c) {
                $w += self::LEBAR_HURUF[$c] ?? (ctype_digit($c) ? 507 : 500);
            }

            return $w * $px;
        };
        $spasi = self::LEBAR_HURUF[' '] * $px;
        $baris = 1;
        $isi   = 0.0;
        foreach (preg_split('/\s+/u', $teks) as $kata) {
            $w = $ukur($kata);
            if ($isi === 0.0) {
                $isi = $w;
            } elseif ($isi + $spasi + $w <= $muat) {
                $isi += $spasi + $w;
            } else {
                $baris++;
                $isi = $w;
            }
            while ($isi > $muat) { // kata lebih panjang dari sel → dipotong per huruf
                $baris++;
                $isi -= $muat;
            }
        }

        return $baris;
    }
}
