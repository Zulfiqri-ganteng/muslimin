<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\PengumumanModel;

class Pengumuman extends BaseController
{
    protected PengumumanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PengumumanModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        return view('admin/pengumuman/index', [
            'title' => 'Pengumuman',
            'rows'  => $this->model->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function store()
    {
        if (! $this->model->insert($this->collect())) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        cache()->delete('pengumuman_aktif');
        $this->audit->record('create', 'pengumuman', $this->model->getInsertID(), 'Tambah pengumuman');

        return redirect()->to(site_url('admin/pengumuman'))->with('success', 'Pengumuman ditambahkan.');
    }

    public function update($id)
    {
        $id = (int) $id;
        if (! $this->model->update($id, $this->collect())) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        cache()->delete('pengumuman_aktif');
        $this->audit->record('update', 'pengumuman', $id, 'Ubah pengumuman');

        return redirect()->to(site_url('admin/pengumuman'))->with('success', 'Pengumuman diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        cache()->delete('pengumuman_aktif');
        $this->audit->record('delete', 'pengumuman', $id, 'Hapus pengumuman');

        return redirect()->to(site_url('admin/pengumuman'))->with('success', 'Pengumuman dihapus.');
    }

    private function collect(): array
    {
        return [
            'judul'    => trim((string) $this->request->getPost('judul')),
            'isi'      => trim((string) $this->request->getPost('isi')),
            'is_aktif' => $this->request->getPost('is_aktif') ? 1 : 0,
        ];
    }
}
