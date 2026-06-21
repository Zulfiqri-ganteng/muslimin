<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\JamPelajaranModel;

class JamPelajaran extends BaseController
{
    protected JamPelajaranModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JamPelajaranModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $shift = in_array($this->request->getGet('shift'), ['pagi', 'siang'], true)
            ? $this->request->getGet('shift') : 'pagi';

        return view('admin/master/jam', [
            'title' => 'Master Jam Pelajaran',
            'shift' => $shift,
            'rows'  => $this->model->urutShift($shift),
        ]);
    }

    public function store()
    {
        $data = $this->collect();

        // cegah duplikat shift+jam_ke (termasuk baris yang pernah dihapus)
        $existing = $this->model->withDeleted()
            ->where('shift', $data['shift'])->where('jam_ke', $data['jam_ke'])->first();
        if ($existing) {
            if (($existing['deleted_at'] ?? null) === null) {
                return redirect()->back()->withInput()->with('error', "Jam ke-{$data['jam_ke']} shift {$data['shift']} sudah ada. Gunakan nomor jam lain atau edit yang ada.");
            }
            // pulihkan slot yang sebelumnya dihapus
            $this->model->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->model->protect(true);
            $this->audit->record('create', 'jam_pelajaran', (int) $existing['id'], "Pulihkan jam {$data['shift']} ke-{$data['jam_ke']}");

            return redirect()->to(site_url('admin/master/jam?shift=' . $data['shift']))->with('success', 'Jam pelajaran ditambahkan.');
        }

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'jam_pelajaran', $this->model->getInsertID(), "Tambah jam {$data['shift']} ke-{$data['jam_ke']}");

        return redirect()->to(site_url('admin/master/jam?shift=' . $data['shift']))->with('success', 'Jam pelajaran ditambahkan.');
    }

    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();

        // bentrok shift+jam_ke dengan baris LAIN?
        $dup = $this->model->withDeleted()
            ->where('shift', $data['shift'])->where('jam_ke', $data['jam_ke'])
            ->where('id !=', $id)->first();
        if ($dup) {
            if (($dup['deleted_at'] ?? null) === null) {
                return redirect()->back()->withInput()->with('error', "Jam ke-{$data['jam_ke']} shift {$data['shift']} sudah ada.");
            }
            // baris bentrok itu sudah terhapus → hapus permanen agar slot unik bebas
            $this->model->delete($dup['id'], true);
        }

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'jam_pelajaran', $id, "Ubah jam {$data['shift']} ke-{$data['jam_ke']}");

        return redirect()->to(site_url('admin/master/jam?shift=' . $data['shift']))->with('success', 'Jam pelajaran diperbarui.');
    }

    public function delete($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        $this->model->delete($id);
        $this->audit->record('delete', 'jam_pelajaran', $id, 'Hapus jam pelajaran');

        return redirect()->to(site_url('admin/master/jam?shift=' . ($row['shift'] ?? 'pagi')))->with('success', 'Jam pelajaran dihapus.');
    }

    /** Ambil & normalisasi input; durasi dihitung dari selisih waktu. */
    private function collect(): array
    {
        $mulai   = (string) $this->request->getPost('waktu_mulai');
        $selesai = (string) $this->request->getPost('waktu_selesai');
        $durasi  = 0;
        if ($mulai && $selesai) {
            $durasi = (int) max(0, (strtotime($selesai) - strtotime($mulai)) / 60);
        }

        return [
            'shift'         => in_array($this->request->getPost('shift'), ['pagi', 'siang'], true) ? $this->request->getPost('shift') : 'pagi',
            'jam_ke'        => (int) $this->request->getPost('jam_ke'),
            'waktu_mulai'   => $mulai,
            'waktu_selesai' => $selesai,
            'durasi'        => $durasi,
            'is_istirahat'  => $this->request->getPost('is_istirahat') ? 1 : 0,
        ];
    }
}
