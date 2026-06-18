<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\HariModel;

class Hari extends BaseController
{
    protected HariModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new HariModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        return view('admin/master/hari', [
            'title' => 'Master Hari',
            'rows'  => $this->model->orderBy('urutan', 'ASC')->findAll(),
        ]);
    }

    public function store()
    {
        $data = [
            'nama'   => trim((string) $this->request->getPost('nama')),
            'urutan' => (int) $this->request->getPost('urutan'),
            'aktif'  => $this->request->getPost('aktif') ? 1 : 0,
        ];

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'hari', $this->model->getInsertID(), 'Tambah hari ' . $data['nama']);

        return redirect()->to(site_url('admin/master/hari'))->with('success', 'Hari ditambahkan.');
    }

    public function update($id)
    {
        $id   = (int) $id;
        $data = [
            'nama'   => trim((string) $this->request->getPost('nama')),
            'urutan' => (int) $this->request->getPost('urutan'),
            'aktif'  => $this->request->getPost('aktif') ? 1 : 0,
        ];

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'hari', $id, 'Ubah hari ' . $data['nama']);

        return redirect()->to(site_url('admin/master/hari'))->with('success', 'Hari diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        $this->audit->record('delete', 'hari', $id, 'Hapus hari');

        return redirect()->to(site_url('admin/master/hari'))->with('success', 'Hari dihapus.');
    }
}
