<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\PengumumanModel;

/**
 * Pengumuman (API). Cermin App\Controllers\Admin\Pengumuman.
 *
 *   GET    /api/v1/admin/pengumuman
 *   POST   /api/v1/admin/pengumuman
 *   POST   /api/v1/admin/pengumuman/{id}
 *   DELETE /api/v1/admin/pengumuman/{id}
 */
class Pengumuman extends BaseApiController
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
        $rows = array_map([$this, 'transform'], $this->model->orderBy('id', 'DESC')->findAll());
        return $this->ok($rows);
    }

    public function store()
    {
        if (! $this->model->insert($this->collect($this->body()))) {
            return $this->invalid($this->model->errors());
        }
        $id = (int) $this->model->getInsertID();
        cache()->delete('pengumuman_aktif');
        $this->audit->record('create', 'pengumuman', $id, 'Tambah pengumuman (via mobile)');

        return $this->created($this->transform($this->model->find($id)), 'Pengumuman berhasil ditambahkan.');
    }

    public function update($id = null)
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Pengumuman tidak ditemukan.');
        }
        if (! $this->model->update($id, $this->collect($this->body()))) {
            return $this->invalid($this->model->errors());
        }
        cache()->delete('pengumuman_aktif');
        $this->audit->record('update', 'pengumuman', $id, 'Ubah pengumuman (via mobile)');

        return $this->ok($this->transform($this->model->find($id)), 'Pengumuman berhasil diperbarui.');
    }

    public function destroy($id = null)
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Pengumuman tidak ditemukan.');
        }
        $this->model->delete($id);
        cache()->delete('pengumuman_aktif');
        $this->audit->record('delete', 'pengumuman', $id, 'Hapus pengumuman (via mobile)');

        return $this->ok(null, 'Pengumuman berhasil dihapus.');
    }

    private function collect(array $in): array
    {
        return [
            'judul'    => trim((string) ($in['judul'] ?? '')),
            'isi'      => trim((string) ($in['isi'] ?? '')),
            'is_aktif' => ! empty($in['is_aktif']) ? 1 : 0,
        ];
    }

    private function transform(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'judul'      => $r['judul'],
            'isi'        => $r['isi'],
            'is_aktif'   => (int) $r['is_aktif'] === 1,
            'created_at' => $r['created_at'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }
}
