<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\JurnalLabModel;
use App\Models\KelasModel;
use App\Models\LabModel;
use App\Models\TeknisiModel;

/**
 * Jurnal realisasi pemakaian lab (catatan aktual tiap sesi/kegiatan).
 */
class JurnalLab extends BaseController
{
    protected JurnalLabModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JurnalLabModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    public function index()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $labId = (int) $this->request->getGet('lab_id');
        $dari  = trim((string) $this->request->getGet('dari'));
        $sampai = trim((string) $this->request->getGet('sampai'));
        $per   = $this->perPage();
        $page  = max(1, (int) ($this->request->getGet('page') ?: 1));

        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('jurnal_lab.kegiatan', $q)->orLike('guru.nama', $q)->orLike('lab.nama', $q)
                ->groupEnd();
        }
        if ($labId > 0) {
            $builder = $builder->where('jurnal_lab.lab_id', $labId);
        }
        if ($dari !== '') {
            $builder = $builder->where('jurnal_lab.tanggal >=', $dari);
        }
        if ($sampai !== '') {
            $builder = $builder->where('jurnal_lab.tanggal <=', $sampai);
        }
        $rows  = $builder->orderBy('jurnal_lab.tanggal', 'DESC')->orderBy('jurnal_lab.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        return view('admin/jurnal_lab/index', [
            'title'       => 'Jurnal Pemakaian Lab',
            'rows'        => $rows,
            'pager'       => $pager,
            'q'           => $q,
            'labId'       => $labId,
            'dari'        => $dari,
            'sampai'      => $sampai,
            'per'         => $per,
            'labOpts'     => (new LabModel())->options(),
            'guruOpts'    => (new GuruModel())->options(),
            'kelasOpts'   => (new KelasModel())->options(),
            'teknisiOpts' => (new TeknisiModel())->options(),
            'kondisiList' => JurnalLabModel::KONDISI_SETELAH,
        ]);
    }

    public function store()
    {
        if (! $this->model->insert($this->collect())) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'jurnal_lab', $this->model->getInsertID(), 'Tambah jurnal lab');

        return redirect()->to(site_url('admin/jurnal-lab'))->with('success', 'Jurnal pemakaian dicatat.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        if ($this->model->find($id)) {
            $this->model->delete($id);
            $this->audit->record('delete', 'jurnal_lab', $id, 'Hapus jurnal lab');
        }

        return redirect()->to(site_url('admin/jurnal-lab'))->with('success', 'Jurnal dihapus.');
    }

    private function collect(): array
    {
        $post    = fn (string $k) => trim((string) $this->request->getPost($k));
        $kondisi = strtolower($post('kondisi_setelah'));

        return [
            'lab_id'          => (int) $this->request->getPost('lab_id') ?: null,
            'tanggal'         => $post('tanggal') ?: date('Y-m-d'),
            'jam_mulai'       => $post('jam_mulai') ?: null,
            'jam_selesai'     => $post('jam_selesai') ?: null,
            'guru_id'         => (int) $this->request->getPost('guru_id') ?: null,
            'kelas_id'        => (int) $this->request->getPost('kelas_id') ?: null,
            'kegiatan'        => $post('kegiatan') ?: null,
            'jumlah_hadir'    => (int) $this->request->getPost('jumlah_hadir') ?: null,
            'kondisi_setelah' => in_array($kondisi, JurnalLabModel::KONDISI_SETELAH, true) ? $kondisi : 'baik',
            'kendala'         => $post('kendala') ?: null,
            'teknisi_id'      => (int) $this->request->getPost('teknisi_id') ?: null,
            'keterangan'      => $post('keterangan') ?: null,
        ];
    }
}
