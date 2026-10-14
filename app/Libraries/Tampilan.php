<?php

namespace App\Libraries;

use Config\Tampilan as TampilanConfig;

/**
 * Pembantu peta rombak tampilan admin (Config\Tampilan): grup sebuah alamat / berkas view, dan versi Alpine yang dimuat.
 * Rancangan: docs/RENCANA-UPGRADE-TAMPILAN.md §5a (masa transisi Alpine).
 */
final class Tampilan
{
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
