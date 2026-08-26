<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AsetModel;
use App\Models\AuditModel;
use App\Models\KerusakanModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Kerusakan aset (API). Cermin App\Controllers\Admin\Kerusakan.
 * Rute: /api/v1/admin/kerusakan
 */
class Kerusakan extends BaseApiController
{
    protected KerusakanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new KerusakanModel();
        $this->audit = new AuditModel();
    }

    public function index(): ResponseInterface
    {
        $per  = (int) $this->request->getGet('per');
        $page = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        $b = $this->model->withRelations();
        if ($q !== '') {
            $b = $b->groupStart()->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('kerusakan.deskripsi', $q)->groupEnd();
        }
        if (in_array($status, KerusakanModel::STATUS, true)) {
            $b = $b->where('kerusakan.status', $status);
        }
        $rows  = $b->orderBy('kerusakan.tanggal_lapor', 'DESC')->orderBy('kerusakan.id', 'DESC')->paginate($per, 'default', $page);
        $total = $this->model->pager->getTotal();

        $terbuka = $this->model->whereIn('status', ['dilaporkan', 'diproses'])->countAllResults();

        return $this->collection(
            array_map([$this, 'transform'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $total, 'terbuka' => $terbuka]
        );
    }

    public function store(): ResponseInterface
    {
        $in        = $this->body();
        $asetModel = new AsetModel();
        $asetId    = (int) ($in['aset_id'] ?? 0);
        $aset      = $asetId ? $asetModel->find($asetId) : null;
        if (! $aset) {
            return $this->missing('Aset tidak ditemukan.');
        }

        $tingkat = strtolower((string) ($in['tingkat'] ?? 'ringan'));
        $data = [
            'aset_id'       => $asetId,
            'tanggal_lapor' => trim((string) ($in['tanggal_lapor'] ?? '')) ?: date('Y-m-d'),
            'pelapor'       => trim((string) ($in['pelapor'] ?? '')) ?: null,
            'deskripsi'     => trim((string) ($in['deskripsi'] ?? '')),
            'tingkat'       => in_array($tingkat, KerusakanModel::TINGKAT, true) ? $tingkat : 'ringan',
            'status'        => 'dilaporkan',
            'teknisi_id'    => (int) ($in['teknisi_id'] ?? 0) ?: null,
            'keterangan'    => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
        if (! $this->model->insert($data)) {
            return $this->invalid($this->model->errors());
        }
        $newId = (int) $this->model->getInsertID();
        if ($aset['status'] !== 'dihapus') {
            $asetModel->update($asetId, ['status' => 'perbaikan']);
        }

        master_data_changed('aset');
        $this->audit->record('create', 'kerusakan', $newId, 'Lapor kerusakan aset ' . $aset['nomor_aset'] . ' (via mobile)');

        return $this->created($this->transform($this->fresh($newId)), 'Kerusakan dicatat.');
    }

    public function status($id = null): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return $this->missing('Data tidak ditemukan.');
        }
        $status = strtolower((string) ($this->body()['status'] ?? ''));
        if (! in_array($status, KerusakanModel::STATUS, true)) {
            return $this->invalid(['status' => 'Status tidak valid.']);
        }

        $this->model->update($id, ['id' => $id, 'status' => $status]);
        if (in_array($status, ['selesai', 'tak_teratasi'], true) && $row['aset_id']) {
            $this->bebaskanAset((int) $row['aset_id'], $id);
        }

        master_data_changed('aset');
        $this->audit->record('update', 'kerusakan', $id, 'Status kerusakan → ' . $status . ' (via mobile)');

        return $this->ok($this->transform($this->fresh($id)), 'Status kerusakan diperbarui.');
    }

    public function destroy($id = null): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return $this->missing('Data tidak ditemukan.');
        }
        $this->model->delete($id);
        if ($row['aset_id']) {
            $this->bebaskanAset((int) $row['aset_id'], $id);
        }

        master_data_changed('aset');
        $this->audit->record('delete', 'kerusakan', $id, 'Hapus kerusakan (via mobile)');

        return $this->ok(null, 'Laporan kerusakan dihapus.');
    }

    private function bebaskanAset(int $asetId, int $exceptId): void
    {
        if ($this->model->terbukaCount($asetId, $exceptId) > 0) {
            return;
        }
        $asetModel = new AsetModel();
        $aset      = $asetModel->find($asetId);
        if ($aset && $aset['status'] === 'perbaikan') {
            $asetModel->update($asetId, ['status' => 'tersedia']);
        }
    }

    private function fresh(int $id): array
    {
        return $this->model->withRelations()->where('kerusakan.id', $id)->first() ?? [];
    }

    private function transform(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'aset_id'       => ((int) ($r['aset_id'] ?? 0)) ?: null,
            'aset_nama'     => $r['aset_nama'] ?? null,
            'nomor_aset'    => $r['nomor_aset'] ?? null,
            'tanggal_lapor' => $r['tanggal_lapor'] ?? null,
            'pelapor'       => $r['pelapor'] ?? null,
            'deskripsi'     => $r['deskripsi'],
            'tingkat'       => $r['tingkat'],
            'status'        => $r['status'],
            'teknisi_nama'  => $r['teknisi_nama'] ?? null,
            'keterangan'    => $r['keterangan'] ?? null,
        ];
    }
}
