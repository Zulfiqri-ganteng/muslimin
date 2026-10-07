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

        // Peran terbatas (Operator/Hubin) hanya boleh ke alamat API yang diizinkan Config\Peran 'api_akses'
        // (mis. "pkl"), plus keperluan akun sendiri ("auth/…": profil, keluar, sidik jari). Selain itu 403
        // — API admin lain (master data, dll.) tetap tertutup bagi mereka. Admin: semua.
        $alamat = trim((string) preg_replace('#^api/v1/?#i', '', trim((string) $this->jalur($request), '/')), '/');
        if (! str_starts_with(strtolower($alamat), 'auth/') && ! HakAkses::bolehApiAlamat($admin['role'] ?? null, $alamat)) {
            return $this->forbidden('Akun ' . HakAkses::label($admin['role'] ?? null) . ' tidak punya akses ke fitur ini di aplikasi.');
        }

        // Peran terbatas yang masih memakai sandi sementara: hanya boleh ganti sandi (admin/profile) atau keluar
        // (auth/…), sama seperti di web. Admin tidak diubah perilakunya (aplikasi lama sudah beredar).
        if ((int) ($admin['wajib_ganti_sandi'] ?? 0) === 1
            && ! in_array('*', HakAkses::aksesApi($admin['role'] ?? null), true)
            && ! str_starts_with(strtolower($alamat), 'auth/')
            && ! HakAkses::cocokAwalan(strtolower($alamat), 'admin/profile')) {
            return $this->forbidden('Anda masih memakai sandi sementara. Ganti sandi lebih dulu (menu Profil → Ganti Password).');
        }

        unset($admin['password']);
        ApiAuth::set($admin, (int) $tokenRow['id']);
        (new ApiTokenModel())->touch((int) $tokenRow['id']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // tidak ada aksi setelah request
    }

    /** Jalur permintaan relatif terhadap baseURL (mis. "api/v1/pkl/ajuan"), tanpa query. */
    private function jalur(RequestInterface $request): string
    {
        return method_exists($request, 'getPath') ? (string) $request->getPath() : '';
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
