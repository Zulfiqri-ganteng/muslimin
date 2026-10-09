<?php

namespace App\Libraries;

use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Impor SKBM dari Excel sekolah — lembar "SKBM 2026-2027" (Daftar Lampiran 3 SK Pembagian Tugas Mengajar) di berkas jadwal.
 *
 * ALUR AMAN (tak ada yang tersimpan sebelum Admin menyetujui):
 *   1. baca()      — memilih lembar (yang bernama SKBM), membaca blok kolom kelas + baris guru/mapel/JP.
 *   2. cocokkan()  — mencocokkan kolom kelas ke Master Kelas dan nama guru ke Master Guru; memilih blok kelas yang benar.
 *   3. terapkan()  — menulis SKBM satu tahun ajaran dalam satu transaksi, tercatat di Audit Log.
 *
 * Lembar SKBM bisa memuat BEBERAPA blok kolom kelas yang berdampingan (pada berkas sekolah: blok pertama bukan penugasan
 * SK, blok kedua — di sebelah JUMLAH JP / JUMLAH KOREKSI — adalah penugasan SK yang sebenarnya, 491 sel). Blok yang
 * kolom kelasnya paling banyak dikenali Master Kelas dipilih otomatis (seri → yang paling kanan); Admin bisa menggantinya
 * di pratinjau. Sel berisi angka > 0 = guru mengajar mapel itu di kelas itu, dengan JP sebanyak angka tersebut.
 * Berkas tidak disimpan setelah dibaca.
 */
final class SkbmImpor
{
    public const MAKS_BYTE = 8388608; // berkas jadwal sekolah ±2 MB; disarankan menyalin lembar SKBM saja ke berkas kecil
    public const MAKS_GURU = 300;
    public const MAKS_KELAS = 80;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    private static function judul(string $s): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }

    /** Lembar yang kemungkinan besar SKBM: namanya memuat "SKBM"; bila hanya satu lembar, lembar itu. */
    public static function pilihLembar(array $nama): ?string
    {
        foreach ($nama as $n) {
            if (stripos($n, 'skbm') !== false) {
                return $n;
            }
        }

        return count($nama) === 1 ? (string) $nama[0] : null;
    }

    private function teks(Worksheet $ws, int $c, int $r): string
    {
        $v = $ws->getCell([$c, $r])->getValue();

        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
    }

    // =================================================================
    // 1. Baca
    // =================================================================

    /**
     * @return array{lembar:string, tahun:?string, blok:list<array<string,mixed>>, guru:list<array<string,mixed>>, peringatan:list<string>}
     *
     * @throws \RuntimeException berisi pesan yang aman ditampilkan ke pengguna
     */
    public function baca(string $path, ?string $lembar = null): array
    {
        clearstatcache(true, $path);
        if (! is_file($path) || filesize($path) > self::MAKS_BYTE) {
            throw new \RuntimeException('Berkas terlalu besar (maksimal 8 MB) atau tidak terbaca. Salin lembar SKBM saja ke berkas Excel baru agar ringan.');
        }
        try {
            $reader = IOFactory::createReader('Xlsx');
            if (! $reader->canRead($path)) {
                throw new \RuntimeException('Berkas bukan Excel (.xlsx) yang sah.');
            }
            $daftar = $reader->listWorksheetNames($path);
            $pilih  = $lembar !== null && in_array($lembar, $daftar, true) ? $lembar : self::pilihLembar($daftar);
            if ($pilih === null) {
                throw new \RuntimeException('Lembar SKBM tidak ditemukan. Lembar yang ada: ' . implode(', ', array_slice($daftar, 0, 8)) . (count($daftar) > 8 ? ', …' : '') . '. Ubah nama lembar SKBM agar memuat kata "SKBM", atau salin ke berkas baru.');
            }
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$pilih]);
            $x  = $reader->load($path);
            $ws = $x->getSheetByName($pilih);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new \RuntimeException('Berkas Excel tidak bisa dibuka (rusak, dilindungi sandi, atau terlalu berat).');
        }

        $hasil = $this->bacaLembar($ws);
        $hasil['lembar'] = $pilih;
        $hasil['tahun']  = $this->terkaTahun($pilih, $ws);

        return $hasil;
    }

    private function terkaTahun(string $lembar, Worksheet $ws): ?string
    {
        $cari = [$lembar];
        for ($r = 1; $r <= 6; $r++) {
            $cari[] = $this->teks($ws, 1, $r);
        }
        foreach ($cari as $t) {
            if (preg_match('/(\d{4})\s*[\/\-–]\s*(\d{4})/u', $t, $m) && (int) $m[2] === (int) $m[1] + 1) {
                return $m[1] . '/' . $m[2];
            }
        }

        return null;
    }

    private function bacaLembar(Worksheet $ws): array
    {
        $maxR = min($ws->getHighestDataRow(), 600);
        $maxC = min(Coordinate::columnIndexFromString($ws->getHighestDataColumn()), 160);

        $hr = $kolNama = $kolMapel = $kolKode = $kolNo = 0;
        for ($r = 1; $r <= min(30, $maxR) && $hr === 0; $r++) {
            for ($c = 1; $c <= min($maxC, 12); $c++) {
                if (self::judul($this->teks($ws, $c, $r)) === 'NAMA GURU') {
                    $hr = $r;
                    $kolNama = $c;
                    break;
                }
            }
        }
        if ($hr === 0) {
            throw new \RuntimeException('Judul tabel tidak ditemukan. Harus ada baris judul dengan tulisan "NAMA GURU" dan "MATA PELAJARAN", lalu tingkat dan nama kelas di dua baris di bawahnya.');
        }
        for ($c = 1; $c <= min($maxC, 12); $c++) {
            $t = self::judul($this->teks($ws, $c, $hr));
            if ($kolMapel === 0 && ($t === 'MATA PELAJARAN' || $t === 'MAPEL')) {
                $kolMapel = $c;
            }
            if ($kolKode === 0 && $t === 'KODE GURU') {
                $kolKode = $c;
            }
            if ($kolNo === 0 && in_array($t, ['NO', 'NO.', 'NOMOR'], true)) {
                $kolNo = $c;
            }
        }
        if ($kolMapel === 0) {
            throw new \RuntimeException('Kolom "Mata Pelajaran" tidak ditemukan di baris judul.');
        }

        // ---- blok kolom kelas: kelompok di baris judul, tingkat di baris berikutnya, nama kelas di baris ketiga
        $blok   = [];
        $cur    = null;
        $grup   = '';
        $tingkat = '';
        $nKelas = 0;
        for ($c = $kolMapel + 1; $c <= $maxC; $c++) {
            $g = $this->teks($ws, $c, $hr);
            $t = $this->teks($ws, $c, $hr + 1);
            $l = $this->teks($ws, $c, $hr + 2);
            $jumlah = preg_match('/JUMLAH|TOTAL|KETERANGAN/iu', $g . ' ' . $t . ' ' . $l) === 1;
            if ($g !== '' && ! $jumlah) {
                $grup = $g;
            }
            if ($t !== '' && ! $jumlah && preg_match('/\b(XII|XI|X)\b/iu', $t, $m)) {
                $tingkat = strtoupper($m[1]);
            }
            if ($l === '' || $jumlah) {
                $cur = null; // pemisah antar blok
                continue;
            }
            if ($cur === null) {
                $blok[] = ['indeks' => count($blok), 'dari' => Coordinate::stringFromColumnIndex($c), 'sampai' => Coordinate::stringFromColumnIndex($c), 'kelas' => []];
                $cur = count($blok) - 1;
            }
            $blok[$cur]['kelas'][] = ['c' => $c, 'grup' => $grup, 'tingkat' => $tingkat, 'label' => $l, 'kunci' => HonorKoreksi::kunciKelas($tingkat, $l)];
            $blok[$cur]['sampai']  = Coordinate::stringFromColumnIndex($c);
            if (++$nKelas > self::MAKS_KELAS * 3) {
                throw new \RuntimeException('Terlalu banyak kolom kelas.');
            }
        }
        if ($blok === []) {
            throw new \RuntimeException('Kolom kelas tidak ditemukan. Nama kelas (mis. TKJ 1) harus ada tepat dua baris di bawah baris judul.');
        }

        // ---- baris guru
        $guru       = [];
        $idxGuru    = null;
        $kosong     = 0;
        $peringatan = [];
        for ($r = $hr + 3; $r <= $maxR; $r++) {
            $no    = $kolNo > 0 ? $this->teks($ws, $kolNo, $r) : '';
            $nama  = $this->teks($ws, $kolNama, $r);
            $kode  = $kolKode > 0 ? $this->teks($ws, $kolKode, $r) : '';
            $mapel = $this->teks($ws, $kolMapel, $r);
            if ($no !== '' && preg_match('/^TOTAL\b/iu', $no) === 1) {
                break; // "TOTAL JP / KELAS" = baris hitungan di bawah tabel: akhir daftar guru (di bawahnya ada tanda tangan, bukan guru)
            }
            if ($no === '' && $nama === '' && $kode === '' && $mapel === '') {
                if ($guru !== [] && ++$kosong >= 2) {
                    break;
                }
                continue;
            }
            $kosong = 0;
            if ($nama !== '' && ($no === '' || ctype_digit($no) || str_starts_with($no, '='))) {
                if (count($guru) >= self::MAKS_GURU) {
                    throw new \RuntimeException('Terlalu banyak guru (maksimal ' . self::MAKS_GURU . ').');
                }
                $guru[]  = ['no' => ctype_digit($no) ? (int) $no : null, 'nama' => mb_substr($nama, 0, 150), 'baris' => [], 'jp' => 0, 'kelas' => 0];
                $idxGuru = count($guru) - 1;
            }
            if ($idxGuru === null || ($kode === '' && $mapel === '' && $nama === '')) {
                continue;
            }
            $sel = [];
            foreach ($blok as $b) {
                foreach ($b['kelas'] as $k) {
                    $v = $ws->getCell([(int) $k['c'], $r])->getValue();
                    if (is_numeric($v) && (float) $v > 0) {
                        $jp = (int) round((float) $v);
                        if ($jp > Skbm::MAKS_JP) {
                            $peringatan[] = 'Baris ' . $r . ' (' . $guru[$idxGuru]['nama'] . '), kelas ' . $k['label'] . ': angka ' . $jp . ' melebihi batas JP — dilewati.';
                            continue;
                        }
                        $sel[$b['indeks']][(int) $k['c']] = $jp;
                    }
                }
            }
            $guru[$idxGuru]['baris'][] = ['r' => $r, 'kode' => mb_substr($kode, 0, 16), 'mapel' => $mapel === '' ? '-' : mb_substr($mapel, 0, Skbm::MAKS_NAMA), 'sel' => $sel];
        }
        if ($guru === []) {
            throw new \RuntimeException('Tidak ada baris guru di bawah judul tabel.');
        }

        return ['lembar' => '', 'tahun' => null, 'blok' => $blok, 'guru' => $guru, 'peringatan' => array_slice($peringatan, 0, 40)];
    }

    // =================================================================
    // 2. Cocokkan
    // =================================================================

    /**
     * Tambahkan: blok[].kelas[].kelas_id (null = tak dikenal), blok[].dikenal, blok_dipilih, dan per guru: guru_id / status
     * (cocok|mirip|ganda|tidak) / kandidat; juga jp & kelas per guru untuk blok yang dipilih.
     *
     * @param array<string,mixed> $payload hasil baca()
     */
    public function cocokkan(array $payload, ?int $blokPilihan = null): array
    {
        $peta = [];
        foreach ((new HonorKoreksi($this->db))->daftarKelas() as $k) {
            $u = HonorKoreksi::uraiKelas($k['nama']);
            $peta[HonorKoreksi::kunciKelas($k['tingkat'] !== '' ? $k['tingkat'] : $u['tingkat'], $u['label'])] = $k['id'];
        }
        $terbaik = 0;
        $skor    = -1;
        foreach ($payload['blok'] as $i => &$b) {
            $b['dikenal'] = 0;
            foreach ($b['kelas'] as &$k) {
                $k['kelas_id'] = $peta[$k['kunci']] ?? null;
                $b['dikenal'] += $k['kelas_id'] !== null ? 1 : 0;
            }
            unset($k);
            if ($b['dikenal'] >= $skor) { // seri → blok yang lebih kanan
                $skor    = $b['dikenal'];
                $terbaik = $i;
            }
        }
        unset($b);
        $pilih = $blokPilihan !== null && isset($payload['blok'][$blokPilihan]) ? $blokPilihan : $terbaik;
        $payload['blok_dipilih'] = $pilih;

        $master = $this->db->table('guru')->select('id, nama')->where('deleted_at', null)->where('induk_id', null)->get()->getResultArray();
        $pencocok = new HonorKoreksiImpor($this->db);
        $pakai = [];
        foreach ($payload['guru'] as &$g) {
            [$g['guru_id'], $g['status'], $g['kandidat']] = $pencocok->cariBaris((string) $g['nama'], $master, $pakai);
            if ($g['guru_id'] !== null) {
                $pakai[(int) $g['guru_id']] = true;
            }
            $g['jp'] = 0;
            $g['kelas'] = 0;
            foreach ($g['baris'] as $br) {
                foreach ($br['sel'][$pilih] ?? [] as $jp) {
                    $g['jp'] += $jp;
                    $g['kelas']++;
                }
            }
        }
        unset($g);

        return $payload;
    }

    // =================================================================
    // 3. Terapkan
    // =================================================================

    /**
     * @param array<string,mixed>      $payload   hasil baca()+cocokkan() (dihitung ulang server, bukan dari kiriman form)
     * @param array<int|string,string> $keputusan indeks blok guru → "guru:ID" | "lewati"
     * @param bool                     $ganti     true = SKBM tahun ajaran itu dibuang dulu
     *
     * @return array{ok:bool, pesan:string, ringkas?:array<string,mixed>}
     */
    public function terapkan(string $tahun, array $payload, array $keputusan, bool $ganti = true): array
    {
        if (! Skbm::tahunValid($tahun)) {
            return ['ok' => false, 'pesan' => 'Tahun ajaran tidak sah (contoh: 2026/2027).'];
        }
        $blok = (int) ($payload['blok_dipilih'] ?? 0);
        if (! isset($payload['blok'][$blok])) {
            return ['ok' => false, 'pesan' => 'Blok kelas tidak ditemukan.'];
        }
        $peringatan = [];
        $kolomKelas = [];
        foreach ($payload['blok'][$blok]['kelas'] as $k) {
            if ($k['kelas_id'] === null) {
                $peringatan[] = 'Kolom kelas "' . trim($k['grup'] . ' ' . $k['tingkat'] . ' ' . $k['label']) . '" tidak dikenal di Master Kelas — angkanya dilewati.';
                continue;
            }
            $kolomKelas[(int) $k['c']] = (int) $k['kelas_id'];
        }
        if ($kolomKelas === []) {
            return ['ok' => false, 'pesan' => 'Tidak satu pun kolom kelas pada blok yang dipilih cocok dengan Master Kelas.'];
        }

        $pilih   = [];
        $dipakai = [];
        foreach ($payload['guru'] as $i => $g) {
            if (! preg_match('/^guru:(\d+)$/', (string) ($keputusan[$i] ?? 'lewati'), $m)) {
                continue;
            }
            $gid = (int) $m[1];
            $ada = (int) $this->db->table('guru')->where('id', $gid)->where('deleted_at', null)->where('induk_id', null)->countAllResults() === 1;
            if (! $ada) {
                $peringatan[] = 'Guru "' . $g['nama'] . '": pilihan tidak ditemukan di Master Guru — dilewati.';
                continue;
            }
            if (isset($dipakai[$gid])) {
                $peringatan[] = 'Guru "' . $g['nama'] . '": orang yang sama sudah dipakai blok guru lain — dilewati.';
                continue;
            }
            $dipakai[$gid] = true;
            $pilih[$i]     = $gid;
        }
        if ($pilih === []) {
            return ['ok' => false, 'pesan' => 'Belum ada guru yang dipilih untuk diimpor.'];
        }

        $idMapel = [];
        foreach ($this->db->table('mata_pelajaran')->select('id, nama_mapel')->where('deleted_at', null)->orderBy('id', 'DESC')->get()->getResultArray() as $r) {
            $idMapel[mb_strtolower(trim((string) $r['nama_mapel']))] = (int) $r['id'];
        }
        $now = date('Y-m-d H:i:s');
        $st  = ['guru' => 0, 'mapel' => 0, 'sel' => 0, 'dilewati' => 0, 'jp' => 0];
        $this->db->transStart();
        if ($ganti) {
            $this->db->table('skbm_mapel')->where('tahun_ajaran', $tahun)->delete(); // sel ikut terhapus
        }
        $sudah = [];
        foreach ($this->db->table('skbm_mapel')->select('guru_id')->distinct()->where('tahun_ajaran', $tahun)->get()->getResultArray() as $r) {
            $sudah[(int) $r['guru_id']] = true;
        }
        $urut = (int) ($this->db->table('skbm_mapel')->selectMax('urut')->where('tahun_ajaran', $tahun)->get()->getRow()->urut ?? 0);
        foreach ($pilih as $i => $gid) {
            if (isset($sudah[$gid])) {
                $st['dilewati']++;
                $peringatan[] = 'Guru "' . $payload['guru'][$i]['nama'] . '" sudah ada di SKBM ' . $tahun . ' — dilewati (pilih "ganti" bila mau menimpa).';
                continue;
            }
            foreach ($payload['guru'][$i]['baris'] as $br) {
                $this->db->table('skbm_mapel')->insert([
                    'tahun_ajaran' => $tahun, 'guru_id' => $gid, 'mapel_nama' => $br['mapel'], 'mapel_id' => $idMapel[mb_strtolower(trim($br['mapel']))] ?? null,
                    'kode' => $br['kode'] !== '' ? $br['kode'] : null, 'urut' => ++$urut, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $rid = (int) $this->db->insertID();
                foreach ($br['sel'][$blok] ?? [] as $c => $jp) {
                    if (! isset($kolomKelas[$c])) {
                        continue;
                    }
                    $this->db->table('skbm_sel')->insert(['skbm_mapel_id' => $rid, 'kelas_id' => $kolomKelas[$c], 'jp' => $jp, 'created_at' => $now]);
                    $st['sel']++;
                    $st['jp'] += $jp;
                }
                $st['mapel']++;
            }
            $st['guru']++;
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['ok' => false, 'pesan' => 'Gagal menyimpan hasil impor. Tidak ada yang berubah.'];
        }
        (new AuditModel())->record('import', 'skbm_mapel', null, "Impor Excel SKBM $tahun: {$st['guru']} guru, {$st['mapel']} baris mapel, {$st['sel']} kelas, {$st['jp']} JP, {$st['dilewati']} dilewati");

        return [
            'ok'      => true,
            'pesan'   => "Impor SKBM $tahun selesai: {$st['guru']} guru, {$st['mapel']} baris mapel, {$st['sel']} kelas, total " . $st['jp'] . ' JP.',
            'ringkas' => $st + ['peringatan' => $peringatan],
        ];
    }
}
