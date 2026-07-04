<?php

namespace App\Filters;

use App\Libraries\ApiAuth;
use App\Models\AdminModel;
use App\Models\ApiTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filter autentikasi API berbasis Bearer token.
 *
 * Membaca header "Authorization: Bearer <token>", memvalidasi ke tabel api_tokens,
 * lalu menaruh data admin di App\Libraries\ApiAuth untuk dibaca controller.
 * Bila tidak valid → balas 401 JSON standar.
 */
class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (! preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            return $this->unauthorized('Token tidak ditemukan. Silakan login kembali.');
        }

        $tokenRow = (new ApiTokenModel())->findValid($m[1]);
        if (! $tokenRow) {
            return $this->unauthorized('Sesi berakhir atau token tidak valid. Silakan login kembali.');
        }

        $admin = (new AdminModel())->find((int) $tokenRow['admin_id']);
        if (! $admin) {
            return $this->unauthorized('Akun tidak ditemukan.');
        }

        unset($admin['password']);
        ApiAuth::set($admin, (int) $tokenRow['id']);
        (new ApiTokenModel())->touch((int) $tokenRow['id']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // tidak ada aksi setelah request
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON([
                'status'  => 'error',
                'ok'      => false,
                'message' => $message,
                'data'    => null,
            ]);
    }
}
