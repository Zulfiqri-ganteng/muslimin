<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * Pengirim notifikasi Firebase Cloud Messaging (API HTTP v1), tanpa pustaka
 * Composer tambahan.
 *
 * Alur:  kunci service account (berkas JSON, path di .env FCM_KEY_PATH)
 *        → JWT RS256 bertanda tangan kunci privat
 *        → ditukar ke access token Google (di-cache ±50 menit)
 *        → POST https://fcm.googleapis.com/v1/projects/{id}/messages:send
 *
 * JWT di sini HANYA untuk server membuktikan diri ke Google; login aplikasi
 * tetap memakai token Bearer biasa. Berkas kunci WAJIB di luar folder repo
 * (repo publik) — lihat docs/DESAIN-ABSENSI-KELAS-NOTIF.md (B5, B9).
 */
class Fcm
{
    public const KANAL = 'jadwal_guru';

    private const SCOPE       = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URI   = 'https://oauth2.googleapis.com/token';
    private const CACHE_KUNCI = 'fcm_access_token';

    /** @var array<string,mixed>|false|null  null = belum dibaca, false = tidak ada */
    private static $kred;

    public static function pathKunci(): string
    {
        return trim((string) env('FCM_KEY_PATH', ''));
    }

    /** Isi berkas service account, atau null bila belum dipasang / tidak valid. */
    public static function kredensial(): ?array
    {
        if (self::$kred === null) {
            $p = self::pathKunci();
            $j = ($p !== '' && is_file($p) && is_readable($p)) ? json_decode((string) file_get_contents($p), true) : null;
            self::$kred = is_array($j) && ! empty($j['project_id']) && ! empty($j['client_email']) && ! empty($j['private_key'])
                ? $j
                : false;
        }

        return self::$kred ?: null;
    }

    /** Firebase sudah dipasang di server? */
    public static function siap(): bool
    {
        return self::kredensial() !== null;
    }

    public static function projectId(): ?string
    {
        return self::kredensial()['project_id'] ?? null;
    }

    /** Uji kunci ke Google (minta access token baru). null = berhasil, selain itu pesan galat. */
    public static function cekIzin(): ?string
    {
        try {
            self::aksesToken(true);

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * JWT RS256 untuk grant "jwt-bearer" OAuth2 service account Google.
     */
    public static function jwt(array $kred, int $now): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $kepala = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $klaim  = $b64((string) json_encode([
            'iss'   => $kred['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $kred['token_uri'] ?? self::TOKEN_URI,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $kunci = openssl_pkey_get_private((string) $kred['private_key']);
        if ($kunci === false) {
            throw new RuntimeException('Kunci privat Firebase tidak valid.');
        }
        $tanda = '';
        if (! openssl_sign($kepala . '.' . $klaim, $tanda, $kunci, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Gagal menandatangani JWT Firebase.');
        }

        return $kepala . '.' . $klaim . '.' . $b64($tanda);
    }

    /** Access token Google (cache). $baru = paksa minta ulang (mis. setelah 401). */
    private static function aksesToken(bool $baru = false): string
    {
        $kred  = self::kredensial() ?? throw new RuntimeException('Firebase belum dipasang di server (FCM_KEY_PATH).');
        $kunci = self::CACHE_KUNCI . '_' . md5((string) $kred['client_email']);
        $cache = cache();

        if (! $baru) {
            $simpan = $cache->get($kunci);
            if (is_string($simpan) && $simpan !== '') {
                return $simpan;
            }
        }

        $res = self::http()->post($kred['token_uri'] ?? self::TOKEN_URI, [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => self::jwt($kred, time()),
            ],
        ]);
        $j = json_decode($res->getBody(), true);
        if ($res->getStatusCode() !== 200 || empty($j['access_token'])) {
            throw new RuntimeException('Izin Google ditolak: ' . ($j['error_description'] ?? $j['error'] ?? ('HTTP ' . $res->getStatusCode())));
        }
        $cache->save($kunci, $j['access_token'], max(60, (int) ($j['expires_in'] ?? 3600) - 600));

        return $j['access_token'];
    }

    /**
     * Kirim satu notifikasi ke satu token HP.
     *
     * @param array<string,scalar> $data muatan tambahan (dibaca aplikasi saat notif diketuk)
     *
     * @return array{ok:bool,kode:?string,pesan:?string,token_mati:bool}
     */
    public static function kirim(string $token, string $judul, string $isi, array $data = []): array
    {
        try {
            $pid  = self::projectId() ?? throw new RuntimeException('Firebase belum dipasang di server (FCM_KEY_PATH).');
            $body = ['message' => [
                'token'        => $token,
                'notification' => ['title' => $judul, 'body' => $isi],
                'data'         => array_map('strval', $data),
                'android'      => [
                    'priority'     => 'HIGH',
                    'notification' => ['channel_id' => self::KANAL, 'sound' => 'default'],
                ],
            ]];
            $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($pid) . '/messages:send';

            for ($coba = 0; $coba < 2; $coba++) {
                $res  = self::http()->post($url, [
                    'headers' => ['Authorization' => 'Bearer ' . self::aksesToken($coba > 0)],
                    'json'    => $body,
                ]);
                $code = $res->getStatusCode();
                if ($code === 401 && $coba === 0) {
                    continue; // access token kedaluwarsa lebih cepat → minta ulang sekali
                }
                if ($code === 200) {
                    return ['ok' => true, 'kode' => null, 'pesan' => null, 'token_mati' => false];
                }

                $j     = json_decode($res->getBody(), true);
                $err   = $j['error'] ?? [];
                $kode  = (string) ($err['status'] ?? ('HTTP_' . $code));
                foreach ($err['details'] ?? [] as $d) {
                    if (! empty($d['errorCode'])) {
                        $kode = (string) $d['errorCode'];
                    }
                }
                $pesan = (string) ($err['message'] ?? ('HTTP ' . $code));
                $mati  = $kode === 'UNREGISTERED' || $code === 404
                    || ($kode === 'INVALID_ARGUMENT' && stripos($pesan, 'registration token') !== false);

                return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan, 'token_mati' => $mati];
            }

            return ['ok' => false, 'kode' => 'UNAUTHENTICATED', 'pesan' => 'Izin Google ditolak.', 'token_mati' => false];
        } catch (Throwable $e) {
            return ['ok' => false, 'kode' => 'GALAT', 'pesan' => $e->getMessage(), 'token_mati' => false];
        }
    }

    private static function http()
    {
        // Instans baru tiap panggilan: CURLRequest bersama menyimpan header/body lama.
        return \Config\Services::curlrequest(['timeout' => 15, 'http_errors' => false], null, null, false);
    }
}
