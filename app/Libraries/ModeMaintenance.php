<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Mode Maintenance (Sistem → Mode Maintenance; docs/DESAIN-ALAT-SISTEM.md F2). Saat aktif: situs publik, semua layanan siswa,
 * dan API Android membalas 503 + info perawatan; HANYA Admin yang tetap bisa bekerja (web & aplikasi). Keputusan user 2026-10-29.
 *
 * Status disimpan di BERKAS writable/maintenance.json (bukan database) supaya tetap bekerja walau database bermasalah —
 * halaman perawatan juga tidak menyentuh database. Jalan darurat: `phpm spark maintenance off`.
 */
class ModeMaintenance
{
    public const PESAN_BAWAAN = 'Sistem sedang dalam perawatan agar lebih baik. Silakan coba lagi beberapa saat lagi.';
    public const MAKS_PESAN   = 300;

    /** Jeda coba lagi (detik) untuk header Retry-After bila perkiraan selesai kosong/sudah lewat. */
    public const COBA_LAGI = 300;

    /** Alamat API (sesudah api/v1/) yang tetap terbuka: masuk/keluar akun, info aplikasi, status, rilis APK. */
    public const API_TERBUKA = ['auth/login', 'auth/biometric/login', 'auth/logout', 'app/bootstrap', 'app/status', 'app/apk/register'];

    /** Alamat web admin yang tetap terbuka bagi siapa pun (supaya Admin bisa masuk, staf lain bisa keluar). */
    public const ADMIN_TERBUKA = ['admin/login', 'admin/logout'];

    private static ?string $berkas = null;

    /** @var array<string, mixed>|null status terbaca (sekali per permintaan) */
    private static ?array $tersimpan = null;

    /** Ganti lokasi berkas status (dipakai uji). null = kembali ke writable/maintenance.json. */
    public static function pakaiBerkas(?string $path): void
    {
        self::$berkas    = $path;
        self::$tersimpan = null;
    }

    public static function berkas(): string
    {
        return self::$berkas ?? WRITEPATH . 'maintenance.json';
    }

    /**
     * Status lengkap: aktif, pesan, selesai (Y-m-d H:i perkiraan selesai, boleh null), mulai, oleh, dimatikan, dimatikan_oleh.
     *
     * @return array{aktif: bool, pesan: string, selesai: ?string, mulai: ?string, oleh: ?string, dimatikan: ?string, dimatikan_oleh: ?string}
     */
    public static function status(): array
    {
        if (self::$tersimpan === null) {
            $isi = is_file(self::berkas()) ? json_decode((string) @file_get_contents(self::berkas()), true) : null;
            self::$tersimpan = is_array($isi) ? $isi : [];
        }
        $s = self::$tersimpan;

        return [
            'aktif'          => ($s['aktif'] ?? false) === true,
            'pesan'          => trim((string) ($s['pesan'] ?? '')) !== '' ? (string) $s['pesan'] : self::PESAN_BAWAAN,
            'selesai'        => self::waktuSah($s['selesai'] ?? null),
            'mulai'          => $s['mulai'] ?? null,
            'oleh'           => $s['oleh'] ?? null,
            'dimatikan'      => $s['dimatikan'] ?? null,
            'dimatikan_oleh' => $s['dimatikan_oleh'] ?? null,
        ];
    }

    public static function aktif(): bool
    {
        return self::status()['aktif'];
    }

    /**
     * Nyalakan (atau perbarui pesan/perkiraan selesai bila sudah aktif — waktu mulai tetap).
     *
     * @param string|null $selesai "Y-m-d H:i" atau "Y-m-d\TH:i" (isian datetime-local); kosong = tanpa perkiraan
     */
    public static function nyalakan(string $pesan, ?string $selesai, string $oleh): array
    {
        $lama = self::status();
        self::tulis([
            'aktif'   => true,
            'pesan'   => mb_substr(trim($pesan), 0, self::MAKS_PESAN) ?: self::PESAN_BAWAAN,
            'selesai' => self::waktuSah($selesai),
            'mulai'   => $lama['aktif'] ? ($lama['mulai'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'),
            'oleh'    => $oleh,
        ]);

        return self::status();
    }

    /** Matikan; pesan terakhir disimpan supaya bisa dipakai lagi lain kali. */
    public static function matikan(string $oleh): array
    {
        $lama = self::status();
        self::tulis([
            'aktif'          => false,
            'pesan'          => $lama['pesan'],
            'selesai'        => null,
            'mulai'          => $lama['mulai'],
            'oleh'           => $lama['oleh'],
            'dimatikan'      => date('Y-m-d H:i:s'),
            'dimatikan_oleh' => $oleh,
        ]);

        return self::status();
    }

    /**
     * Satu pintu untuk web, API, dan perintah darurat: periksa isian, nyalakan/perbarui atau matikan, catat di Audit Log
     * (Audit Log dilewati diam-diam bila database bermasalah — status di berkas tetap berubah).
     *
     * @return array{ok: bool, pesan: string, status: array}
     */
    public static function simpan(bool $aktif, string $pesan, string $selesaiIn, string $oleh): array
    {
        $lama = self::status();
        if (! $aktif) {
            if (! $lama['aktif']) {
                return ['ok' => true, 'pesan' => 'Mode maintenance memang sudah mati.', 'status' => $lama];
            }
            $s = self::matikan($oleh);
            self::catat('Mode maintenance DIMATIKAN oleh ' . $oleh . ' (aktif sejak ' . (string) $lama['mulai'] . ')');

            return ['ok' => true, 'pesan' => 'Mode maintenance dimatikan. Situs, layanan siswa, dan aplikasi Android kembali normal.', 'status' => $s];
        }

        $selesaiIn = trim($selesaiIn);
        $selesai   = self::waktuSah($selesaiIn);
        if ($selesaiIn !== '' && $selesai === null) {
            return ['ok' => false, 'pesan' => 'Perkiraan selesai tidak sah. Pilih tanggal dan jam, atau kosongkan.', 'status' => $lama];
        }
        if ($selesai !== null && strtotime($selesai) <= time()) {
            return ['ok' => false, 'pesan' => 'Perkiraan selesai harus waktu yang akan datang.', 'status' => $lama];
        }
        if (mb_strlen(trim($pesan)) > self::MAKS_PESAN) {
            return ['ok' => false, 'pesan' => 'Pesan terlalu panjang (paling banyak ' . self::MAKS_PESAN . ' huruf).', 'status' => $lama];
        }
        $s = self::nyalakan($pesan, $selesai, $oleh);
        self::catat(($lama['aktif'] ? 'Mode maintenance diperbarui' : 'Mode maintenance DIAKTIFKAN') . ' oleh ' . $oleh
            . ($selesai !== null ? ' — perkiraan selesai ' . self::labelWaktu($selesai) : ''));

        return ['ok' => true, 'pesan' => $lama['aktif'] ? 'Pesan mode maintenance diperbarui.'
            : 'Mode maintenance AKTIF. Situs publik, layanan siswa, dan aplikasi Android sekarang menampilkan info perawatan; hanya Admin yang tetap bisa bekerja.', 'status' => $s];
    }

    private static function catat(string $deskripsi): void
    {
        try {
            (new \App\Models\AuditModel())->record('update', 'maintenance', null, $deskripsi);
        } catch (\Throwable) {
            // database bermasalah — status di berkas tetap berlaku
        }
    }

    /** "2026-10-30 22:00" / "2026-10-30T22:00" → "2026-10-30 22:00"; selain itu null. */
    public static function waktuSah(mixed $w): ?string
    {
        $w = str_replace('T', ' ', trim((string) $w));
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $w)) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $w);

        return $d !== false && $d->format('Y-m-d H:i') === $w ? $w : null;
    }

    /** "2026-10-30 22:00" → "Kamis, 30 Oktober 2026 pukul 22.00 WIB" ('' bila kosong). */
    public static function labelWaktu(?string $w): string
    {
        if ($w === null || ($t = strtotime($w)) === false) {
            return '';
        }
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int) date('w', $t)];

        return $hari . ', ' . IsianBantu::tanggalIndo(date('Y-m-d', $t)) . ' pukul ' . date('H.i', $t) . ' WIB';
    }

    /** Bentuk untuk API & JSON (kontrak Flutter: BLUEPRINT-SISTEM.md). */
    public static function untukApi(): array
    {
        $s = self::status();

        return [
            'aktif'         => $s['aktif'],
            'pesan'         => $s['pesan'],
            'selesai'       => $s['aktif'] && $s['selesai'] !== null ? $s['selesai'] . ':00' : null,
            'selesai_label' => $s['aktif'] ? self::labelWaktu($s['selesai']) : '',
            'mulai'         => $s['aktif'] ? $s['mulai'] : null,
            'coba_lagi'     => self::cobaLagi(),
        ];
    }

    /** Detik sampai perkiraan selesai (paling sedikit 60), atau COBA_LAGI bila tanpa perkiraan / sudah lewat. */
    public static function cobaLagi(): int
    {
        $s = self::status()['selesai'];
        $d = $s !== null ? strtotime($s) - time() : 0;

        return $d > 0 ? max(60, $d) : self::COBA_LAGI;
    }

    // =================================================================
    // Siapa boleh lewat (fungsi murni — diuji dev:uji-maintenance)
    // =================================================================

    /** 'admin' (panel web), 'api' (aplikasi Android), atau 'publik' (situs, form siswa, subdomain). */
    public static function jenisJalur(string $path): string
    {
        $p = strtolower(trim($path, '/'));

        return match (true) {
            $p === 'admin' || str_starts_with($p, 'admin/') => 'admin',
            $p === 'api' || str_starts_with($p, 'api/')     => 'api',
            default                                          => 'publik',
        };
    }

    /**
     * Boleh lewat saat maintenance aktif? Admin (sesi web / token API) selalu boleh; panel admin tanpa sesi diteruskan ke
     * penyaring login (halaman masuk tetap terbuka); staf lain & semua pengunjung publik tidak.
     *
     * @param string $peranSesi  peran akun web yang sedang masuk ('' = belum masuk)
     * @param string $peranToken peran pemilik token API ('' = tanpa token / token tidak sah)
     */
    public static function lolos(string $path, string $peranSesi = '', string $peranToken = ''): bool
    {
        $p = strtolower(trim($path, '/'));

        return match (self::jenisJalur($p)) {
            'admin' => in_array($p, self::ADMIN_TERBUKA, true) || $peranSesi === '' || $peranSesi === 'admin',
            'api'   => $peranToken === 'admin' || in_array((string) preg_replace('#^api/v\d+/#', '', $p), self::API_TERBUKA, true),
            default => false,
        };
    }

    /** Pesan penolakan masuk (web & API) bagi akun selain Admin saat maintenance aktif, atau null bila boleh masuk. */
    public static function tolakMasuk(?string $peran): ?string
    {
        if (! self::aktif() || $peran === 'admin') {
            return null;
        }

        return 'Sistem sedang dalam mode maintenance. Untuk sementara hanya Admin yang bisa masuk. ' . self::status()['pesan'];
    }

    /**
     * Balasan 503 untuk permintaan yang ditolak: JSON (API, AJAX, fetch) atau halaman perawatan (tanpa database).
     */
    public static function respons(RequestInterface $request, string $jenis, bool $stafMasuk = false): ResponseInterface
    {
        $res = service('response')->setStatusCode(503)->setHeader('Retry-After', (string) self::cobaLagi())
            ->setHeader('Cache-Control', 'no-store');
        $info = self::untukApi();
        if ($jenis === 'api' || self::mintaJson($request)) {
            return $res->setJSON(['status' => 'error', 'ok' => false, 'kode' => 'maintenance', 'message' => $info['pesan'],
                'data' => ['maintenance' => $info]]);
        }

        return $res->setBody(self::halaman($info, $stafMasuk));
    }

    /** HTML halaman perawatan (juga dipakai pratinjau Admin). */
    public static function halaman(array $info, bool $stafMasuk = false): string
    {
        return view('errors/html/maintenance', ['info' => $info, 'stafMasuk' => $stafMasuk], ['saveData' => false]);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private static function mintaJson(RequestInterface $request): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest'
            || str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json')
            || str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json');
    }

    private static function tulis(array $isi): void
    {
        $tmp = self::berkas() . '.tmp';
        if (@file_put_contents($tmp, json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false
            || ! @rename($tmp, self::berkas())) {
            @unlink($tmp);

            throw new \RuntimeException('Berkas status maintenance tidak bisa ditulis: ' . self::berkas());
        }
        self::$tersimpan = null;
    }
}
