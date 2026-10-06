<?php

namespace App\Filters;

use App\Libraries\ApiAuth;
use App\Libraries\HakAkses;
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

        // Akun yang dinonaktifkan langsung terputus (401 → aplikasi otomatis keluar).
        if ((int) ($admin['aktif'] ?? 1) !== 1) {
            return $this->unauthorized('Akun Anda sudah tidak aktif. Hubungi admin sekolah.');
        }

        // API admin belum dipilah per peran, jadi hanya peran yang diizinkan
        // (Config\Peran 'api') yang boleh lewat — menutup pintu belakang bagi
        // akun terbatas (Operator/Hubin) yang login lewat aplikasi.
        if (! HakAkses::bolehApi($admin['role'] ?? null)) {
            return $this->forbidden('Peran akun ini belum bisa memakai aplikasi. Silakan gunakan situs web.');
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
        return $this->galat(401, $message);
    }

    private function forbidden(string $message): ResponseInterface
    {
        return $this->galat(403, $message);
    }

    private function galat(int $kode, string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode($kode)
            ->setJSON([
                'status'  => 'error',
                'ok'      => false,
                'message' => $message,
                'data'    => null,
            ]);
    }
}
