<?php

namespace App\Controllers\Api;

use App\Libraries\ApiAuth;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Induk semua controller API. Menyediakan amplop respons JSON yang seragam:
 *
 *   { "status": "success"|"error", "message": string, "data": mixed, "meta"?: object }
 *
 * Semua controller API mewarisi ini agar bentuk respons konsisten untuk Flutter.
 */
abstract class BaseApiController extends Controller
{
    use ResponseTrait;

    /** @var IncomingRequest */
    protected $request;

    protected $helpers = ['cache'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    // ================= Amplop respons =================

    /** 200 OK dengan data. */
    protected function ok($data = null, string $message = 'Berhasil', array $meta = []): ResponseInterface
    {
        return $this->envelope('success', $message, $data, 200, $meta);
    }

    /** 201 Created. */
    protected function created($data = null, string $message = 'Data berhasil dibuat'): ResponseInterface
    {
        return $this->envelope('success', $message, $data, 201);
    }

    /** 400 / error umum. */
    protected function failure(string $message, int $code = 400, $data = null): ResponseInterface
    {
        return $this->envelope('error', $message, $data, $code);
    }

    /** 422 dengan daftar error validasi per-field. */
    protected function invalid(array $errors, string $message = 'Data yang dikirim tidak valid'): ResponseInterface
    {
        return $this->envelope('error', $message, ['errors' => $errors], 422);
    }

    /** 404. */
    protected function missing(string $message = 'Data tidak ditemukan'): ResponseInterface
    {
        return $this->envelope('error', $message, null, 404);
    }

    /** 403. */
    protected function forbidden(string $message = 'Anda tidak memiliki akses'): ResponseInterface
    {
        return $this->envelope('error', $message, null, 403);
    }

    /**
     * Amplop respons daftar dengan meta paginasi.
     *
     * @param array $items daftar item halaman ini
     * @param array $pager ['page'=>int,'perPage'=>int,'total'=>int]
     */
    protected function collection(array $items, array $pager, string $message = 'Berhasil'): ResponseInterface
    {
        $page    = (int) ($pager['page'] ?? 1);
        $perPage = (int) ($pager['perPage'] ?? count($items));
        $total   = (int) ($pager['total'] ?? count($items));

        return $this->ok($items, $message, [
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ]);
    }

    private function envelope(string $status, string $message, $data, int $code, array $meta = []): ResponseInterface
    {
        // 'ok' (boolean) disertakan agar klien mobile bisa cek satu flag; 'status'
        // dipertahankan untuk kompatibilitas. Keduanya selalu konsisten.
        $body = ['status' => $status, 'ok' => $status === 'success', 'message' => $message, 'data' => $data];
        if ($meta) {
            $body['meta'] = $meta;
        }
        return $this->response->setStatusCode($code)->setJSON($body);
    }

    // ================= Helper autentikasi =================

    /** Data admin yang sedang login (via token), atau null. */
    protected function admin(): ?array
    {
        return ApiAuth::admin();
    }

    /** ID admin yang sedang login. */
    protected function adminId(): ?int
    {
        return ApiAuth::id();
    }

    // ================= Helper input =================

    /** URL publik penuh untuk berkas upload (logo/foto) di public/uploads. */
    protected function uploadUrl(?string $file): ?string
    {
        return $file ? base_url('uploads/' . $file) : null;
    }

    /** Ambil body JSON sebagai array asosiatif (fallback ke form-encoded). */
    protected function body(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && $json !== []) {
            return $json;
        }
        return (array) $this->request->getPost();
    }
}
