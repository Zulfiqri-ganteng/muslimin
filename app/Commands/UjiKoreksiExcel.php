<?php

namespace App\Commands;

use App\Libraries\HonorDokumen;
use App\Libraries\HonorImpor;
use App\Libraries\HonorKoreksi;
use App\Libraries\HonorKoreksiCetak;
use App\Libraries\HonorKoreksiImpor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembanding Excel KOREKSI: mengimpor berkas asli sekolah "KOREKSI NILAI.xlsx" (butuh "HONOR ASTS.xlsx" untuk daftar
 * penerima) ke sebuah honor, mengekspornya lagi lewat HonorKoreksiCetak, lalu membandingkan SEL DEMI SEL dengan aslinya.
 * Seluruh perubahan database di-ROLLBACK. Berkas asli ada di folder formatdatasekolah/ (tidak ikut repo).
 *
 * Perbedaan yang SENGAJA diterima (coretan manual sekolah, bukan aturan): warna jingga di beberapa sel nama, serta dua
 * baris pimpinan teratas yang di Excel sekolah tanpa total/nama ulang.
 *
 * Jalankan:  php spark dev:uji-koreksi-excel
 */
class UjiKoreksiExcel extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-koreksi-excel';
    protected $description = 'Impor KOREKSI NILAI.xlsx asli lalu ekspor ulang dan bandingkan sel demi sel (di-rollback).';

    private int $lulus = 0;
    private int $gagal = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [SAMA]  ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'green');
        } else {
            $this->gagal++;
            CLI::write('  [BEDA]  ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'red');
        }
    }

    public function run(array $params)
    {
        $honor    = ROOTPATH . 'formatdatasekolah/HONOR ASTS.xlsx';
        $koreksi  = ROOTPATH . 'formatdatasekolah/KOREKSI NILAI.xlsx';
        if (! is_file($honor) || ! is_file($koreksi)) {
            CLI::write('Berkas asli (HONOR ASTS.xlsx / KOREKSI NILAI.xlsx) tidak ada di folder formatdatasekolah — pembandingan dilewati.', 'yellow');

            return EXIT_SUCCESS;
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $this->banding($honor, $koreksi);
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

    private function nilai(Worksheet $ws, string $c): mixed
    {
        $v = $ws->getCell($c)->getValue();

        return is_string($v) ? trim($v) : $v;
    }

    private function teks(Worksheet $ws, string $c): string
    {
        return trim((string) $this->nilai($ws, $c));
    }

    private function warna(Worksheet $ws, string $c): string
    {
        $f = $ws->getStyle($c)->getFill();

        return $f->getFillType() === 'solid' ? strtoupper($f->getStartColor()->getRGB()) : '-';
    }

    /** Garis efektif satu sisi: milik sel itu, atau sisi berhadapan milik tetangga (tepi bersama). */
    private function tepi(Worksheet $ws, int $kol, int $row, string $sisi): string
    {
        $get = static function (int $k, int $r, string $s) use ($ws): string {
            if ($k < 1 || $r < 1) {
                return 'none';
            }
            $b = $ws->getStyle(Coordinate::stringFromColumnIndex($k) . $r)->getBorders();

            return match ($s) {
                'top' => $b->getTop()->getBorderStyle(), 'bottom' => $b->getBottom()->getBorderStyle(), 'left' => $b->getLeft()->getBorderStyle(), default => $b->getRight()->getBorderStyle(),
            };
        };
        $x = $get($kol, $row, $sisi);
        if ($x !== 'none') {
            return $x;
        }
        [$k2, $r2, $s2] = match ($sisi) {
            'top' => [$kol, $row - 1, 'bottom'], 'bottom' => [$kol, $row + 1, 'top'], 'left' => [$kol - 1, $row, 'right'], default => [$kol + 1, $row, 'left'],
        };

        return $get($k2, $r2, $s2);
    }

    /**
     * Sisi sel yang berada DI DALAM sel gabungan (tidak terlihat): kunci "kolom|baris|sisi".
     *
     * @param list<string> ...$daftar daftar rentang gabungan, mis. ["A62:A63", "B5:B6"]
     */
    private function sisiDalam(array ...$daftar): array
    {
        $out = [];
        foreach ($daftar as $merges) {
            foreach ($merges as $rng) {
                [$a, $z] = explode(':', $rng);
                [$k1, $r1] = Coordinate::coordinateFromString($a);
                [$k2, $r2] = Coordinate::coordinateFromString($z);
                $c1 = Coordinate::columnIndexFromString($k1);
                $c2 = Coordinate::columnIndexFromString($k2);
                for ($r = (int) $r1; $r <= (int) $r2; $r++) {
                    for ($c = $c1; $c <= $c2; $c++) {
                        if ($r < (int) $r2) {
                            $out["$c|$r|bottom"] = true;
                            $out["$c|" . ($r + 1) . '|top'] = true;
                        }
                        if ($c < $c2) {
                            $out["$c|$r|right"] = true;
                            $out[($c + 1) . "|$r|left"] = true;
                        }
                    }
                }
            }
        }

        return $out;
    }

    private function banding(string $honorPath, string $koreksiPath): void
    {
        $db   = db_connect();
        $imp  = new HonorImpor();
        $dokL = new HonorDokumen();
        $periode = $db->table('ujian_periode')->where('jenis', 'ASTS1')->where('deleted_at', null)->orderBy('id', 'DESC')->get()->getRowArray();
        if (($lama = $dokL->dokumenPeriode((int) $periode['id'])) !== null) {
            $dokL->hapusDokumen((int) $lama['id']);
        }
        // 1. daftar penerima honor dari Excel HONOR (nama persis seperti di sekolah)
        $p = $imp->baca($honorPath);
        $c = $imp->cocokkan($p['baris']);
        $putus = [];
        foreach ($c as $i => $b) {
            $putus[$i] = $b['guru_id'] !== null ? 'guru:' . $b['guru_id'] : (! empty($b['kandidat']) ? 'guru:' . $b['kandidat'][0]['id'] : 'baru:guru');
        }
        $r = $imp->terapkan($periode, $p, $putus);
        $this->cek('honor ASTS 1 disiapkan dari HONOR ASTS.xlsx (59 penerima)', $r['ok'] && $r['ringkas']['penerima'] === 59, $r['pesan']);
        $dokId = (int) $dokL->dokumenPeriode((int) $periode['id'])['id'];

        // 2. impor KOREKSI
        $ki = new HonorKoreksiImpor();
        $x  = $ki->baca($koreksiPath);
        $this->cek('KOREKSI terbaca: 51 guru, 42 kolom kelas', count($x['guru']) === 51 && count($x['kelas']) === 42, count($x['guru']) . ' guru, ' . count($x['kelas']) . ' kelas');
        $jml = 0;
        foreach ($x['guru'] as $g) {
            foreach ($g['baris'] as $br) {
                $jml += count($br['sel']);
            }
        }
        $this->cek('KOREKSI terbaca: 491 sel berangka, total 20.006 lembar', $jml === 491 && array_sum(array_column($x['guru'], 'total')) === 20006, $jml . ' sel');
        $x = $ki->cocokkan($x, $dokId);
        $this->cek('42 kolom kelas semua dikenal di Master Kelas', count(array_filter($x['kelas'], static fn ($k) => $k['kelas_id'] === null)) === 0);
        $st = array_count_values(array_column($x['guru'], 'status'));
        $this->cek('51 nama guru cocok ke penerima honor (cocok/mirip, tidak ada yang tidak/ganda)', ($st['cocok'] ?? 0) + ($st['mirip'] ?? 0) === 51 && ! isset($st['tidak']) && ! isset($st['ganda']), json_encode($st));
        $keputusan = [];
        foreach ($x['guru'] as $i => $g) {
            $keputusan[$i] = $g['baris_id'] !== null ? 'baris:' . $g['baris_id'] : 'lewati';
        }
        $hasil = $ki->terapkan($dokId, $x, $keputusan, true);
        $this->cek('impor KOREKSI diterapkan', $hasil['ok'] && $hasil['ringkas']['sel'] === 491, $hasil['pesan']);
        $kor = new HonorKoreksi();
        $m   = $kor->muat($dokId);
        $this->cek('ceklis di sistem: 51 guru, total lembar 20.006', $m['jumlah_guru'] === 51 && $m['total'] === 20006, $m['jumlah_guru'] . ' guru, total ' . $m['total']);

        // 3. ekspor lalu bandingkan
        $isi = HonorKoreksiCetak::xlsx(HonorKoreksiCetak::spreadsheet($m, $periode));
        $tmp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'zzkoreksi_' . bin2hex(random_bytes(4)) . '.xlsx';
        file_put_contents($tmp, $isi);
        $baru = IOFactory::load($tmp)->getSheetByName('KOREKSI');
        if (isset($_SERVER['argv'][2]) && is_dir((string) $_SERVER['argv'][2])) {
            copy($tmp, rtrim((string) $_SERVER['argv'][2], '/\\') . DIRECTORY_SEPARATOR . 'HASIL KOREKSI NILAI.xlsx');
        }
        unlink($tmp);
        $asli = IOFactory::load($koreksiPath)->getSheetByName('KOREKSI');
        $this->bandingkan($asli, $baru);
    }

    private function bandingkan(Worksheet $asli, Worksheet $baru): void
    {
        CLI::newLine();
        CLI::write('== Perbandingan sel demi sel dengan KOREKSI NILAI.xlsx asli ==', 'yellow');
        $nilai = fn (Worksheet $w, string $c): mixed => $this->nilai($w, $c);
        $maks  = 124;
        $kolAU = 47;
        $kolAV = 48;
        // baris pimpinan teratas (Kepala Sekolah, Kepala TU) di Excel sekolah tanpa total dan tanpa nama ulang — diterima berbeda
        $pimpinan = [7, 8];
        // coretan jingga manual di Excel sekolah — diterima berbeda
        $jingga = ['B14', 'AV14', 'B120', 'B121', 'B122', 'B123', 'B124', 'AV120', 'AV121', 'AV122', 'AV123', 'AV124'];

        foreach (['A1', 'A2', 'A3'] as $c) {
            $this->cek("teks $c sama", $this->teks($asli, $c) === $this->teks($baru, $c), $this->teks($asli, $c) === $this->teks($baru, $c) ? '' : '"' . mb_substr($this->teks($baru, $c), 0, 120) . '"');
        }
        $b = [];
        for ($i = 1; $i <= $kolAV; $i++) {
            $h = Coordinate::stringFromColumnIndex($i);
            foreach ([5, 6] as $row) {
                if ($this->teks($asli, $h . $row) !== $this->teks($baru, $h . $row)) {
                    $b[] = $h . $row . ': "' . $this->teks($asli, $h . $row) . '" ≠ "' . $this->teks($baru, $h . $row) . '"';
                }
            }
        }
        $this->cek('header baris 5–6 (kelompok kelas & nama kelas, 48 kolom) sama persis', $b === [], implode(' | ', array_slice($b, 0, 5)));

        $ma = array_keys($asli->getMergeCells());
        $mb = array_keys($baru->getMergeCells());
        sort($ma);
        sort($mb);
        // Guru no. 23: di Excel sekolah kolom No. tidak digabung (A62:A63) dan kodenya tak lengkap — sistem menggabungkan & memberi 23A/23B.
        $bedaMerge = array_values(array_diff(array_merge(array_diff($ma, $mb), array_diff($mb, $ma)), ['A62:A63']));
        $this->cek('semua ' . count($ma) . ' sel gabungan sama persis (kecuali A62:A63 yang tak digabung di Excel sekolah)', $bedaMerge === [], implode(' ', array_slice($bedaMerge, 0, 8)));

        $b = [];
        for ($i = 1; $i <= $kolAV; $i++) {
            $h = Coordinate::stringFromColumnIndex($i);
            if (abs($asli->getColumnDimension($h)->getWidth() - $baru->getColumnDimension($h)->getWidth()) > 0.01) {
                $b[] = $h . ': ' . $asli->getColumnDimension($h)->getWidth() . ' ≠ ' . $baru->getColumnDimension($h)->getWidth();
            }
        }
        $this->cek('lebar 48 kolom sama', $b === [], implode(' | ', array_slice($b, 0, 5)));

        $b = [];
        foreach (array_merge([1, 5, 6], range(7, $maks + 1)) as $row) {
            if (in_array($row, $pimpinan, true)) {
                continue;
            }
            $ha = $asli->getRowDimension($row)->getRowHeight();
            $hb = $baru->getRowDimension($row)->getRowHeight();
            if (abs(($ha === -1.0 ? 15 : $ha) - ($hb === -1.0 ? 15 : $hb)) > 0.2) {
                $b[] = "baris $row: $ha ≠ $hb";
            }
        }
        $this->cek('tinggi baris sama (kecuali 2 baris pimpinan teratas)', $b === [], implode(' | ', array_slice($b, 0, 6)));

        // nilai sel
        $b = [];
        for ($row = 7; $row <= $maks; $row++) {
            foreach (['A', 'B', 'C', 'D'] as $h) {
                if ((string) $nilai($asli, $h . $row) !== (string) $nilai($baru, $h . $row)) {
                    $b[] = $h . $row . ': "' . $nilai($asli, $h . $row) . '" ≠ "' . $nilai($baru, $h . $row) . '"';
                }
            }
        }
        // Dua kekhasan Excel sekolah yang diterima: kode guru no. 23 tak lengkap (C62/C63), dan nama pendek di baris terakhir
        // (di sistem memakai nama penerima honor yang lengkap).
        $b = array_values(array_filter($b, static fn (string $x): bool => ! str_starts_with($x, 'C62:') && ! str_starts_with($x, 'C63:') && ! str_starts_with($x, 'B124:')));
        $this->cek('No., Nama, Kode Guru, Mata Pelajaran sama di 118 baris (kecuali kode no. 23 dan nama pendek di baris terakhir)', $b === [], implode(' | ', array_slice($b, 0, 6)));

        $b = [];
        $nBiru = 0;
        for ($row = 7; $row <= $maks; $row++) {
            for ($i = 5; $i <= 46; $i++) {
                $h = Coordinate::stringFromColumnIndex($i) . $row;
                $va = $nilai($asli, $h);
                $vb = $nilai($baru, $h);
                if ((is_numeric($va) ? (int) $va : null) !== (is_numeric($vb) ? (int) $vb : null)) {
                    $b[] = "$h: " . var_export($va, true) . ' ≠ ' . var_export($vb, true);
                }
                $nBiru += is_numeric($va) ? 1 : 0;
            }
        }
        $this->cek("angka lembar di $nBiru sel (42 kolom × 118 baris) sama persis", $b === [] && $nBiru === 491, implode(' | ', array_slice($b, 0, 6)));

        $b = [];
        for ($row = 7; $row <= $maks; $row++) {
            foreach (['AU', 'AV'] as $h) {
                if (in_array($row, $pimpinan, true) || in_array($h . $row, $jingga, true)) {
                    continue;
                }
                if ((string) $nilai($asli, $h . $row) !== (string) $nilai($baru, $h . $row)) {
                    $b[] = $h . $row . ': "' . $nilai($asli, $h . $row) . '" ≠ "' . $nilai($baru, $h . $row) . '"';
                }
            }
        }
        $this->cek('kolom Keterangan (rumus =SUM) & nama ulang sama (kecuali 2 baris pimpinan)', $b === [], implode(' | ', array_slice($b, 0, 6)));

        $b = [];
        for ($i = 5; $i <= 47; $i++) {
            $h = Coordinate::stringFromColumnIndex($i) . ($maks + 1);
            if ((string) $nilai($asli, $h) !== (string) $nilai($baru, $h)) {
                $b[] = $h . ': ' . $nilai($asli, $h) . ' ≠ ' . $nilai($baru, $h);
            }
        }
        $this->cek('baris hitungan di bawah tabel (=COUNT per kelas, =SUM total) sama', $b === [], implode(' | ', array_slice($b, 0, 4)));
        $ca = $asli->getCell('AU125')->getOldCalculatedValue();
        $cb = $baru->getCell('AU125')->getOldCalculatedValue();
        $this->cek('total semua lembar (nilai terhitung) sama: ' . var_export($ca, true), (int) $ca === (int) $cb, var_export($cb, true));
        $this->cek('hitungan guru per kelas (E125) sama', (int) $asli->getCell('E125')->getOldCalculatedValue() === (int) $baru->getCell('E125')->getOldCalculatedValue());

        // warna latar sel kelas: biru/abu-abu/krem sama
        $b = [];
        for ($row = 7; $row <= $maks; $row++) {
            for ($i = 5; $i <= 46; $i++) {
                $h = Coordinate::stringFromColumnIndex($i) . $row;
                if ($this->warna($asli, $h) !== $this->warna($baru, $h)) {
                    $b[] = $h . ': ' . $this->warna($asli, $h) . ' ≠ ' . $this->warna($baru, $h);
                }
            }
        }
        // Baris 124 (guru terakhir) di Excel sekolah berwarna krem — coretan manual (baris mapel tanpa kelas lain tetap abu-abu).
        $b = array_values(array_filter($b, static fn (string $x): bool => ! preg_match('/^[A-Z]+124:/', $x)));
        $this->cek('warna 4.956 sel kelas sama (biru 00B0F0 = mengoreksi, abu-abu C0C0C0 = tidak; kecuali baris 124 yang dikremkan manual)', $b === [], implode(' | ', array_slice($b, 0, 6)));
        $b = [];
        foreach (['A5', 'D5', 'E5', 'E6', 'AU5', 'AV5'] as $h) {
            if ($this->warna($asli, $h) !== $this->warna($baru, $h)) {
                $b[] = $h . ': ' . $this->warna($asli, $h) . ' ≠ ' . $this->warna($baru, $h);
            }
        }
        $this->cek('warna header abu-abu D9D9D9 sama', $b === [], implode(' | ', $b));

        // huruf
        $f = static fn (Worksheet $w, string $c): string => $w->getStyle($c)->getFont()->getName() . '/' . $w->getStyle($c)->getFont()->getSize() . ($w->getStyle($c)->getFont()->getBold() ? '/B' : '') . ($w->getStyle($c)->getFont()->getItalic() ? '/I' : '');
        $b = [];
        foreach (['A1', 'A2', 'A3', 'A5', 'E5', 'E6', 'AU5', 'B9', 'D9', 'E9', 'C9', 'E125', 'AU125'] as $h) {
            if ($f($asli, $h) !== $f($baru, $h)) {
                $b[] = $h . ': ' . $f($asli, $h) . ' ≠ ' . $f($baru, $h);
            }
        }
        $this->cek('huruf (judul 14 tebal, catatan miring 9, header tebal, nama kelas 8, isi 11, total 20) sama', $b === [], implode(' | ', $b));
        $b = [];
        for ($row = 7; $row <= $maks; $row++) {
            if (in_array($row, $pimpinan, true) || $nilai($asli, 'AU' . $row) === null) {
                continue;
            }
            if ($asli->getStyle('AU' . $row)->getFont()->getSize() !== $baru->getStyle('AU' . $row)->getFont()->getSize()) {
                $b[] = 'AU' . $row;
            }
        }
        $this->cek('ukuran huruf total (20) di semua baris yang berisi rumus sama', $b === [], implode(' ', array_slice($b, 0, 8)));

        // garis tepi efektif
        // Garis di DALAM sel gabungan tidak terlihat — tidak dibandingkan.
        $dalam = $this->sisiDalam(array_keys($asli->getMergeCells()), array_keys($baru->getMergeCells()));
        $b = [];
        for ($row = 5; $row <= $maks; $row++) {
            if (in_array($row, $pimpinan, true)) {
                continue;
            }
            for ($i = 1; $i <= $kolAV; $i++) {
                if ($row <= 6 && $i === $kolAV) { // AV5:AV6 tak bergaris di Excel sekolah
                    continue;
                }
                foreach (['top', 'bottom', 'left', 'right'] as $sisi) {
                    if (isset($dalam[$i . '|' . $row . '|' . $sisi])) {
                        continue;
                    }
                    if ($this->tepi($asli, $i, $row, $sisi) !== $this->tepi($baru, $i, $row, $sisi)) {
                        $b[] = Coordinate::stringFromColumnIndex($i) . $row . ".$sisi";
                    }
                }
            }
        }
        $this->cek('garis tepi tabel (kolom A–AV, baris 5–' . $maks . ', di luar bagian dalam sel gabungan) sama', $b === [], count($b) . ' beda: ' . implode(' ', array_slice($b, 0, 8)));

        // perataan inti
        $al = static fn (Worksheet $w, string $c): string => $w->getStyle($c)->getAlignment()->getHorizontal() . '/' . $w->getStyle($c)->getAlignment()->getVertical() . ($w->getStyle($c)->getAlignment()->getWrapText() ? '/wrap' : '');
        $b = [];
        foreach (['A5', 'E5', 'E6', 'A9', 'B9', 'C9', 'D9', 'E9', 'AU9', 'AV9', 'E125'] as $h) {
            if ($al($asli, $h) !== $al($baru, $h)) {
                $b[] = $h . ': ' . $al($asli, $h) . ' ≠ ' . $al($baru, $h);
            }
        }
        $this->cek('perataan (tengah/kiri, atas-bawah, bungkus teks) sama', $b === [], implode(' | ', $b));

        // cetak
        $pa = $asli->getPageSetup();
        $pb = $baru->getPageSetup();
        $ma_ = $asli->getPageMargins();
        $mb_ = $baru->getPageMargins();
        $this->cek('cetak: landscape, Letter, muat 1 halaman lebar', $pa->getOrientation() === $pb->getOrientation() && $pa->getPaperSize() === $pb->getPaperSize() && $pa->getFitToPage() === $pb->getFitToPage() && $pa->getFitToWidth() === $pb->getFitToWidth() && $pa->getFitToHeight() === $pb->getFitToHeight(), $pb->getOrientation() . '/' . $pb->getPaperSize());
        $this->cek('cetak: margin sama', $ma_->getLeft() === $mb_->getLeft() && $ma_->getRight() === $mb_->getRight() && $ma_->getTop() === $mb_->getTop() && $ma_->getBottom() === $mb_->getBottom() && $ma_->getHeader() === $mb_->getHeader());
        $this->cek('zoom layar 70% sama', $asli->getSheetView()->getZoomScale() === $baru->getSheetView()->getZoomScale());
        $this->cek('nama lembar "KOREKSI" sama', $asli->getTitle() === $baru->getTitle());
    }
}
