<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\LabReport;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Laporan Laboratorium (API) — agregat JSON untuk ditampilkan di aplikasi.
 * Cermin App\Controllers\Admin\LaporanLab (memakai Library bersama LabReport).
 * Rute: /api/v1/admin/laporan-lab?dari=&sampai=
 */
class LaporanLab extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $dari   = trim((string) $this->request->getGet('dari')) ?: date('Y-m-01');
        $sampai = trim((string) $this->request->getGet('sampai')) ?: date('Y-m-t');
        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return $this->ok(['dari' => $dari, 'sampai' => $sampai] + LabReport::hitung($dari, $sampai));
    }
}
