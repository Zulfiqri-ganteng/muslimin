<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\JurusanModel;

class Jurusan extends BaseController
{
    protected JurusanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JurusanModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        return view('admin/master/jurusan', [
            'title' => 'Master Jurusan',
            'rows'  => $this->model->orderBy('kode', 'ASC')->findAll(),
        ]);
    }

    public function store()
    {
        $data = [
            'kode' => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama' => trim((string) $this->request->getPost('nama')),
        ];

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'jurusan', $this->model->getInsertID(), 'Tambah jurusan ' . $data['kode']);
        cache()->delete('opt_jurusan');

        return redirect()->to(site_url('admin/master/jurusan'))->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function update($id)
    {
        $id   = (int) $id;
        $data = [
            'kode' => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama' => trim((string) $this->request->getPost('nama')),
            'id'   => $id, // isi placeholder {id} pada rule is_unique saat edit
        ];

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'jurusan', $id, 'Ubah jurusan ' . $data['kode']);
        cache()->delete('opt_jurusan');

        return redirect()->to(site_url('admin/master/jurusan'))->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        $this->audit->record('delete', 'jurusan', $id, 'Hapus jurusan');
        cache()->delete('opt_jurusan');

        return redirect()->to(site_url('admin/master/jurusan'))->with('success', 'Jurusan dihapus.');
    }
}
