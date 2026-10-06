<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\LoginThrottle;
use App\Models\AdminModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session('isLoggedIn')) {
            return redirect()->to(site_url(HakAkses::beranda(session('admin')['role'] ?? null)));
        }
        return view('admin/login', ['title' => 'Login Admin']);
    }

    public function attemptLogin()
    {
        $login    = trim((string) $this->request->getPost('login'));
        $password = (string) $this->request->getPost('password');
        $ip       = $this->request->getIPAddress();

        if ($login === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Username/email dan password wajib diisi.');
        }

        // Proteksi brute-force: tolak lebih dulu bila sedang terkunci.
        $throttle = new LoginThrottle();
        $wait     = $throttle->retryAfter($login, $ip);
        if ($wait > 0) {
            $mins = (int) ceil($wait / 60);
            return redirect()->back()->withInput()->with(
                'error',
                "Terlalu banyak percobaan login gagal. Coba lagi dalam {$mins} menit."
            );
        }

        $admin = (new AdminModel())->findByLogin($login);

        if (! $admin || ! password_verify($password, $admin['password'])) {
            $throttle->hit($login, $ip, 'web');
            return redirect()->back()->withInput()->with('error', 'Username/email atau password salah.');
        }

        $throttle->clear($login, $ip);

        // Sandi sudah benar → baru aman memberi tahu status akunnya
        // (penebak sandi tak bisa memakai pesan ini untuk mengintip akun).
        if ((int) ($admin['aktif'] ?? 1) !== 1) {
            return redirect()->back()->withInput()->with('error', 'Akun Anda dinonaktifkan. Hubungi admin sekolah.');
        }
        if (! HakAkses::dikenal($admin['role'] ?? null)) {
            return redirect()->back()->withInput()->with('error', 'Peran akun Anda tidak dikenali. Hubungi admin sekolah.');
        }

        // Catat waktu masuk tanpa menyentuh updated_at.
        db_connect()->table('admins')->where('id', (int) $admin['id'])
            ->update(['last_login_at' => date('Y-m-d H:i:s')]);

        unset($admin['password']);
        session()->regenerate(true); // cegah session fixation
        session()->set([
            'isLoggedIn'   => true,
            'admin'        => $admin,
            'lastActivity' => time(),
            'loginAt'      => time(),
        ]);

        // Tiap peran langsung mendarat di halamannya (Hubin → PKL, Admin → dashboard).
        return redirect()->to(site_url(HakAkses::beranda($admin['role'])))
            ->with('success', 'Selamat datang, ' . $admin['full_name'] . '!');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('admin/login'))->with('success', 'Anda telah keluar.');
    }
}
