<?php

namespace App\Controllers\Api\Admin;

use App\Models\TempatUjiModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Tempat Uji UKK (API, fase UKK A1). Cermin App\Controllers\Admin\Master\TempatUji.
 * Rute: /api/v1/admin/master/tempat-uji (index ?q=&per=&page= · store · update · destroy · bulk-delete)
 */
class TempatUji extends BaseCrud
{
    protected string $module     = 'tempat_uji';
    protected string $auditTable = 'tempat_uji';
    protected string $entity     = 'tempat uji';

    protected function makeModel(): Model
    {
        return new TempatUjiModel();
    }

    protected function baseBuilder()
    {
        return $this->model->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('tempat_uji.nama', $q)->orLike('tempat_uji.kode', $q)->orLike('tempat_uji.alamat', $q)->groupEnd();
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('tempat_uji.nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        return [
            'kode'       => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'       => trim((string) ($in['nama'] ?? '')),
            'alamat'     => trim((string) ($in['alamat'] ?? '')) ?: null,
            'kapasitas'  => (int) ($in['kapasitas'] ?? 0) ?: null,
            'lab_id'     => (int) ($in['lab_id'] ?? 0) ?: null,
            'keterangan' => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'kode'       => $r['kode'],
            'nama'       => $r['nama'],
            'alamat'     => $r['alamat'] ?? null,
            'kapasitas'  => ((int) ($r['kapasitas'] ?? 0)) ?: null,
            'lab_id'     => ((int) ($r['lab_id'] ?? 0)) ?: null,
            'lab_nama'   => $r['lab_nama'] ?? null,
            'keterangan' => $r['keterangan'] ?? null,
        ];
    }

    protected function periksa(array $payload): array
    {
        return $this->rujukanHilang($payload, ['lab_id' => ['lab', 'Lab']]);
    }

    /** Sama dengan web: lepas rujukan tempat uji di jadwal UKK (soft delete → FK tak jalan). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal_ukk')->whereIn('tempat_uji_id', $ids)->update(['tempat_uji_id' => null]);
    }

    protected function freshRow(int $id): array
    {
        return $this->model->withRelations()->where('tempat_uji.id', $id)->first() ?? [];
    }
}
