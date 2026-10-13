<?php

namespace App\Libraries;

use App\Models\AuditModel;
use Config\Peran;

/**
 * Mesin hak akses yang DIATUR ADMIN (Sistem → Hak Akses). Rancangan: docs/DESAIN-HAK-AKSES.md.
 *
 * Sumber data: tabel `peran` (satu baris per peran: label, beranda, boleh aplikasi, peta menu, daftar hak rinci PKL/Surat).
 * Tabel belum ada (hosting belum `php spark migrate`) → peran dibaca dari BAWAAN = aturan lama (Config\Peran +
 * pkl_pengaturan.hak_peran), jadi kode baru tidak pernah mengubah perilaku sebelum migrasi.
 *
 * Aturan pokok:
 *   - Admin selalu penuh (tak bisa dicabut) → sistem tak pernah terkunci.
 *   - Alamat dicocokkan ke katalog menu (MenuAdmin, awalan terpanjang). Tak cocok entri mana pun = khusus Admin.
 *   - Menu: GET/HEAD butuh Lihat atau Kelola; metode lain (simpan/hapus) butuh Kelola.
 *   - Modul (PKL, Surat Sekolah): butuh Boleh + hak rinci per alamat (PklHak::hakUntukAlamat).
 *   - Peran tak dikenal / kosong = tidak boleh apa pun.
 * Dibaca lewat fasad Libraries\HakAkses oleh penjaga rute (AuthFilter), menu samping, gerbang API, dan controller.
 */
final class HakPeran
{
    public const ADMIN = 'admin';

    /** Peran bawaan sistem (tak bisa dihapus). */
    public const SISTEM = ['admin', 'operator', 'hubin'];

    /** Pola kode peran: huruf kecil/angka/garis bawah, 3–20 huruf, diawali huruf. */
    public const POLA_KODE = '/^[a-z][a-z0-9_]{2,19}$/';

    /** @var array<string, array<string, mixed>>|null kode => peran */
    private static ?array $cache = null;
    private static ?bool $tabelAda = null;

    // =================================================================
    // Membaca
    // =================================================================

    /** @return array<string, array<string, mixed>> kode => peran (urut: urut, label) */
    public static function semua(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $daftar = null;
        if (self::tabelAda()) {
            try {
                $daftar = [];
                $bawaan = null;
                foreach (db_connect()->table('peran')->orderBy('urut', 'ASC')->orderBy('label', 'ASC')->get()->getResultArray() as $r) {
                    $p = self::dariBaris($r);
                    // Isian JSON rusak (bukan tulisan sistem) pada peran bawaan → pakai bawaannya (aman, seperti aturan lama).
                    if ($p['rusak'] !== [] && isset(config(Peran::class)->peran[$p['kode']])) {
                        $bawaan ??= self::bawaan();
                        foreach ($p['rusak'] as $kolom) {
                            $p[$kolom] = $bawaan[$p['kode']][$kolom] ?? [];
                        }
                    }
                    unset($p['rusak']);
                    $daftar[$p['kode']] = $p;
                }
            } catch (\Throwable $e) {
                log_message('error', '[HakPeran] gagal membaca tabel peran: ' . $e->getMessage());
                $daftar = null;
            }
        }
        if ($daftar === null || $daftar === []) {
            $daftar = self::bawaan();
        }
        // Admin wajib ada & penuh, apa pun isi tabelnya (baris hilang/rusak → dari Config\Peran).
        if (! isset($daftar[self::ADMIN])) {
            $c = config(Peran::class)->peran[self::ADMIN] ?? ['label' => 'Admin'];
            $daftar = [self::ADMIN => [
                'kode' => self::ADMIN, 'label' => (string) $c['label'], 'ringkas' => (string) ($c['ringkas'] ?? ''),
                'beranda' => (string) ($c['beranda'] ?? 'admin/dashboard'), 'api' => true, 'urut' => 0, 'menu' => [], 'hak' => [],
            ]] + $daftar;
        }
        $daftar[self::ADMIN]['penuh']  = true;
        $daftar[self::ADMIN]['sistem'] = true;

        return self::$cache = $daftar;
    }

    /** Peran, atau null bila tak dikenal. */
    public static function peran(?string $kode): ?array
    {
        $kode = (string) $kode;

        return $kode !== '' ? (self::semua()[$kode] ?? null) : null;
    }

    public static function dikenal(?string $kode): bool
    {
        return self::peran($kode) !== null;
    }

    public static function label(?string $kode): string
    {
        $p = self::peran($kode);
        if ($p !== null) {
            return (string) $p['label'];
        }

        return trim((string) $kode) !== '' ? ucfirst((string) $kode) . ' (tak dikenal)' : 'Tanpa peran';
    }

    /** Kode peran yang haknya bisa diatur (semua selain Admin), urut seperti semua(). @return list<string> */
    public static function peranAtur(): array
    {
        return array_values(array_filter(array_keys(self::semua()), static fn (string $k): bool => $k !== self::ADMIN));
    }

    /** Daftar hak rinci (PKL/Surat) yang dipegang peran. Admin: semua kunci yang dikenal. @return list<string> */
    public static function hakDaftar(?string $kode): array
    {
        $p = self::peran($kode);
        if ($p === null) {
            return [];
        }

        return ! empty($p['penuh']) ? PklHak::semuaKunci() : array_values($p['hak'] ?? []);
    }

    public static function punyaHak(?string $kode, string $hak): bool
    {
        $p = self::peran($kode);
        if ($p === null) {
            return false;
        }

        return ! empty($p['penuh']) || in_array($hak, $p['hak'] ?? [], true);
    }

    /**
     * Level peran pada satu entri katalog: 'kelola' | 'lihat' | 'boleh' | null. Admin: level tertinggi. Entri 'ikut' memakai level
     * tertinggi dari entri yang diikutinya.
     */
    public static function level(?string $kode, array $entri): ?string
    {
        $p = self::peran($kode);
        if ($p === null) {
            return null;
        }
        if ($entri['jenis'] === MenuAdmin::UMUM) {
            return MenuAdmin::KELOLA;
        }
        if ($entri['jenis'] === MenuAdmin::ADMIN) {
            return ! empty($p['penuh']) ? MenuAdmin::KELOLA : null;
        }
        if (! empty($p['penuh'])) {
            return $entri['jenis'] === MenuAdmin::MODUL ? MenuAdmin::BOLEH : MenuAdmin::KELOLA;
        }
        if ($entri['ikut'] !== []) {
            $terbaik = null;
            foreach ($entri['ikut'] as $k) {
                $e  = MenuAdmin::entri($k);
                $lv = $e !== null ? self::level($kode, $e) : null;
                if ($lv === MenuAdmin::KELOLA || $lv === MenuAdmin::BOLEH) {
                    return $lv;
                }
                $terbaik ??= $lv;
            }

            return $terbaik;
        }
        $lv = $p['menu'][$entri['kunci']] ?? null;

        return is_string($lv) && MenuAdmin::levelSah($entri, $lv) ? $lv : null;
    }

    /** Level peran pada alamat (entri yang mencakupnya), atau null. */
    public static function levelAlamat(?string $kode, string $alamat): ?string
    {
        $e = MenuAdmin::cariAlamat(self::rapikan($alamat));

        return $e !== null ? self::level($kode, $e) : null;
    }

    /**
     * Bolehkah peran ini membuka alamat ini dengan metode ini?
     *
     * @param string $alamat jalur relatif tanpa domain, mis. "admin/master/siswa/12"
     * @param string $metode GET | POST | … (huruf besar/kecil sama saja)
     */
    public static function boleh(?string $kode, string $alamat, string $metode = 'GET'): bool
    {
        $p = self::peran($kode);
        if ($p === null) {
            return false;
        }
        $alamat = self::rapikan($alamat);
        if (str_contains($alamat, '..')) { // jalur penyusup (admin/pkl/../master/…) tak pernah lolos
            return false;
        }
        foreach (config(Peran::class)->umum as $awalan) {
            if (self::cocokAwalan($alamat, (string) $awalan)) {
                return true;
            }
        }
        if (! empty($p['penuh'])) {
            return true;
        }

        $e = MenuAdmin::cariAlamat($alamat);
        if ($e === null) {
            return false;
        }
        $lv = self::level($kode, $e);
        if ($lv === null) {
            return false;
        }
        if ($e['jenis'] === MenuAdmin::MODUL) {
            $hak = PklHak::hakUntukAlamat($alamat, $metode);
            if ($hak === null) {
                return true;
            }

            return $hak !== 'khusus_admin' && PklHak::boleh($kode, $hak);
        }
        if ($e['jenis'] === MenuAdmin::MENU && ! self::metodeBaca($metode)) {
            return $lv === MenuAdmin::KELOLA;
        }

        return true;
    }

    /** GET/HEAD/OPTIONS = membaca; selain itu mengubah. */
    public static function metodeBaca(string $metode): bool
    {
        return in_array(strtoupper($metode), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /** Halaman pertama setelah login: beranda peran bila boleh dibuka, selain itu menu pertama yang boleh, selain itu Profil. */
    public static function beranda(?string $kode): string
    {
        $p = self::peran($kode);
        if ($p === null) {
            return 'admin/profile';
        }
        $b = trim((string) ($p['beranda'] ?? ''), '/');
        if ($b !== '' && self::boleh($kode, $b)) {
            return $b;
        }
        foreach (MenuAdmin::sidebar() as $g) {
            foreach ($g['items'] as $it) {
                if (empty($it[4]) && (string) $it[0] !== 'admin/profile' && self::boleh($kode, (string) $it[0])) {
                    return (string) $it[0];
                }
            }
        }

        return 'admin/profile';
    }

    // ----- aplikasi Android -------------------------------------------

    public static function bolehApi(?string $kode): bool
    {
        $p = self::peran($kode);

        return $p !== null && ! empty($p['api']);
    }

    /**
     * Awalan alamat API yang boleh dipakai: ['*'] = semua (Admin), daftar awalan modul yang dipegang (mis. ['pkl', 'surat']),
     * [] = tidak boleh memakai aplikasi. Peran selain Admin hanya bisa memakai modul yang punya API ('api' di katalog).
     *
     * @return list<string>
     */
    public static function aksesApi(?string $kode): array
    {
        $p = self::peran($kode);
        if ($p === null || empty($p['api'])) {
            return [];
        }
        if (! empty($p['penuh'])) {
            return ['*'];
        }
        $out = [];
        foreach (MenuAdmin::katalog() as $e) {
            if ($e['api'] !== [] && self::level($kode, $e) !== null) {
                array_push($out, ...$e['api']);
            }
        }

        return array_values(array_unique($out));
    }

    /** Bolehkah peran ini memakai alamat API ini (tanpa "api/v1/")? Hak rinci diperiksa lagi di controller. */
    public static function bolehApiAlamat(?string $kode, string $alamat): bool
    {
        $akses = self::aksesApi($kode);
        if ($akses === []) {
            return false;
        }
        if (in_array('*', $akses, true)) {
            return true;
        }
        $alamat = self::rapikan($alamat);
        if (str_contains($alamat, '..')) {
            return false;
        }
        foreach (array_merge(config(Peran::class)->umum, $akses) as $awalan) {
            if (self::cocokAwalan($alamat, (string) $awalan)) {
                return true;
            }
        }

        return false;
    }

    // =================================================================
    // Bawaan (aturan lama) & penyimpanan
    // =================================================================

    /**
     * Peran BAWAAN = aturan sebelum hak bisa diatur: Config\Peran (label, beranda, menu, aplikasi) + hak PKL yang dulu tersimpan
     * di pkl_pengaturan.hak_peran (diperluas ke hak per jenis surat), atau hak bawaan PklHak bila kosong.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function bawaan(): array
    {
        $hakLama = PklHak::hakLamaTersimpan();
        $out     = [];
        $urut    = 0;
        foreach (config(Peran::class)->peran as $kode => $c) {
            $menu  = [];
            $penuh = in_array('*', (array) ($c['akses'] ?? []), true);
            if (! $penuh) {
                foreach ((array) ($c['akses'] ?? []) as $awalan) {
                    $e = MenuAdmin::cariAlamat(self::rapikan((string) $awalan));
                    if ($e !== null && in_array($e['jenis'], [MenuAdmin::MENU, MenuAdmin::MODUL], true)) {
                        $menu[$e['kunci']] = $e['jenis'] === MenuAdmin::MODUL ? MenuAdmin::BOLEH : MenuAdmin::KELOLA;
                    }
                }
            }
            $out[(string) $kode] = [
                'kode'    => (string) $kode,
                'label'   => (string) $c['label'],
                'ringkas' => (string) ($c['ringkas'] ?? ''),
                'beranda' => (string) ($c['beranda'] ?? ''),
                'api'     => ! empty($c['api']),
                'sistem'  => true,
                'urut'    => ++$urut,
                'menu'    => $menu,
                'hak'     => $penuh ? [] : ($hakLama[$kode] ?? PklHak::bawaanLengkap()[$kode] ?? []),
                'penuh'   => $penuh,
            ];
        }

        return $out;
    }

    /** Tabel peran sudah ada (migrasi 2026-10-17-000001 sudah dijalankan)? */
    public static function tabelAda(): bool
    {
        if (self::$tabelAda === null) {
            try {
                self::$tabelAda = db_connect()->tableExists('peran');
            } catch (\Throwable $e) {
                self::$tabelAda = false;
            }
        }

        return self::$tabelAda;
    }

    /** Lupakan simpanan per-permintaan (setelah menyimpan, dan oleh uji). */
    public static function lupakan(): void
    {
        self::$cache    = null;
        self::$tabelAda = null;
        PklHak::lupakanLama();
    }

    /**
     * Simpan daftar hak rinci (PKL/Surat) untuk beberapa peran sekaligus. Kunci asing dibuang; Admin diabaikan.
     *
     * @param array<string, list<string>> $peta kode peran => daftar kunci hak
     */
    public static function simpanHak(array $peta): bool
    {
        if (! self::tabelAda()) {
            return false;
        }
        $kenal = PklHak::semuaKunci();
        $baris = [];
        foreach ($peta as $kode => $daftar) {
            if ($kode !== self::ADMIN && self::dikenal($kode)) {
                $baris[$kode] = ['hak' => json_encode(array_values(array_intersect($kenal, array_map('strval', (array) $daftar))))]; // urutan baku
            }
        }

        return self::perbaruiBaris($baris);
    }

    /**
     * Perbarui beberapa baris peran dalam SATU transaksi manual (bukan transStart/transComplete: pada mode strict CodeIgniter,
     * satu kueri gagal di transaksi sebelumnya membuat semua transComplete berikutnya diam-diam dibatalkan).
     *
     * @param array<string, array<string, mixed>> $baris kode peran => kolom yang diubah
     */
    private static function perbaruiBaris(array $baris): bool
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        try {
            foreach ($baris as $kode => $kolom) {
                if ($db->table('peran')->where('kode', (string) $kode)->update($kolom + ['updated_at' => $now]) === false) {
                    throw new \RuntimeException('baris peran ' . $kode . ' tidak tersimpan');
                }
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[HakPeran] simpan gagal: ' . $e->getMessage());
            self::lupakan();

            return false;
        }
        self::lupakan();

        return true;
    }

    /**
     * Simpan peta menu beberapa peran. Level yang tidak sah untuk entrinya, entri admin/umum/ikut, dan kunci asing dibuang.
     *
     * @param array<string, array<string, string>> $peta kode peran => [kunci entri => level]
     */
    public static function simpanMenu(array $peta): bool
    {
        if (! self::tabelAda()) {
            return false;
        }
        $baris = [];
        foreach ($peta as $kode => $menu) {
            if ($kode !== self::ADMIN && self::dikenal($kode)) {
                $baris[$kode] = ['menu' => json_encode(self::rapikanMenu((array) $menu), JSON_FORCE_OBJECT)];
            }
        }

        return self::perbaruiBaris($baris);
    }

    /**
     * @param array<string, mixed> $menu
     *
     * @return array<string, string>
     */
    public static function rapikanMenu(array $menu): array
    {
        $out = [];
        foreach (MenuAdmin::katalog() as $kunci => $e) {
            $lv = $menu[$kunci] ?? null;
            if ($e['tampil'] && in_array($e['jenis'], [MenuAdmin::MENU, MenuAdmin::MODUL], true) && is_string($lv) && MenuAdmin::levelSah($e, $lv)) {
                $out[$kunci] = $lv;
            }
        }

        return $out;
    }

    /**
     * Tambah peran baru. $data: label, ringkas, beranda, api (bool), salin (kode peran yang menu & haknya disalin, opsional).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $konteks ['oleh', …]
     *
     * @return array{ok: bool, pesan: string, kode?: string}
     */
    public static function tambahPeran(array $data, array $konteks): array
    {
        if (! self::tabelAda()) {
            return ['ok' => false, 'pesan' => 'Tabel peran belum ada. Jalankan "php spark migrate" di server lebih dulu.'];
        }
        $label = IsianBantu::rapikan((string) ($data['label'] ?? ''));
        if (mb_strlen($label) < 3 || mb_strlen($label) > 60) {
            return ['ok' => false, 'pesan' => 'Nama peran harus 3–60 huruf.'];
        }
        foreach (self::semua() as $p) {
            if (mb_strtolower((string) $p['label']) === mb_strtolower($label)) {
                return ['ok' => false, 'pesan' => 'Nama peran "' . $label . '" sudah ada.'];
            }
        }
        $kode = self::kodeDari($label);
        $salin = self::peran((string) ($data['salin'] ?? ''));
        if ($salin !== null && ! empty($salin['penuh'])) {
            $salin = null; // menyalin Admin = memberi semua hak; tidak diizinkan diam-diam
        }
        $now = date('Y-m-d H:i:s');
        $urut = (int) (db_connect()->table('peran')->selectMax('urut', 'm')->get()->getRowArray()['m'] ?? 0) + 1;
        $ok = db_connect()->table('peran')->insert([
            'kode'    => $kode,
            'label'   => $label,
            'ringkas' => mb_substr(IsianBantu::rapikan((string) ($data['ringkas'] ?? '')), 0, 300),
            'beranda' => mb_substr(trim((string) ($data['beranda'] ?? ''), '/'), 0, 120),
            'api'     => ! empty($data['api']) ? 1 : 0,
            'sistem'  => 0,
            'urut'    => $urut,
            'menu'    => json_encode($salin !== null ? self::rapikanMenu($salin['menu'] ?? []) : [], JSON_FORCE_OBJECT),
            'hak'     => json_encode($salin !== null ? array_values($salin['hak'] ?? []) : []),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        self::lupakan();
        if (! $ok) {
            return ['ok' => false, 'pesan' => 'Peran gagal disimpan. Coba lagi.'];
        }
        (new AuditModel())->record('create', 'peran', null, mb_substr('Peran baru "' . $label . '" (' . $kode . ') dibuat oleh ' . ($konteks['oleh'] ?? '?')
            . ($salin !== null ? ', hak disalin dari ' . $salin['label'] : ', tanpa hak'), 0, 255));

        return ['ok' => true, 'pesan' => 'Peran "' . $label . '" dibuat.' . ($salin !== null ? ' Menu dan haknya disalin dari ' . $salin['label'] . '.' : ' Atur menunya di tabel di bawah.'), 'kode' => $kode];
    }

    /**
     * Ubah label/ringkas/beranda/aplikasi sebuah peran (bukan Admin).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, pesan: string}
     */
    public static function ubahPeran(string $kode, array $data, array $konteks): array
    {
        $p = self::peran($kode);
        if ($p === null || ! self::tabelAda()) {
            return ['ok' => false, 'pesan' => 'Peran tidak ditemukan.'];
        }
        if (! empty($p['penuh'])) {
            return ['ok' => false, 'pesan' => 'Peran Admin tidak bisa diubah.'];
        }
        $label = IsianBantu::rapikan((string) ($data['label'] ?? ''));
        if (mb_strlen($label) < 3 || mb_strlen($label) > 60) {
            return ['ok' => false, 'pesan' => 'Nama peran harus 3–60 huruf.'];
        }
        foreach (self::semua() as $k => $lain) {
            if ($k !== $kode && mb_strtolower((string) $lain['label']) === mb_strtolower($label)) {
                return ['ok' => false, 'pesan' => 'Nama peran "' . $label . '" sudah dipakai peran lain.'];
            }
        }
        $beranda = trim((string) ($data['beranda'] ?? ''), '/');
        db_connect()->table('peran')->where('kode', $kode)->update([
            'label'   => $label,
            'ringkas' => mb_substr(IsianBantu::rapikan((string) ($data['ringkas'] ?? '')), 0, 300),
            'beranda' => mb_substr($beranda, 0, 120),
            'api'     => ! empty($data['api']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::lupakan();
        $ubah = [];
        if ($label !== $p['label']) {
            $ubah[] = 'nama "' . $p['label'] . '" → "' . $label . '"';
        }
        if ($beranda !== trim((string) $p['beranda'], '/')) {
            $ubah[] = 'beranda → ' . ($beranda !== '' ? $beranda : '(otomatis)');
        }
        if (! empty($data['api']) !== ! empty($p['api'])) {
            $ubah[] = ! empty($data['api']) ? 'boleh aplikasi Android' : 'tanpa aplikasi Android';
        }
        (new AuditModel())->record('update', 'peran', null, mb_substr('Peran ' . $kode . ' diubah oleh ' . ($konteks['oleh'] ?? '?') . ': ' . ($ubah === [] ? '(keterangan saja)' : implode('; ', $ubah)), 0, 255));

        return ['ok' => true, 'pesan' => 'Peran "' . $label . '" disimpan.'];
    }

    /**
     * Hapus peran buatan (bukan bawaan sistem) yang tidak dipakai akun mana pun.
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, pesan: string}
     */
    public static function hapusPeran(string $kode, array $konteks): array
    {
        $p = self::peran($kode);
        if ($p === null || ! self::tabelAda()) {
            return ['ok' => false, 'pesan' => 'Peran tidak ditemukan.'];
        }
        if (! empty($p['sistem']) || in_array($kode, self::SISTEM, true)) {
            return ['ok' => false, 'pesan' => 'Peran bawaan sistem (' . $p['label'] . ') tidak bisa dihapus. Hak-haknya tetap bisa diatur.'];
        }
        $dipakai = (int) db_connect()->table('admins')->where('role', $kode)->countAllResults();
        if ($dipakai > 0) {
            return ['ok' => false, 'pesan' => 'Peran "' . $p['label'] . '" masih dipakai ' . $dipakai . ' akun. Ganti dulu peran akun-akun itu di Kelola Akun.'];
        }
        db_connect()->table('peran')->where('kode', $kode)->where('sistem', 0)->delete();
        self::lupakan();
        (new AuditModel())->record('delete', 'peran', null, mb_substr('Peran "' . $p['label'] . '" (' . $kode . ') dihapus oleh ' . ($konteks['oleh'] ?? '?'), 0, 255));

        return ['ok' => true, 'pesan' => 'Peran "' . $p['label'] . '" dihapus.'];
    }

    /** Kode unik dari nama peran: "Waka Kesiswaan" → "waka_kesiswaan" (dipotong 20 huruf, diberi angka bila bentrok). */
    public static function kodeDari(string $label): string
    {
        $dasar = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label)), '_'));
        if ($dasar === '' || ! ctype_alpha($dasar[0])) {
            $dasar = 'peran_' . $dasar;
        }
        $dasar = substr($dasar, 0, 17);
        if (strlen($dasar) < 3) {
            $dasar = str_pad($dasar, 3, 'x');
        }
        $kode = rtrim($dasar, '_');
        for ($n = 2; self::dikenal($kode) || in_array($kode, ['admin', 'impor', 'siswa', 'sistem'], true); $n++) {
            $kode = rtrim(substr($dasar, 0, 17), '_') . '_' . $n;
        }

        return $kode;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** @param array<string, mixed> $r baris tabel peran */
    private static function dariBaris(array $r): array
    {
        $menu  = json_decode((string) ($r['menu'] ?? ''), true);
        $hak   = json_decode((string) ($r['hak'] ?? ''), true);
        $kode  = (string) $r['kode'];
        $rusak = array_keys(array_filter(['menu' => ! is_array($menu), 'hak' => ! is_array($hak)]));

        return [
            'rusak'   => $rusak,
            'kode'    => $kode,
            'label'   => (string) $r['label'],
            'ringkas' => (string) ($r['ringkas'] ?? ''),
            'beranda' => (string) ($r['beranda'] ?? ''),
            'api'     => (int) ($r['api'] ?? 0) === 1,
            'sistem'  => (int) ($r['sistem'] ?? 0) === 1,
            'urut'    => (int) ($r['urut'] ?? 0),
            'menu'    => is_array($menu) ? array_filter($menu, 'is_string') : [],
            'hak'     => is_array($hak) ? array_values(array_filter($hak, 'is_string')) : [],
            'penuh'   => $kode === self::ADMIN,
        ];
    }

    /**
     * Apakah $alamat sama dengan $awalan atau berada di bawahnya? Batas segmen dijaga: "admin/pkl-lain" TIDAK cocok "admin/pkl".
     */
    public static function cocokAwalan(string $alamat, string $awalan): bool
    {
        $alamat = self::rapikan($alamat);
        $awalan = self::rapikan($awalan);

        return $alamat === $awalan || str_starts_with($alamat, $awalan . '/');
    }

    /** Samakan bentuk: huruf kecil, tanpa query/fragmen, tanpa garis miring ganda/ujung. */
    public static function rapikan(string $alamat): string
    {
        $alamat = strtolower($alamat);
        $alamat = preg_split('/[?#]/', $alamat, 2)[0];
        $alamat = preg_replace('#/{2,}#', '/', $alamat) ?? $alamat;

        return trim($alamat, '/');
    }
}
