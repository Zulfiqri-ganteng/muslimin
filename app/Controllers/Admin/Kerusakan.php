<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AsetModel;
use App\Models\AuditModel;
use App\Models\KerusakanModel;
use App\Models\TeknisiModel;

/**
 * Laporan Kerusakan aset (alur/workflow).
 *
 * Lapor  → catat + set aset.status = 'perbaikan'.
 * Status → dilaporkan → diproses → selesai / tak_teratasi.
 * Saat kerusakan selesai/tak_teratasi (dan tak ada kerusakan lain terbuka),
 * aset dikembalikan ke 'tersedia'. Tindakan detail dicatat di menu Perbaikan.
 */
class Kerusakan extends BaseController
{
    protected KerusakanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new KerusakanModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, KerusakanModel::STATUS, true)) {
            $status = '';
        }
        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('kerusakan.deskripsi', $q)
                ->groupEnd();
        }
        if ($status !== '') {
            $builder = $builder->where('kerusakan.status', $status);
        }
        $rows  = $builder->orderBy('kerusakan.tanggal_lapor', 'DESC')->orderBy('kerusakan.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        $terbuka = $this->model->whereIn('status', ['dilaporkan', 'diproses'])->countAllResults();

        return view('admin/kerusakan/index', [
            'title'       => 'Kerusakan Aset',
            'rows'        => $rows,
            'pager'       => $pager,
            'q'           => $q,
            'status'      => $status,
            'per'         => $per,
            'terbuka'     => $terbuka,
            'asetOpts'    => (new AsetModel())->options(),
            'teknisiOpts' => (new TeknisiModel())->options(),
            'tingkatList' => KerusakanModel::TINGKAT,
            'statusList'  => KerusakanModel::STATUS,
        ]);
    }

    public function store()
    {
        $asetModel = new AsetModel();
        $asetId    = (int) $this->request->getPost('aset_id');
        $aset      = $asetId ? $asetModel->find($asetId) : null;
        if (! $aset) {
            return redirect()->back()->withInput()->with('error', 'Aset tidak ditemukan.');
        }

        $data = $this->collect($asetId);
        $data['status'] = 'dilaporkan';

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->insert($data);
        $newId = $this->model->getInsertID();
        // Aset yang dilaporkan rusak → tandai sedang perbaikan.
        if ($aset['status'] !== 'dihapus') {
            $asetModel->update($asetId, ['status' => 'perbaikan']);
        }
        $db->transComplete();

        master_data_changed('aset');
        $this->audit->record('create', 'kerusakan', $newId, 'Lapor kerusakan aset ' . $aset['nomor_aset']);

        return redirect()->to(site_url('admin/kerusakan'))->with('success', 'Kerusakan dicatat. Aset ditandai sedang perbaikan.');
    }

    /** Ubah status kerusakan (diproses/selesai/tak_teratasi/dilaporkan). */
    public function status($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/kerusakan'))->with('error', 'Data tidak ditemukan.');
        }
        $status = strtolower(trim((string) $this->request->getPost('status')));
        if (! in_array($status, KerusakanModel::STATUS, true)) {
            return redirect()->to(site_url('admin/kerusakan'))->with('error', 'Status tidak valid.');
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->update($id, ['id' => $id, 'status' => $status]);
        // Kerusakan tuntas → bebaskan aset bila tak ada kerusakan lain yang terbuka.
        if (in_array($status, ['selesai', 'tak_teratasi'], true) && $row['aset_id']) {
            $this->bebaskanAset((int) $row['aset_id'], $id);
        }
        $db->transComplete();

        master_data_changed('aset');
        $this->audit->record('update', 'kerusakan', $id, 'Status kerusakan → ' . $status);

        return redirect()->to(site_url('admin/kerusakan'))->with('success', 'Status kerusakan diperbarui.');
    }

    public function delete($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/kerusakan'))->with('error', 'Data tidak ditemukan.');
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->model->delete($id);
        if ($row['aset_id']) {
            $this->bebaskanAset((int) $row['aset_id'], $id);
        }
        $db->transComplete();

        master_data_changed('aset');
        $this->audit->record('delete', 'kerusakan', $id, 'Hapus laporan kerusakan');

        return redirect()->to(site_url('admin/kerusakan'))->with('success', 'Laporan kerusakan dihapus.');
    }

    /** Set aset 'tersedia' bila statusnya 'perbaikan' dan tak ada kerusakan lain terbuka. */
    private function bebaskanAset(int $asetId, int $exceptKerusakanId): void
    {
        if ($this->model->terbukaCount($asetId, $exceptKerusakanId) > 0) {
            return;
        }
        $asetModel = new AsetModel();
        $aset      = $asetModel->find($asetId);
        if ($aset && $aset['status'] === 'perbaikan') {
            $asetModel->update($asetId, ['status' => 'tersedia']);
        }
    }

    private function collect(int $asetId): array
    {
        $post    = fn (string $k) => trim((string) $this->request->getPost($k));
        $tingkat = strtolower($post('tingkat'));

        return [
            'aset_id'       => $asetId,
            'tanggal_lapor' => $post('tanggal_lapor') ?: date('Y-m-d'),
            'pelapor'       => $post('pelapor') ?: null,
            'deskripsi'     => $post('deskripsi'),
            'tingkat'       => in_array($tingkat, KerusakanModel::TINGKAT, true) ? $tingkat : 'ringan',
            'teknisi_id'    => (int) $this->request->getPost('teknisi_id') ?: null,
            'keterangan'    => $post('keterangan') ?: null,
        ];
    }
}
