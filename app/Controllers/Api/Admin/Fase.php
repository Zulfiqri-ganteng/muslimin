<?php

namespace App\Controllers\Api\Admin;

use App\Models\FaseModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Fase (API). Cermin App\Controllers\Admin\Master\Fase.
 * Rute: /api/v1/admin/master/fase
 */
class Fase extends BaseCrud
{
    protected string $module     = 'fase';
    protected string $auditTable = 'fase';
    protected string $entity     = 'fase';

    protected function makeModel(): Model
    {
        return new FaseModel();
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
        return $builder->orderBy('urutan', 'ASC');
    }

    protected function collect(array $in): array
    {
        $deskripsi = trim((string) ($in['deskripsi'] ?? ''));

        return [
            'kode'      => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'      => trim((string) ($in['nama'] ?? '')),
            'urutan'    => (int) ($in['urutan'] ?? 0),
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'        => (int) $r['id'],
            'kode'      => $r['kode'],
            'nama'      => $r['nama'],
            'urutan'    => (int) $r['urutan'],
            'deskripsi' => $r['deskripsi'],
        ];
    }

    /** Anti-orphan: kelas yang memakai fase ini dilepas (fase_id = NULL). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('kelas')->whereIn('fase_id', $ids)->update(['fase_id' => null]);
    }
}
