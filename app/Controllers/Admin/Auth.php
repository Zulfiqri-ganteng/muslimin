<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session('isLoggedIn')) {
            return redirect()->to(site_url('admin/dashboard'));
        }
        return view('admin/login', ['title' => 'Login Admin']);
    }

    public function attemptLogin()
    {
        $login    = trim((string) $this->request->getPost('login'));
        $password = (string) $this->request->getPost('password');

        if ($login === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Username/email dan password wajib diisi.');
        }

        $admin = (new AdminModel())->findByLogin($login);

        if (! $admin || ! password_verify($password, $admin['password'])) {
            return redirect()->back()->withInput()->with('error', 'Username/email atau password salah.');
        }

        unset($admin['password']);
        session()->set([
            'isLoggedIn' => true,
            'admin'      => $admin,
        ]);

        return redirect()->to(site_url('admin/dashboard'))->with('success', 'Selamat datang, ' . $admin['full_name'] . '!');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('admin/login'))->with('success', 'Anda telah keluar.');
    }
}
