<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Models\SubmissionModel;

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
        ]);
    }
}
