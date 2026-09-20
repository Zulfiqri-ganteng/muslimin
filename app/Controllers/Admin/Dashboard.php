<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UjianReport;
use App\Models\SettingModel;
use App\Models\SubmissionModel;
use App\Models\UjianPeriodeModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $model = new SubmissionModel();

        return view('admin/dashboard', [
            'title'   => 'Dashboard',
            'kur'     => Kurikulum::dashboardData(),          // statistik penjadwalan
            'stats'   => $model->getStats(),                  // statistik kesediaan
            'setting' => (new SettingModel())->get(),
            'recent'  => $model->orderBy('created_at', 'DESC')->findAll(5),
            'absensi' => Absensi::ringkasHarian(date('Y-m-d')), // highlight hari ini
            // Ringkasan 4 gelombang ujian; hanya membaca, tidak membuat periode.
            'ujian'   => UjianReport::dashboard((new UjianPeriodeModel())->tahunBerjalan()),
        ]);
    }
}
