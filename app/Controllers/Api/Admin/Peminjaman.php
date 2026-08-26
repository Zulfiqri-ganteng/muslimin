<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AsetModel;
use App\Models\AuditModel;
use App\Models\PeminjamanModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Peminjaman & Pengembalian aset (API). Cermin App\Controllers\Admin\Peminjaman.
 * Rute: /api/v1/admin/peminjaman
 */
class Peminjaman extends BaseApiController
{
    protected PeminjamanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PeminjamanModel();
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
            $b = $b->groupStart()->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('peminjaman.peminjam_nama', $q)->groupEnd();
        }
        if (in_array($status, PeminjamanModel::STATUS, true)) {
            $b = $b->where('peminjaman.status', $status);
        }
        $rows  = $b->orderBy('peminjaman.tanggal_pinjam', 'DESC')->orderBy('peminjaman.id', 'DESC')->paginate($per, 'default', $page);
        $total = $this->model->pager->getTotal();

        $today       = date('Y-m-d');
        $sedang      = $this->model->where('status', 'dipinjam')->countAllResults();
        $terlambat   = $this->model->where('status', 'dipinjam')->where('tanggal_kembali_rencana IS NOT NULL')
            ->where('tanggal_kembali_rencana <', $today)->countAllResults();

        return $this->collection(
            array_map([$this, 'transform'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $total, 'sedang' => $sedang, 'terlambat' => $terlambat]
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
        if ($aset['status'] !== 'tersedia') {
            return $this->failure('Aset tidak tersedia (sedang ' . $aset['status'] . ').', 409);
        }

        $tipe    = strtolower((string) ($in['peminjam_tipe'] ?? 'umum'));
        $kondisi = strtolower((string) ($in['kondisi_pinjam'] ?? 'baik'));
        $data = [
            'aset_id'                 => $asetId,
            'peminjam_nama'           => trim((string) ($in['peminjam_nama'] ?? '')),
            'peminjam_tipe'           => in_array($tipe, PeminjamanModel::TIPE, true) ? $tipe : 'umum',
            'peminjam_ref'            => (int) ($in['peminjam_ref'] ?? 0) ?: null,
            'tujuan'                  => trim((string) ($in['tujuan'] ?? '')) ?: null,
            'tanggal_pinjam'          => trim((string) ($in['tanggal_pinjam'] ?? '')) ?: date('Y-m-d'),
            'tanggal_kembali_rencana' => trim((string) ($in['tanggal_kembali_rencana'] ?? '')) ?: null,
            'kondisi_pinjam'          => in_array($kondisi, AsetModel::KONDISI, true) ? $kondisi : 'baik',
            'status'                  => 'dipinjam',
            'petugas_id'              => (int) ($in['petugas_id'] ?? 0) ?: null,
            'keterangan'              => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];

        if (! $this->model->insert($data)) {
            return $this->invalid($this->model->errors());
        }
        $newId = (int) $this->model->getInsertID();
        $asetModel->update($asetId, ['status' => 'dipinjam']);

        master_data_changed('aset');
        $this->audit->record('create', 'peminjaman', $newId, 'Pinjam aset ' . $aset['nomor_aset'] . ' (via mobile)');

        return $this->created($this->transform($this->fresh($newId)), 'Peminjaman dicatat.');
    }

    public function kembalikan($id = null): ResponseInterface
    {
        $id     = (int) $id;
        $pinjam = $this->model->find($id);
        if (! $pinjam) {
            return $this->missing('Data peminjaman tidak ditemukan.');
        }
        if ($pinjam['status'] !== 'dipinjam') {
            return $this->failure('Peminjaman ini sudah selesai.', 409);
        }

        $in     = $this->body();
        $status = strtolower((string) ($in['status'] ?? 'dikembalikan'));
        $status = in_array($status, ['dikembalikan', 'hilang'], true) ? $status : 'dikembalikan';
        $kondisi = strtolower((string) ($in['kondisi_kembali'] ?? ''));
        $kondisi = in_array($kondisi, AsetModel::KONDISI, true) ? $kondisi : null;

        $this->model->update($id, [
            'id'                     => $id,
            'tanggal_kembali_aktual' => trim((string) ($in['tanggal_kembali_aktual'] ?? '')) ?: date('Y-m-d'),
            'kondisi_kembali'        => $kondisi,
            'status'                 => $status,
            'keterangan'             => trim((string) ($in['keterangan'] ?? '')) ?: $pinjam['keterangan'],
        ]);

        $asetModel = new AsetModel();
        if ($pinjam['aset_id']) {
            if ($status === 'hilang') {
                $asetModel->update($pinjam['aset_id'], ['status' => 'dihapus']);
            } else {
                $upd = ['status' => 'tersedia'];
                if ($kondisi !== null) {
                    $upd['kondisi'] = $kondisi;
                }
                $asetModel->update($pinjam['aset_id'], $upd);
            }
        }

        master_data_changed('aset');
        $this->audit->record('update', 'peminjaman', $id, 'Pengembalian ' . $status . ' (via mobile)');

        return $this->ok($this->transform($this->fresh($id)), $status === 'hilang' ? 'Barang ditandai hilang.' : 'Pengembalian dicatat.');
    }

    public function destroy($id = null): ResponseInterface
    {
        $id     = (int) $id;
        $pinjam = $this->model->find($id);
        if (! $pinjam) {
            return $this->missing('Data tidak ditemukan.');
        }
        if ($pinjam['status'] === 'dipinjam' && $pinjam['aset_id']) {
            (new AsetModel())->update($pinjam['aset_id'], ['status' => 'tersedia']);
        }
        $this->model->delete($id);

        master_data_changed('aset');
        $this->audit->record('delete', 'peminjaman', $id, 'Hapus peminjaman (via mobile)');

        return $this->ok(null, 'Catatan peminjaman dihapus.');
    }

    private function fresh(int $id): array
    {
        return $this->model->withRelations()->where('peminjaman.id', $id)->first() ?? [];
    }

    private function transform(array $r): array
    {
        $today = date('Y-m-d');
        $late  = $r['status'] === 'dipinjam' && ! empty($r['tanggal_kembali_rencana']) && $r['tanggal_kembali_rencana'] < $today;

        return [
            'id'                      => (int) $r['id'],
            'aset_id'                 => ((int) ($r['aset_id'] ?? 0)) ?: null,
            'aset_nama'               => $r['aset_nama'] ?? null,
            'nomor_aset'              => $r['nomor_aset'] ?? null,
            'peminjam_nama'           => $r['peminjam_nama'],
            'peminjam_tipe'           => $r['peminjam_tipe'],
            'tujuan'                  => $r['tujuan'] ?? null,
            'tanggal_pinjam'          => $r['tanggal_pinjam'] ?? null,
            'tanggal_kembali_rencana' => $r['tanggal_kembali_rencana'] ?? null,
            'tanggal_kembali_aktual'  => $r['tanggal_kembali_aktual'] ?? null,
            'kondisi_pinjam'          => $r['kondisi_pinjam'] ?? null,
            'kondisi_kembali'         => $r['kondisi_kembali'] ?? null,
            'status'                  => $r['status'],
            'terlambat'               => $late,
            'petugas_nama'            => $r['petugas_nama'] ?? null,
            'keterangan'              => $r['keterangan'] ?? null,
        ];
    }
}
