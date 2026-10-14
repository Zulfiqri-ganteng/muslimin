<?php

namespace App\Libraries;

use Config\Tampilan as TampilanConfig;

/**
 * Pembantu rombak tampilan admin (rancangan: docs/RENCANA-UPGRADE-TAMPILAN.md):
 *   - peta grup (Config\Tampilan): grup sebuah alamat / berkas view, dan versi Alpine yang dimuat (§5a masa transisi Alpine);
 *   - TEMA (§3, F3 + revisi R2 atas permintaan user): TIGA MODE — Terang (persis template Flowbite, bawaan), Gelap (mode gelap
 *     Flowbite di bingkai, kit & halaman), Warna Sekolah (lilac asli sekolah). Disimpan sebagai pasangan kolom settings.tema_warna ×
 *     settings.tema_sidebar (tanpa migrasi baru), diatur Admin di Pengaturan Sekolah → Tampilan. Layout & halaman masuk menulis
 *     <html data-mode="…" data-warna="…" data-sidebar="…"> dari server — tanpa skrip sebaris, tanpa kedipan; hosting yang belum
 *     menjalankan migrasinya tetap jalan dengan mode bawaan;
 *   - status menu samping dari cookie (ciut / grup terbuka, ditulis app.js) → dirender server, jadi menu tidak berkedip saat pindah halaman.
 */
final class Tampilan
{
    /** Warna utama: nilai settings.tema_warna. Ramp warnanya di resources/css/admin.css ([data-warna]). */
    public const WARNA = ['lilac' => 'Ungu Lilac', 'biru' => 'Biru Flowbite'];

    /** Gaya menu samping: nilai settings.tema_sidebar. Tokennya (--sb-*) di resources/css/admin.css ([data-sidebar]). */
    public const SIDEBAR = ['warna' => 'Berwarna', 'terang' => 'Terang', 'gelap' => 'Gelap'];

    /**
     * Tiga mode tampilan (kunci = nilai <html data-mode> & isian "pilihan" di Pengaturan) → pasangan kolom yang disimpan.
     *
     * @var array<string, array{nama: string, warna: string, sidebar: string, ket: string}>
     */
    public const MODE = [
        'terang'  => ['nama' => 'Terang', 'warna' => 'biru', 'sidebar' => 'terang',
            'ket' => 'Persis template Flowbite: latar putih, aksen biru. Bawaan.'],
        'gelap'   => ['nama' => 'Gelap', 'warna' => 'biru', 'sidebar' => 'gelap',
            'ket' => 'Mode gelap Flowbite: latar abu tua, tulisan terang — nyaman dipakai malam hari.'],
        'sekolah' => ['nama' => 'Warna Sekolah', 'warna' => 'lilac', 'sidebar' => 'warna',
            'ket' => 'Warna asli sekolah: menu samping ungu, aksen lilac & mint.'],
    ];

    public const BAWAAN_MODE = 'terang';

    /** Pasangan bawaan (kolom kosong / belum dimigrasi). */
    public const BAWAAN = ['warna' => 'biru', 'sidebar' => 'terang'];

    /**
     * Mode dari pasangan kolom tersimpan: menu samping gelap → Gelap; warna lilac → Warna Sekolah; selain itu (termasuk kosong) → Terang.
     * Kombinasi lama "Atur sendiri" (sebelum R2) ikut dipetakan ke salah satu dari tiga mode.
     */
    public static function modeDari(string $warna, string $sidebar): string
    {
        if ($sidebar === 'gelap') {
            return 'gelap';
        }

        return $warna === 'lilac' ? 'sekolah' : self::BAWAAN_MODE;
    }

    /**
     * Tema yang berlaku dari baris pengaturan sekolah — selalu salah satu dari tiga mode (pasangan warna & menu samping baku).
     *
     * @param array<string, mixed>|null $setting
     *
     * @return array{mode: string, warna: string, sidebar: string}
     */
    public static function tema(?array $setting): array
    {
        $mode = self::modeDari((string) ($setting['tema_warna'] ?? ''), (string) ($setting['tema_sidebar'] ?? ''));

        return ['mode' => $mode, 'warna' => self::MODE[$mode]['warna'], 'sidebar' => self::MODE[$mode]['sidebar']];
    }

    /** Nama mode untuk pesan ("Terang", "Gelap", "Warna Sekolah"). */
    public static function namaTema(string $mode): string
    {
        return self::MODE[$mode]['nama'] ?? self::MODE[self::BAWAAN_MODE]['nama'];
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
