<?php

namespace App\Commands;

use App\Libraries\HonorImpor;
use App\Libraries\Skbm;
use App\Libraries\SkbmCetak;
use App\Libraries\SkbmImpor;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembanding Excel SKBM: SKBM dari lembar "SKBM 2026-2027" di berkas jadwal sekolah diimpor ke database (dalam transaksi yang di-ROLLBACK),
 * lalu Excel hasil Libraries\SkbmCetak dibandingkan SEL DEMI SEL dengan lembar sekolah itu sendiri: judul, header (teks, gabungan sel, huruf, warna,
 * garis, tinggi baris, lebar kolom), isi (NO, nama, kode, mapel, JP tiap kelas, jumlah per baris & per guru), baris total, tanda tangan, dan
 * pengaturan cetak. Bagian yang SENGAJA beda (dicatat di bawah) tidak dihitung.
 *
 * Beda yang disengaja: blok kolom kiri (E–AN) yang disembunyikan sekolah tidak ditiru, jadi kolom kelas ada di E.. (bukan AO..); baris-baris yang
 * disembunyikan sekolah tetap ditampilkan; kolom JUMLAH KOREKSI di berkas sekolah hanya terisi sebagian (rumusnya tak konsisten) sehingga tak dibandingkan;
 * tinggi baris data (sekolah mengatur manual); nomor SK, nama sekolah, dan tanda tangan diambil dari data sistem; area cetak ikut tanda tangan.
 *
 * Jalankan:  php spark dev:uji-skbm-excel      (butuh formatdatasekolah/Jadwal_SMK_Bina_Nusa….xlsx)
 */
class UjiSkbmExcel extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-skbm-excel';
    protected $description = 'Bandingkan Excel SKBM buatan sistem dengan lembar SKBM berkas sekolah, sel demi sel (data uji di-rollback).';

    private int $sama   = 0;
    private int $beda   = 0;
    private int $catatan = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->sama++;
            CLI::write('  [SAMA]  ' . $judul, 'green');
        } else {
            $this->beda++;
            CLI::write('  [BEDA]  ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    private static function teks(mixed $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
    }

    private static function kunci(string $s): string
    {
        return mb_strtoupper(trim((string) preg_replace('/[\s.]+/u', ' ', $s)));
    }

    public function run(array $params)
    {
        $berkas = glob(ROOTPATH . 'formatdatasekolah/Jadwal_SMK_Bina_Nusa*SKBM*.xlsx')[0] ?? null;
        if ($berkas === null) {
            CLI::write('Berkas sekolah tidak ada di formatdatasekolah/ — pembanding dilewati.', 'yellow');

            return EXIT_SUCCESS;
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $this->jalankan($berkas);
            $kode = $this->beda === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $kode = EXIT_ERROR;
        }
        while ($db->transRollback()) {
            // batalkan semua tingkat transaksi
        }
        cache()->delete('opt_guru');
        CLI::newLine();
        CLI::write(sprintf('HASIL: %d sama, %d beda  (semua perubahan uji di-rollback)', $this->sama, $this->beda), $this->beda === 0 ? 'green' : 'red');

        return $kode;
    }

    private function jalankan(string $berkas): void
    {
        $db     = db_connect();
        $tahun  = '2026/2027';
        $reader = IOFactory::createReader('Xlsx');
        $reader->setLoadSheetsOnly(['SKBM 2026-2027']);
        $A = $reader->load($berkas)->getSheetByName('SKBM 2026-2027');

        // ---- impor lembar sekolah ke database (guru yang belum ada di Master dibuatkan sementara, supaya SEMUA baris ikut)
        $imp = new SkbmImpor();
        $p   = $imp->cocokkan($imp->baca($berkas));
        foreach ($p['guru'] as $i => $g) {
            if ($g['guru_id'] === null) {
                $db->table('guru')->insert(['kode_guru' => 'ZZSKX' . $i, 'nama' => $g['nama'], 'bukan_pengajar' => 0, 'max_beban' => 24]);
            }
        }
        $p = $imp->cocokkan($p);
        $kep = [];
        foreach ($p['guru'] as $i => $g) {
            $kep[$i] = $g['guru_id'] !== null ? 'guru:' . $g['guru_id'] : 'lewati';
        }
        $r = $imp->terapkan($tahun, $p, $kep, true);
        if (! $r['ok']) {
            throw new \RuntimeException('Impor gagal: ' . $r['pesan']);
        }
        $lib   = new Skbm();
        $nomor = preg_match('/Nomor\s*:\s*(.+)$/u', self::teks($A->getCell('A4')->getValue()), $mm) === 1 ? trim($mm[1]) : '';
        $lib->simpanNomorSk($tahun, $nomor);
        $m = $lib->muat($tahun);
        $B = SkbmCetak::spreadsheet($m, SkbmCetak::info($tahun))->getActiveSheet();
        CLI::write(sprintf('  (info) %d guru, %d baris mapel, %d guru×kelas, %d JP diimpor dari lembar sekolah; nomor SK: "%s"', $m['jumlah_guru'], $m['jumlah_mapel'], $m['jumlah_sel'], $m['total_jp'], $nomor), 'light_gray');

        // peta kolom: kelas ke-i → sekolah AO(41)+i, ours E(5)+i; JUMLAH JP / TOTAL / KOREKSI → sekolah 83/84/85, ours 47/48/49
        $nK   = count($m['kelas']);
        $kS   = static fn (int $i): int => 41 + $i;
        $kO   = static fn (int $i): int => 5 + $i;
        $jpS  = 83;
        $totS = 84;
        $jpO  = 5 + $nK;
        $totO = $jpO + 1;
        $nilai = static fn (Worksheet $ws, int $c, int $r): mixed => $ws->getCell([$c, $r])->getValue();
        $hitung = static fn (Worksheet $ws, int $c, int $r): mixed => $ws->getCell([$c, $r])->getCalculatedValue();

        // ================================================================= judul
        $this->bagian('Judul (4 baris)');
        foreach ([1 => 'DAFTAR LAMPIRAN 3', 3 => 'TAHUN PELAJARAN ' . $tahun] as $r => $isi) {
            $this->cek("A$r = \"$isi\"", self::teks($nilai($B, 1, $r)) === self::teks($nilai($A, 1, $r)) && self::teks($nilai($B, 1, $r)) === $isi, self::teks($nilai($B, 1, $r)) . ' ≠ ' . self::teks($nilai($A, 1, $r)));
        }
        $this->cek('A2 "SURAT KEPUTUSAN  KEPALA <SEKOLAH>" (dua spasi seperti berkas sekolah)', self::teks($nilai($B, 1, 2)) === self::teks($nilai($A, 1, 2)), $nilai($B, 1, 2) . ' ≠ ' . $nilai($A, 1, 2));
        $this->cek('A4 "Nomor : <nomor SK>"', self::teks($nilai($B, 1, 4)) === self::teks($nilai($A, 1, 4)), $nilai($B, 1, 4) . ' ≠ ' . $nilai($A, 1, 4));

        // ================================================================= header
        $this->bagian('Header 3 baris (teks)');
        foreach ([1 => 'NO', 2 => 'NAMA GURU', 3 => 'KODE GURU', 4 => 'MATA PELAJARAN'] as $c => $t) {
            $this->cek("{$c}: \"$t\"", self::teks($nilai($B, $c, 6)) === $t && self::teks($nilai($A, $c, 6)) === $t);
        }
        $salahGrup = [];
        $salahLabel = [];
        $awalGrup = [];
        foreach ($m['grup'] as $g) {
            $awalGrup[$g['dari']] = true;
        }
        foreach ($m['kelas'] as $i => $k) {
            if (isset($awalGrup[$i])) {
                foreach ([6, 7] as $baris) {
                    if (self::teks($nilai($B, $kO($i), $baris)) !== self::teks($nilai($A, $kS($i), $baris))) {
                        $salahGrup[] = Coordinate::stringFromColumnIndex($kO($i)) . $baris . ':' . self::teks($nilai($B, $kO($i), $baris)) . '≠' . self::teks($nilai($A, $kS($i), $baris));
                    }
                }
            }
            if (self::kunci((string) $nilai($B, $kO($i), 8)) !== self::kunci((string) $nilai($A, $kS($i), 8))) {
                $salahLabel[] = $k['nama'] . ':' . self::kunci((string) $nilai($B, $kO($i), 8)) . '≠' . self::kunci((string) $nilai($A, $kS($i), 8));
            }
        }
        $this->cek('kelompok kelas (KELAS PAGI/SIANG) dan tingkat (X/XI/XII) di awal tiap kelompok sama (' . count($m['grup']) . ' kelompok)', $salahGrup === [], implode(' ; ', $salahGrup));
        $this->cek("nama {$nK} kelas di baris 8 sama (TKJ 1 … AKL; titik/spasi/baris baru diabaikan)", $salahLabel === [], implode(' ; ', array_slice($salahLabel, 0, 5)));
        $this->cek('JUMLAH JP · JUMLAH TOTAL JP · JUMLAH KOREKSI', self::teks($nilai($B, $jpO, 6)) === 'JUMLAH JP' && self::teks($nilai($B, $totO, 6)) === 'JUMLAH TOTAL JP' && self::teks($nilai($B, $totO + 1, 6)) === 'JUMLAH KOREKSI'
            && self::teks($nilai($A, $jpS, 6)) === 'JUMLAH JP' && self::teks($nilai($A, $totS, 6)) === 'JUMLAH TOTAL JP' && self::teks($nilai($A, 85, 6)) === 'JUMLAH KOREKSI');

        // ================================================================= isi
        $this->bagian('Isi: NO, nama, kode, mapel, JP tiap kelas, jumlah');
        $f1S = 0;
        for ($r = 9; $r <= 200; $r++) {
            if (str_starts_with(self::teks($nilai($A, 1, $r)), 'TOTAL JP / KELAS')) {
                $f1S = $r;
                break;
            }
        }
        $f1O = 0;
        for ($r = 9; $r <= 200; $r++) {
            if (str_starts_with(self::teks($nilai($B, 1, $r)), 'TOTAL JP / KELAS')) {
                $f1O = $r;
                break;
            }
        }
        $this->cek('letak baris "TOTAL JP / KELAS" sama (baris ' . $f1S . ')', $f1S > 0 && $f1S === $f1O, "sekolah=$f1S ours=$f1O");
        $akhir = $f1S - 2;
        $selNo = $selNama = $selKode = $selMapel = $selJp = $selJumlah = $selTotal = 0;
        $dNo = $dNama = $dKode = $dMapel = $dJp = $dJumlah = $dTotal = [];
        $namaBedaTulis = 0;
        for ($r = 9; $r <= $akhir; $r++) {
            if (self::teks($nilai($A, 1, $r)) !== self::teks($nilai($B, 1, $r))) {
                $dNo[] = "A$r: " . self::teks($nilai($A, 1, $r)) . '≠' . self::teks($nilai($B, 1, $r));
            }
            $selNo++;
            $nS = self::teks($nilai($A, 2, $r));
            $nO = self::teks($nilai($B, 2, $r));
            if ($nS !== $nO) {
                if (HonorImpor::normalNama($nS) === HonorImpor::normalNama($nO)) {
                    $namaBedaTulis++;
                } else {
                    $dNama[] = "B$r: $nS ≠ $nO";
                }
            }
            $selNama++;
            if (self::teks($nilai($A, 3, $r)) !== self::teks($nilai($B, 3, $r))) {
                $dKode[] = "C$r: " . self::teks($nilai($A, 3, $r)) . '≠' . self::teks($nilai($B, 3, $r));
            }
            $selKode++;
            if (self::teks($nilai($A, 4, $r)) !== self::teks($nilai($B, 4, $r))) {
                $dMapel[] = "D$r: " . self::teks($nilai($A, 4, $r)) . '≠' . self::teks($nilai($B, 4, $r));
            }
            $selMapel++;
            for ($i = 0; $i < $nK; $i++) {
                $a = $nilai($A, $kS($i), $r);
                $b = $nilai($B, $kO($i), $r);
                $a = is_numeric($a) && (float) $a > 0 ? (int) $a : null;
                $b = is_numeric($b) && (float) $b > 0 ? (int) $b : null;
                if ($a !== $b) {
                    $dJp[] = Coordinate::stringFromColumnIndex($kO($i)) . $r . ': ' . json_encode($a) . '≠' . json_encode($b);
                }
                $selJp++;
            }
            $a = $hitung($A, $jpS, $r);
            $b = $hitung($B, $jpO, $r);
            if ((int) $a !== (int) $b) {
                $dJumlah[] = "JUMLAH JP baris {$r}: {$a} ≠ {$b}";
            }
            $selJumlah++;
        }
        // JUMLAH TOTAL JP per guru: sel pertama tiap blok guru (sekolah: gabungan vertikal; ours: gabungan vertikal) — bandingkan nilai terhitung
        $rowsGuru = [];
        for ($r = 9; $r <= $akhir; $r++) {
            if (self::teks($nilai($A, 2, $r)) !== '') {
                $rowsGuru[] = $r;
            }
        }
        foreach ($rowsGuru as $r) {
            $a = $hitung($A, $totS, $r);
            $b = $hitung($B, $totO, $r);
            if ((int) $a !== (int) $b) {
                $dTotal[] = "TOTAL JP guru baris {$r}: {$a} ≠ {$b}";
            }
            $selTotal++;
        }
        $this->cek("NO guru (tertulis di baris pertama tiap guru; nomor di SK boleh melompat) — $selNo sel", $dNo === [], implode(' ; ', array_slice($dNo, 0, 4)));
        $this->cek("nama guru — $selNama sel" . ($namaBedaTulis > 0 ? " ($namaBedaTulis beda hanya titik/spasi dengan Master Guru: diterima)" : ''), $dNama === [], implode(' ; ', array_slice($dNama, 0, 4)));
        $this->cek("KODE GURU (3A, 3B, … persis seperti di SK) — $selKode sel", $dKode === [], implode(' ; ', array_slice($dKode, 0, 4)));
        $this->cek("MATA PELAJARAN — $selMapel sel", $dMapel === [], implode(' ; ', array_slice($dMapel, 0, 4)));
        $this->cek("JP tiap kelas ({$nK} kolom × " . ($akhir - 8) . " baris = $selJp sel)", ($dJp ?? []) === [], implode(' ; ', array_slice($dJp ?? [], 0, 6)));
        $this->cek("JUMLAH JP tiap baris (rumus) — $selJumlah sel", $dJumlah === [], implode(' ; ', array_slice($dJumlah, 0, 4)));
        $this->cek("JUMLAH TOTAL JP tiap guru (rumus, gabungan sel) — $selTotal guru", $dTotal === [], implode(' ; ', array_slice($dTotal, 0, 4)));

        // ================================================================= baris total & tanda tangan
        $this->bagian('Baris total dan tanda tangan');
        $this->cek('A130 "TOTAL JP SELURUHNYA"', self::teks($nilai($A, 1, $f1S + 1)) === 'TOTAL JP SELURUHNYA' && self::teks($nilai($B, 1, $f1O + 1)) === 'TOTAL JP SELURUHNYA');
        $dTot = [];
        for ($i = 0; $i < $nK; $i++) {
            if ((int) $hitung($A, $kS($i), $f1S) !== (int) $hitung($B, $kO($i), $f1O)) {
                $dTot[] = Coordinate::stringFromColumnIndex($kO($i)) . ': ' . $hitung($A, $kS($i), $f1S) . '≠' . $hitung($B, $kO($i), $f1O);
            }
        }
        $this->cek("TOTAL JP / KELAS ($nK kelas, rumus)", $dTot === [], implode(' ; ', array_slice($dTot, 0, 5)));
        $this->cek('TOTAL JP seluruhnya (rumus) dan total kolom JUMLAH JP / JUMLAH TOTAL JP', (int) $hitung($A, 41, $f1S + 1) === (int) $hitung($B, 5, $f1O + 1)
            && (int) $hitung($A, $jpS, $f1S) === (int) $hitung($B, $jpO, $f1O) && (int) $hitung($A, $totS, $f1S) === (int) $hitung($B, $totO, $f1O),
            $hitung($A, 41, $f1S + 1) . '/' . $hitung($A, $jpS, $f1S) . '/' . $hitung($A, $totS, $f1S) . ' ≠ ' . $hitung($B, 5, $f1O + 1) . '/' . $hitung($B, $jpO, $f1O) . '/' . $hitung($B, $totO, $f1O));
        $this->cek('TOTAL JP seluruhnya = total JP yang diimpor (' . $m['total_jp'] . ')', (int) $hitung($B, 5, $f1O + 1) === (int) $m['total_jp']);
        $set = $db->table('settings')->select('headmaster_name')->get()->getRowArray() ?? [];
        $this->cek('tanda tangan: nama kepala sekolah (dari Pengaturan Sekolah) di B' . ($f1O + 6) . ', tinggi baris NRKS 28', self::teks($nilai($B, 2, $f1O + 6)) === self::teks($set['headmaster_name'] ?? '') && (float) $B->getRowDimension($f1O + 7)->getRowHeight() === 28.0 && self::teks($nilai($A, 2, $f1S + 6)) !== '');
        $this->catat('berkas sekolah mencetak tanda tangan di luar area cetak (A1:CF132) — di sistem ikut tercetak');

        // ================================================================= gabungan sel
        $this->bagian('Gabungan sel');
        $petaKol = static function (int $c) use ($jpO): ?int {
            if ($c >= 1 && $c <= 4) {
                return $c;
            }

            return $c >= 41 ? $c - 41 + 5 : null;
        };
        $norm = static function (string $rentang) use ($petaKol): ?string {
            [$a, $b] = array_pad(explode(':', $rentang), 2, null);
            $ca = Coordinate::coordinateFromString($a);
            $cb = Coordinate::coordinateFromString($b ?? $a);
            $c1 = $petaKol(Coordinate::columnIndexFromString($ca[0]));
            $c2 = $petaKol(Coordinate::columnIndexFromString($cb[0]));
            if ($c1 === null || $c2 === null) {
                return null;
            }

            return Coordinate::stringFromColumnIndex($c1) . $ca[1] . ':' . Coordinate::stringFromColumnIndex($c2) . $cb[1];
        };
        $gabS = [];
        foreach (array_keys($A->getMergeCells()) as $g) {
            $n = $norm($g);
            if ($n === null) {
                continue; // blok kolom kiri (E–AN) yang disembunyikan sekolah
            }
            [$aa, $bb] = explode(':', $n);
            $r1 = (int) preg_replace('/\D/', '', $aa);
            $r2 = (int) preg_replace('/\D/', '', $bb);
            $kolAwal = preg_replace('/\d/', '', $aa);
            // buang yang khas berkas sekolah: baris kosong digabung (128) dan kolom KOREKSI yang gabungannya tak lengkap (CG → AW)
            if ($r1 === $f1S - 1 || $kolAwal === Coordinate::stringFromColumnIndex($totO + 1)) {
                continue;
            }
            $gabS[$n] = true;
        }
        $gabO = [];
        foreach (array_keys($B->getMergeCells()) as $g) {
            [$aa] = explode(':', $g);
            if (preg_replace('/\d/', '', $aa) === Coordinate::stringFromColumnIndex($totO + 1)) {
                continue;
            }
            $gabO[$g] = true;
        }
        $kurang = array_diff_key($gabS, $gabO);
        $lebih  = array_diff_key($gabO, $gabS);
        // Gabungan JUMLAH TOTAL JP yang hanya ada di sistem dan menutup baris yang DISEMBUNYIKAN di berkas sekolah: sekolah tak menggabungnya (diterima).
        foreach (array_keys($lebih) as $g) {
            [$aa, $bb] = explode(':', $g);
            $dari   = (int) preg_replace('/\D/', '', $aa);
            $sampai = (int) preg_replace('/\D/', '', $bb);
            for ($rr = $dari; $rr <= $sampai; $rr++) {
                if (! $A->getRowDimension($rr)->getVisible()) {
                    unset($lebih[$g]);
                    $this->catat('gabungan JUMLAH TOTAL JP ' . $g . ' ada di sistem tetapi tidak di berkas sekolah (baris ' . $rr . ' disembunyikan di sana)');
                    break;
                }
            }
        }
        $this->cek('gabungan sel header (A6:A8 … JUMLAH …), kelompok kelas, baris total, dan JUMLAH TOTAL JP per guru sama (' . count($gabS) . ' gabungan)', $kurang === [] && $lebih === [],
            'kurang di sistem: ' . implode(',', array_slice(array_keys($kurang), 0, 6)) . ' | lebih di sistem: ' . implode(',', array_slice(array_keys($lebih), 0, 6)));

        // ================================================================= gaya (huruf, warna, rata, garis)
        $this->bagian('Gaya sel kunci (huruf, warna isi, rata, putar, garis)');
        $gaya = static function (Worksheet $ws, int $c, int $r): array {
            $s  = $ws->getStyle([$c, $r]);
            $f  = $s->getFont();
            $al = $s->getAlignment();
            $b  = $s->getBorders();
            $isi = $s->getFill();

            return [
                'huruf' => $f->getName() . '/' . $f->getSize() . ($f->getBold() ? '/B' : ''),
                'isi'   => $isi->getFillType() === 'none' ? '-' : $isi->getStartColor()->getRGB(),
                'rata'  => $al->getHorizontal() . '/' . $al->getVertical() . ($al->getWrapText() ? '/wrap' : '') . '/' . $al->getTextRotation(),
                'garis' => implode('', array_map(static fn ($x): string => $x->getBorderStyle() === Border::BORDER_NONE ? '-' : 't', [$b->getTop(), $b->getBottom(), $b->getLeft(), $b->getRight()])),
            ];
        };
        $pasangan = [
            'A1 judul' => [[1, 1], [1, 1]], 'A4 nomor' => [[1, 4], [1, 4]],
            'A6 NO' => [[1, 6], [1, 6]], 'B6 NAMA GURU' => [[2, 6], [2, 6]], 'C6 KODE GURU' => [[3, 6], [3, 6]], 'D6 MATA PELAJARAN' => [[4, 6], [4, 6]],
            'kelompok kelas (baris 6)' => [[41, 6], [5, 6]], 'tingkat (baris 7)' => [[41, 7], [5, 7]], 'nama kelas miring (baris 8, TKJ 1)' => [[41, 8], [5, 8]],
            'JUMLAH JP (header)' => [[$jpS, 6], [$jpO, 6]], 'JUMLAH TOTAL JP (header)' => [[$totS, 6], [$totO, 6]],
            'NO data (A9)' => [[1, 9], [1, 9]], 'NAMA data (B9)' => [[2, 9], [2, 9]], 'KODE data (C9)' => [[3, 9], [3, 9]],
            'sel JP (kelas) berisi angka' => [[$kS(array_search(true, array_map(static fn ($k) => false, $m['kelas']), true) ?: 0) + 25, 11], [$kO(25), 11]],
            'JUMLAH JP data (baris 11)' => [[$jpS, 11], [$jpO, 11]], 'JUMLAH TOTAL JP data (baris 11)' => [[$totS, 11], [$totO, 11]],
            'TOTAL JP / KELAS (label)' => [[1, $f1S], [1, $f1O]], 'TOTAL JP / KELAS (isi kelas)' => [[41, $f1S], [5, $f1O]],
            'TOTAL JP (JUMLAH JP)' => [[$jpS, $f1S], [$jpO, $f1O]], 'TOTAL JP SELURUHNYA (isi)' => [[41, $f1S + 1], [5, $f1O + 1]],
        ];
        $tanpaGaris = ['JUMLAH JP (header)' => true, 'JUMLAH TOTAL JP data (baris 11)' => true]; // jangkar gabungan vertikal: garis bawahnya ada di sel paling bawah
        foreach ($pasangan as $nama => [[$cs, $rs], [$co, $ro]]) {
            $gs = $gaya($A, $cs, $rs);
            $go = $gaya($B, $co, $ro);
            if (isset($tanpaGaris[$nama])) {
                unset($gs['garis'], $go['garis']);
            }
            $this->cek("$nama  [" . $go['huruf'] . ' | isi ' . $go['isi'] . ' | ' . $go['rata'] . ']', $gs === $go, 'sekolah ' . json_encode($gs) . ' ≠ sistem ' . json_encode($go));
        }
        $this->catat('huruf kolom MATA PELAJARAN: sekolah memakai Arial 8 (kadang 9) — sistem Arial 8; font nama kelas 7 pt hanya untuk label panjang');

        // ================================================================= ukuran & cetak
        $this->bagian('Ukuran dan pengaturan cetak');
        $lebar = static fn (Worksheet $ws, int $c): float => round((float) $ws->getColumnDimensionByColumn($c)->getWidth(), 2);
        $dLebar = [];
        foreach ([['NO', 1, 1], ['NAMA GURU', 2, 2], ['KODE GURU', 3, 3], ['MATA PELAJARAN', 4, 4], ['kolom kelas', 41, 5], ['kolom kelas terakhir', 41 + $nK - 1, 5 + $nK - 1], ['JUMLAH JP', $jpS, $jpO], ['JUMLAH TOTAL JP', $totS, $totO], ['JUMLAH KOREKSI', 85, $totO + 1]] as [$nama, $cs, $co]) {
            if (abs($lebar($A, $cs) - $lebar($B, $co)) > 0.01) {
                $dLebar[] = "$nama: " . $lebar($A, $cs) . '≠' . $lebar($B, $co);
            }
        }
        $this->cek('lebar kolom (NO 4,73 · NAMA 26 · KODE 4,27 · MAPEL 20,27 · kelas 2,73 · JUMLAH 5,36 · KOREKSI 9,18)', $dLebar === [], implode(' ; ', $dLebar));
        $this->cek('tinggi baris header: 51 · 16,5 · 33; baris kosong sebelum total 15,5', (float) $B->getRowDimension(6)->getRowHeight() === (float) $A->getRowDimension(6)->getRowHeight() && (float) $B->getRowDimension(7)->getRowHeight() === (float) $A->getRowDimension(7)->getRowHeight()
            && (float) $B->getRowDimension(8)->getRowHeight() === (float) $A->getRowDimension(8)->getRowHeight() && (float) $B->getRowDimension($f1O - 1)->getRowHeight() === (float) $A->getRowDimension($f1S - 1)->getRowHeight(),
            implode('/', array_map(static fn ($w, $r) => $w->getRowDimension($r)->getRowHeight(), [$A, $A, $A, $B, $B, $B], [6, 7, 8, 6, 7, 8])));
        $pa = $A->getPageSetup();
        $pb = $B->getPageSetup();
        $this->cek('cetak: ' . $pb->getOrientation() . ', kertas ' . $pb->getPaperSize() . ' (Legal), skala ' . $pb->getScale() . ' %, ulang baris ' . implode('–', $pb->getRowsToRepeatAtTop()), $pa->getOrientation() === $pb->getOrientation() && $pa->getPaperSize() === $pb->getPaperSize() && $pa->getScale() === $pb->getScale() && $pa->getRowsToRepeatAtTop() === $pb->getRowsToRepeatAtTop());
        $ma = $A->getPageMargins();
        $mb = $B->getPageMargins();
        $this->cek('margin atas/bawah/kiri/kanan', [$ma->getTop(), $ma->getBottom(), $ma->getLeft(), $ma->getRight()] === [$mb->getTop(), $mb->getBottom(), $mb->getLeft(), $mb->getRight()]);
        $this->cek('warna tab lembar B3A2C7 dan nama lembar "SKBM 2026-2027"', $A->getTabColor()->getRGB() === $B->getTabColor()->getRGB() && $A->getTitle() === $B->getTitle(), $A->getTitle() . ' / ' . $B->getTitle());
        $this->catat('blok kolom kiri (E–AN) yang disembunyikan sekolah tidak ditiru; baris tersembunyi di berkas sekolah tetap ditampilkan; kolom JUMLAH KOREKSI sekolah hanya terisi sebagian');
        CLI::write(sprintf('  (info) %d catatan perbedaan yang disengaja', $this->catatan), 'light_gray');
    }

    private function catat(string $teks): void
    {
        $this->catatan++;
        CLI::write('  [catatan] ' . $teks, 'light_gray');
    }
}
