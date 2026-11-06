<?php

namespace App\Libraries;

/**
 * Token API publik Layanan Siswa (aplikasi Android, tanpa akun; docs/DESAIN-PERPANJANGAN-PKL.md bagian 8b). Pengganti sesi web
 * LayananSiswa::SESI: setelah siswa membuktikan diri (LayananSiswa::cocokkan — NISN / tanggal lahir, dibatasi LoginThrottle) aplikasi
 * menerima token berumur sama dengan sesi web (LayananSiswa::TTL), dikirim di header `X-Layanan-Token`.
 *
 * Bentuk: base64url(JSON {v, s: siswa_id, e: kedaluwarsa unix}) . "." . base64url(HMAC-SHA256 isi, kunci aplikasi). Tanpa tabel,
 * tanpa data pribadi selain id siswa. Kunci = encryption.key di .env (kosong → token tidak bisa dibuat, API menjawab 503).
 */
final class LayananToken
{
    public const HEADER = 'X-Layanan-Token';
    public const VERSI  = 1;

    /** Kunci tanda tangan (biner) satu keperluan, '' bila encryption.key belum diatur. */
    private static function kunci(string $tujuan = 'layanan-siswa'): string
    {
        $k = (string) (config('Encryption')->key ?? '');
        if (str_starts_with($k, 'hex2bin:')) {
            $k = (string) hex2bin(substr($k, 8));
        } elseif (str_starts_with($k, 'base64:')) {
            $k = (string) base64_decode(substr($k, 7), true);
        }

        return $k !== '' ? hash('sha256', $tujuan . '|' . $k, true) : ''; // kunci turunan: token ini tak bisa dipakai untuk keperluan lain
    }

    public static function siap(): bool
    {
        return self::kunci() !== '';
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** @return array{token: string, berlaku_sampai: string}|null null bila kunci aplikasi belum diatur */
    public static function buat(int $siswaId, ?int $sekarang = null): ?array
    {
        $kunci = self::kunci();
        if ($kunci === '' || $siswaId <= 0) {
            return null;
        }
        $exp  = ($sekarang ?? time()) + LayananSiswa::TTL;
        $isi  = self::b64((string) json_encode(['v' => self::VERSI, 's' => $siswaId, 'e' => $exp]));
        $ttd  = self::b64(hash_hmac('sha256', $isi, $kunci, true));

        return ['token' => $isi . '.' . $ttd, 'berlaku_sampai' => date('Y-m-d H:i:s', $exp)];
    }

    /** Id siswa dari token yang sah & belum kedaluwarsa, atau null. */
    public static function baca(string $token, ?int $sekarang = null): ?int
    {
        $kunci = self::kunci();
        $bagi  = explode('.', trim($token));
        if ($kunci === '' || count($bagi) !== 2 || strlen($token) > 500) {
            return null;
        }
        [$isi, $ttd] = $bagi;
        if (! hash_equals(self::b64(hash_hmac('sha256', $isi, $kunci, true)), $ttd)) {
            return null;
        }
        $d = json_decode(self::unb64($isi), true);
        if (! is_array($d) || (int) ($d['v'] ?? 0) !== self::VERSI || (int) ($d['s'] ?? 0) <= 0 || (int) ($d['e'] ?? 0) < ($sekarang ?? time())) {
            return null;
        }

        return (int) $d['s'];
    }

    /**
     * Token IZIN formulir publik lain (mis. lapor diterima PKL, keperluan "pkl-lapor") — pengganti izin di sesi web yang berumur
     * [$umur] detik; dikirim di header `X-Izin-Token`. Kunci turunan per keperluan, jadi token satu keperluan tidak berlaku untuk
     * keperluan lain (juga tidak sebagai token Layanan Siswa). [$isi] = isi izin yang sama dengan isi sesi web.
     *
     * @param array<string, mixed> $isi
     *
     * @return array{token: string, berlaku_sampai: string}|null null bila kunci aplikasi belum diatur
     */
    public static function buatIzin(string $tujuan, array $isi, int $umur, ?int $sekarang = null): ?array
    {
        $kunci = self::kunci($tujuan);
        if ($kunci === '') {
            return null;
        }
        $exp  = ($sekarang ?? time()) + $umur;
        $muat = self::b64((string) json_encode(['v' => self::VERSI, 'i' => $isi, 'e' => $exp], JSON_UNESCAPED_UNICODE));
        $ttd  = self::b64(hash_hmac('sha256', $muat, $kunci, true));

        return ['token' => $muat . '.' . $ttd, 'berlaku_sampai' => date('Y-m-d H:i:s', $exp)];
    }

    /**
     * Isi token izin yang sah & belum kedaluwarsa untuk [$tujuan], atau null.
     *
     * @return array<string, mixed>|null
     */
    public static function bacaIzin(string $tujuan, string $token, ?int $sekarang = null): ?array
    {
        $kunci = self::kunci($tujuan);
        $bagi  = explode('.', trim($token));
        if ($kunci === '' || count($bagi) !== 2 || strlen($token) > 1000) {
            return null;
        }
        [$muat, $ttd] = $bagi;
        if (! hash_equals(self::b64(hash_hmac('sha256', $muat, $kunci, true)), $ttd)) {
            return null;
        }
        $d = json_decode(self::unb64($muat), true);
        if (! is_array($d) || (int) ($d['v'] ?? 0) !== self::VERSI || ! is_array($d['i'] ?? null) || (int) ($d['e'] ?? 0) < ($sekarang ?? time())) {
            return null;
        }

        return $d['i'];
    }
}
