<?php

namespace App\Filters;

use App\Libraries\ModeMaintenance;
use App\Models\AdminModel;
use App\Models\ApiTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Penyaring global Mode Maintenance (docs/DESAIN-ALAT-SISTEM.md F2). Saat mati: hanya membaca satu berkas kecil, tanpa database.
 * Saat aktif: situs publik & semua layanan siswa (termasuk subdomain) → halaman perawatan 503; API → JSON 503 (kode
 * "maintenance"); panel admin → hanya Admin. Siapa boleh lewat = ModeMaintenance::lolos().
 */
class MaintenanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! ModeMaintenance::aktif() || strtoupper($request->getMethod()) === 'OPTIONS') {
            return null;
        }
        $path  = $request instanceof IncomingRequest ? trim($request->getPath(), '/') : '';
        $jenis = ModeMaintenance::jenisJalur($path);

        $peranSesi = '';
        if ($jenis === 'admin' && session('isLoggedIn')) {
            $peranSesi = (string) (((array) session('admin'))['role'] ?? '');
        }
        $peranToken = $jenis === 'api' ? $this->peranToken($request) : '';

        if (ModeMaintenance::lolos($path, $peranSesi, $peranToken)) {
            return null;
        }

        return ModeMaintenance::respons($request, $jenis, $peranSesi !== '');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // tidak ada aksi
    }

    /** Peran pemilik token Bearer yang sah ('' bila tanpa token, token tidak sah, atau database sedang bermasalah). */
    private function peranToken(RequestInterface $request): string
    {
        if (! preg_match('/Bearer\s+(\S+)/i', $request->getHeaderLine('Authorization'), $m)) {
            return '';
        }
        try {
            $token = (new ApiTokenModel())->findValid($m[1]);
            $admin = $token ? (new AdminModel())->find((int) $token['admin_id']) : null;

            return $admin && (int) ($admin['aktif'] ?? 1) === 1 ? (string) ($admin['role'] ?? '') : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
