<?php

namespace App\Controllers\Api;

use App\Libraries\ApiAuth;
use App\Libraries\LoginThrottle;
use App\Models\AdminModel;
use App\Models\ApiTokenModel;
use App\Models\BiometricCredentialModel;

/**
 * Autentikasi API untuk panel admin di aplikasi mobile.
 *
 *   POST /api/v1/auth/login              { login, password, device_name? }        → token + profil
 *   POST /api/v1/auth/logout             (Bearer)                                 → cabut token ini
 *   GET  /api/v1/auth/me                 (Bearer)                                 → profil admin
 *
 *   -- Login sidik jari (biometrik) --
 *   POST /api/v1/auth/biometric/register (Bearer) { device_id, device_name? }     → biometric_secret (sekali)
 *   POST /api/v1/auth/biometric/login    { device_id, biometric_secret }          → token + profil
 *   POST /api/v1/auth/biometric/disable  (Bearer) { device_id? }                  → matikan (device / semua)
 *   GET  /api/v1/auth/biometric/status   (Bearer) ?device_id=...                  → { enabled }
 */
class Auth extends BaseApiController
{
    public function login()
    {
        $in       = $this->body();
        $login    = trim((string) ($in['login'] ?? ''));
        $password = (string) ($in['password'] ?? '');
        $device   = trim((string) ($in['device_name'] ?? ''));
        $ip       = $this->request->getIPAddress();

        if ($login === '' || $password === '') {
            return $this->invalid([
                'login'    => $login === '' ? 'Username atau email wajib diisi.' : null,
                'password' => $password === '' ? 'Password wajib diisi.' : null,
            ], 'Lengkapi data login.');
        }

        // Proteksi brute-force.
        $throttle = new LoginThrottle();
        $wait     = $throttle->retryAfter($login, $ip);
        if ($wait > 0) {
            $mins = (int) ceil($wait / 60);
            return $this->failure("Terlalu banyak percobaan gagal. Coba lagi dalam {$mins} menit.", 429);
        }

        $admin = (new AdminModel())->findByLogin($login);
        if (! $admin || ! password_verify($password, $admin['password'])) {
            $throttle->hit($login, $ip, 'api');
            return $this->failure('Username/email atau password salah.', 401);
        }

        $throttle->clear($login, $ip);
        $token = (new ApiTokenModel())->issue((int) $admin['id'], $device ?: null);
        unset($admin['password']);

        return $this->ok([
            'token'      => $token,
            'token_type' => 'Bearer',
            'admin'      => $this->publicAdmin($admin),
        ], 'Selamat datang, ' . $admin['full_name'] . '!');
    }

    // ==================== LOGIN SIDIK JARI (BIOMETRIK) ====================

    /**
     * Daftarkan biometrik untuk perangkat ini (butuh sesi aktif / Bearer).
     * Mengembalikan `biometric_secret` SEKALI — aplikasi menyimpannya di secure
     * storage yang dibuka sidik jari, server hanya menyimpan hash-nya.
     */
    public function biometricRegister()
    {
        $in       = $this->body();
        $deviceId = trim((string) ($in['device_id'] ?? ''));
        $device   = trim((string) ($in['device_name'] ?? ''));

        if ($deviceId === '') {
            return $this->invalid(['device_id' => 'device_id wajib diisi.'], 'Lengkapi data perangkat.');
        }

        $secret = (new BiometricCredentialModel())
            ->register((int) $this->adminId(), $deviceId, $device ?: null);

        return $this->ok([
            'device_id'        => $deviceId,
            'biometric_secret' => $secret,
        ], 'Login sidik jari diaktifkan untuk perangkat ini.');
    }

    /**
     * Login via sidik jari: tukar (device_id + biometric_secret) dengan token API.
     * Tanpa Bearer. Sidik jari diverifikasi di HP; server hanya cek secret.
     */
    public function biometricLogin()
    {
        $in       = $this->body();
        $deviceId = trim((string) ($in['device_id'] ?? ''));
        $secret   = (string) ($in['biometric_secret'] ?? '');
        $ip       = $this->request->getIPAddress();

        if ($deviceId === '' || $secret === '') {
            return $this->invalid([
                'device_id'        => $deviceId === '' ? 'device_id wajib diisi.' : null,
                'biometric_secret' => $secret === '' ? 'biometric_secret wajib diisi.' : null,
            ], 'Lengkapi data login sidik jari.');
        }

        // Throttle memakai device_id sebagai identitas.
        $throttle = new LoginThrottle();
        $wait     = $throttle->retryAfter($deviceId, $ip);
        if ($wait > 0) {
            $mins = (int) ceil($wait / 60);
            return $this->failure("Terlalu banyak percobaan gagal. Coba lagi dalam {$mins} menit.", 429);
        }

        $cred = (new BiometricCredentialModel())->verify($deviceId, $secret);
        if (! $cred) {
            $throttle->hit($deviceId, $ip, 'biometric');
            return $this->failure('Sidik jari tidak dikenali. Silakan login dengan password.', 401);
        }

        $admin = (new AdminModel())->find((int) $cred['admin_id']);
        if (! $admin) {
            return $this->failure('Akun tidak ditemukan.', 401);
        }

        $throttle->clear($deviceId, $ip);
        $token = (new ApiTokenModel())->issue((int) $admin['id'], $cred['device_name'] ?? null);
        unset($admin['password']);

        return $this->ok([
            'token'      => $token,
            'token_type' => 'Bearer',
            'admin'      => $this->publicAdmin($admin),
        ], 'Selamat datang kembali, ' . $admin['full_name'] . '!');
    }

    /** Matikan login sidik jari untuk satu perangkat, atau semua bila device_id kosong. */
    public function biometricDisable()
    {
        $in       = $this->body();
        $deviceId = trim((string) ($in['device_id'] ?? ''));
        $model    = new BiometricCredentialModel();

        if ($deviceId !== '') {
            $model->disableForDevice((int) $this->adminId(), $deviceId);
            return $this->ok(null, 'Login sidik jari dinonaktifkan untuk perangkat ini.');
        }

        $model->disableAllFor((int) $this->adminId());
        return $this->ok(null, 'Login sidik jari dinonaktifkan untuk semua perangkat.');
    }

    /** Cek apakah admin aktif punya biometrik pada perangkat tertentu. */
    public function biometricStatus()
    {
        $deviceId = trim((string) $this->request->getGet('device_id'));
        if ($deviceId === '') {
            return $this->invalid(['device_id' => 'device_id wajib diisi.'], 'Lengkapi data perangkat.');
        }

        $enabled = (new BiometricCredentialModel())->isEnabled((int) $this->adminId(), $deviceId);
        return $this->ok(['enabled' => $enabled]);
    }

    public function me()
    {
        return $this->ok(['admin' => $this->publicAdmin($this->admin())]);
    }

    public function logout()
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            (new ApiTokenModel())->revoke($m[1]);
        }
        ApiAuth::clear();
        return $this->ok(null, 'Anda telah keluar.');
    }

    /** Bentuk profil admin yang aman dikirim ke klien. */
    private function publicAdmin(array $a): array
    {
        return [
            'id'        => (int) $a['id'],
            'full_name' => $a['full_name'] ?? '',
            'username'  => $a['username'] ?? '',
            'email'     => $a['email'] ?? '',
            'phone'     => $a['phone'] ?? null,
            'photo'     => $this->uploadUrl($a['photo'] ?? null),
            'role'      => $a['role'] ?? 'admin',
        ];
    }
}
