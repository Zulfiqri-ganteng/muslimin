<?php

namespace App\Controllers\Api;

use App\Controllers\Admin\Absensi;
use App\Controllers\Admin\Kurikulum;
use App\Models\SettingModel;
use App\Models\SubmissionModel;

/**
 * Dashboard admin (butuh token). Cermin App\Controllers\Admin\Dashboard.
 * Reuse penuh: Kurikulum::dashboardData() (statik) + SubmissionModel::getStats().
 */
class Dashboard extends BaseApiController
{
    /** GET /api/v1/admin/dashboard */
    public function index()
    {
        $model = new SubmissionModel();
        $stats = $model->getStats();
        $kur   = Kurikulum::dashboardData();

        $recent = array_map(static fn ($r) => [
            'id'                 => (int) $r['id'],
            'nama_lengkap'       => $r['nama_lengkap'],
            'guru_mapel'         => $r['guru_mapel'] ?? null,
            'status_kepegawaian' => $r['status_kepegawaian'] ?? null,
            'status'             => $r['status'],
            'bersedia'           => (int) ($r['bersedia_mengajar'] ?? 0) === 1,
            'created_at'         => $r['created_at'] ?? null,
        ], $model->orderBy('created_at', 'DESC')->findAll(5));

        $chart = array_map(static fn ($b) => [
            'id'        => (int) $b['id'],
            'kode_guru' => $b['kode_guru'] ?? null,
            'nama'      => $b['nama'],
            'max_beban' => (int) $b['max_beban'],
            'total_jp'  => (int) $b['total_jp'],
            'jml_tugas' => (int) $b['jml_tugas'],
        ], $kur['chart'] ?? []);

        $setting = (new SettingModel())->get();
        $admin   = $this->admin() ?? [];

        return $this->ok([
            'school' => [
                'name'          => $setting['school_name'] ?? '',
                'academic_year' => $setting['academic_year'] ?? null,
                'logo'          => $this->uploadUrl($setting['logo'] ?? null),
            ],
            'admin' => [
                'full_name' => $admin['full_name'] ?? '',
                'photo'     => $this->uploadUrl($admin['photo'] ?? null),
            ],
            // Highlight utama dashboard: absensi guru hari ini.
            'absensi' => Absensi::ringkasHarian(date('Y-m-d')),
            'kesediaan' => [
                'total'     => (int) $stats['total'],
                'today'     => (int) $stats['today'],
                'by_status' => array_map('intval', $stats['byStatus']),
            ],
            'penjadwalan' => [
                'guru'         => (int) $kur['guru'],
                'kelas'        => (int) $kur['kelas'],
                'mapel'        => (int) $kur['mapel'],
                'jp_terjadwal' => (int) $kur['jpTerjadwal'],
                'bentrok'      => (int) $kur['bentrok'],
                'kurang'       => (int) $kur['kurang'],
                'lebih'        => (int) $kur['lebih'],
            ],
            'beban_chart' => $chart,
            'recent'      => $recent,
        ]);
    }
}
