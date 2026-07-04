<?php

namespace App\Controllers\Api;

use App\Libraries\ApiAuth;
use App\Models\AdminModel;
use App\Models\ApiTokenModel;

/**
 * Autentikasi API untuk panel admin di aplikasi mobile.
 *
 *   POST /api/v1/auth/login   { login, password, device_name? }  → token + profil
 *   POST /api/v1/auth/logout  (Bearer)                           → cabut token ini
 *   GET  /api/v1/auth/me      (Bearer)                           → profil admin
 */
class Auth extends BaseApiController
{
    public function login()
    {
        $in       = $this->body();
        $login    = trim((string) ($in['login'] ?? ''));
        $password = (string) ($in['password'] ?? '');
        $device   = trim((string) ($in['device_name'] ?? ''));

        if ($login === '' || $password === '') {
            return $this->invalid([
                'login'    => $login === '' ? 'Username atau email wajib diisi.' : null,
                'password' => $password === '' ? 'Password wajib diisi.' : null,
            ], 'Lengkapi data login.');
        }

        $admin = (new AdminModel())->findByLogin($login);
        if (! $admin || ! password_verify($password, $admin['password'])) {
            return $this->failure('Username/email atau password salah.', 401);
        }

        $token = (new ApiTokenModel())->issue((int) $admin['id'], $device ?: null);
        unset($admin['password']);

        return $this->ok([
            'token'      => $token,
            'token_type' => 'Bearer',
            'admin'      => $this->publicAdmin($admin),
        ], 'Selamat datang, ' . $admin['full_name'] . '!');
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
