<?php

namespace App\Controllers\Api\Admin;

use App\Models\SparepartModel;
use CodeIgniter\Model;

/**
 * Master Sparepart (API). Cermin App\Controllers\Admin\Master\Sparepart.
 * Rute: /api/v1/admin/master/sparepart
 */
class Sparepart extends BaseCrud
{
    protected string $module     = 'sparepart';
    protected string $auditTable = 'sparepart';
    protected string $entity     = 'sparepart';

    protected function makeModel(): Model
    {
        return new SparepartModel();
    }

    protected function applyFilters($builder)
    {
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama', $q)->orLike('kode', $q)->orLike('kategori', $q)->groupEnd();
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $harga = trim((string) ($in['harga'] ?? ''));

        return [
            'kode'         => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'         => trim((string) ($in['nama'] ?? '')),
            'kategori'     => trim((string) ($in['kategori'] ?? '')) ?: null,
            'satuan'       => trim((string) ($in['satuan'] ?? '')) ?: 'unit',
            'stok'         => (int) ($in['stok'] ?? 0),
            'stok_minimum' => (int) ($in['stok_minimum'] ?? 0),
            'harga'        => $harga !== '' ? (float) $harga : null,
            'lokasi'       => trim((string) ($in['lokasi'] ?? '')) ?: null,
            'keterangan'   => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'           => (int) $r['id'],
            'kode'         => $r['kode'],
            'nama'         => $r['nama'],
            'kategori'     => $r['kategori'] ?? null,
            'satuan'       => $r['satuan'] ?? 'unit',
            'stok'         => (int) ($r['stok'] ?? 0),
            'stok_minimum' => (int) ($r['stok_minimum'] ?? 0),
            'menipis'      => (int) ($r['stok'] ?? 0) <= (int) ($r['stok_minimum'] ?? 0),
            'harga'        => $r['harga'] !== null ? (float) $r['harga'] : null,
            'lokasi'       => $r['lokasi'] ?? null,
            'keterangan'   => $r['keterangan'] ?? null,
        ];
    }
}
