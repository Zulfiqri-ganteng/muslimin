<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\UkkReport;
use App\Models\TahunAjaranModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Rekap Hasil UKK (API, fase UKK A2) — agregat JSON untuk aplikasi; PDF/Excel tetap di web.
 * Cermin App\Controllers\Admin\LaporanUkk (memakai Library bersama UkkReport, pola LaporanLab).
 * Rute: /api/v1/admin/laporan-ukk?tahun_ajaran_id= (kosong = semua tahun ajaran)
 */
class LaporanUkk extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $tahunId = max(0, (int) $this->request->getGet('tahun_ajaran_id'));
        $tahun   = $tahunId > 0 ? (new TahunAjaranModel())->find($tahunId) : null;
        if ($tahunId > 0 && ! $tahun) {
            return $this->missing('Tahun ajaran tidak ditemukan.');
        }

        return $this->ok([
            'tahun_ajaran_id'    => $tahunId ?: null,
            'tahun_ajaran_label' => $tahun ? $tahun['tahun'] . ' ' . $tahun['semester'] : 'Semua tahun ajaran',
        ] + UkkReport::hitung($tahunId));
    }
}
