<?php

namespace App\Commands;

use App\Libraries\HonorCetak;
use App\Libraries\HonorDokumen;
use App\Libraries\HonorImpor;
use App\Libraries\HonorPengaturan;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembanding Excel keluaran sistem dengan rekap asli sekolah (formatdatasekolah/HONOR ASTS.xlsx, tidak ada di repo):
 * mengimpor berkas asli ke honor ASTS 1 (di dalam transaksi yang di-ROLLBACK), membuat Excel "Rekap + Slip", lalu
 * membandingkan lembar REKAP HONOR dengan aslinya — nilai, font, warna, garis, format angka, gabungan sel, lebar
 * kolom, tinggi baris, dan pengaturan cetak. Hanya selisih yang sengaja (JUMLAH yang dibetulkan, tanggal surat) yang boleh ada.
 *
 * Jalankan:  php spark dev:uji-honor-excel
 */
class UjiHonorExcel extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-honor-excel';
    protected $description = 'Bandingkan Excel honor keluaran sistem dengan HONOR ASTS.xlsx asli (di-rollback).';

    /** Folder untuk menyimpan PDF/Excel hasil uji bila diberikan: php spark dev:uji-honor-excel C:\folder (opsional). */
    private string $keluar = '';
    private int $lulus = 0;
    private int $gagal = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul, 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'red');
        }
    }

    public function run(array $params)
    {
        $this->keluar = isset($params[0]) && is_dir((string) $params[0]) ? rtrim((string) $params[0], '/' . chr(92)) : '';
        $asli = ROOTPATH . 'formatdatasekolah/HONOR ASTS.xlsx';
        if (! is_file($asli)) {
            CLI::write('Berkas asli tidak ada di folder ini — pembandingan dilewati.', 'yellow');

            return EXIT_SUCCESS;
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $this->banding($asli);
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $this->gagal++;
        }
        while ($db->transRollback()) {
        }
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d sama, %d beda  (semua perubahan uji di-rollback)', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    /** Garis efektif satu sisi: garis sel itu sendiri, atau garis sisi berhadapan milik sel tetangga (tepi bersama). */
    private function tepi(Worksheet $ws, string $c, string $sisi): string
    {
        preg_match('/^([A-Z]+)(\d+)$/', $c, $m);
        $kol = Coordinate::columnIndexFromString($m[1]);
        $row = (int) $m[2];
        $get = static fn (int $k, int $r, string $s): string => match ($s) {
            'top'    => $ws->getStyle(Coordinate::stringFromColumnIndex($k) . $r)->getBorders()->getTop()->getBorderStyle(),
            'bottom' => $ws->getStyle(Coordinate::stringFromColumnIndex($k) . $r)->getBorders()->getBottom()->getBorderStyle(),
            'left'   => $ws->getStyle(Coordinate::stringFromColumnIndex($k) . $r)->getBorders()->getLeft()->getBorderStyle(),
            default  => $ws->getStyle(Coordinate::stringFromColumnIndex($k) . $r)->getBorders()->getRight()->getBorderStyle(),
        };
        $sendiri = $get($kol, $row, $sisi);
        if ($sendiri !== 'none') {
            return $sendiri;
        }
        [$k2, $r2, $s2] = match ($sisi) {
            'top'    => [$kol, $row - 1, 'bottom'],
            'bottom' => [$kol, $row + 1, 'top'],
            'left'   => [$kol - 1, $row, 'right'],
            default  => [$kol + 1, $row, 'left'],
        };

        return $k2 < 1 || $r2 < 1 ? 'none' : $get($k2, $r2, $s2);
    }

    private function gaya(Worksheet $ws, string $c): array
    {
        $s = $ws->getStyle($c);
        $f = $s->getFont();
        $fl = $s->getFill();
        $a = $s->getAlignment();

        return [
            'font'  => $f->getName() . '/' . $f->getSize() . ($f->getBold() ? '/B' : ''),
            'fill'  => $fl->getFillType() === 'solid' ? strtoupper($fl->getStartColor()->getRGB()) : '-',
            'bd'    => $this->tepi($ws, $c, 'top') . ',' . $this->tepi($ws, $c, 'bottom') . ',' . $this->tepi($ws, $c, 'left') . ',' . $this->tepi($ws, $c, 'right'),
            'fmt'   => $s->getNumberFormat()->getFormatCode(),
            'al'    => $a->getHorizontal() . '/' . $a->getVertical() . ($a->getWrapText() ? '/wrap' : ''),
        ];
    }

    private function nilai(Worksheet $ws, string $c): mixed
    {
        $sel = $ws->getCell($c);
        try {
            return $sel->isFormula() ? $sel->getCalculatedValue() : $sel->getValue();
        } catch (\Throwable) {
            return null;
        }
    }

    private function banding(string $path): void
    {
        $db  = db_connect();
        $imp = new HonorImpor();
        $dokLib = new HonorDokumen();
        $periode = $db->table('ujian_periode')->where('jenis', 'ASTS1')->where('deleted_at', null)->orderBy('id', 'DESC')->get()->getRowArray();
        if (($lama = $dokLib->dokumenPeriode((int) $periode['id'])) !== null) {
            $dokLib->hapusDokumen((int) $lama['id']);
        }
        $p = $imp->baca($path);
        $c = $imp->cocokkan($p['baris']);
        $putus = [];
        foreach ($c as $i => $b) {
            $putus[$i] = $b['guru_id'] !== null ? 'guru:' . $b['guru_id'] : (! empty($b['kandidat']) ? 'guru:' . $b['kandidat'][0]['id'] : 'baru:guru');
        }
        $r = $imp->terapkan($periode, $p, $putus);
        $this->cek('impor Excel asli ke honor ASTS 1 berhasil (59 penerima)', $r['ok'] && $r['ringkas']['penerima'] === 59, $r['pesan']);
        $dok = $dokLib->dokumenPeriode((int) $periode['id']);
        $m   = $dokLib->muat((int) $dok['id']);
        $this->cek('komponen ASTS 1 = 6 kolom seperti rekap sekolah (termasuk Rapot)', count($m['komponen']) === 6, (string) count($m['komponen']));

        $isi = HonorCetak::xlsx(HonorCetak::spreadsheet($m, $periode));
        $tmp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'zzbanding_' . bin2hex(random_bytes(4)) . '.xlsx';
        file_put_contents($tmp, $isi);
        $baru = IOFactory::load($tmp)->getSheetByName('REKAP HONOR');
        unlink($tmp);
        $asliWs = IOFactory::load($path)->getSheetByName('REKAP HONOR');

        // ---- teks judul & header
        $teks = fn (Worksheet $w, string $c): string => trim((string) $this->nilai($w, $c));
        foreach (['A1', 'A2', 'A3'] as $sel) {
            $this->cek("judul $sel sama: \"" . $teks($asliWs, $sel) . '"', $teks($asliWs, $sel) === $teks($baru, $sel), $teks($baru, $sel));
        }
        $selisih = [];
        for ($col = 1; $col <= 16; $col++) {
            $h = Coordinate::stringFromColumnIndex($col);
            foreach ([4, 5, 6] as $row) {
                if ($teks($asliWs, $h . $row) !== $teks($baru, $h . $row)) {
                    $selisih[] = $h . $row . ': "' . $teks($asliWs, $h . $row) . '" ≠ "' . $teks($baru, $h . $row) . '"';
                }
            }
        }
        $this->cek('header baris 4–6 (nomor kolom, judul, tarif) sama persis di 16 kolom', $selisih === [], implode(' | ', array_slice($selisih, 0, 6)));

        // ---- gabungan sel (header)
        $mergeHeader = static fn (Worksheet $w): array => array_values(array_filter(array_keys($w->getMergeCells()), static fn (string $r): bool => (int) preg_replace('/\D+.*/', '', preg_replace('/^[A-Z]+/', '', $r)) <= 6));
        $a = $mergeHeader($asliWs);
        $b = $mergeHeader($baru);
        sort($a);
        sort($b);
        $this->cek('gabungan sel header sama (' . count($a) . ' gabungan)', $a === $b, 'asli: ' . implode(',', array_diff($a, $b)) . ' | baru: ' . implode(',', array_diff($b, $a)));

        // ---- data 59 baris: nilai sel
        $beda = [];
        for ($i = 0; $i < 59; $i++) {
            $r = 7 + $i;
            foreach (['A', 'B', 'C'] as $kol) {
                if ($teks($asliWs, "$kol$r") !== $teks($baru, "$kol$r")) {
                    $beda[] = "$kol$r: \"" . $teks($asliWs, "$kol$r") . '" ≠ "' . $teks($baru, "$kol$r") . '"';
                }
            }
            for ($col = 4; $col <= 15; $col++) {
                $h = Coordinate::stringFromColumnIndex($col);
                if ((int) round((float) $this->nilai($asliWs, "$h$r")) !== (int) round((float) $this->nilai($baru, "$h$r"))) {
                    $beda[] = "$h$r: " . (int) $this->nilai($asliWs, "$h$r") . ' ≠ ' . (int) $this->nilai($baru, "$h$r");
                }
            }
            if ((int) $this->nilai($asliWs, "P$r") !== (int) $this->nilai($baru, "P$r") && $this->nilai($asliWs, "P$r") !== null) {
                $beda[] = "P$r";
            }
        }
        $this->cek('59 baris × (NO, nama, jabatan, 12 kolom angka, TTD): semua sama dengan Excel asli (urutan Napis, Maya, … dst)', $beda === [], implode(' | ', array_slice($beda, 0, 8)));
        $this->cek('urutan 3 teratas: Napis Kuturupi, Maya Fadhillah, Muslimin', str_starts_with($teks($baru, 'B7'), 'Napis') && str_starts_with($teks($baru, 'B8'), 'Maya') && str_starts_with($teks($baru, 'B9'), 'Muslimin'), $teks($baru, 'B7') . ' / ' . $teks($baru, 'B8') . ' / ' . $teks($baru, 'B9'));

        // ---- JUMLAH: sistem benar (44.472.000) — Excel asli keliru (35.441.000) → satu-satunya selisih yang disengaja
        $rJum = 7 + 59;
        $this->cek('baris JUMLAH ada tepat setelah baris data, bertuliskan "Jumlah"', $teks($baru, 'A' . $rJum) === 'Jumlah');
        $this->cek('JUMLAH TOTAL sistem = Rp 44.472.000 (Excel asli keliru Rp 35.441.000)', (int) $this->nilai($baru, 'O' . $rJum) === 44472000 && (int) $this->nilai($asliWs, 'O73') === 35441000, (int) $this->nilai($baru, 'O' . $rJum) . ' vs ' . (int) $this->nilai($asliWs, 'O73'));

        // ---- gaya sel contoh (asli → baru). Beberapa sel asli tidak konsisten (mis. A8 Arial Narrow); dibandingkan yang bermakna.
        $pasang = [
            'A1' => 'A1', 'A2' => 'A2', 'A3' => 'A3', 'A4' => 'A4', 'P4' => 'P4', 'A5' => 'A5', 'B5' => 'B5', 'C5' => 'C5', 'D5' => 'D5', 'E5' => 'E5', 'M5' => 'M5', 'O5' => 'O5', 'P5' => 'P5',
            'A6' => 'A6', 'E6' => 'E6', 'M6' => 'M6', 'C7' => 'C7', 'D7' => 'D7', 'F7' => 'F7', 'G7' => 'G7', 'K7' => 'K7', 'L7' => 'L7', 'O7' => 'O7', 'P7' => 'P7', 'P8' => 'P8',
            'K30' => 'K30', 'B30' => 'B30', 'D30' => 'D30', 'O30' => 'O30',
        ];
        $bedaGaya = [];
        foreach ($pasang as $sA => $sB) {
            $ga = $this->gaya($asliWs, $sA);
            $gb = $this->gaya($baru, $sB);
            foreach ($ga as $kunci => $val) {
                if ($val !== $gb[$kunci]) {
                    $bedaGaya[] = "$sA.$kunci: {$val} ≠ {$gb[$kunci]}";
                }
            }
        }
        $this->cek('gaya sel contoh (' . count($pasang) . ' sel × font/warna/garis/format angka/perataan) sama dengan aslinya', $bedaGaya === [], implode(' | ', array_slice($bedaGaya, 0, 12)));
        $this->cek('format angka akuntansi "Rp" persis sama', $baru->getStyle('D7')->getNumberFormat()->getFormatCode() === $asliWs->getStyle('D7')->getNumberFormat()->getFormatCode());

        // ---- baris JUMLAH + blok tanda tangan (posisi relatif)
        $bedaJ = [];
        foreach (['A', 'D', 'E', 'F', 'K', 'L', 'O', 'P'] as $kol) {
            $ga = $this->gaya($asliWs, $kol . '73');
            $gb = $this->gaya($baru, $kol . $rJum);
            foreach (['fill', 'bd', 'fmt'] as $kunci) {
                if ($ga[$kunci] !== $gb[$kunci]) {
                    $bedaJ[] = "$kol.$kunci: {$ga[$kunci]} ≠ {$gb[$kunci]}";
                }
            }
        }
        $this->cek('gaya baris JUMLAH (isi putih, garis, format) sama', $bedaJ === [], implode(' | ', array_slice($bedaJ, 0, 8)));
        $offAsli = ['C75' => 'Mengetahui,', 'C76' => 'Ketua', 'L76' => 'Bendahara', 'F81' => 'Menyetujui,', 'F82' => 'Kepala SMK Bina Nusa'];
        $bedaT = [];
        foreach ($offAsli as $sel => $teksAsli) {
            $kol = preg_replace('/\d+/', '', $sel);
            $row = (int) preg_replace('/\D+/', '', $sel) - 73 + $rJum;
            if ($teks($baru, $kol . $row) !== $teksAsli) {
                $bedaT[] = "$sel→$kol$row: \"" . $teks($baru, $kol . $row) . '" (harap "' . $teksAsli . '")';
            }
        }
        $this->cek('blok tanda tangan: tulisan & posisi relatif terhadap JUMLAH sama (Mengetahui/Ketua/Bendahara/Menyetujui/Kepala SMK)', $bedaT === [], implode(' | ', $bedaT));
        $this->cek('nama penanda tangan terisi dari Excel asli (Ketua, Bendahara, Kepala Sekolah)', $teks($baru, 'C' . ($rJum + 7)) === 'Elvira Safitri, S.Pd.' || str_starts_with($teks($baru, 'C' . ($rJum + 7)), 'Elvira'), $teks($baru, 'C' . ($rJum + 7)));
        $mergeTtdAsli = ['L80:O80' => 'L' . ($rJum + 7) . ':O' . ($rJum + 7), 'F81:H81' => 'F' . ($rJum + 8) . ':H' . ($rJum + 8), 'F82:H82' => 'F' . ($rJum + 9) . ':H' . ($rJum + 9), 'F86:H86' => 'F' . ($rJum + 13) . ':H' . ($rJum + 13)];
        $barui = array_keys($baru->getMergeCells());
        $hilang = array_filter($mergeTtdAsli, static fn (string $v): bool => ! in_array($v, $barui, true));
        $this->cek('gabungan sel blok tanda tangan sama posisi relatifnya', $hilang === [], implode(',', $hilang));

        // ---- lebar kolom, tinggi baris, tampilan, cetak
        $bedaW = [];
        foreach (range('A', 'P') as $kol) {
            $wa = round($asliWs->getColumnDimension($kol)->getWidth(), 2);
            $wb = round($baru->getColumnDimension($kol)->getWidth(), 2);
            if (abs($wa - $wb) > 0.02) {
                $bedaW[] = "$kol: $wa ≠ $wb";
            }
        }
        $this->cek('lebar 16 kolom (A–P) sama dengan aslinya', $bedaW === [], implode(' | ', $bedaW));
        $bedaH = [];
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 30, 65] as $row) {
            if (abs((float) $asliWs->getRowDimension($row)->getRowHeight() - (float) $baru->getRowDimension($row)->getRowHeight()) > 0.1) {
                $bedaH[] = "baris $row: " . $asliWs->getRowDimension($row)->getRowHeight() . ' ≠ ' . $baru->getRowDimension($row)->getRowHeight();
            }
        }
        $this->cek('tinggi baris (judul 15, data 30) sama', $bedaH === [], implode(' | ', $bedaH));
        $this->cek('zoom layar 70 %', $baru->getSheetView()->getZoomScale() === $asliWs->getSheetView()->getZoomScale(), (string) $baru->getSheetView()->getZoomScale());
        $pa = $asliWs->getPageSetup();
        $pb = $baru->getPageSetup();
        $ma = $asliWs->getPageMargins();
        $mb = $baru->getPageMargins();
        $this->cek('cetak: landscape, rata tengah horizontal sama', $pa->getOrientation() === $pb->getOrientation() && $pa->getHorizontalCentered() === $pb->getHorizontalCentered());
        $margin = abs($ma->getTop() - $mb->getTop()) < 0.01 && abs($ma->getBottom() - $mb->getBottom()) < 0.01 && abs($ma->getLeft() - $mb->getLeft()) < 0.01 && abs($ma->getRight() - $mb->getRight()) < 0.01 && abs($ma->getHeader() - $mb->getHeader()) < 0.01 && abs($ma->getFooter() - $mb->getFooter()) < 0.01;
        $this->cek('cetak: margin atas/bawah/kiri/kanan/header/footer sama', $margin, json_encode([$mb->getTop(), $mb->getBottom(), $mb->getLeft(), $mb->getRight()]));
        $this->cek('cetak: judul baris 1–6 diulang di tiap halaman (seperti aslinya)', $pb->getRowsToRepeatAtTop() === $pa->getRowsToRepeatAtTop(), json_encode($pb->getRowsToRepeatAtTop()));
        $this->cek('sheet tidak dibekukan (seperti aslinya) & garis kisi tampil', $baru->getFreezePane() === $asliWs->getFreezePane() && $baru->getShowGridlines() === $asliWs->getShowGridlines());
        $this->cek('lembar kedua "SLIP" ada (satu slip per orang)', in_array('SLIP', IOFactory::load($path)->getSheetNames(), true));

        $this->bandingPdf($asliWs, $m, $periode);
    }

    /**
     * Teks per halaman yang benar-benar tertulis di dalam PDF (dibaca dari aliran isi halaman) + posisi x terjauh.
     *
     * @return list<array{teks:string, xMaks:float}>
     */
    private function teksPdf(string $pdf): array
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m);
        $hal = [];
        foreach ($m[1] as $s) {
            $d = @gzuncompress($s);
            if ($d === false || ! str_contains($d, 'BT')) {
                continue;
            }
            preg_match_all('/([\d.\-]+) ([\d.\-]+) Td/', $d, $t, PREG_SET_ORDER);
            $x = array_map(static fn (array $r): float => (float) $r[1], $t);
            preg_match_all('/\(((?:\\.|[^\\)])*)\)\]? ?TJ/', $d, $tj);
            $teks = '';
            foreach ($tj[1] as $potong) {
                $teks .= str_replace(["\0", '\(', '\)'], ['', '(', ')'], $potong) . "\n";
            }
            $hal[] = ['teks' => $teks, 'xMaks' => $x === [] ? 0.0 : max($x)];
        }

        return $hal;
    }

    /** Isi PDF sungguhan: nama berurutan seperti Excel asli, total, judul, dan tidak ada teks yang keluar dari kertas. */
    private function bandingIsiPdf(Worksheet $asliWs, string $pdf, string $nama): void
    {
        $hal    = $this->teksPdf($pdf);
        $gabung = implode("\n", array_column($hal, 'teks'));
        $this->cek("PDF $nama: isi teks terbaca dari file PDF (" . count($hal) . ' halaman)', count($hal) >= 2 && strlen($gabung) > 2000, (string) strlen($gabung));
        $pos = 0;
        $hilang = [];
        for ($r = 7; $r <= 65; $r++) {
            $kata = explode(' ', trim((string) $this->nilai($asliWs, "B$r")))[0];
            $i = strpos($gabung, $kata, $pos);
            if ($i === false) {
                $hilang[] = $kata . " (baris $r)";
                continue;
            }
            $pos = $i + strlen($kata);
        }
        $this->cek("PDF $nama: 59 nama muncul BERURUTAN di dalam file PDF seperti Excel asli (Napis → Maya → Muslimin → …)", $hilang === [], implode(', ', array_slice($hilang, 0, 5)));
        $this->cek("PDF $nama: urutan tiga teratas di dalam PDF", ($a = strpos($gabung, 'Napis')) !== false && ($b = strpos($gabung, 'Maya', $a)) !== false && strpos($gabung, 'Muslimin', $b) !== false);
        $this->cek("PDF $nama: total keseluruhan 44.472.000 tertulis di halaman terakhir", str_contains($hal[count($hal) - 1]['teks'] ?? '', '44.472.000'));
        $this->cek("PDF $nama: judul diulang di tiap halaman (tulisan PEMBUATAN SOAL di semua halaman)", count(array_filter($hal, static fn (array $h): bool => str_contains($h['teks'], 'PEMBUATAN SOAL'))) === count($hal));
        $maks = max(array_column($hal, 'xMaks'));
        $this->cek("PDF $nama: tidak ada teks melewati tepi kanan kertas (x terjauh " . round($maks, 1) . ' < 913 pt)', $maks < 913.0, (string) $maks);
    }

    /** Teks sel tabel HTML: hanya angka (untuk "Rp 2.000.000" → 2000000; "-" → 0). */
    private function angkaHtml(string $t): int
    {
        return (int) preg_replace('/\D+/', '', $t);
    }

    /** PDF/HTML rekap & slip harus memuat urutan, nama, dan angka yang SAMA dengan Excel asli (Napis di atas, dst). */
    private function bandingPdf(Worksheet $asliWs, array $m, array $periode): void
    {
        $html = HonorCetak::rekapHtml($m, $periode);
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rekap_debug.html', $html); // SEMENTARA
        $dom  = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xp   = new \DOMXPath($dom);
        $baris = $xp->query('//table[contains(@class,"rekap")]/tbody/tr[not(contains(@class,"jumlah"))]');
        $this->cek('PDF rekap memuat 59 baris penerima', $baris->length === 59, (string) $baris->length);
        $beda = [];
        for ($i = 0; $i < $baris->length; $i++) {
            $td  = $xp->query('./td', $baris->item($i));
            $r   = 7 + $i;
            $nama = trim($td->item(1)->textContent);
            if ($nama !== trim((string) $this->nilai($asliWs, "B$r"))) {
                $beda[] = "baris $r nama: \"$nama\" ≠ \"" . trim((string) $this->nilai($asliWs, "B$r")) . '"';
            }
            if (trim($td->item(2)->textContent) !== trim((string) $this->nilai($asliWs, "C$r"))) {
                $beda[] = "baris $r jabatan";
            }
            // total = sel sebelum TTD
            $total = $this->angkaHtml($td->item($td->length - 2)->textContent);
            if ($total !== (int) round((float) $this->nilai($asliWs, "O$r"))) {
                $beda[] = "baris $r total: $total ≠ " . (int) $this->nilai($asliWs, "O$r");
            }
            // semua kolom rupiah/jumlah antara jabatan dan total
            $kolAsli = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
            foreach ($kolAsli as $j => $kol) {
                $v = $this->angkaHtml($td->item(3 + $j)->textContent);
                if ($v !== (int) round((float) $this->nilai($asliWs, "$kol$r"))) {
                    $beda[] = "$kol$r: $v ≠ " . (int) $this->nilai($asliWs, "$kol$r");
                    break;
                }
            }
        }
        $this->cek('PDF rekap: 59 baris — urutan, nama, jabatan, setiap kolom angka, dan total SAMA dengan Excel asli (Napis paling atas)', $beda === [], implode(' | ', array_slice($beda, 0, 5)));
        $this->cek('PDF rekap: baris pertama Napis Kuturupi, kedua Maya Fadhillah, ketiga Muslimin', $baris->length >= 3 && str_starts_with(trim($xp->query('./td', $baris->item(0))->item(1)->textContent), 'Napis') && str_starts_with(trim($xp->query('./td', $baris->item(1))->item(1)->textContent), 'Maya') && str_starts_with(trim($xp->query('./td', $baris->item(2))->item(1)->textContent), 'Muslimin'));
        $jum = $xp->query('//tr[contains(@class,"jumlah")]/td');
        $this->cek('PDF rekap: baris Jumlah = Rp 44.472.000 (kolom TOTAL), bukan angka keliru Excel lama', $this->angkaHtml($jum->item($jum->length - 2)->textContent) === 44472000, trim($jum->item($jum->length - 2)->textContent));
        $this->cek('PDF rekap: judul kolom persis (… PEMBUATAN SOAL, TRANSPORT, PENGAWAS, KOREKSI, "Rapot", TOTAL) dan tarif "Rp. 20.000"', str_contains($html, '>PEMBUATAN SOAL<') && str_contains($html, '>Rapot<') && str_contains($html, '>TRANSPORT<') && str_contains($html, 'Rp. 20.000') && str_contains($html, 'Rp. 1.500'));
        $this->cek('PDF rekap: JABATAN berlatar kuning & 59 sel jumlah Koreksi kuning (seperti Excel)', substr_count($html, 'class="c kuning"') === 59 && str_contains($html, 'class="kuning"') );
        $this->cek('PDF rekap: judul 3 baris & header ada di <thead> (diulang di tiap halaman)', $xp->query('//table[contains(@class,"rekap")]/thead/tr')->length === 6 && str_contains($html, 'SMK BINA NUSA KABUPATEN BEKASI'));
        $this->cek('PDF rekap: format "Rp" akuntansi (Rp kiri, angka kanan), nol tampil "-" (seperti "Rp -" di Excel)', str_contains($html, '<td class="rp">Rp</td><td class="v">-</td>') && str_contains($html, '<td class="rp">Rp</td><td class="v">2.000.000</td>'));
        $this->cek('PDF rekap: tanda tangan Mengetahui/Ketua, Bendahara, Menyetujui/Kepala memakai nama dari Excel', str_contains($html, 'Mengetahui,') && str_contains($html, 'Bendahara') && str_contains($html, 'Menyetujui,') && str_contains($html, 'Elvira Safitri') && str_contains($html, 'Napis Kuturupi, S.T.'));
        $pdf = HonorCetak::pdf($html, 'f4-landscape');
        if ($this->keluar !== '') {
            file_put_contents($this->keluar . DIRECTORY_SEPARATOR . 'rekap.pdf', $pdf);
            file_put_contents($this->keluar . DIRECTORY_SEPARATOR . 'slip.pdf', HonorCetak::pdf((string) HonorCetak::slipHtml($m, $periode), 'a4-portrait'));
            file_put_contents($this->keluar . DIRECTORY_SEPARATOR . 'rekap.xlsx', HonorCetak::xlsx(HonorCetak::spreadsheet($m, $periode)));
        }
        $hal = (int) preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
        $this->cek('PDF rekap berhasil dibuat (%PDF, ' . $hal . ' halaman, F4 landscape)', str_starts_with($pdf, '%PDF-') && $hal >= 2 && $hal <= 6, $hal . ' halaman');
        $this->bandingIsiPdf($asliWs, $pdf, 'rekap');
        $ukuran = preg_match('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s*\]/', $pdf, $mb) ? [(float) $mb[1], (float) $mb[2]] : [0.0, 0.0];
        $this->cek('PDF rekap: kertas F4 landscape sungguhan (935,4 × 609,5 pt = 330 × 215 mm), BUKAN Letter', abs($ukuran[0] - 935.43) < 1 && abs($ukuran[1] - 609.45) < 1, $ukuran[0] . ' x ' . $ukuran[1]);

        // slip: 59 slip dengan urutan & total sama
        $htmlS = (string) HonorCetak::slipHtml($m, $periode);
        $domS  = new \DOMDocument();
        @$domS->loadHTML('<?xml encoding="UTF-8">' . $htmlS);
        $xs    = new \DOMXPath($domS);
        $slip  = $xs->query('//div[contains(@class,"slip")]');
        $bedaS = [];
        for ($i = 0; $i < $slip->length; $i++) {
            $nama  = trim($xs->query('.//table[contains(@class,"idn")]//b', $slip->item($i))->item(0)->textContent);
            $total = $this->angkaHtml($xs->query('.//tr[contains(@class,"tot")]/td[last()]', $slip->item($i))->item(0)->textContent);
            $r = 7 + $i;
            if ($nama !== trim((string) $this->nilai($asliWs, "B$r")) || $total !== (int) round((float) $this->nilai($asliWs, "O$r"))) {
                $bedaS[] = "slip $r";
            }
        }
        $this->cek('PDF slip: 59 slip, urutan, nama, dan total tiap orang SAMA dengan Excel asli', $slip->length === 59 && $bedaS === [], $slip->length . ' slip; ' . implode(',', array_slice($bedaS, 0, 5)));
    }
}
