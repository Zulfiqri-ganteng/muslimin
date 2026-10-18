<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Pemberitahuan as Daftar;
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
}
