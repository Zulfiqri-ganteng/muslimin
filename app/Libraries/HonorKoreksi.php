<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\GuruModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Ceklis KOREKSI honor ("KOREKSI NILAI"): pembagian lembar jawaban ke guru per rombel.
 *
 * DATA (per honor / per periode ujian):
 *   - PESERTA per kelas (honor_koreksi_kelas): jumlah siswa yang ikut ujian di kelas itu. Bawaan = siswa aktif; diketik
 *     Admin bila beda. Satu angka per kelas dipakai di semua baris mapel kelas itu (seperti Excel sekolah).
 *   - BARIS MAPEL (honor_koreksi_mapel): satu guru penerima honor × satu mapel. "-" = guru terdaftar tanpa mapel.
 *   - SEL (honor_koreksi_sel): kelas yang dikoreksi pada baris mapel itu; jumlah NULL = ikut peserta kelas, terisi = angka khusus.
 *
 * RUMUS: lembar guru = Σ jumlah sel semua baris mapelnya = angka "Koreksi" di honor (HonorHitung memakai ceklis ini bila
 * honor punya ceklis; kalau tidak, hitungan lama: siswa aktif × kelas yang diampu). Rupiah tetap dihitung HonorDokumen.
 *
 * Sumber isi ceklis: data pengampu (isiDariPengampu), Excel "KOREKSI NILAI" sekolah (HonorKoreksiImpor), atau salinan dari
 * honor lain (salinDari); semuanya bisa dikoreksi per sel. Honor DIKUNCI tidak bisa diubah. Perubahan dicatat di Audit Log.
 * Rancangan: docs/DESAIN-HONOR.md bagian 11.
 */
final class HonorKoreksi
{
    public const MAKS_PESERTA = 999;
    public const MAKS_MAPEL_PER_GURU = 40;
    public const MAKS_BARIS_MAPEL = 600;
    public const MAKS_NAMA_MAPEL = 150;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // =================================================================
    // Pembantu murni
    // =================================================================

    /**
     * Pecah nama kelas jadi bagian-bagiannya. "XII TKJ 3" → tingkat XII, jurusan TKJ, nomor 3, label "TKJ.3"; "X AKL" → label "AKL".
     *
     * @return array{tingkat:string, jurusan:string, nomor:int, label:string}
     */
    public static function uraiKelas(string $nama): array
    {
        $n = trim((string) preg_replace('/\s+/u', ' ', $nama));
        $tingkat = '';
        $sisa    = $n;
        if (preg_match('/^(XII|XI|X)\s+(.+)$/iu', $n, $m)) {
            $tingkat = strtoupper($m[1]);
            $sisa    = trim($m[2]);
        }
        if (preg_match('/^(.*?)[\s.]*(\d+)$/u', $sisa, $m2) && trim($m2[1]) !== '') {
            $jurusan = trim($m2[1]);
            $nomor   = (int) $m2[2];

            return ['tingkat' => $tingkat, 'jurusan' => $jurusan, 'nomor' => $nomor, 'label' => $jurusan . '.' . $nomor];
        }

        return ['tingkat' => $tingkat, 'jurusan' => $sisa, 'nomor' => 0, 'label' => $sisa];
    }

    /** Kunci pencocokan kelas lintas sumber: tingkat + label tanpa tanda baca, huruf besar ("XII" + "TKJ.1" → "XIITKJ1"). */
    public static function kunciKelas(string $tingkat, string $label): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $tingkat . $label));
    }

    private static function huruf(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - $m - 1, 26);
        }

        return $s;
    }

    /** Kode guru di ceklis: guru satu baris = "{no}", lebih dari satu = "{no}A", "{no}B", … */
    public static function kodeGuru(int $no, int $indeks, int $jumlah): string
    {
        return $jumlah <= 1 ? (string) $no : $no . self::huruf($indeks);
    }

    // =================================================================
    // Kelas & peserta
    // =================================================================

    /**
     * Semua kelas (belum dihapus) menurut urutan kolom ceklis: kelompok (tingkat X→XI→XII, lalu yang lebih dulu dibuat) →
     * jurusan (yang lebih dulu dibuat) → nomor. Contoh: PAGI X (TKJ.1–10, MPLB.1–5, AKL), SIANG XI, SIANG XII, PAGI XII.
     *
     * @return list<array{id:int, nama:string, label:string, tingkat:string, shift:string, grup:string, siswa:int}>
     */
    public function daftarKelas(): array
    {
        $rows = $this->db->table('kelas')->select('id, nama_kelas, tingkat, shift')->where('deleted_at', null)->orderBy('id', 'ASC')->get()->getResultArray();
        $siswa = [];
        foreach ($this->db->table('siswa')->select('kelas_id, COUNT(*) AS n')->where('status', 'aktif')->where('deleted_at', null)
            ->where('kelas_id IS NOT NULL')->groupBy('kelas_id')->get()->getResultArray() as $r) {
            $siswa[(int) $r['kelas_id']] = (int) $r['n'];
        }
        $rank = ['X' => 1, 'XI' => 2, 'XII' => 3];
        $minGrup = [];
        $minJur  = [];
        $item    = [];
        foreach ($rows as $r) {
            $u        = self::uraiKelas((string) $r['nama_kelas']);
            $tingkat  = $u['tingkat'] !== '' ? $u['tingkat'] : strtoupper((string) $r['tingkat']);
            $shift    = strtolower(trim((string) ($r['shift'] ?? '')));
            $g        = $tingkat . '|' . $shift;
            $j        = $g . '|' . mb_strtolower($u['jurusan']);
            $id       = (int) $r['id'];
            $minGrup[$g] = min($minGrup[$g] ?? $id, $id);
            $minJur[$j]  = min($minJur[$j] ?? $id, $id);
            $item[] = ['id' => $id, 'nama' => (string) $r['nama_kelas'], 'label' => $u['label'], 'tingkat' => $tingkat, 'shift' => $shift,
                'grup' => 'KELAS ' . ($shift !== '' ? strtoupper($shift) . ' ' : '') . $tingkat, 'siswa' => $siswa[$id] ?? 0,
                'o' => [$rank[$tingkat] ?? 9, $minGrup[$g], $minJur[$j], $u['nomor'], $id], 'g' => $g, 'j' => $j];
        }
        // urutan akhir dihitung SETELAH semua nilai minimum diketahui
        foreach ($item as &$x) {
            $x['o'] = [$rank[$x['tingkat']] ?? 9, $minGrup[$x['g']], $minJur[$x['j']], $x['o'][3], $x['id']];
        }
        unset($x);
        usort($item, static fn (array $a, array $b): int => $a['o'] <=> $b['o']);

        return array_map(static fn (array $x): array => array_diff_key($x, ['o' => 1, 'g' => 1, 'j' => 1]), $item);
    }

    // =================================================================
    // Baca
    // =================================================================

    public function dokumen(int $dokumenId): ?array
    {
        return $this->db->table('honor_dokumen')->where('id', $dokumenId)->get()->getRowArray();
    }

    /** Apakah honor ini punya ceklis (minimal satu baris mapel)? */
    public function ada(int $dokumenId): bool
    {
        return (int) $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->countAllResults() > 0;
    }

    /**
     * Ceklis lengkap: kelas (kolom), kelompok kolom, guru → baris mapel → sel, dan semua total.
     *
     * @return array{dokumen:array<string,mixed>, kelas:list<array<string,mixed>>, grup:list<array<string,mixed>>,
     *               guru:list<array<string,mixed>>, total:int, jumlah_guru:int, jumlah_mapel:int}|null
     */
    public function muat(int $dokumenId): ?array
    {
        $dok = $this->dokumen($dokumenId);
        if ($dok === null) {
            return null;
        }

        $kelas  = $this->daftarKelas();
        $simpan = [];
        foreach ($this->db->table('honor_koreksi_kelas')->where('dokumen_id', $dokumenId)->get()->getResultArray() as $r) {
            $simpan[(int) $r['kelas_id']] = $r;
        }
        $idx = [];
        foreach ($kelas as $i => &$k) {
            $s            = $simpan[$k['id']] ?? null;
            $k['peserta'] = $s !== null ? (int) $s['peserta'] : $k['siswa'];
            $k['manual']  = $s !== null && (int) $s['manual'] === 1;
            $k['jml']     = 0;
            $k['lembar']  = 0;
            $idx[$k['id']] = $i;
        }
        unset($k);

        $grup = [];
        foreach ($kelas as $i => $k) {
            $n = count($grup);
            if ($n > 0 && $grup[$n - 1]['judul'] === $k['grup']) {
                $grup[$n - 1]['sampai'] = $i;
            } else {
                $grup[] = ['judul' => $k['grup'], 'dari' => $i, 'sampai' => $i];
            }
        }

        $baris = $this->db->table('honor_baris')->select('id, guru_id, nama, jabatan')->where('dokumen_id', $dokumenId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $mapelPer = [];
        $mapelIds = [];
        foreach ($this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $m) {
            $mapelPer[(int) $m['baris_id']][] = $m;
            $mapelIds[] = (int) $m['id'];
        }
        $selPer = [];
        if ($mapelIds !== []) {
            foreach ($this->db->table('honor_koreksi_sel')->whereIn('mapel_row_id', $mapelIds)->get()->getResultArray() as $s) {
                $selPer[(int) $s['mapel_row_id']][(int) $s['kelas_id']] = $s['jumlah'] === null ? null : (int) $s['jumlah'];
            }
        }

        $guru  = [];
        $total = 0;
        $nMapel = 0;
        foreach ($baris as $i => $b) {
            $rows = $mapelPer[(int) $b['id']] ?? [];
            if ($rows === []) {
                continue;
            }
            $no    = $i + 1;
            $jml   = count($rows);
            $mapel = [];
            $totalGuru = 0;
            foreach ($rows as $j => $m) {
                $sel  = [];
                $ubah = [];
                $tm   = 0;
                foreach ($selPer[(int) $m['id']] ?? [] as $kid => $jumlah) {
                    if (! isset($idx[$kid])) { // kelasnya sudah dihapus
                        continue;
                    }
                    $k        = &$kelas[$idx[$kid]];
                    $efektif  = $jumlah ?? $k['peserta'];
                    $sel[$kid] = $efektif;
                    if ($jumlah !== null && $jumlah !== $k['peserta']) {
                        $ubah[$kid] = true;
                    }
                    $k['jml']++;
                    $k['lembar'] += $efektif;
                    $tm += $efektif;
                    unset($k);
                }
                $mapel[] = ['id' => (int) $m['id'], 'nama' => (string) $m['mapel_nama'], 'mapel_id' => $m['mapel_id'] === null ? null : (int) $m['mapel_id'],
                    'kode' => self::kodeGuru($no, $j, $jml), 'sel' => $sel, 'ubah' => $ubah, 'total' => $tm];
                $totalGuru += $tm;
                $nMapel++;
            }
            $guru[] = ['baris_id' => (int) $b['id'], 'no' => $no, 'nama' => (string) $b['nama'], 'jabatan' => (string) ($b['jabatan'] ?? ''),
                'guru_id' => $b['guru_id'] === null ? null : (int) $b['guru_id'], 'mapel' => $mapel, 'total' => $totalGuru];
            $total += $totalGuru;
        }

        return ['dokumen' => $dok, 'kelas' => $kelas, 'grup' => $grup, 'guru' => $guru, 'total' => $total, 'jumlah_guru' => count($guru), 'jumlah_mapel' => $nMapel];
    }

    /**
     * Ringkasan angka untuk layar setelah sebuah perubahan (server = sumber angka resmi).
     *
     * @return array{total:int, per_baris:array<int,int>, per_mapel:array<int,int>, per_kelas:array<int,array<string,int>>, jumlah_guru:int, jumlah_mapel:int}
     */
    public function ringkas(int $dokumenId): array
    {
        $m = $this->muat($dokumenId);
        $perBaris = [];
        $perMapel = [];
        foreach ($m['guru'] ?? [] as $g) {
            $perBaris[$g['baris_id']] = $g['total'];
            foreach ($g['mapel'] as $x) {
                $perMapel[$x['id']] = $x['total'];
            }
        }
        $perKelas = [];
        foreach ($m['kelas'] ?? [] as $k) {
            $perKelas[$k['id']] = ['peserta' => $k['peserta'], 'jml' => $k['jml'], 'lembar' => $k['lembar'], 'manual' => $k['manual'] ? 1 : 0];
        }

        return ['total' => (int) ($m['total'] ?? 0), 'per_baris' => $perBaris, 'per_mapel' => $perMapel, 'per_kelas' => $perKelas,
            'jumlah_guru' => (int) ($m['jumlah_guru'] ?? 0), 'jumlah_mapel' => (int) ($m['jumlah_mapel'] ?? 0)];
    }

    /** @return array<int,int> baris_id => total lembar (hanya baris yang ada di ceklis) */
    public function totalPerBaris(int $dokumenId): array
    {
        $out = [];
        foreach (($this->muat($dokumenId)['guru'] ?? []) as $g) {
            $out[$g['baris_id']] = $g['total'];
        }

        return $out;
    }

    /**
     * Total lembar per ORANG (id guru induk) — bahan HonorHitung. Data guru ganda digabung ke induknya.
     *
     * @return array<int,int>
     */
    public function totalPerOrang(int $dokumenId): array
    {
        $peta = GuruModel::petaOrang();
        $out  = [];
        foreach (($this->muat($dokumenId)['guru'] ?? []) as $g) {
            if ($g['guru_id'] === null) {
                continue;
            }
            $o       = $peta[$g['guru_id']] ?? $g['guru_id'];
            $out[$o] = ($out[$o] ?? 0) + $g['total'];
        }

        return $out;
    }

    /**
     * Bandingkan total ceklis dengan angka "Koreksi" yang sekarang tertulis di honor, per penerima.
     *
     * @return array{ada_komponen:bool, baris:list<array<string,mixed>>, beda:int}
     */
    public function bandingkanDenganHonor(int $dokumenId): array
    {
        $komp = $this->db->table('honor_dok_komponen')->where('dokumen_id', $dokumenId)->where('sumber', 'koreksi')->orderBy('urut', 'ASC')->get()->getRowArray();
        if ($komp === null) {
            return ['ada_komponen' => false, 'baris' => [], 'beda' => 0];
        }
        $totals = $this->totalPerBaris($dokumenId);
        $nilai  = [];
        foreach ($this->db->table('honor_nilai n')->select('n.baris_id, n.nilai, n.manual')->join('honor_baris b', 'b.id = n.baris_id')
            ->where('b.dokumen_id', $dokumenId)->where('n.dok_komponen_id', (int) $komp['id'])->get()->getResultArray() as $r) {
            $nilai[(int) $r['baris_id']] = $r;
        }
        $out  = [];
        $beda = 0;
        foreach ($this->db->table('honor_baris')->select('id, nama')->where('dokumen_id', $dokumenId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $b) {
            $id       = (int) $b['id'];
            $sekarang = (int) ($nilai[$id]['nilai'] ?? 0);
            $baru     = (int) ($totals[$id] ?? 0);
            if ($sekarang === 0 && $baru === 0 && ! isset($totals[$id])) {
                continue;
            }
            $selisih = $sekarang !== $baru;
            $beda   += $selisih ? 1 : 0;
            $out[] = ['baris_id' => $id, 'nama' => (string) $b['nama'], 'sekarang' => $sekarang, 'ceklis' => $baru, 'beda' => $selisih, 'manual' => (int) ($nilai[$id]['manual'] ?? 0) === 1];
        }

        return ['ada_komponen' => true, 'baris' => $out, 'beda' => $beda];
    }

    // =================================================================
    // Tulis — mengembalikan ['ok' => bool, 'pesan' => string, …]
    // =================================================================

    /** Peserta per kelas: kelas_id => isian ("" = kembali ke jumlah siswa aktif). */
    public function simpanPeserta(int $dokumenId, array $peserta): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $kelas = [];
        foreach ($this->daftarKelas() as $k) {
            $kelas[$k['id']] = $k;
        }
        $baru = [];
        foreach ($peserta as $idKirim => $isi) {
            $id = (int) $idKirim;
            if (! isset($kelas[$id])) {
                return $this->gagal('Ada kelas yang tidak dikenal. Muat ulang halaman lalu coba lagi.');
            }
            $t = trim((string) $isi);
            if ($t === '') {
                $baru[$id] = null;
                continue;
            }
            if (! ctype_digit($t) || (int) $t > self::MAKS_PESERTA) {
                return $this->gagal('Peserta kelas ' . $kelas[$id]['nama'] . ' harus bilangan bulat 0–' . self::MAKS_PESERTA . '.');
            }
            $baru[$id] = (int) $t;
        }

        $lama = [];
        foreach ($this->db->table('honor_koreksi_kelas')->where('dokumen_id', $dokumenId)->get()->getResultArray() as $r) {
            $lama[(int) $r['kelas_id']] = $r;
        }
        $now   = date('Y-m-d H:i:s');
        $catat = [];
        $this->db->transStart();
        foreach ($baru as $id => $n) {
            $siswa = $kelas[$id]['siswa'];
            $ada   = $lama[$id] ?? null;
            $efekLama = $ada !== null ? (int) $ada['peserta'] : $siswa;
            if ($n === null) { // kembali ke jumlah siswa aktif
                if ($ada !== null) {
                    $this->db->table('honor_koreksi_kelas')->where('id', $ada['id'])->delete();
                }
                $n = $siswa;
            } elseif ($ada === null) {
                $this->db->table('honor_koreksi_kelas')->insert(['dokumen_id' => $dokumenId, 'kelas_id' => $id, 'peserta' => $n, 'manual' => $n !== $siswa ? 1 : 0, 'updated_at' => $now]);
            } else {
                $this->db->table('honor_koreksi_kelas')->where('id', $ada['id'])->update(['peserta' => $n, 'manual' => $n !== $siswa ? 1 : 0, 'updated_at' => $now]);
            }
            if ($n !== $efekLama) {
                $catat[] = $kelas[$id]['nama'] . ' ' . $efekLama . '→' . $n;
            }
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal menyimpan peserta. Coba lagi.');
        }
        if ($catat !== []) {
            $this->audit('update', 'honor_koreksi_kelas', $dokumenId, 'Peserta koreksi honor ' . $this->label($dokumenId) . ': ' . implode('; ', array_slice($catat, 0, 25)) . (count($catat) > 25 ? '; …' : ''));
        }

        return ['ok' => true, 'pesan' => $catat === [] ? 'Tidak ada perubahan.' : 'Peserta ' . count($catat) . ' kelas disimpan.'] + $this->ringkas($dokumenId);
    }

    /** Kembalikan peserta ke jumlah siswa aktif — hanya yang tidak diketik manual, atau semuanya bila $semua. */
    public function segarkanPeserta(int $dokumenId, bool $semua = false): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $b = $this->db->table('honor_koreksi_kelas')->where('dokumen_id', $dokumenId);
        if (! $semua) {
            $b->where('manual', 0);
        }
        $n = (int) $b->countAllResults();
        $d = $this->db->table('honor_koreksi_kelas')->where('dokumen_id', $dokumenId);
        if (! $semua) {
            $d->where('manual', 0);
        }
        $d->delete(); // tanpa baris tersimpan = ikut jumlah siswa aktif saat ini
        $this->audit('update', 'honor_koreksi_kelas', $dokumenId, 'Peserta koreksi honor ' . $this->label($dokumenId) . ' disamakan dengan siswa aktif' . ($semua ? ' (termasuk yang diketik)' : ''));

        return ['ok' => true, 'pesan' => 'Peserta disamakan dengan jumlah siswa aktif saat ini' . ($semua ? ' (semua kelas).' : ' (kelas yang tidak diketik sendiri).')] + $this->ringkas($dokumenId) + ['jumlah' => $n];
    }

    /**
     * Isi ceklis dari data pengampu (guru × mapel × kelas di Master). Hanya penerima honor yang ditautkan ke Master Guru.
     * Penerima yang sudah punya baris mapel DILEWATI, kecuali $ganti = true (seluruh ceklis lama dibuang dulu).
     */
    public function isiDariPengampu(int $dokumenId, bool $ganti = false): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $peta = GuruModel::petaOrang();
        $orangKeBaris = [];
        foreach ($this->db->table('honor_baris')->select('id, guru_id')->where('dokumen_id', $dokumenId)->where('guru_id IS NOT NULL')->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $b) {
            $o = $peta[(int) $b['guru_id']] ?? (int) $b['guru_id'];
            $orangKeBaris[$o] ??= (int) $b['id'];
        }
        if ($orangKeBaris === []) {
            return $this->gagal('Belum ada penerima honor yang ditautkan ke Master Guru. Tambahkan penerima dulu.');
        }

        $peng = $this->db->table('pengampu p')->select('p.guru_id, p.kelas_id, p.mapel_id, m.nama_mapel')
            ->join('kelas k', 'k.id = p.kelas_id AND k.deleted_at IS NULL')
            ->join('mata_pelajaran m', 'm.id = p.mapel_id', 'left')
            ->where('p.deleted_at', null)->orderBy('p.mapel_id', 'ASC')->orderBy('p.id', 'ASC')->get()->getResultArray();
        $per = []; // baris_id => mapel_id => ['nama','kelas'=>[…]]
        $tanpaPenerima = [];
        foreach ($peng as $p) {
            $o = $peta[(int) $p['guru_id']] ?? (int) $p['guru_id'];
            if (! isset($orangKeBaris[$o])) {
                $tanpaPenerima[$o] = true;
                continue;
            }
            // Satu baris per NAMA mapel (beberapa mapel_id bisa bernama sama, mis. mapel yang sama untuk tingkat berbeda).
            $nama = trim((string) preg_replace('/\s+/u', ' ', (string) ($p['nama_mapel'] ?? ''))) !== '' ? trim((string) preg_replace('/\s+/u', ' ', (string) $p['nama_mapel'])) : 'Mapel #' . (int) $p['mapel_id'];
            $kunci = mb_strtolower($nama);
            $per[$orangKeBaris[$o]][$kunci]['nama'] = $nama;
            $per[$orangKeBaris[$o]][$kunci]['mapel_id'] ??= (int) $p['mapel_id'];
            $per[$orangKeBaris[$o]][$kunci]['kelas'][(int) $p['kelas_id']] = true;
        }
        if ($per === []) {
            return $this->gagal('Tidak ada data pengampu untuk penerima honor ini.');
        }

        return $this->tulisCeklis($dokumenId, $per, $ganti, 'data pengampu', $tanpaPenerima === [] ? '' : ' ' . count($tanpaPenerima) . ' guru pengampu belum jadi penerima honor (tidak dimasukkan).');
    }

    /**
     * Isi ceklis dari SKBM (Lampiran 3 SK Pembagian Tugas Mengajar) tahun ajaran honor ini — sumber yang disarankan, karena
     * SKBM adalah salinan SK resmi (lihat Libraries\Skbm). Hanya penerima honor yang ditautkan ke Master Guru; satu baris
     * per nama mapel. Penerima yang sudah punya baris mapel DILEWATI, kecuali $ganti = true (ceklis lama dibuang dulu).
     */
    public function isiDariSkbm(int $dokumenId, bool $ganti = false): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        if (! $this->db->tableExists('skbm_mapel') || ! $this->db->tableExists('skbm_sel')) {
            return $this->gagal('Fitur SKBM belum aktif di server ini: migrasi database belum dijalankan (php spark migrate).');
        }
        $tahun = $this->tahunHonor($dokumenId);
        $skbm  = new Skbm($this->db);
        if ($tahun === null || ! $skbm->ada($tahun)) {
            return $this->gagal('SKBM tahun ajaran ' . ($tahun ?? '?') . ' belum diisi. Isi dulu di menu Guru → SKBM.');
        }
        $peta = GuruModel::petaOrang();
        $orangKeBaris = [];
        foreach ($this->db->table('honor_baris')->select('id, guru_id')->where('dokumen_id', $dokumenId)->where('guru_id IS NOT NULL')->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $b) {
            $o = $peta[(int) $b['guru_id']] ?? (int) $b['guru_id'];
            $orangKeBaris[$o] ??= (int) $b['id'];
        }
        if ($orangKeBaris === []) {
            return $this->gagal('Belum ada penerima honor yang ditautkan ke Master Guru. Tambahkan penerima dulu.');
        }
        $per = [];
        $tanpaPenerima = [];
        foreach ($skbm->barisUntukHonor($tahun) as $orang => $daftar) {
            if (! isset($orangKeBaris[$orang])) {
                $tanpaPenerima[$orang] = true;
                continue;
            }
            foreach ($daftar as $x) {
                $per[$orangKeBaris[$orang]][mb_strtolower($x['nama'])] = ['nama' => $x['nama'], 'mapel_id' => (int) ($x['mapel_id'] ?? 0), 'kelas' => array_fill_keys($x['kelas'], true)];
            }
        }
        if ($per === []) {
            return $this->gagal('Tidak ada guru di SKBM ' . $tahun . ' yang menjadi penerima honor ini.');
        }

        return $this->tulisCeklis($dokumenId, $per, $ganti, 'SKBM ' . $tahun, $tanpaPenerima === [] ? '' : ' ' . count($tanpaPenerima) . ' guru di SKBM belum jadi penerima honor (tidak dimasukkan).');
    }

    /** Tahun ajaran honor (dari periode ujiannya), atau null. */
    public function tahunHonor(int $dokumenId): ?string
    {
        $r = $this->db->table('honor_dokumen d')->select('p.tahun_ajaran')->join('ujian_periode p', 'p.id = d.periode_id')->where('d.id', $dokumenId)->get()->getRowArray();

        return $r === null ? null : (string) $r['tahun_ajaran'];
    }

    /**
     * Tulis ceklis dari bahan siap: baris_id => kunci mapel => ['nama','mapel_id','kelas'=>[kelas_id => true]]. Dipakai semua
     * sumber isi otomatis (pengampu, SKBM). Penerima yang sudah punya isi dilewati kecuali $ganti.
     *
     * @param array<int, array<string, array{nama:string, mapel_id:int, kelas:array<int,bool>}>> $per
     */
    private function tulisCeklis(int $dokumenId, array $per, bool $ganti, string $sumber, string $catatan = ''): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->transStart();
        if ($ganti) {
            $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->delete(); // sel ikut terhapus
        }
        $sudah = [];
        foreach ($this->db->table('honor_koreksi_mapel')->select('baris_id')->where('dokumen_id', $dokumenId)->distinct()->get()->getResultArray() as $r) {
            $sudah[(int) $r['baris_id']] = true;
        }
        $nGuru = $nMapel = $nSel = $dilewati = 0;
        foreach ($per as $barisId => $mapels) {
            if (isset($sudah[$barisId])) {
                $dilewati++;
                continue;
            }
            $urut = 0;
            foreach ($mapels as $d) {
                $this->db->table('honor_koreksi_mapel')->insert([
                    'dokumen_id' => $dokumenId, 'baris_id' => $barisId, 'mapel_nama' => mb_substr($d['nama'], 0, self::MAKS_NAMA_MAPEL),
                    'mapel_id' => $d['mapel_id'] > 0 ? $d['mapel_id'] : null, 'urut' => ++$urut, 'created_at' => $now,
                ]);
                $rid = (int) $this->db->insertID();
                foreach (array_keys($d['kelas']) as $kid) {
                    $this->db->table('honor_koreksi_sel')->insert(['mapel_row_id' => $rid, 'kelas_id' => $kid, 'jumlah' => null, 'created_at' => $now]);
                    $nSel++;
                }
                $nMapel++;
            }
            $nGuru++;
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal("Gagal mengisi ceklis dari $sumber. Tidak ada yang berubah.");
        }
        $this->audit('create', 'honor_koreksi_mapel', $dokumenId, 'Isi ceklis koreksi honor ' . $this->label($dokumenId) . " dari $sumber: $nGuru guru, $nMapel baris mapel, $nSel sel" . ($ganti ? ' (ceklis lama diganti)' : ''));

        $pesan = "Ceklis diisi dari $sumber: $nGuru guru, $nMapel baris mapel, $nSel kelas.";
        if ($dilewati > 0) {
            $pesan .= " $dilewati guru dilewati karena sudah punya isi.";
        }
        $pesan .= $catatan;

        return ['ok' => true, 'pesan' => $pesan, 'guru' => $nGuru, 'mapel' => $nMapel, 'sel' => $nSel] + $this->ringkas($dokumenId);
    }

    /**
     * Salin ceklis dari honor lain (penerima dicocokkan lewat tautan Master Guru, lalu nama persis). Ceklis yang sekarang
     * harus kosong, kecuali $ganti. $denganPeserta: jumlah peserta tiap kelas ikut disalin.
     */
    public function salinDari(int $dokumenId, int $sumberId, bool $denganPeserta = false, bool $ganti = false): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        if ($sumberId === $dokumenId || $this->dokumen($sumberId) === null) {
            return $this->gagal('Honor sumber salinan tidak ditemukan.');
        }
        if (! $ganti && $this->ada($dokumenId)) {
            return $this->gagal('Ceklis honor ini sudah berisi. Kosongkan dulu atau pilih "ganti".');
        }
        $peta   = GuruModel::petaOrang();
        $tujuan = $this->db->table('honor_baris')->select('id, guru_id, nama')->where('dokumen_id', $dokumenId)->get()->getResultArray();
        $kunciGuru = [];
        $kunciNama = [];
        foreach ($tujuan as $b) {
            if ($b['guru_id'] !== null) {
                $kunciGuru[$peta[(int) $b['guru_id']] ?? (int) $b['guru_id']] ??= (int) $b['id'];
            }
            $kunciNama[mb_strtolower(trim((string) $b['nama']))] ??= (int) $b['id'];
        }
        $sumberBaris = [];
        foreach ($this->db->table('honor_baris')->select('id, guru_id, nama')->where('dokumen_id', $sumberId)->get()->getResultArray() as $b) {
            $tid = null;
            if ($b['guru_id'] !== null) {
                $tid = $kunciGuru[$peta[(int) $b['guru_id']] ?? (int) $b['guru_id']] ?? null;
            }
            $tid ??= $kunciNama[mb_strtolower(trim((string) $b['nama']))] ?? null;
            $sumberBaris[(int) $b['id']] = $tid;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->transStart();
        if ($ganti) {
            $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->delete();
        }
        $nGuru = [];
        $nMapel = $nSel = $tak = 0;
        $mapelSumber = $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $sumberId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($mapelSumber as $m) {
            $tid = $sumberBaris[(int) $m['baris_id']] ?? null;
            if ($tid === null) {
                $tak++;
                continue;
            }
            $this->db->table('honor_koreksi_mapel')->insert(['dokumen_id' => $dokumenId, 'baris_id' => $tid, 'mapel_nama' => $m['mapel_nama'], 'mapel_id' => $m['mapel_id'], 'urut' => (int) $m['urut'], 'created_at' => $now]);
            $rid = (int) $this->db->insertID();
            foreach ($this->db->table('honor_koreksi_sel')->where('mapel_row_id', $m['id'])->get()->getResultArray() as $s) {
                $this->db->table('honor_koreksi_sel')->insert(['mapel_row_id' => $rid, 'kelas_id' => (int) $s['kelas_id'], 'jumlah' => $s['jumlah'], 'created_at' => $now]);
                $nSel++;
            }
            $nGuru[$tid] = true;
            $nMapel++;
        }
        if ($denganPeserta) {
            $this->db->table('honor_koreksi_kelas')->where('dokumen_id', $dokumenId)->delete();
            foreach ($this->db->table('honor_koreksi_kelas')->where('dokumen_id', $sumberId)->get()->getResultArray() as $r) {
                $this->db->table('honor_koreksi_kelas')->insert(['dokumen_id' => $dokumenId, 'kelas_id' => (int) $r['kelas_id'], 'peserta' => (int) $r['peserta'], 'manual' => (int) $r['manual'], 'updated_at' => $now]);
            }
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return $this->gagal('Gagal menyalin ceklis. Tidak ada yang berubah.');
        }
        $this->audit('create', 'honor_koreksi_mapel', $dokumenId, 'Salin ceklis koreksi dari honor ' . $this->label($sumberId) . ' ke ' . $this->label($dokumenId) . ': ' . count($nGuru) . " guru, $nMapel baris mapel, $nSel sel");

        return ['ok' => true, 'pesan' => 'Ceklis disalin: ' . count($nGuru) . " guru, $nMapel baris mapel, $nSel kelas." . ($tak > 0 ? " $tak baris mapel dilewati karena gurunya tidak ada di honor ini." : '')] + $this->ringkas($dokumenId);
    }

    /** Buang seluruh ceklis (baris mapel + sel). Peserta per kelas ikut dibuang bila $denganPeserta. */
    public function kosongkan(int $dokumenId, bool $denganPeserta = false): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $n = (int) $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->countAllResults();
        $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->delete();
        if ($denganPeserta) {
            $this->db->table('honor_koreksi_kelas')->where('dokumen_id', $dokumenId)->delete();
        }
        $this->audit('delete', 'honor_koreksi_mapel', $dokumenId, 'Kosongkan ceklis koreksi honor ' . $this->label($dokumenId) . " ($n baris mapel)");

        return ['ok' => true, 'pesan' => $n > 0 ? "Ceklis dikosongkan ($n baris mapel dibuang)." : 'Ceklis sudah kosong.'] + $this->ringkas($dokumenId);
    }

    /** Tambah baris mapel untuk seorang penerima ("-" = terdaftar tanpa mapel). */
    public function tambahMapel(int $dokumenId, int $barisId, string $nama): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $nama = HonorPengaturan::rapikan($nama);
        if ($nama === '' || mb_strlen($nama) > self::MAKS_NAMA_MAPEL) {
            return $this->gagal('Nama mapel wajib diisi (maksimal ' . self::MAKS_NAMA_MAPEL . ' huruf). Tulis "-" bila guru ini tidak mengoreksi mapel.');
        }
        $b = $this->db->table('honor_baris')->select('id, nama')->where('id', $barisId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        if ($b === null) {
            return $this->gagal('Penerima tidak ditemukan di honor ini.');
        }
        $ada = $this->db->table('honor_koreksi_mapel')->selectMax('urut')->where('dokumen_id', $dokumenId)->where('baris_id', $barisId)->get()->getRow();
        $jml = (int) $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->where('baris_id', $barisId)->countAllResults();
        if ($jml >= self::MAKS_MAPEL_PER_GURU) {
            return $this->gagal('Satu guru maksimal ' . self::MAKS_MAPEL_PER_GURU . ' baris mapel.');
        }
        if ((int) $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->countAllResults() >= self::MAKS_BARIS_MAPEL) {
            return $this->gagal('Ceklis sudah mencapai batas ' . self::MAKS_BARIS_MAPEL . ' baris mapel.');
        }
        $mid = $this->db->table('mata_pelajaran')->select('id')->where('nama_mapel', $nama)->where('deleted_at', null)->get()->getRowArray();
        $this->db->table('honor_koreksi_mapel')->insert([
            'dokumen_id' => $dokumenId, 'baris_id' => $barisId, 'mapel_nama' => $nama, 'mapel_id' => $mid['id'] ?? null,
            'urut' => min(65000, (int) ($ada->urut ?? 0) + 1), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();
        $this->audit('create', 'honor_koreksi_mapel', $id, 'Tambah mapel "' . $nama . '" untuk ' . $b['nama'] . ' di ceklis koreksi honor ' . $this->label($dokumenId));

        return ['ok' => true, 'pesan' => '"' . $nama . '" ditambahkan untuk ' . $b['nama'] . '.', 'id' => $id];
    }

    public function ubahMapel(int $dokumenId, int $mapelRowId, string $nama): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $nama = HonorPengaturan::rapikan($nama);
        if ($nama === '' || mb_strlen($nama) > self::MAKS_NAMA_MAPEL) {
            return $this->gagal('Nama mapel wajib diisi (maksimal ' . self::MAKS_NAMA_MAPEL . ' huruf).');
        }
        $m = $this->db->table('honor_koreksi_mapel')->where('id', $mapelRowId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        if ($m === null) {
            return $this->gagal('Baris mapel tidak ditemukan.');
        }
        if ($m['mapel_nama'] === $nama) {
            return ['ok' => true, 'pesan' => 'Tidak ada perubahan.', 'nama' => $nama];
        }
        $mid = $this->db->table('mata_pelajaran')->select('id')->where('nama_mapel', $nama)->where('deleted_at', null)->get()->getRowArray();
        $this->db->table('honor_koreksi_mapel')->where('id', $mapelRowId)->update(['mapel_nama' => $nama, 'mapel_id' => $mid['id'] ?? null]);
        $this->audit('update', 'honor_koreksi_mapel', $mapelRowId, 'Ubah mapel "' . $m['mapel_nama'] . '" → "' . $nama . '" di ceklis koreksi honor ' . $this->label($dokumenId));

        return ['ok' => true, 'pesan' => 'Tersimpan.', 'nama' => $nama];
    }

    public function hapusMapel(int $dokumenId, int $mapelRowId): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $m = $this->db->table('honor_koreksi_mapel')->where('id', $mapelRowId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        if ($m === null) {
            return $this->gagal('Baris mapel tidak ditemukan.');
        }
        $this->db->table('honor_koreksi_mapel')->where('id', $mapelRowId)->delete();
        $this->audit('delete', 'honor_koreksi_mapel', $mapelRowId, 'Hapus mapel "' . $m['mapel_nama'] . '" dari ceklis koreksi honor ' . $this->label($dokumenId));

        return ['ok' => true, 'pesan' => 'Baris mapel "' . $m['mapel_nama'] . '" dihapus.'] + $this->ringkas($dokumenId);
    }

    /** Keluarkan seorang penerima dari ceklis (semua baris mapelnya). */
    public function hapusGuru(int $dokumenId, int $barisId): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $b = $this->db->table('honor_baris')->select('id, nama')->where('id', $barisId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        if ($b === null) {
            return $this->gagal('Penerima tidak ditemukan di honor ini.');
        }
        $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $dokumenId)->where('baris_id', $barisId)->delete();
        $this->audit('delete', 'honor_koreksi_mapel', $barisId, 'Keluarkan ' . $b['nama'] . ' dari ceklis koreksi honor ' . $this->label($dokumenId));

        return ['ok' => true, 'pesan' => $b['nama'] . ' dikeluarkan dari ceklis.'] + $this->ringkas($dokumenId);
    }

    /**
     * Nyalakan / matikan satu kelas pada satu baris mapel, dan (opsional) beri angka khusus sel itu.
     * $jumlah: null/"" = ikut peserta kelas; angka sama dengan peserta kelas juga disimpan sebagai "ikut peserta".
     */
    public function setSel(int $dokumenId, int $mapelRowId, int $kelasId, bool $aktif, mixed $jumlah = null): array
    {
        if (($salah = $this->bolehUbah($dokumenId)) !== null) {
            return $salah;
        }
        $m = $this->db->table('honor_koreksi_mapel')->where('id', $mapelRowId)->where('dokumen_id', $dokumenId)->get()->getRowArray();
        $kelas = null;
        foreach ($this->daftarKelas() as $k) {
            if ($k['id'] === $kelasId) {
                $kelas = $k;
                break;
            }
        }
        if ($m === null || $kelas === null) {
            return $this->gagal('Baris mapel atau kelas tidak ditemukan. Muat ulang halaman.');
        }
        $ada = $this->db->table('honor_koreksi_sel')->where('mapel_row_id', $mapelRowId)->where('kelas_id', $kelasId)->get()->getRowArray();
        if (! $aktif) {
            if ($ada !== null) {
                $this->db->table('honor_koreksi_sel')->where('id', $ada['id'])->delete();
            }

            return ['ok' => true, 'pesan' => 'Tersimpan.'] + $this->ringkas($dokumenId);
        }

        $khusus = null;
        $t      = trim((string) $jumlah);
        if ($t !== '') {
            if (! ctype_digit($t) || (int) $t > self::MAKS_PESERTA) {
                return $this->gagal('Jumlah lembar harus bilangan bulat 0–' . self::MAKS_PESERTA . '.');
            }
            $peserta = $this->pesertaKelas($dokumenId, $kelasId, $kelas['siswa']);
            $khusus  = (int) $t === $peserta ? null : (int) $t;
        }
        if ($ada === null) {
            $this->db->table('honor_koreksi_sel')->insert(['mapel_row_id' => $mapelRowId, 'kelas_id' => $kelasId, 'jumlah' => $khusus, 'created_at' => date('Y-m-d H:i:s')]);
        } elseif ($t !== '' || $jumlah === '') {
            $this->db->table('honor_koreksi_sel')->where('id', $ada['id'])->update(['jumlah' => $khusus]);
        }

        return ['ok' => true, 'pesan' => 'Tersimpan.'] + $this->ringkas($dokumenId);
    }

    // =================================================================
    // Internal
    // =================================================================

    /** Jumlah lembar efektif sebuah sel (angka khusus, atau peserta kelas); null bila sel tidak menyala. */
    public function nilaiSel(int $dokumenId, int $mapelRowId, int $kelasId): ?int
    {
        $s = $this->db->table('honor_koreksi_sel s')->select('s.jumlah')->join('honor_koreksi_mapel m', 'm.id = s.mapel_row_id')
            ->where('s.mapel_row_id', $mapelRowId)->where('s.kelas_id', $kelasId)->where('m.dokumen_id', $dokumenId)->get()->getRowArray();
        if ($s === null) {
            return null;
        }
        if ($s['jumlah'] !== null) {
            return (int) $s['jumlah'];
        }
        foreach ($this->daftarKelas() as $k) {
            if ($k['id'] === $kelasId) {
                return $this->pesertaKelas($dokumenId, $kelasId, $k['siswa']);
            }
        }

        return null;
    }

    private function pesertaKelas(int $dokumenId, int $kelasId, int $siswa): int
    {
        $r = $this->db->table('honor_koreksi_kelas')->select('peserta')->where('dokumen_id', $dokumenId)->where('kelas_id', $kelasId)->get()->getRowArray();

        return $r !== null ? (int) $r['peserta'] : $siswa;
    }

    /** null = boleh diubah; selain itu balasan penolakan (honor tidak ada / terkunci). */
    private function bolehUbah(int $dokumenId): ?array
    {
        $dok = $this->dokumen($dokumenId);
        if ($dok === null) {
            return $this->gagal('Honor tidak ditemukan.');
        }
        if ((string) $dok['status'] === 'dikunci') {
            return $this->gagal('Honor ini sudah DIKUNCI dan tidak bisa diubah. Buka kunci dulu (di tab Honor) bila perlu.');
        }

        return null;
    }

    private function label(int $dokumenId): string
    {
        return (new HonorDokumen($this->db))->labelPublik($dokumenId);
    }

    private function gagal(string $pesan): array
    {
        return ['ok' => false, 'pesan' => $pesan];
    }

    private function audit(string $aksi, string $tabel, ?int $id, string $deskripsi): void
    {
        (new AuditModel())->record($aksi, $tabel, $id, $deskripsi);
    }
}
