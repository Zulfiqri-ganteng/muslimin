<?php

namespace App\Libraries;

/**
 * Fasad hak akses — dipakai penjaga rute (AuthFilter), menu samping (layouts/admin), gerbang API (ApiAuthFilter / Api\Auth),
 * dan controller, supaya semuanya selalu sepakat. Keputusannya ada di Libraries\HakPeran (peran & menu yang diatur Admin di
 * Sistem → Hak Akses) dan Libraries\PklHak (hak rinci PKL & Surat Sekolah). Rancangan: docs/DESAIN-HAK-AKSES.md.
 * Prinsip: peran tak dikenal = TIDAK boleh apa pun; Admin selalu penuh.
 */
final class HakAkses
{
    /** @return array<string, array<string, mixed>> kode => peran (label, ringkas, beranda, api, menu, hak, …) */
    public static function semua(): array
    {
        return HakPeran::semua();
    }

    /** Entri peran, atau null bila peran tak dikenal. */
    public static function peran(?string $peran): ?array
    {
        return HakPeran::peran($peran);
    }

    public static function dikenal(?string $peran): bool
    {
        return HakPeran::dikenal($peran);
    }

    public static function label(?string $peran): string
    {
        return HakPeran::label($peran);
    }

    /** Halaman pertama setelah login. Peran tak dikenal diarahkan ke Profil (aman). */
    public static function beranda(?string $peran): string
    {
        return HakPeran::beranda($peran);
    }

    /** Boleh memakai aplikasi Android (token API)? */
    public static function bolehApi(?string $peran): bool
    {
        return HakPeran::bolehApi($peran);
    }

    /** Boleh menyetujui (ACC) ajuan PKL? Diatur Admin (hak 'acc'); Admin selalu boleh. */
    public static function bolehAcc(?string $peran): bool
    {
        return PklHak::boleh($peran, 'acc');
    }

    /** Hak rinci PKL/Surat yang diatur Admin — lihat PklHak (satu kunci, 'surat_acc:*', atau 'a|b'). */
    public static function bolehPkl(?string $peran, string $hak): bool
    {
        return PklHak::boleh($peran, $hak);
    }

    /**
     * Daftar awalan alamat API yang boleh dipakai peran ini, untuk aplikasi (menyusun menu): ['*'] = semua
     * (Admin), ['pkl', 'surat'] = modul yang dipegang, [] = tidak boleh memakai API.
     *
     * @return list<string>
     */
    public static function aksesApi(?string $peran): array
    {
        return HakPeran::aksesApi($peran);
    }

    /** Bolehkah peran ini memakai alamat API ini (tanpa "api/v1/")? */
    public static function bolehApiAlamat(?string $peran, string $alamat): bool
    {
        return HakPeran::bolehApiAlamat($peran, $alamat);
    }

    /**
     * Bolehkah peran ini membuka alamat ini? Tanpa $metode = GET (membuka/melihat) — dipakai menu samping & tombol;
     * penjaga rute mengirim metode permintaan sebenarnya (menu "Lihat saja" menolak POST).
     *
     * @param string $alamat jalur relatif tanpa domain, mis. "admin/master/siswa/12"
     */
    public static function boleh(?string $peran, string $alamat, string $metode = 'GET'): bool
    {
        return HakPeran::boleh($peran, $alamat, $metode);
    }

    /** Level peran pada halaman ini: 'kelola' | 'lihat' | 'boleh' | null. */
    public static function levelAlamat(?string $peran, string $alamat): ?string
    {
        return HakPeran::levelAlamat($peran, $alamat);
    }

    /**
     * Penolakan baku untuk pagar kedua di controller (setelah penjaga rute). Peran yang hanya boleh MELIHAT halaman itu
     * (menu "Lihat saja") lalu mencoba mengubah → kembali ke halamannya dengan pesan "hanya bisa melihat"; tanpa hak sama
     * sekali → ke beranda perannya.
     *
     * @param string $alamat halaman yang dijaga, mis. "admin/skbm"
     * @param string $nama   nama halaman untuk pesan, mis. "SKBM"
     *
     * @return array{0: string, 1: string} [alamat tujuan, pesan]
     */
    public static function tolak(?string $peran, string $alamat, string $nama): array
    {
        $lihat = HakPeran::boleh($peran, $alamat, 'GET');

        return [
            $lihat ? $alamat : HakPeran::beranda($peran),
            'Akun ' . self::label($peran) . ($lihat ? ' hanya bisa melihat ' . $nama . ', tidak bisa mengubahnya' : ' tidak punya hak membuka ' . $nama)
                . '. Admin bisa mengaturnya di Sistem → Hak Akses.',
        ];
    }

    /** Pagar kedua di controller: akun yang sedang login boleh menjalankan permintaan ini (alamat + metode sekarang)? */
    public static function bolehSekarang(): bool
    {
        $req = service('request');

        return HakPeran::boleh((string) (session('admin')['role'] ?? ''), uri_string(), method_exists($req, 'getMethod') ? (string) $req->getMethod() : 'GET');
    }

    /**
     * Apakah $alamat sama dengan $awalan atau berada di bawahnya?
     * Batas segmen dijaga: "admin/pkl-lain" TIDAK cocok dengan "admin/pkl".
     */
    public static function cocokAwalan(string $alamat, string $awalan): bool
    {
        return HakPeran::cocokAwalan($alamat, $awalan);
    }
}
