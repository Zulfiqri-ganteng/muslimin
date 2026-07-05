<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /** Batas tidak ada aktivitas sebelum sesi dianggap kadaluarsa (detik). */
    private const IDLE_TIMEOUT = 3600; // 1 jam

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('isLoggedIn')) {
            $session->setFlashdata('error', 'Silakan login terlebih dahulu.');
            return redirect()->to(site_url('admin/login'));
        }

        // Auto-logout bila 1 jam tanpa aktivitas.
        $last = (int) $session->get('lastActivity');
        if ($last > 0 && (time() - $last) > self::IDLE_TIMEOUT) {
            $session->destroy();
            return redirect()->to(site_url('admin/login'))
                ->with('error', 'Sesi berakhir karena tidak ada aktivitas selama 1 jam. Silakan login kembali.');
        }

        // Perbarui stempel aktivitas.
        $session->set('lastActivity', time());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // tidak ada aksi
    }
}
