<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Pemberitahuan as Daftar;
use App\Libraries\PklPengingat;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * GET admin/pemberitahuan — isi ikon lonceng (JSON) untuk diperbarui berkala oleh app.js tanpa memuat ulang halaman.
 * Isinya disaring hak peran yang login (Libraries\Pemberitahuan). Terbuka bagi semua peran (Config\Peran::$umum).
 */
class Pemberitahuan extends BaseController
{
    public function index(): ResponseInterface
    {
        $data = Daftar::untuk((string) (session('admin')['role'] ?? ''));

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['ok' => true] + $data);
    }

    /**
     * POST admin/pemberitahuan/tunda-pengingat — tombol "Ingatkan lagi 1 jam lagi" di dialog layar penuh pekerjaan PKL lewat batas
     * (Libraries\PklPengingat). Hanya menyimpan waktu tunda di sesi, lalu kembali ke halaman asal (alamat admin saja).
     */
    public function tundaPengingat(): RedirectResponse
    {
        PklPengingat::tunda();
        $kembali = trim((string) $this->request->getPost('kembali'), '/');
        if (preg_match('~^admin(/[A-Za-z0-9_\-/]*)?$~', $kembali) !== 1 || str_contains($kembali, '..')) {
            $kembali = 'admin';
        }

        return redirect()->to(site_url($kembali));
    }
}
