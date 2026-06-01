<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;

class Settings extends BaseController
{
    protected SettingModel $model;

    public function __construct()
    {
        $this->model = new SettingModel();
    }

    public function index()
    {
        return view('admin/settings', [
            'title'   => 'Pengaturan Sekolah',
            'setting' => $this->model->get(),
        ]);
    }

    public function save()
    {
        $current = $this->model->get();

        $data = [
            'school_name'     => trim((string) $this->request->getPost('school_name')),
            'school_level'    => trim((string) $this->request->getPost('school_level')),
            'headmaster_name' => trim((string) $this->request->getPost('headmaster_name')),
            'headmaster_nip'  => trim((string) $this->request->getPost('headmaster_nip')),
            'city'            => trim((string) $this->request->getPost('city')),
            'academic_year'   => trim((string) $this->request->getPost('academic_year')),
            'address'         => trim((string) $this->request->getPost('address')),
            'phone'           => trim((string) $this->request->getPost('phone')),
            'email'           => trim((string) $this->request->getPost('email')),
            'website'         => trim((string) $this->request->getPost('website')),
            'form_intro'      => trim((string) $this->request->getPost('form_intro')),
            'form_open'       => $this->request->getPost('form_open') ? 1 : 0,
        ];

        // Upload logo (opsional)
        $logo = $this->request->getFile('logo');
        if ($logo && $logo->isValid() && ! $logo->hasMoved()) {
            if (! in_array($logo->getClientMimeType(), ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'], true)) {
                return redirect()->back()->with('error', 'Logo harus berupa gambar (PNG/JPG/WEBP).');
            }
            $path    = FCPATH . 'uploads';
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $newName = 'logo_' . time() . '.' . $logo->getExtension();
            $logo->move($path, $newName);

            // hapus logo lama
            if (! empty($current['logo']) && is_file($path . DIRECTORY_SEPARATOR . $current['logo'])) {
                @unlink($path . DIRECTORY_SEPARATOR . $current['logo']);
            }
            $data['logo'] = $newName;
        }

        $this->model->store($data);

        return redirect()->to(site_url('admin/settings'))->with('success', 'Pengaturan sekolah berhasil disimpan.');
    }
}
