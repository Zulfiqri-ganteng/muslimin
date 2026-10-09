<?php

namespace App\Libraries;

use Config\Peran;

/**
 * Pembaca hak akses peran (data ada di Config\Peran).
 *
 * Dipakai tiga tempat supaya selalu sepakat: penjaga rute (AuthFilter), menu
 * samping (layouts/admin), dan gerbang API (ApiAuthFilter / Api\Auth).
 * Prinsip: peran tak dikenal = TIDAK boleh apa pun.
 */
final class HakAkses
{
    /** @return array<string, array<string, mixed>> */
    public static function semua(): array
    {
        return config(Peran::class)->peran;
    }

    /** Entri peran, atau null bila peran tak dikenal. */
    public static function peran(?string $peran): ?array
    {
        $peran = (string) $peran;

        return $peran !== '' ? (self::semua()[$peran] ?? null) : null;
    }

    public static function dikenal(?string $peran): bool
    {
        return self::peran($peran) !== null;
    }

    public static function label(?string $peran): string
    {
        $p = self::peran($peran);
        if ($p !== null) {
            return (string) $p['label'];
        }

        return trim((string) $peran) !== '' ? ucfirst((string) $peran) . ' (tak dikenal)' : 'Tanpa peran';
    }

    /** Halaman pertama setelah login. Peran tak dikenal diarahkan ke Profil (aman). */
    public static function beranda(?string $peran): string
    {
        $p = self::peran($peran);

        return $p !== null ? (string) $p['beranda'] : 'admin/profile';
    }

    /** Boleh memakai aplikasi Android (token API)? */
    public static function bolehApi(?string $peran): bool
    {
        $p = self::peran($peran);

        return $p !== null && ! empty($p['api']);
    }

    /**
     * Boleh menyetujui (ACC) ajuan PKL? Ditentukan Admin di PKL → Hak Akses (Libraries\PklHak).
     * Bawaan: Waka Hubin; Admin selalu boleh (cadangan, wajib menyatakan "mewakili").
     */
    public static function bolehAcc(?string $peran): bool
    {
        return self::bolehPkl($peran, 'acc');
    }

    /** Hak PKL yang diatur Admin (acc, surat, surat_sekolah, laporan, ubah, ttd, pengaturan, hapus) — lihat PklHak::HAK. */
    public static function bolehPkl(?string $peran, string $hak): bool
    {
        return self::peran($peran) !== null && PklHak::boleh($peran, $hak);
    }

    /**
     * Daftar awalan alamat API yang boleh dipakai peran ini, untuk aplikasi (menyusun menu): ['*'] = semua
     * (Admin), ['pkl'] = hanya modul PKL (Operator, Waka Hubin), [] = tidak boleh memakai API.
     *
     * @return list<string>
     */
    public static function aksesApi(?string $peran): array
    {
        $p = self::peran($peran);
        if ($p === null || empty($p['api'])) {
            return [];
        }
        if (in_array('*', (array) ($p['akses'] ?? []), true)) {
            return ['*'];
        }

        return array_values(array_map('strval', (array) ($p['api_akses'] ?? [])));
    }

    /**
     * Bolehkah peran ini memakai alamat API ini? Admin: semua. Peran lain: hanya awalan di 'api_akses'
     * (mis. 'pkl' → "pkl", "pkl/ajuan/12"). Alamat API tanpa "api/v1/".
     */
    public static function bolehApiAlamat(?string $peran, string $alamat): bool
    {
        $p = self::peran($peran);
        if ($p === null || empty($p['api'])) {
            return false;
        }
        if (in_array('*', (array) ($p['akses'] ?? []), true)) {
            return true;
        }
        if (str_contains($alamat, '..')) { // jalur penyusup (pkl/../admin/…) tak pernah lolos
            return false;
        }
        // Keperluan akun sendiri (profil, ganti sandi) terbuka bagi semua peran, sama seperti di web ('umum').
        foreach (config(Peran::class)->umum as $awalan) {
            if (self::cocokAwalan($alamat, (string) $awalan)) {
                return true;
            }
        }
        foreach ((array) ($p['api_akses'] ?? []) as $awalan) {
            if (self::cocokAwalan($alamat, (string) $awalan)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bolehkah peran ini membuka alamat ini?
     *
     * @param string $alamat jalur relatif tanpa domain, mis. "admin/master/siswa/12"
     */
    public static function boleh(?string $peran, string $alamat): bool
    {
        $p = self::peran($peran);
        if ($p === null) {
            return false;
        }

        $alamat = self::rapikan($alamat);
        if (str_contains($alamat, '..')) { // jalur penyusup (admin/pkl/../master/…) tak pernah lolos
            return false;
        }

        foreach (config(Peran::class)->umum as $awalan) {
            if (self::cocokAwalan($alamat, $awalan)) {
                return true;
            }
        }
        // Pengecualian didahulukan: ditolak walau induknya diizinkan.
        foreach ($p['kecuali'] ?? [] as $awalan) {
            if (self::cocokAwalan($alamat, $awalan)) {
                return false;
            }
        }
        foreach ($p['akses'] as $awalan) {
            if ($awalan === '*' || self::cocokAwalan($alamat, $awalan)) {
                return self::lolosHakPkl($peran, $alamat);
            }
        }

        return false;
    }

    /**
     * Lapis kedua khusus PKL: peran sudah punya akses "admin/pkl", tetapi sebagian alamatnya menuntut hak yang
     * diatur Admin (ACC, unduh surat, laporan, tanda tangan, pengaturan, hapus, ubah) atau khusus Admin.
     * Alamat PKL lain (beranda, daftar, detail, status siswa) terbuka bagi semua yang punya akses PKL.
     */
    private static function lolosHakPkl(?string $peran, string $alamat): bool
    {
        $hak = PklHak::hakUntukAlamat($alamat);
        if ($hak === null) {
            return true;
        }
        if ($hak === 'khusus_admin') {
            return (string) $peran === 'admin';
        }

        return PklHak::boleh($peran, $hak);
    }

    /**
     * Apakah $alamat sama dengan $awalan atau berada di bawahnya?
     * Batas segmen dijaga: "admin/pkl-lain" TIDAK cocok dengan "admin/pkl".
     */
    public static function cocokAwalan(string $alamat, string $awalan): bool
    {
        $alamat = self::rapikan($alamat);
        $awalan = self::rapikan($awalan);

        return $alamat === $awalan || str_starts_with($alamat, $awalan . '/');
    }

    /** Samakan bentuk: huruf kecil, tanpa query/fragmen, tanpa garis miring ganda/ujung. */
    private static function rapikan(string $alamat): string
    {
        $alamat = strtolower($alamat);
        $alamat = preg_split('/[?#]/', $alamat, 2)[0];
        $alamat = preg_replace('#/{2,}#', '/', $alamat) ?? $alamat;

        return trim($alamat, '/');
    }
}
