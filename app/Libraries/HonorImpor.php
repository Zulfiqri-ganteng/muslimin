<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\GuruModel;
use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Impor rekap honor dari Excel lama sekolah (mis. "HONOR ASTS.xlsx") ke dokumen Honor Ujian (Fase 3).
 *
 * ALUR AMAN (tiga langkah, tak ada yang tersimpan sebelum Admin menyetujui):
 *   1. baca()      — membaca berkas: kolom (NO, NAMA, JABATAN, tiap komponen), baris penerima, nama penanda tangan.
 *   2. cocokkan()  — mencocokkan nama ke Master Guru: cocok / mirip / ganda / tidak ada. Admin memutuskan tiap baris
 *                    (pakai guru ini, tambahkan ke Master Guru sebagai guru atau staf, atau lewati).
 *   3. terapkan()  — menulis ke dokumen dalam satu transaksi, dicatat di Audit Log.
 *
 * Yang dibaca dari tiap komponen satuan adalah JUMLAH-nya (kolom judul), BUKAN kolom rupiahnya; rupiah selalu dihitung
 * ulang sistem dari tarif dokumen, sehingga kesalahan rumus di Excel lama (mis. JUMLAH yang hanya menjumlah sebagian
 * baris) tidak ikut terbawa. Berkas tidak disimpan setelah dibaca.
 */
final class HonorImpor
{
    public const MAKS_BYTE  = 2097152;
    public const MAKS_BARIS = 300;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Pembantu murni
    // =================================================================

    /** Nama untuk pencocokan: huruf kecil, tanpa gelar (belakang & depan), tanpa tanda baca. */
    public static function normalNama(string $nama): string
    {
        $s = mb_strtolower($nama);
        $s = (string) preg_replace('/,.*$/u', '', $s);                              // gelar setelah koma
        $s = (string) preg_replace('/\b(?:\p{L}{1,3}\.)+\p{L}{1,5}\.?/u', ' ', $s);  // gelar bertitik: s.pd, s.t, m.pd.i
        $s = (string) preg_replace('/\b(?:drs|dra|dr|ir|hj|h|prof)\b\.?/u', ' ', $s); // gelar depan
        $s = (string) preg_replace('/[^\p{L}\s]/u', ' ', $s);

        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * Cocokkan baris Excel ke baris sebuah dokumen honor untuk MENGIKUTI URUTAN Excel (angka tidak disentuh).
     * Tiga tingkat, tiap tingkat hanya memakai baris dokumen yang belum terpakai: (1) nama persis (spasi dirapikan),
     * (2) nama tanpa tanda baca/spasi ("S.Pd" = "S.Pd."), (3) nama tanpa gelar — hanya bila tepat SATU kandidat
     * (lebih dari satu = ganda, dilewati). Nama dokumen dan nama Master Guru sama-sama dicoba.
     *
     * @param list<array<string,mixed>> $excel baris Excel dalam urutannya (kunci 'nama')
     * @param list<array<string,mixed>> $dok   baris dokumen dalam urutan sekarang (kunci 'id', 'nama', 'guru_nama')
     *
     * @return array{urut:list<int>, cocok:array<int,int>, tak_ada_di_dokumen:list<string>, tak_ada_di_excel:list<string>, ganda:list<string>}
     *               urut = id baris dokumen menurut urutan baru; cocok = indeks baris Excel → id baris dokumen
     */
    public static function cocokkanUrutan(array $excel, array $dok): array
    {
        $rapi  = static fn (string $s): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
        $padat = static fn (string $s): string => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s));
        $kunci = [];
        foreach ($dok as $d) {
            foreach (['nama', 'guru_nama'] as $f) {
                $n = (string) ($d[$f] ?? '');
                if ($n === '') {
                    continue;
                }
                $kunci[(int) $d['id']][0][$rapi($n)] = true;
                $kunci[(int) $d['id']][1][$padat($n)] = true;
                $nn = self::normalNama($n);
                if ($nn !== '') {
                    $kunci[(int) $d['id']][2][$nn] = true;
                }
            }
        }

        $pakai = [];
        $cocok = [];
        $tak   = [];
        $ganda = [];
        foreach ($excel as $i => $e) {
            $nama = (string) ($e['nama'] ?? '');
            if (trim($nama) === '') {
                continue;
            }
            $cari = [0 => $rapi($nama), 1 => $padat($nama), 2 => self::normalNama($nama)];
            $ketemu = null;
            foreach ($cari as $tingkat => $k) {
                if ($k === '') {
                    continue;
                }
                $kand = [];
                foreach ($kunci as $id => $per) {
                    if (! isset($pakai[$id]) && isset($per[$tingkat][$k])) {
                        $kand[] = $id;
                    }
                }
                if (count($kand) === 1 || (count($kand) > 1 && $tingkat < 2)) {
                    $ketemu = $kand[0];
                    break;
                }
                if (count($kand) > 1) { // nama tanpa gelar sama dengan lebih dari satu orang: jangan menebak
                    $ganda[] = $nama;
                    continue 2;
                }
            }
            if ($ketemu === null) {
                $tak[] = $nama;
                continue;
            }
            $pakai[$ketemu] = true;
            $cocok[$i]      = $ketemu;
        }

        $urut = array_values($cocok);
        $sisa = [];
        foreach ($dok as $d) {
            if (! isset($pakai[(int) $d['id']])) {
                $urut[] = (int) $d['id'];
                $sisa[] = (string) $d['nama'];
            }
        }

        return ['urut' => $urut, 'cocok' => $cocok, 'tak_ada_di_dokumen' => array_merge($tak, $ganda), 'tak_ada_di_excel' => $sisa, 'ganda' => $ganda];
    }

    /** Judul kolom Excel → kode komponen ('total' / 'ttd' untuk kolom khusus, 'x:TEKS' bila tak dikenal). */
    public static function kodeDariHeader(string $teks): ?string
    {
        $u = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $teks)));
        if ($u === '') {
            return null;
        }
        $peta = [
            'TOTAL' => 'total', 'JUMLAH' => 'total', 'TTD' => 'ttd', 'TANDA TANGAN' => 'ttd', 'PANITIA' => 'tunj_panitia', 'STRUKTURAL' => 'tunj_struktural',
            'WALI' => 'tunj_walas', 'SOAL' => 'soal', 'TRANSPORT' => 'transport', 'PENGAWAS' => 'pengawas', 'KOREKSI' => 'koreksi',
            'RAPOT' => 'rapot', 'RAPORT' => 'rapot', 'LEMBUR' => 'lembur',
        ];
        foreach ($peta as $kunci => $kode) {
            if (str_contains($u, $kunci)) {
                return $kode;
            }
        }

        return 'x:' . $u;
    }

    // =================================================================
    // 1. Baca berkas
    // =================================================================

    /**
     * @return array{baris:list<array<string,mixed>>, kolom:list<array<string,mixed>>, ttd:array<string,string>, peringatan:list<string>, lembar:string}
     *
     * @throws \RuntimeException berisi pesan yang aman ditampilkan ke pengguna
     */
    public function baca(string $path): array
    {
        clearstatcache(true, $path);
        if (! is_file($path) || filesize($path) > self::MAKS_BYTE) {
            throw new \RuntimeException('Berkas terlalu besar (maksimal 2 MB) atau tidak terbaca.');
        }
        try {
            $reader = IOFactory::createReader('Xlsx');
            if (! $reader->canRead($path)) {
                throw new \RuntimeException('Berkas bukan Excel (.xlsx) yang sah.');
            }
            $x = $reader->load($path);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Berkas Excel tidak bisa dibuka (rusak atau dilindungi sandi).');
        }
        // Berkas bisa berisi beberapa lembar (mis. REKAP dan SLIP yang sama-sama punya kolom "NAMA"): pilih yang
        // paling lengkap — paling banyak kolom komponen, lalu paling banyak penerima.
        $terbaik = null;
        $skor    = -1;
        $galat   = null;
        foreach ($x->getWorksheetIterator() as $ws) {
            try {
                $hasil = $this->bacaLembar($ws);
            } catch (\RuntimeException $e) {
                $galat = $e;
                continue;
            }
            if ($hasil === null) {
                continue;
            }
            $s = count($hasil['kolom']) * 1000 + count($hasil['baris']);
            if ($s > $skor) {
                $skor    = $s;
                $terbaik = $hasil;
            }
        }
        if ($terbaik !== null) {
            return $terbaik;
        }
        if ($galat !== null) {
            throw $galat;
        }
        throw new \RuntimeException('Tabel honor tidak ditemukan. Pastikan ada baris judul kolom dengan tulisan "NO", "NAMA", dan "JABATAN".');
    }

    private function teks(Worksheet $ws, int $c, int $r): string
    {
        $v = $this->nilaiSel($ws, $c, $r);

        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
    }

    private function nilaiSel(Worksheet $ws, int $c, int $r): mixed
    {
        $sel = $ws->getCell(Coordinate::stringFromColumnIndex($c) . $r);
        try {
            return $sel->isFormula() ? $sel->getCalculatedValue() : $sel->getValue();
        } catch (\Throwable) {
            return null;
        }
    }

    private function bacaLembar(Worksheet $ws): ?array
    {
        $maxR = min($ws->getHighestDataRow(), 450);
        $maxC = min(Coordinate::columnIndexFromString($ws->getHighestDataColumn()), 30);

        // baris judul kolom: ada sel "NAMA"
        $hr = $nameCol = $noCol = $jabCol = 0;
        for ($r = 1; $r <= min(25, $maxR) && $hr === 0; $r++) {
            for ($c = 1; $c <= $maxC; $c++) {
                if (mb_strtoupper($this->teks($ws, $c, $r)) === 'NAMA') {
                    $hr = $r;
                    $nameCol = $c;
                    break;
                }
            }
        }
        if ($hr === 0) {
            return null;
        }
        for ($c = 1; $c <= $maxC; $c++) {
            $t = mb_strtoupper($this->teks($ws, $c, $hr));
            if ($noCol === 0 && in_array($t, ['NO', 'NO.', 'NOMOR'], true)) {
                $noCol = $c;
            }
            if ($jabCol === 0 && $t === 'JABATAN') {
                $jabCol = $c;
            }
        }

        $peringatan = [];
        $kolom      = [];
        $totalCol   = 0;
        $mulai      = max($nameCol, $jabCol) + 1;
        for ($c = $mulai; $c <= $maxC; $c++) {
            $judul = $this->teks($ws, $c, $hr);
            $kode  = self::kodeDariHeader($judul);
            if ($kode === null || $kode === 'ttd') {
                continue;
            }
            if ($kode === 'total') {
                $totalCol = $c;
                continue;
            }
            if (str_starts_with($kode, 'x:')) {
                $peringatan[] = 'Kolom "' . $judul . '" tidak dikenal sebagai komponen honor — dicocokkan lewat nama bila ada komponen bernama sama, bila tidak dilewati.';
            }
            // tarif per satuan yang tertulis di bawah judul ("Rp. 20.000")
            $tarifExcel = null;
            if (preg_match('/(\d[\d.]*)/', $this->teks($ws, $c, $hr + 1), $m)) {
                $tarifExcel = HonorPengaturan::angka($m[1]);
            }
            $kolom[] = ['c' => $c, 'kode' => $kode, 'judul' => $judul, 'tarif_excel' => $tarifExcel];
        }
        if ($kolom === []) {
            throw new \RuntimeException('Tidak ada kolom komponen honor (mis. TRANSPORT, PENGAWAS) di bawah JABATAN.');
        }

        // baris penerima
        $baris = [];
        $akhir = $hr;
        for ($r = $hr + 1; $r <= $maxR; $r++) {
            $nama = $this->teks($ws, $nameCol, $r);
            $no   = $noCol > 0 ? $this->teks($ws, $noCol, $r) : '';
            if (mb_strtoupper($no) === 'JUMLAH' || str_starts_with(mb_strtoupper($nama), 'JUMLAH') || str_starts_with(mb_strtoupper($nama), 'TOTAL')) {
                break;
            }
            $akhir = $r;
            if ($nama === '') {
                continue;
            }
            if (count($baris) >= self::MAKS_BARIS) {
                throw new \RuntimeException('Terlalu banyak baris (maksimal ' . self::MAKS_BARIS . ' penerima).');
            }
            $nilai = [];
            foreach ($kolom as $k) {
                $v = $this->nilaiSel($ws, (int) $k['c'], $r);
                if ($v === null || (is_string($v) && trim($v) === '')) {
                    $nilai[$k['kode']] = 0;
                    continue;
                }
                $n = is_numeric($v) && (float) $v >= 0 && floor((float) $v) === (float) $v ? (int) $v : HonorPengaturan::angka((string) $v);
                if ($n === null) {
                    $peringatan[] = 'Baris ' . $r . ' (' . $nama . '), kolom ' . $k['judul'] . ': isi "' . (is_scalar($v) ? (string) $v : '?') . '" bukan angka bulat — dianggap 0.';
                    $n = 0;
                }
                $nilai[$k['kode']] = $n;
            }
            $totalExcel = null;
            if ($totalCol > 0) {
                $tv = $this->nilaiSel($ws, $totalCol, $r);
                $totalExcel = is_numeric($tv) ? (int) round((float) $tv) : null;
            }
            $baris[] = [
                'baris_excel' => $r, 'no' => ctype_digit($no) ? (int) $no : null, 'nama' => $nama,
                'jabatan'     => $jabCol > 0 ? mb_substr($this->teks($ws, $jabCol, $r), 0, 120) : '',
                'nilai'       => $nilai, 'total_excel' => $totalExcel,
            ];
        }
        if ($baris === []) {
            throw new \RuntimeException('Tidak ada baris penerima di bawah judul kolom.');
        }

        return [
            'lembar'     => $ws->getTitle(),
            'kolom'      => $kolom,
            'baris'      => $baris,
            'ttd'        => $this->bacaTtd($ws, $akhir + 1, $maxR, $maxC),
            'peringatan' => $peringatan,
        ];
    }

    /** Nama Ketua / Bendahara / Kepala Sekolah dari blok tanda tangan di bawah tabel (mencari penanda, lalu nama di bawahnya). */
    private function bacaTtd(Worksheet $ws, int $dari, int $maxR, int $maxC): array
    {
        $out = [];
        for ($r = $dari; $r <= $maxR; $r++) {
            for ($c = 1; $c <= $maxC; $c++) {
                $t = mb_strtolower($this->teks($ws, $c, $r));
                $kunci = null;
                if (str_starts_with($t, 'ketua') && ! str_contains($t, 'program')) {
                    $kunci = 'ketua';
                } elseif (str_starts_with($t, 'bendahara')) {
                    $kunci = 'bendahara';
                } elseif (str_starts_with($t, 'kepala')) {
                    $kunci = 'kepsek';
                }
                if ($kunci === null || isset($out[$kunci])) {
                    continue;
                }
                for ($rr = $r + 1; $rr <= min($maxR, $r + 8); $rr++) {
                    $nama = IsianBantu::rapikanGelar(HonorPengaturan::rapikan($this->teks($ws, $c, $rr)));
                    if ($nama !== '' && IsianBantu::namaOrangSah($nama) && mb_strlen($nama) <= 150) {
                        $out[$kunci] = $nama;
                        break;
                    }
                }
            }
        }

        return $out;
    }

    // =================================================================
    // 2. Cocokkan nama ke Master Guru
    // =================================================================

    /**
     * @param list<array<string,mixed>> $baris dari baca()
     *
     * @return list<array<string,mixed>> tiap baris + status (cocok|mirip|ganda|tidak), guru_id bawaan, kandidat
     */
    public function cocokkan(array $baris): array
    {
        $guru  = $this->db->table('guru')->select('id, nama, kode_guru, bukan_pengajar')->where('deleted_at', null)->where('induk_id', null)->get()->getResultArray();
        $peta  = [];
        $norm  = [];
        foreach ($guru as $g) {
            $n = self::normalNama((string) $g['nama']);
            $norm[(int) $g['id']] = $n;
            if ($n !== '') {
                $peta[$n][] = $g;
            }
        }
        $kenal = static fn (array $g): array => ['id' => (int) $g['id'], 'nama' => $g['nama'], 'kode_guru' => $g['kode_guru'], 'staf' => (int) $g['bukan_pengajar'] === 1];

        foreach ($baris as &$b) {
            $n   = self::normalNama((string) $b['nama']);
            $ada = $peta[$n] ?? [];
            $b['guru_id'] = null;
            if (count($ada) === 1) {
                $b['status']   = 'cocok';
                $b['guru_id']  = (int) $ada[0]['id'];
                $b['kandidat'] = [$kenal($ada[0])];
                continue;
            }
            if (count($ada) > 1) {
                $b['status']   = 'ganda';
                $b['kandidat'] = array_map($kenal, $ada);
                continue;
            }
            $mirip  = [];
            $tokenB = $n === '' ? [] : explode(' ', $n);
            foreach ($guru as $g) {
                $gn     = $norm[(int) $g['id']];
                $tokenG = $gn === '' ? [] : explode(' ', $gn);
                $kecil  = count($tokenB) <= count($tokenG) ? $tokenB : $tokenG;
                $besar  = count($tokenB) <= count($tokenG) ? $tokenG : $tokenB;
                $subset = count($kecil) >= 2 && array_diff($kecil, $besar) === [];
                $dekat  = min(strlen($n), strlen($gn)) >= 8 && levenshtein($n, $gn) <= 2;
                if ($subset || $dekat) {
                    $mirip[] = $g;
                }
            }
            if ($mirip !== []) {
                $b['status']   = 'mirip';
                $b['kandidat'] = array_map($kenal, array_slice($mirip, 0, 5));
                $b['guru_id']  = count($mirip) === 1 ? (int) $mirip[0]['id'] : null;
            } else {
                $b['status']   = 'tidak';
                $b['kandidat'] = [];
            }
        }
        unset($b);

        return $baris;
    }

    /**
     * Analisis hasil baca terhadap komponen sebuah honor (atau komponen aktif bila honor belum dibuat): total hitungan
     * SISTEM per baris dan keseluruhan, kolom yang tak punya komponen, dan beda tarif dengan yang tertulis di Excel.
     *
     * @param list<array<string,mixed>> $komponen baris honor_dok_komponen / honor_komponen (kode, nama, tipe, tarif)
     */
    public function analisis(array $payload, array $komponen): array
    {
        $byKode = [];
        $byNama = [];
        foreach ($komponen as $k) {
            $byKode[$k['kode']] = $k;
            $byNama[mb_strtoupper((string) $k['nama'])] = $k;
        }
        $peta = [];   // kode Excel → komponen
        $tanpa = [];
        $peringatan = [];
        foreach ($payload['kolom'] as $kol) {
            $k = $byKode[$kol['kode']] ?? ($byNama[mb_strtoupper((string) $kol['judul'])] ?? null);
            if ($k === null) {
                $tanpa[] = $kol['judul'];
                continue;
            }
            $peta[$kol['kode']] = $k;
            if ($kol['tarif_excel'] !== null && $k['tipe'] === 'satuan' && (int) $kol['tarif_excel'] !== (int) $k['tarif']) {
                $peringatan[] = 'Tarif ' . $k['nama'] . ' di Excel Rp ' . number_format((int) $kol['tarif_excel'], 0, ',', '.') . ' berbeda dari tarif sistem Rp ' . number_format((int) $k['tarif'], 0, ',', '.') . ' — yang dipakai: tarif sistem.';
            }
        }
        $total = 0;
        $totalExcel = 0;
        $baris = [];
        foreach ($payload['baris'] as $i => $b) {
            $t = 0;
            foreach ($b['nilai'] as $kode => $n) {
                $k = $peta[$kode] ?? null;
                if ($k !== null) {
                    $t += $k['tipe'] === 'tetap' ? (int) $n : (int) $n * (int) $k['tarif'];
                }
            }
            $baris[$i] = $t;
            $total += $t;
            $totalExcel += (int) ($b['total_excel'] ?? 0);
        }

        return ['total_baris' => $baris, 'total' => $total, 'total_excel' => $totalExcel, 'kolom_tanpa_komponen' => $tanpa, 'peringatan' => $peringatan];
    }

    // =================================================================
    // 3. Terapkan
    // =================================================================

    /**
     * @param array<string,mixed>      $periode    baris ujian_periode
     * @param array<string,mixed>      $payload    hasil baca()
     * @param array<int|string,string> $keputusan  indeks baris → "guru:ID" | "baru:guru" | "baru:staf" | "lewati"
     *
     * @return array{ok:bool, pesan:string, ringkas?:array<string,mixed>}
     */
    public function terapkan(array $periode, array $payload, array $keputusan, bool $bersihkan = false): array
    {
        $dokLib = new HonorDokumen($this->db);
        $dok    = $dokLib->dokumenPeriode((int) $periode['id']);
        if ($dok === null) {
            $r = $dokLib->buat($periode);
            if (! $r['ok']) {
                return ['ok' => false, 'pesan' => $r['pesan']];
            }
            $dok = $dokLib->dokumenId((int) $r['id']);
        }
        if ($dok['status'] === 'dikunci') {
            return ['ok' => false, 'pesan' => 'Honor ini sudah DIKUNCI dan tidak bisa diubah.'];
        }
        $dokId = (int) $dok['id'];

        $dk = $this->db->table('honor_dok_komponen')->where('dokumen_id', $dokId)->get()->getResultArray();
        $byKode = [];
        $byNama = [];
        foreach ($dk as $k) {
            $byKode[$k['kode']] = $k;
            $byNama[mb_strtoupper((string) $k['nama'])] = $k;
        }
        $petaKol = [];
        $tanpa   = [];
        foreach ($payload['kolom'] as $kol) {
            $k = $byKode[$kol['kode']] ?? ($byNama[mb_strtoupper((string) $kol['judul'])] ?? null);
            if ($k === null) {
                $tanpa[] = $kol['judul'];
            } else {
                $petaKol[$kol['kode']] = $k;
            }
        }

        $peringatan = [];
        $stat = ['baru' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'guru_dibuat' => 0];
        $guruModel = new GuruModel();

        $this->db->transStart();
        if ($bersihkan) {
            $this->db->table('honor_baris')->where('dokumen_id', $dokId)->delete();
        }
        $ada = [];
        foreach ($this->db->table('honor_baris')->where('dokumen_id', $dokId)->get()->getResultArray() as $b) {
            if ($b['guru_id'] !== null) {
                $ada[(int) $b['guru_id']] = (int) $b['id'];
            }
        }
        $dipakai = [];
        foreach ($payload['baris'] as $i => $b) {
            $d = (string) ($keputusan[$i] ?? 'lewati');
            $guruId = 0;
            // Nama di rekap = nama persis seperti ditulis di Excel sekolah (itu yang tercetak); tautan ke Master Guru
            // hanya untuk hitung otomatis dan pemeriksaan.
            $namaExcel = mb_substr(HonorPengaturan::rapikan((string) $b['nama']), 0, 150);
            if (preg_match('/^guru:(\d+)$/', $d, $m)) {
                $g = $this->db->table('guru')->select('id, nama')->where('id', (int) $m[1])->where('deleted_at', null)->where('induk_id', null)->get()->getRowArray();
                if ($g === null) {
                    $peringatan[] = 'Baris "' . $b['nama'] . '": guru pilihan tidak ditemukan — dilewati.';
                    $stat['dilewati']++;
                    continue;
                }
                $guruId = (int) $g['id'];
                $namaMaster = $namaExcel;
            } elseif ($d === 'baru:guru' || $d === 'baru:staf') {
                $namaMaster = mb_substr(HonorPengaturan::rapikan((string) $b['nama']), 0, 150);
                $kode = (int) ($this->db->query("SELECT MAX(CAST(kode_guru AS UNSIGNED)) AS m FROM guru WHERE kode_guru REGEXP '^[0-9]+$'")->getRow()->m ?? 0) + 1;
                $guruId = (int) $guruModel->insert([
                    'kode_guru' => (string) $kode, 'nama' => $namaMaster, 'max_beban' => 24, 'bukan_pengajar' => $d === 'baru:staf' ? 1 : 0,
                ]);
                if ($guruId <= 0) {
                    $peringatan[] = 'Baris "' . $b['nama'] . '": gagal menambah ke Master Guru (' . implode(' ', $guruModel->errors()) . ') — dilewati.';
                    $stat['dilewati']++;
                    continue;
                }
                $stat['guru_dibuat']++;
            } else {
                $stat['dilewati']++;
                continue;
            }
            if (isset($dipakai[$guruId])) {
                $peringatan[] = 'Baris "' . $b['nama'] . '": orang yang sama sudah diimpor dari baris lain — baris ini dilewati.';
                $stat['dilewati']++;
                continue;
            }
            $dipakai[$guruId] = true;

            $jabatan = $b['jabatan'] !== '' ? $b['jabatan'] : null;
            if (isset($ada[$guruId])) {
                $barisId = $ada[$guruId];
                $this->db->table('honor_baris')->where('id', $barisId)->update(['nama' => $namaMaster, 'jabatan' => $jabatan, 'urut' => $i + 1, 'updated_at' => date('Y-m-d H:i:s')]);
                $stat['diperbarui']++;
            } else {
                $barisId = $dokLib->sisipBaris($dokId, $guruId, $namaMaster, $jabatan, $i + 1);
                $ada[$guruId] = $barisId;
                $stat['baru']++;
            }
            foreach ($b['nilai'] as $kode => $n) {
                $k = $petaKol[$kode] ?? null;
                if ($k === null) {
                    continue;
                }
                $maks = $k['tipe'] === 'tetap' ? HonorDokumen::MAKS_NOMINAL : HonorDokumen::MAKS_JUMLAH;
                if ($n > $maks) {
                    $peringatan[] = 'Baris "' . $b['nama'] . '", ' . $k['nama'] . ': angka ' . number_format($n, 0, ',', '.') . ' melebihi batas — dianggap 0.';
                    $n = 0;
                }
                $dokLib->setNilaiLangsung($barisId, (int) $k['id'], (int) $n);
            }
        }

        // nama penanda tangan: hanya mengisi yang masih kosong
        $isi = [];
        foreach (['ketua' => 'ketua_nama', 'bendahara' => 'bendahara_nama', 'kepsek' => 'kepsek_nama'] as $kunci => $kolom) {
            if (! empty($payload['ttd'][$kunci]) && trim((string) ($dok[$kolom] ?? '')) === '') {
                $isi[$kolom] = $payload['ttd'][$kunci];
            }
        }
        if ($isi !== []) {
            $this->db->table('honor_dokumen')->where('id', $dokId)->update($isi + ['updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['ok' => false, 'pesan' => 'Gagal menyimpan hasil impor. Tidak ada yang berubah.'];
        }
        if ($stat['guru_dibuat'] > 0) {
            master_data_changed('guru');
        }
        if ($tanpa !== []) {
            $peringatan[] = 'Kolom di Excel tanpa komponen padanan di honor ini (tidak diimpor): ' . implode(', ', $tanpa) . '.';
        }

        $m = $dokLib->muat($dokId);
        (new AuditModel())->record('import', 'honor_dokumen', $dokId, 'Impor Excel honor ' . $dokLib->labelPublik($dokId) . ": {$stat['baru']} baru, {$stat['diperbarui']} diperbarui, {$stat['dilewati']} dilewati, {$stat['guru_dibuat']} guru ditambah ke Master");

        return [
            'ok' => true,
            'pesan' => "Impor selesai: {$stat['baru']} penerima baru, {$stat['diperbarui']} diperbarui, {$stat['dilewati']} dilewati"
                . ($stat['guru_dibuat'] > 0 ? ", {$stat['guru_dibuat']} orang ditambahkan ke Master Guru" : '') . '. Total honor Rp ' . number_format((int) $m['total'], 0, ',', '.') . '.',
            'ringkas' => $stat + ['total' => (int) $m['total'], 'penerima' => count($m['baris']), 'peringatan' => $peringatan],
        ];
    }
}
