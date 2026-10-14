<?php

namespace App\Libraries;

use Config\Tampilan as TampilanConfig;

/**
 * Pembantu rombak tampilan admin (rancangan: docs/RENCANA-UPGRADE-TAMPILAN.md):
 *   - peta grup (Config\Tampilan): grup sebuah alamat / berkas view, dan versi Alpine yang dimuat (§5a masa transisi Alpine);
 *   - TEMA (§3, F3): warna utama (settings.tema_warna) × gaya menu samping (settings.tema_sidebar), diatur Admin di Pengaturan
 *     Sekolah. Layout & halaman masuk menulis <html data-warna="…" data-sidebar="…"> dari server — tanpa skrip sebaris, tanpa kedipan;
 *     hosting yang belum menjalankan migrasinya tetap jalan dengan tema bawaan;
 *   - status menu samping dari cookie (ciut / grup terbuka, ditulis app.js) → dirender server, jadi menu tidak berkedip saat pindah halaman.
 */
final class Tampilan
{
    /** Warna utama: nilai settings.tema_warna => nama. Ramp warnanya di resources/css/admin.css ([data-warna]). */
    public const WARNA = ['lilac' => 'Ungu Lilac', 'biru' => 'Biru Flowbite'];

    /** Gaya menu samping: nilai settings.tema_sidebar => nama. Tokennya (--sb-*) di resources/css/admin.css ([data-sidebar]). */
    public const SIDEBAR = ['warna' => 'Berwarna', 'terang' => 'Terang', 'gelap' => 'Gelap'];

    public const BAWAAN = ['warna' => 'lilac', 'sidebar' => 'warna'];

    /** Tiga tema siap pakai (rancangan §3); kombinasi lain = "Atur sendiri". */
    public const PRESET = [
        ['nama' => 'Lilac Sekolah', 'warna' => 'lilac', 'sidebar' => 'warna', 'ket' => 'Ungu lilac dengan aksen mint; menu samping ungu tua. Bawaan.'],
        ['nama' => 'Flowbite Terang', 'warna' => 'biru', 'sidebar' => 'terang', 'ket' => 'Biru Flowbite; menu samping putih bergaris tipis.'],
        ['nama' => 'Flowbite Gelap', 'warna' => 'biru', 'sidebar' => 'gelap', 'ket' => 'Biru Flowbite; menu samping abu tua.'],
    ];

    /**
     * Tema yang berlaku dari baris pengaturan sekolah; nilai kosong/tak dikenal (mis. kolom belum ada) → bawaan.
     *
     * @param array<string, mixed>|null $setting
     *
     * @return array{warna: string, sidebar: string}
     */
    public static function tema(?array $setting): array
    {
        $w = (string) ($setting['tema_warna'] ?? '');
        $s = (string) ($setting['tema_sidebar'] ?? '');

        return [
            'warna'   => isset(self::WARNA[$w]) ? $w : self::BAWAAN['warna'],
            'sidebar' => isset(self::SIDEBAR[$s]) ? $s : self::BAWAAN['sidebar'],
        ];
    }

    /** Nama tema untuk pesan: nama tema siap pakai, atau "Atur sendiri (…)". */
    public static function namaTema(string $warna, string $sidebar): string
    {
        foreach (self::PRESET as $p) {
            if ($p['warna'] === $warna && $p['sidebar'] === $sidebar) {
                return $p['nama'];
            }
        }

        return 'Atur sendiri (' . (self::WARNA[$warna] ?? $warna) . ', menu samping ' . mb_strtolower(self::SIDEBAR[$sidebar] ?? $sidebar) . ')';
    }

    /** Kolom tema sudah ada di tabel settings (migrasi 2026-10-19-000001 sudah dijalankan)? */
    public static function temaSiap(): bool
    {
        $db = db_connect();

        return $db->fieldExists('tema_warna', 'settings') && $db->fieldExists('tema_sidebar', 'settings');
    }

    /** Menu samping sedang diciutkan (desktop)? Cookie sb_ciut ditulis app.js (adminLayout.alihCiut). */
    public static function sidebarCiut(): bool
    {
        return self::cookie('sb_ciut') === '1';
    }

    /**
     * Grup menu samping yang dibuka pengguna (cookie sb_grup: kunci "sb_g_xxxxxxxx" dipisah koma, ditulis app.js sbGrup).
     *
     * @return list<string>
     */
    public static function grupTerbuka(): array
    {
        return array_values(array_filter(explode(',', self::cookie('sb_grup')), static fn (string $k): bool => preg_match('/^sb_g_[0-9a-f]{8}$/', $k) === 1));
    }

    private static function cookie(string $nama): string
    {
        $req = service('request');

        return method_exists($req, 'getCookie') ? (string) ($req->getCookie($nama) ?? '') : '';
    }
    /**
     * Grup untuk sebuah alamat (jalur relatif, mis. "admin/master/siswa"): awalan TERPANJANG yang cocok per segmen.
     */
    public static function grupAlamat(string $alamat): ?string
    {
        $alamat  = trim(strtolower($alamat), '/');
        $pilih   = null;
        $panjang = -1;
        foreach (config(TampilanConfig::class)->grup as $kunci => $g) {
            foreach ($g['url'] as $awalan) {
                if (($alamat === $awalan || str_starts_with($alamat, $awalan . '/')) && strlen($awalan) > $panjang) {
                    $pilih   = $kunci;
                    $panjang = strlen($awalan);
                }
            }
        }

        return $pilih;
    }

    /**
     * Grup untuk sebuah berkas view (jalur relatif dari app/Views, garis miring "/"), berdasarkan berkas/folder terpanjang yang cocok.
     */
    public static function grupView(string $view): ?string
    {
        $pilih   = null;
        $panjang = -1;
        foreach (config(TampilanConfig::class)->grup as $kunci => $g) {
            foreach ($g['view'] as $v) {
                $cocok = str_ends_with($v, '.php') ? $view === $v : str_starts_with($view, rtrim($v, '/') . '/');
                if ($cocok && strlen($v) > $panjang) {
                    $pilih   = $kunci;
                    $panjang = strlen($v);
                }
            }
        }

        return $pilih;
    }

    /**
     * Halaman alat pengembang (admin/ui-kit) hanya di laptop: CI_ENVIRONMENT development, ATAU app.baseURL menunjuk localhost /
     * 127.0.0.1 (.env laptop memakai production; aturan sama dengan php spark dev:akun-uji). Di hosting selalu false.
     */
    public static function halamanLaptop(): bool
    {
        if (ENVIRONMENT === 'development') {
            return true;
        }
        $host = (string) parse_url((string) config('App')->baseURL, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1'], true);
    }

    /** Pakai Alpine versi CSP (tanpa eval) di alamat ini? */
    public static function alpineCsp(string $alamat): bool
    {
        $grup = self::grupAlamat($alamat);

        return $grup !== null && in_array($grup, config(TampilanConfig::class)->alpineCsp, true);
    }
}
