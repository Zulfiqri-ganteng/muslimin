<?php

namespace App\Controllers\Api\Admin;

use App\Models\HariModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Hari (API). Cermin App\Controllers\Admin\Master\Hari.
 * Tabel hari DIHAPUS PERMANEN (tanpa soft delete) → cleanup relasi wajib.
 * Rute: /api/v1/admin/master/hari
 */
class Hari extends BaseCrud
{
    protected string $module     = 'hari';
    protected string $auditTable = 'hari';
    protected string $entity     = 'hari';
    protected bool $softDelete   = false;

    protected function makeModel(): Model
    {
        return new HariModel();
    }

    protected function applyFilters($builder)
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        if ($q !== '') {
            $builder = $builder->like('nama', $q);
        }
        if (in_array($status, ['1', '0'], true)) {
            $builder = $builder->where('aktif', (int) $status);
        }
        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('urutan', 'ASC');
    }

    protected function collect(array $in): array
    {
        return [
            'nama'   => trim((string) ($in['nama'] ?? '')),
            'urutan' => (int) ($in['urutan'] ?? 0),
            'aktif'  => ! empty($in['aktif']) ? 1 : 0,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'     => (int) $r['id'],
            'nama'   => $r['nama'],
            'urutan' => (int) $r['urutan'],
            'aktif'  => (int) $r['aktif'] === 1,
        ];
    }

    /** Anti-orphan: hapus permanen → jadwal/ketersediaan/absensi hari itu dibersihkan. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal')->whereIn('hari_id', $ids)->delete();
        $db->table('ketersediaan_guru')->whereIn('hari_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('hari_id', $ids)->delete();
    }
}
