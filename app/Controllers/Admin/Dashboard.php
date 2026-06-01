<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SubmissionModel;
use App\Models\SettingModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $model = new SubmissionModel();

        return view('admin/dashboard', [
            'title'   => 'Dashboard',
            'stats'   => $model->getStats(),
            'setting' => (new SettingModel())->get(),
            'recent'  => $model->orderBy('created_at', 'DESC')->findAll(5),
        ]);
    }
}
