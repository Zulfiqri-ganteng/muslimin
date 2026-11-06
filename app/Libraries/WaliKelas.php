<?php

namespace App\Libraries;

use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;

/**
 * WALI KELAS (2026-11-07). Akun staf dihubungkan ke data guru lewat admins.guru_id; guru yang tercatat sebagai wali di Master Kelas
 * (kelas.wali_kelas_id) otomatis mendapat menu "Kelas Saya" — sebagai TAMBAHAN di atas perannya. Guru tanpa peran lain memakai
 * peran "Wali Kelas" (migrasi 2026-11-07-000001). Admin boleh membuka semua kelas (pengawasan).
 *
 * Akses: alamat admin/kelas-saya ada di Config\Peran::$umum (semua akun login lolos penjaga rute), lalu controller & menu memakai
 * aturan di sini — hanya wali kelas itu (atau Admin) yang bisa melihat isi kelasnya. API: awalan "wali" (umum), aturan sama.
 */
final class WaliKelas
{
    /** Alamat menu samping "Kelas Saya". */
    public const ALAMAT = 'admin/kelas-saya';

    /** Domain email pengganti untuk akun wali buatan sistem (guru tidak punya email di data sekolah; bisa diubah Admin). */
    public const DOMAIN_EMAIL = 'walikelas.local';

    private const HURUF_BESAR = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const HURUF_KECIL = 'abcdefghijkmnopqrstuvwxyz';
    private const ANGKA       = '23456789';

    /** @var array<int, list<array<string, mixed>>> admin id → kelas (cache satu permintaan) */
    private static array $cache = [];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Kolom admins.guru_id sudah ada (server sudah migrate)? */
    public static function siap(): bool
    {
        static $ada = null;
        if ($ada === null) {
            try {
                $ada = db_connect()->fieldExists('guru_id', 'admins');
            } catch (\Throwable $e) {
                $ada = false;
            }
        }

        return $ada;
    }

    public static function lupakan(): void
    {
        self::$cache = [];
    }

    // =================================================================
    // Akses
    // =================================================================

    /**
     * Kelas yang diwalikan akun ini (lewat admins.guru_id). Kosong bila akun belum dihubungkan ke guru / gurunya bukan wali.
     *
     * @return list<array{id: int, nama_kelas: string, tingkat: string, guru_id: int, guru_nama: string}>
     */
    public function kelasAkun(int $adminId): array
    {
        if ($adminId <= 0 || ! self::siap()) {
            return [];
        }
        if (! isset(self::$cache[$adminId])) {
            $rows = $this->db->table('admins a')
                ->select('k.id, k.nama_kelas, k.tingkat, g.id AS guru_id, g.nama AS guru_nama')
                ->join('guru g', 'g.id = a.guru_id AND g.deleted_at IS NULL')
                ->join('kelas k', 'k.wali_kelas_id = g.id AND k.deleted_at IS NULL')
                ->where('a.id', $adminId)->where('a.aktif', 1)
                ->get()->getResultArray();
            usort($rows, static fn (array $x, array $y): int => strnatcasecmp($x['nama_kelas'], $y['nama_kelas']));
            self::$cache[$adminId] = array_map(static fn (array $r): array => [
                'id' => (int) $r['id'], 'nama_kelas' => (string) $r['nama_kelas'], 'tingkat' => (string) $r['tingkat'],
                'guru_id' => (int) $r['guru_id'], 'guru_nama' => (string) $r['guru_nama'],
            ], $rows);
        }

        return self::$cache[$adminId];
    }

    /** Akun (baris sesi/API: id, role) boleh melihat menu Kelas Saya? Admin selalu (pengawasan semua kelas). */
    public function bolehMenu(array $admin): bool
    {
        return ($admin['role'] ?? '') === 'admin' || $this->kelasAkun((int) ($admin['id'] ?? 0)) !== [];
    }

    /** Akun boleh melihat isi kelas $kelasId? Admin semua kelas; selain itu hanya kelas yang ia walikan. */
    public function bolehKelas(array $admin, int $kelasId): bool
    {
        if (($admin['role'] ?? '') === 'admin') {
            return true;
        }

        return in_array($kelasId, array_column($this->kelasAkun((int) ($admin['id'] ?? 0)), 'id'), true);
    }

    /** Penyaring item menu samping / menu API: "Kelas Saya" hanya untuk wali (dan Admin); item lain tidak disentuh. */
    public static function itemTampil(string $alamat, array $admin): bool
    {
        return trim(strtolower($alamat), '/') !== self::ALAMAT || (new self())->bolehMenu($admin);
    }

    // =================================================================
    // Kelola Akun (Admin)
    // =================================================================

    /**
     * Semua wali kelas di Master Kelas + akun yang terhubung (bahan kartu "Akun wali kelas" & pilihan guru di formulir akun).
     *
     * @return list<array{guru_id: int, guru_nama: string, no_wa: ?string, kelas: list<string>, akun: ?array{id: int, nama: string, username: string, role: string, aktif: int}}>
     */
    public function daftarWali(): array
    {
        if (! self::siap()) {
            return [];
        }
        $rows = $this->db->table('kelas k')
            ->select('g.id AS guru_id, g.nama AS guru_nama, g.no_wa, k.nama_kelas, a.id AS akun_id, a.full_name AS akun_nama, a.username, a.role, a.aktif')
            ->join('guru g', 'g.id = k.wali_kelas_id AND g.deleted_at IS NULL')
            ->join('admins a', 'a.guru_id = g.id', 'left')
            ->where('k.deleted_at', null)
            ->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $gid = (int) $r['guru_id'];
            $out[$gid] ??= [
                'guru_id' => $gid, 'guru_nama' => (string) $r['guru_nama'], 'no_wa' => $r['no_wa'] ?: null, 'kelas' => [],
                'akun' => $r['akun_id'] !== null ? ['id' => (int) $r['akun_id'], 'nama' => (string) $r['akun_nama'], 'username' => (string) $r['username'], 'role' => (string) $r['role'], 'aktif' => (int) $r['aktif']] : null,
            ];
            $out[$gid]['kelas'][] = (string) $r['nama_kelas'];
        }
        foreach ($out as &$w) {
            natcasesort($w['kelas']);
            $w['kelas'] = array_values($w['kelas']);
        }
        unset($w);
        $out = array_values($out);
        usort($out, static fn (array $x, array $y): int => strnatcasecmp($x['kelas'][0] ?? '', $y['kelas'][0] ?? ''));

        return $out;
    }

    /**
     * Pilihan guru untuk formulir akun: semua guru aktif + kelas yang diwalikan (label "Nama — wali X TKJ 1").
     *
     * @return list<array{id: int, label: string, wali: bool}>
     */
    public function pilihanGuru(): array
    {
        $wali = [];
        foreach ($this->db->table('kelas')->select('wali_kelas_id, nama_kelas')->where('deleted_at', null)->where('wali_kelas_id IS NOT NULL', null, false)->get()->getResultArray() as $k) {
            $wali[(int) $k['wali_kelas_id']][] = (string) $k['nama_kelas'];
        }
        $out = [];
        foreach ($this->db->table('guru')->select('id, nama')->where('deleted_at', null)->orderBy('nama')->get()->getResultArray() as $g) {
            $kls   = $wali[(int) $g['id']] ?? [];
            $out[] = ['id' => (int) $g['id'], 'label' => (string) $g['nama'] . ($kls !== [] ? ' — wali ' . implode(', ', $kls) : ''), 'wali' => $kls !== []];
        }

        return $out;
    }

    /** Guru $guruId sudah dipakai akun lain (selain $kecualiAkun)? Nama akun pemakainya, atau null. */
    public function dipakaiAkun(int $guruId, int $kecualiAkun = 0): ?string
    {
        $r = $this->db->table('admins')->select('full_name')->where('guru_id', $guruId)->where('id !=', $kecualiAkun)->get()->getRowArray();

        return $r['full_name'] ?? null;
    }

    /**
     * BUAT AKUN WALI KELAS SEKALIGUS: tiap guru wali di Master Kelas yang belum punya akun →
     *   (a) bila ada akun staf yang namanya SAMA (tanpa gelar, huruf besar/kecil diabaikan) dan belum terhubung ke guru → DIHUBUNGKAN saja
     *       (perannya tidak diubah, mis. Waka Hubin yang juga wali kelas);
     *   (b) selain itu dibuatkan akun baru: peran Wali Kelas, username dari nama (mis. "puguh.wira"), email pengganti
     *       username@walikelas.local, HP = no WA guru, sandi sementara acak WAJIB diganti saat pertama masuk.
     * Satu transaksi; jejak Audit Log per akun. Sandi hanya dikembalikan di sini (ditampilkan sekali).
     *
     * @param array<string, mixed> $konteks ['oleh' => nama admin]
     *
     * @return array{ok: bool, pesan: string, dibuat: list<array<string, mixed>>, ditautkan: list<array<string, mixed>>}
     */
    public function buatAkunMassal(array $konteks): array
    {
        if (! self::siap() || ! HakAkses::dikenal('wali_kelas')) {
            return ['ok' => false, 'pesan' => 'Fitur wali kelas belum aktif di server ini: jalankan "php spark migrate".', 'dibuat' => [], 'ditautkan' => []];
        }
        $belum = array_values(array_filter($this->daftarWali(), static fn (array $w): bool => $w['akun'] === null));
        if ($belum === []) {
            return ['ok' => true, 'pesan' => 'Semua wali kelas sudah punya akun.', 'dibuat' => [], 'ditautkan' => []];
        }
        $calon = [];
        foreach ($this->db->table('admins')->select('id, full_name, username, role')->where('guru_id', null)->get()->getResultArray() as $a) {
            $calon[self::namaPolos((string) $a['full_name'])][] = $a;
        }
        $dibuat = $ditautkan = [];
        $now    = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            foreach ($belum as $w) {
                $kunci = self::namaPolos($w['guru_nama']);
                if (count($calon[$kunci] ?? []) === 1) { // tepat satu akun bernama sama → hubungkan
                    $a = $calon[$kunci][0];
                    $this->db->table('admins')->where('id', (int) $a['id'])->where('guru_id', null)->update(['guru_id' => $w['guru_id'], 'updated_at' => $now]);
                    unset($calon[$kunci]);
                    $ditautkan[] = ['nama' => (string) $a['full_name'], 'username' => (string) $a['username'], 'peran' => HakAkses::label((string) $a['role']), 'kelas' => $w['kelas']];
                    continue;
                }
                $username = $this->usernameDari($w['guru_nama']);
                $sandi    = self::sandiAcak();
                $this->db->table('admins')->insert([
                    'full_name' => mb_substr($w['guru_nama'], 0, 150), 'email' => $username . '@' . self::DOMAIN_EMAIL, 'username' => $username,
                    'password' => password_hash($sandi, PASSWORD_DEFAULT), 'phone' => $w['no_wa'] !== null ? mb_substr((string) preg_replace('/[^0-9+]/', '', $w['no_wa']), 0, 20) : null,
                    'role' => 'wali_kelas', 'guru_id' => $w['guru_id'], 'aktif' => 1, 'wajib_ganti_sandi' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $dibuat[] = ['id' => (int) $this->db->insertID(), 'nama' => $w['guru_nama'], 'username' => $username, 'sandi' => $sandi, 'no_wa' => $w['no_wa'], 'kelas' => $w['kelas']];
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[WaliKelas] buat akun massal gagal: ' . $e->getMessage());

            return ['ok' => false, 'pesan' => 'Pembuatan akun gagal, tidak ada yang tersimpan. Coba lagi.', 'dibuat' => [], 'ditautkan' => []];
        }
        $this->db->transCommit();
        self::lupakan();
        $audit = new AuditModel();
        foreach ($dibuat as $d) {
            $audit->record('create', 'admins', $d['id'], mb_substr('Akun wali kelas dibuat otomatis: ' . $d['nama'] . ' (' . $d['username'] . '), wali ' . implode(', ', $d['kelas']) . ' — oleh ' . ($konteks['oleh'] ?? 'Admin'), 0, 255));
        }
        foreach ($ditautkan as $d) {
            $audit->record('update', 'admins', null, mb_substr('Akun ' . $d['nama'] . ' (' . $d['peran'] . ') dihubungkan sebagai wali ' . implode(', ', $d['kelas']) . ' — oleh ' . ($konteks['oleh'] ?? 'Admin'), 0, 255));
        }

        return ['ok' => true, 'pesan' => count($dibuat) . ' akun wali kelas dibuat' . ($ditautkan !== [] ? ', ' . count($ditautkan) . ' akun lama dihubungkan' : '') . '.', 'dibuat' => $dibuat, 'ditautkan' => $ditautkan];
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** "Puguh Wira Sakti, S.Pd" → "puguh wira sakti" (tanpa gelar di belakang koma, spasi rapi, huruf kecil). */
    public static function namaPolos(string $nama): string
    {
        $nama = explode(',', $nama)[0];

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $nama)));
    }

    /** Username unik dari nama guru: dua kata pertama dipisah titik, huruf a-z saja (mis. "puguh.wira"), minimal 4 huruf. */
    public function usernameDari(string $nama): string
    {
        $kata  = array_values(array_filter(explode(' ', (string) preg_replace('/[^a-z ]/', '', strtolower(self::ascii(self::namaPolos($nama)))))));
        $dasar = implode('.', array_slice($kata, 0, 2));
        if (strlen($dasar) < 4) {
            $dasar = str_pad($dasar !== '' ? $dasar : 'wali', 4, '0');
        }
        $dasar    = substr($dasar, 0, 40);
        $kandidat = $dasar;
        for ($n = 2; $this->db->table('admins')->where('username', $kandidat)->countAllResults() > 0
            || $this->db->table('admins')->where('email', $kandidat . '@' . self::DOMAIN_EMAIL)->countAllResults() > 0; $n++) {
            $kandidat = $dasar . $n;
        }

        return $kandidat;
    }

    private static function ascii(string $s): string
    {
        $t = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : false;

        return $t !== false ? $t : $s;
    }

    /** 10 karakter acak (huruf besar, kecil, angka; tanpa karakter yang mirip) — sama dengan sandi sementara Kelola Akun. */
    public static function sandiAcak(int $panjang = 10): string
    {
        $semua = self::HURUF_BESAR . self::HURUF_KECIL . self::ANGKA;
        $pilih = static fn (string $set): string => $set[random_int(0, strlen($set) - 1)];
        $huruf = [$pilih(self::HURUF_BESAR), $pilih(self::HURUF_KECIL), $pilih(self::ANGKA)];
        while (count($huruf) < $panjang) {
            $huruf[] = $pilih($semua);
        }
        for ($i = count($huruf) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$huruf[$i], $huruf[$j]] = [$huruf[$j], $huruf[$i]];
        }

        return implode('', $huruf);
    }
}
