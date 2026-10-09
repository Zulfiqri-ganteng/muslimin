<?php

namespace App\Libraries;

use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Impor ceklis KOREKSI dari Excel sekolah ("KOREKSI NILAI.xlsx": CEKLIS PEMBAGIAN LEMBAR JAWABAN KE GURU PER ROMBEL).
 *
 * ALUR AMAN (tak ada yang tersimpan sebelum Admin menyetujui):
 *   1. baca()      — membaca berkas: kolom kelas (kelompok + label), blok guru (nama, kode, mapel, kelas yang diisi angka).
 *   2. cocokkan()  — mencocokkan kolom kelas ke Master Kelas dan nama guru ke penerima honor (cocok / mirip / ganda / tidak).
 *   3. terapkan()  — menulis ceklis honor dalam satu transaksi, tercatat di Audit Log.
 *
 * Yang dibaca: sel berisi angka > 0 pada kolom kelas = guru itu mengoreksi kelas tersebut sebanyak angka itu. Sel kosong /
 * abu-abu = tidak mengampu. Kolom rumus (Keterangan/total) dan baris hitungan di bawah diabaikan — total dihitung ulang sistem.
 * Jumlah peserta tiap kelas diambil dari angka yang paling sering muncul di kolom kelas itu; sel yang beda disimpan
 * sebagai angka khusus sehingga hasil ekspor persis sama dengan berkas yang diimpor. Berkas tidak disimpan setelah dibaca.
 */
final class HonorKoreksiImpor
{
    public const MAKS_BYTE = 2097152;
    public const MAKS_GURU = 300;
    public const MAKS_BARIS = 600;
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

    private function teks(Worksheet $ws, int $c, int $r): string
    {
        $v = $ws->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue();

        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
    }

    // =================================================================
    // 1. Baca berkas
    // =================================================================

    /**
     * @return array{lembar:string, kelas:list<array<string,mixed>>, guru:list<array<string,mixed>>, peringatan:list<string>}
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
        } catch (\Throwable) {
            throw new \RuntimeException('Berkas Excel tidak bisa dibuka (rusak atau dilindungi sandi).');
        }
        $galat = null;
        foreach ($x->getWorksheetIterator() as $ws) {
            try {
                $hasil = $this->bacaLembar($ws);
            } catch (\RuntimeException $e) {
                $galat = $e;
                continue;
            }
            if ($hasil !== null) {
                return $hasil;
            }
        }
        if ($galat !== null) {
            throw $galat;
        }
        throw new \RuntimeException('Tabel ceklis koreksi tidak ditemukan. Pastikan ada baris judul dengan tulisan "Nama Guru" dan "Mata Pelajaran", lalu baris nama kelas di bawahnya.');
    }

    private function bacaLembar(Worksheet $ws): ?array
    {
        $maxR = min($ws->getHighestDataRow(), 400);
        $maxC = min(Coordinate::columnIndexFromString($ws->getHighestDataColumn()), 120);

        // baris judul: ada sel "NAMA GURU"; di baris yang sama ada "MATA PELAJARAN"
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
            return null;
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

        // kolom kelas: di kanan kolom mapel sampai "Keterangan"; kelompok di baris judul, label kelas di baris bawahnya
        $kelas      = [];
        $grup       = '';
        $peringatan = [];
        for ($c = $kolMapel + 1; $c <= $maxC; $c++) {
            $g = $this->teks($ws, $c, $hr);
            if (self::judul($g) === 'KETERANGAN') {
                break;
            }
            if ($g !== '') {
                $grup = $g;
            }
            $label = $this->teks($ws, $c, $hr + 1);
            if ($label === '') {
                continue;
            }
            $tingkat = preg_match('/\b(XII|XI|X)\b/iu', $grup, $m) ? strtoupper($m[1]) : '';
            if ($tingkat === '' && preg_match('/^(XII|XI|X)\b\s*(.+)$/iu', $label, $m2)) {
                $tingkat = strtoupper($m2[1]);
                $label   = trim($m2[2]);
            }
            $kelas[] = ['c' => $c, 'grup' => $grup, 'label' => $label, 'tingkat' => $tingkat, 'kunci' => HonorKoreksi::kunciKelas($tingkat, $label)];
            if (count($kelas) > self::MAKS_KELAS) {
                throw new \RuntimeException('Terlalu banyak kolom kelas (maksimal ' . self::MAKS_KELAS . ').');
            }
        }
        if ($kelas === []) {
            throw new \RuntimeException('Kolom kelas tidak ditemukan. Nama kelas (mis. TKJ.1) harus ada di baris tepat di bawah baris judul.');
        }

        // blok guru
        $guru  = [];
        $cur   = null;
        $nBaris = 0;
        for ($r = $hr + 2; $r <= $maxR; $r++) {
            $no    = $kolNo > 0 ? $this->teks($ws, $kolNo, $r) : '';
            $nama  = $this->teks($ws, $kolNama, $r);
            $mapel = $this->teks($ws, $kolMapel, $r);
            $kode  = $kolKode > 0 ? $this->teks($ws, $kolKode, $r) : '';
            if ($no === '' && $nama === '' && $mapel === '' && $kode === '') {
                break; // baris hitungan/total di bawah tabel (atau tabel berakhir)
            }
            if ($nama !== '') {
                if (count($guru) >= self::MAKS_GURU) {
                    throw new \RuntimeException('Terlalu banyak guru (maksimal ' . self::MAKS_GURU . ').');
                }
                $guru[] = ['no' => ctype_digit($no) ? (int) $no : null, 'nama' => mb_substr($nama, 0, 150), 'baris' => [], 'total' => 0, 'baris_excel' => $r];
                $cur    = count($guru) - 1;
            }
            if ($cur === null) {
                continue;
            }
            if (++$nBaris > self::MAKS_BARIS) {
                throw new \RuntimeException('Terlalu banyak baris mapel (maksimal ' . self::MAKS_BARIS . ').');
            }
            $sel = [];
            foreach ($kelas as $k) {
                $v = $ws->getCell(Coordinate::stringFromColumnIndex((int) $k['c']) . $r)->getValue();
                if (is_numeric($v) && (float) $v > 0 && floor((float) $v) === (float) $v) {
                    $n = (int) $v;
                    if ($n > HonorKoreksi::MAKS_PESERTA) {
                        $peringatan[] = 'Baris ' . $r . ' (' . $guru[$cur]['nama'] . '), kelas ' . $k['label'] . ': angka ' . $n . ' melebihi batas ' . HonorKoreksi::MAKS_PESERTA . ' — dilewati.';
                        continue;
                    }
                    $sel[(int) $k['c']] = $n;
                    $guru[$cur]['total'] += $n;
                } elseif ($v !== null && $v !== '' && ! is_numeric($v) && is_scalar($v) && ! str_starts_with((string) $v, '=')) {
                    $peringatan[] = 'Baris ' . $r . ' (' . $guru[$cur]['nama'] . '), kelas ' . $k['label'] . ': isi "' . mb_substr((string) $v, 0, 20) . '" bukan angka — dianggap kosong.';
                }
            }
            $guru[$cur]['baris'][] = ['r' => $r, 'kode' => mb_substr($kode, 0, 20), 'mapel' => $mapel === '' ? '-' : mb_substr($mapel, 0, HonorKoreksi::MAKS_NAMA_MAPEL), 'sel' => $sel];
        }
        if ($guru === []) {
            throw new \RuntimeException('Tidak ada baris guru di bawah judul tabel.');
        }

        return ['lembar' => $ws->getTitle(), 'kelas' => $kelas, 'guru' => $guru, 'peringatan' => array_slice($peringatan, 0, 40)];
    }

    // =================================================================
    // 2. Cocokkan
    // =================================================================

    /**
     * Pencocokan kolom kelas → Master Kelas, dan nama guru → penerima honor. Mengembalikan payload + kunci tambahan:
     * kelas[i]['kelas_id'] (null = tak dikenal) dan guru[i]['baris_id'] / ['status'] (cocok|mirip|ganda|tidak) / ['kandidat'].
     *
     * @param array<string,mixed> $payload hasil baca()
     */
    public function cocokkan(array $payload, int $dokumenId): array
    {
        $peta = [];
        foreach ((new HonorKoreksi($this->db))->daftarKelas() as $k) {
            $u = HonorKoreksi::uraiKelas($k['nama']);
            $peta[HonorKoreksi::kunciKelas($k['tingkat'] !== '' ? $k['tingkat'] : $u['tingkat'], $u['label'])] = $k['id'];
        }
        foreach ($payload['kelas'] as &$k) {
            $k['kelas_id'] = $peta[$k['kunci']] ?? null;
        }
        unset($k);

        $doc = $this->db->table('honor_baris')->select('id, nama')->where('dokumen_id', $dokumenId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $pakai = [];
        foreach ($payload['guru'] as &$g) {
            [$g['baris_id'], $g['status'], $g['kandidat']] = $this->cariBaris((string) $g['nama'], $doc, $pakai);
            if ($g['baris_id'] !== null) {
                $pakai[(int) $g['baris_id']] = true;
            }
        }
        unset($g);

        return $payload;
    }

    /**
     * @param list<array<string,mixed>> $doc    baris honor (id, nama)
     * @param array<int,bool>           $pakai  baris yang sudah terpakai blok guru lain
     *
     * @return array{0:?int, 1:string, 2:list<array{id:int,nama:string}>}
     */
    private function cariBaris(string $nama, array $doc, array $pakai): array
    {
        $rapi  = static fn (string $s): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
        $padat = static fn (string $s): string => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s));
        $bebas = array_values(array_filter($doc, static fn (array $d): bool => ! isset($pakai[(int) $d['id']])));
        $kenal = static fn (array $d): array => ['id' => (int) $d['id'], 'nama' => (string) $d['nama']];

        foreach ([[$rapi, 'cocok'], [$padat, 'cocok']] as [$fn, $status]) {
            $k = $fn($nama);
            if ($k === '') {
                continue;
            }
            $hit = array_values(array_filter($bebas, static fn (array $d): bool => $fn((string) $d['nama']) === $k));
            if ($hit !== []) {
                return [(int) $hit[0]['id'], $status, array_map($kenal, $hit)];
            }
        }
        $nn = HonorImpor::normalNama($nama);
        if ($nn !== '') {
            $hit = array_values(array_filter($bebas, static fn (array $d): bool => HonorImpor::normalNama((string) $d['nama']) === $nn));
            if (count($hit) === 1) {
                return [(int) $hit[0]['id'], 'cocok', [$kenal($hit[0])]];
            }
            if (count($hit) > 1) {
                return [null, 'ganda', array_map($kenal, $hit)];
            }
            // mirip: semua kata dari nama yang lebih pendek ada di nama yang lebih panjang (≥ 2 kata) — mis. "Budi Santoso" ~ "Budi Hari Santoso, S.Pd"
            $tb    = explode(' ', $nn);
            $mirip = [];
            foreach ($bebas as $d) {
                $td    = explode(' ', HonorImpor::normalNama((string) $d['nama']));
                $kecil = count($tb) <= count($td) ? $tb : $td;
                $besar = count($tb) <= count($td) ? $td : $tb;
                if (count($kecil) >= 2 && array_diff($kecil, $besar) === []) {
                    $mirip[] = $d;
                }
            }
            if ($mirip !== []) {
                return [count($mirip) === 1 ? (int) $mirip[0]['id'] : null, 'mirip', array_map($kenal, array_slice($mirip, 0, 5))];
            }
        }

        return [null, 'tidak', []];
    }

    // =================================================================
    // 3. Terapkan
    // =================================================================

    /**
     * @param array<string,mixed>      $payload   hasil baca()+cocokkan() (dihitung ulang server, bukan dari kiriman form)
     * @param array<int|string,string> $keputusan indeks blok guru → "baris:ID" | "lewati"
     * @param bool                     $ganti     true = seluruh ceklis yang sekarang dibuang dulu
     *
     * @return array{ok:bool, pesan:string, ringkas?:array<string,mixed>}
     */
    public function terapkan(int $dokumenId, array $payload, array $keputusan, bool $ganti = true): array
    {
        $dok = $this->db->table('honor_dokumen')->where('id', $dokumenId)->get()->getRowArray();
        if ($dok === null) {
            return ['ok' => false, 'pesan' => 'Honor tidak ditemukan.'];
        }
        if ((string) $dok['status'] === 'dikunci') {
            return ['ok' => false, 'pesan' => 'Honor ini sudah DIKUNCI dan tidak bisa diubah.'];
        }
        $barisSah = [];
        foreach ($this->db->table('honor_baris')->select('id')->where('dokumen_id', $dokumenId)->get()->getResultArray() as $b) {
            $barisSah[(int) $b['id']] = true;
        }

        $peringatan = [];
        // kolom kelas → kelas_id (yang tak dikenal dilewati)
        $kolomKelas = [];
        foreach ($payload['kelas'] as $k) {
            if ($k['kelas_id'] === null) {
                $peringatan[] = 'Kolom kelas "' . trim($k['grup'] . ' ' . $k['label']) . '" tidak dikenal di Master Kelas — angkanya dilewati.';
                continue;
            }
            $kolomKelas[(int) $k['c']] = (int) $k['kelas_id'];
        }
        if ($kolomKelas === []) {
            return ['ok' => false, 'pesan' => 'Tidak satu pun kolom kelas di Excel cocok dengan Master Kelas, jadi tidak ada yang bisa diimpor.'];
        }

        // keputusan guru
        $pilih = []; // indeks blok → baris_id
        $dipakai = [];
        foreach ($payload['guru'] as $i => $g) {
            $d = (string) ($keputusan[$i] ?? 'lewati');
            if (! preg_match('/^baris:(\d+)$/', $d, $m)) {
                continue;
            }
            $bid = (int) $m[1];
            if (! isset($barisSah[$bid])) {
                $peringatan[] = 'Guru "' . $g['nama'] . '": penerima pilihan tidak ada di honor ini — dilewati.';
                continue;
            }
            if (isset($dipakai[$bid])) {
                $peringatan[] = 'Guru "' . $g['nama'] . '": penerima yang sama sudah dipakai blok guru lain — dilewati.';
                continue;
            }
            $dipakai[$bid] = true;
            $pilih[$i]     = $bid;
        }
        if ($pilih === []) {
            return ['ok' => false, 'pesan' => 'Belum ada guru yang dipilih untuk diimpor.'];
        }

        // jumlah peserta kelas = angka terbanyak di kolomnya (hanya dari guru yang diimpor)
        $hitung = [];
        foreach ($pilih as $i => $bid) {
            foreach ($payload['guru'][$i]['baris'] as $br) {
                foreach ($br['sel'] as $c => $n) {
                    if (isset($kolomKelas[$c])) {
                        $hitung[$kolomKelas[$c]][$n] = ($hitung[$kolomKelas[$c]][$n] ?? 0) + 1;
                    }
                }
            }
        }
        $peserta = [];
        foreach ($hitung as $kid => $per) {
            arsort($per);
            $peserta[$kid] = (int) array_key_first($per);
        }
        $siswa = [];
        foreach ((new HonorKoreksi($this->db))->daftarKelas() as $k) {
            $siswa[$k['id']] = $k['siswa'];
        }

        $now = date('Y-m-d H:i:s');
        $st  = ['guru' => 0, 'mapel' => 0, 'sel' => 0, 'khusus' => 0, 'dilewati' => 0];
        $this->db->transStart();
        if ($ganti) {
            $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->delete(); // sel ikut terhapus
        }
        $sudah = [];
        foreach ($this->db->table('honor_koreksi_mapel')->select('baris_id')->where('dokumen_id', $dokumenId)->distinct()->get()->getResultArray() as $r) {
            $sudah[(int) $r['baris_id']] = true;
        }
        foreach ($peserta as $kid => $n) {
            $ada = $this->db->table('honor_koreksi_kelas')->where('dokumen_id', $dokumenId)->where('kelas_id', $kid)->get()->getRowArray();
            $isi = ['peserta' => $n, 'manual' => $n !== ($siswa[$kid] ?? 0) ? 1 : 0, 'updated_at' => $now];
            if ($ada === null) {
                $this->db->table('honor_koreksi_kelas')->insert($isi + ['dokumen_id' => $dokumenId, 'kelas_id' => $kid]);
            } else {
                $this->db->table('honor_koreksi_kelas')->where('id', $ada['id'])->update($isi);
            }
        }
        foreach ($pilih as $i => $bid) {
            if (isset($sudah[$bid])) {
                $st['dilewati']++;
                $peringatan[] = 'Guru "' . $payload['guru'][$i]['nama'] . '" sudah punya isi di ceklis — dilewati (pilih "ganti seluruh ceklis" bila mau menimpa).';
                continue;
            }
            $urut = 0;
            foreach ($payload['guru'][$i]['baris'] as $br) {
                $mid = $this->db->table('mata_pelajaran')->select('id')->where('nama_mapel', $br['mapel'])->where('deleted_at', null)->get()->getRowArray();
                $this->db->table('honor_koreksi_mapel')->insert([
                    'dokumen_id' => $dokumenId, 'baris_id' => $bid, 'mapel_nama' => $br['mapel'], 'mapel_id' => $mid['id'] ?? null, 'urut' => ++$urut, 'created_at' => $now,
                ]);
                $rid = (int) $this->db->insertID();
                foreach ($br['sel'] as $c => $n) {
                    if (! isset($kolomKelas[$c])) {
                        continue;
                    }
                    $kid    = $kolomKelas[$c];
                    $khusus = $n === ($peserta[$kid] ?? null) ? null : $n;
                    $this->db->table('honor_koreksi_sel')->insert(['mapel_row_id' => $rid, 'kelas_id' => $kid, 'jumlah' => $khusus, 'created_at' => $now]);
                    $st['sel']++;
                    $st['khusus'] += $khusus !== null ? 1 : 0;
                }
                $st['mapel']++;
            }
            $st['guru']++;
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['ok' => false, 'pesan' => 'Gagal menyimpan hasil impor. Tidak ada yang berubah.'];
        }

        $kor = new HonorKoreksi($this->db);
        $r   = $kor->ringkas($dokumenId);
        (new AuditModel())->record('import', 'honor_koreksi_mapel', $dokumenId, 'Impor Excel ceklis koreksi honor ' . (new HonorDokumen($this->db))->labelPublik($dokumenId)
            . ": {$st['guru']} guru, {$st['mapel']} baris mapel, {$st['sel']} sel ({$st['khusus']} angka khusus), " . count($peserta) . ' kelas diisi pesertanya, ' . $st['dilewati'] . ' dilewati');

        return [
            'ok'      => true,
            'pesan'   => "Impor selesai: {$st['guru']} guru, {$st['mapel']} baris mapel, {$st['sel']} kelas. Total lembar " . number_format($r['total'], 0, ',', '.') . '.',
            'ringkas' => $st + ['total' => $r['total'], 'peserta_kelas' => count($peserta), 'peringatan' => $peringatan],
        ];
    }
}
