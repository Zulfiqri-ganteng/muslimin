<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruMapelModel;
use App\Models\GuruModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\PengampuModel;

class Pengampu extends BaseController
{
    protected PengampuModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PengampuModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $kelasModel = new KelasModel();
        $kelasOpts  = $kelasModel->options();

        // kelas terpilih (default kelas pertama bila ada)
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId === 0 && ! empty($kelasOpts)) {
            $kelasId = (int) array_key_first($kelasOpts);
        }

        $rows    = $kelasId ? $this->model->forKelas($kelasId) : [];
        $totalJp = $kelasId ? $this->model->totalJpKelas($kelasId) : 0;

        // JP default per mapel (untuk auto-isi form)
        $mapelJp = [];
        foreach ((new MataPelajaranModel())->select('id, jp_default')->findAll() as $m) {
            $mapelJp[(int) $m['id']] = (int) $m['jp_default'];
        }

        // peta kompetensi (mapel_id => [guru_id]) untuk filter guru di form
        $kompMap = (new GuruMapelModel())->mapForMapelIds(
            array_column((new MataPelajaranModel())->select('id')->findAll(), 'id')
        );

        return view('admin/master/pengampu', [
            'title'     => 'Penugasan Mengajar (Pengampu)',
            'kelasOpts' => $kelasOpts,
            'kelasId'   => $kelasId,
            'rows'      => $rows,
            'totalJp'   => $totalJp,
            'mapelOpts' => (new MataPelajaranModel())->options(),
            'mapelJp'   => $mapelJp,
            'guruOpts'  => (new GuruModel())->options(),
            'kompMap'   => $kompMap,
        ]);
    }

    public function store()
    {
        $kelasId = (int) $this->request->getPost('kelas_id');
        $mapelId = (int) $this->request->getPost('mapel_id');
        $data    = [
            'kelas_id' => $kelasId,
            'mapel_id' => $mapelId,
            'guru_id'  => (int) $this->request->getPost('guru_id'),
            'jp'       => (int) $this->request->getPost('jp'),
        ];

        // cegah duplikat kelas+mapel (termasuk yg soft-deleted krn UNIQUE di DB)
        $existing = $this->model->withDeleted()->where('kelas_id', $kelasId)->where('mapel_id', $mapelId)->first();
        if ($existing) {
            if ($existing['deleted_at'] === null) {
                return redirect()->back()->with('error', 'Mapel itu sudah ditugaskan di kelas ini. Edit yang ada.');
            }
            // pulihkan baris soft-deleted (protect(false) agar deleted_at boleh di-set null)
            $this->model->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->model->protect(true);
            $id = (int) $existing['id'];
        } else {
            if (! $this->model->insert($data)) {
                return redirect()->back()->withInput()->with('errors', $this->model->errors());
            }
            $id = (int) $this->model->getInsertID();
        }

        $this->audit->record('create', 'pengampu', $id, 'Tambah penugasan kelas#' . $kelasId);
        cache()->delete('rekap_beban');

        return redirect()->to(site_url('admin/master/pengampu?kelas_id=' . $kelasId))->with('success', 'Penugasan disimpan.');
    }

    public function update($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'Data tidak ditemukan.');
        }
        $data = [
            'guru_id' => (int) $this->request->getPost('guru_id'),
            'jp'      => (int) $this->request->getPost('jp'),
        ];
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'pengampu', $id, 'Ubah penugasan');
        cache()->delete('rekap_beban');

        return redirect()->to(site_url('admin/master/pengampu?kelas_id=' . $row['kelas_id']))->with('success', 'Penugasan diperbarui.');
    }

    public function delete($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        $this->model->delete($id);
        $this->audit->record('delete', 'pengampu', $id, 'Hapus penugasan');
        cache()->delete('rekap_beban');

        return redirect()->to(site_url('admin/master/pengampu?kelas_id=' . ($row['kelas_id'] ?? '')))->with('success', 'Penugasan dihapus.');
    }
}
