<?php

namespace App\Controllers\Api\Admin;

use App\Models\JurusanModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Jurusan (API). Cermin App\Controllers\Admin\Master\Jurusan.
 * Rute: /api/v1/admin/master/jurusan
 */
class Jurusan extends BaseCrud
{
    protected string $module     = 'jurusan';
    protected string $auditTable = 'jurusan';
    protected string $entity     = 'jurusan';

    protected function makeModel(): Model
    {
        return new JurusanModel();
    }

    protected function applyFilters($builder)
    {
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder = $builder->groupStart()->like('nama', $q)->orLike('kode', $q)->groupEnd();
        }
        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('kode', 'ASC');
    }

    protected function collect(array $in): array
    {
        return [
            'kode' => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama' => trim((string) ($in['nama'] ?? '')),
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'   => (int) $r['id'],
            'kode' => $r['kode'],
            'nama' => $r['nama'],
        ];
    }

    /** Anti-orphan: kelas yang memakai jurusan ini dilepas (jurusan_id = NULL). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('kelas')->whereIn('jurusan_id', $ids)->update(['jurusan_id' => null]);
    }
}
