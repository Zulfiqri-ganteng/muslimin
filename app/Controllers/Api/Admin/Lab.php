<?php

namespace App\Controllers\Api\Admin;

use App\Models\LabModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Laboratorium (API). Cermin App\Controllers\Admin\Master\Lab.
 * Rute: /api/v1/admin/master/lab
 */
class Lab extends BaseCrud
{
    protected string $module     = 'lab';
    protected string $auditTable = 'lab';
    protected string $entity     = 'lab';

    protected function makeModel(): Model
    {
        return new LabModel();
    }

    protected function baseBuilder()
    {
        return $this->model->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q     = trim((string) $this->request->getGet('q'));
        $jenis = strtolower(trim((string) $this->request->getGet('jenis')));
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('lab.nama', $q)->orLike('lab.kode', $q)->orLike('lab.ruang', $q)->groupEnd();
        }
        if (in_array($jenis, LabModel::JENIS, true)) {
            $builder = $builder->where('lab.jenis', $jenis);
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('lab.nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $jenis = strtolower(trim((string) ($in['jenis'] ?? '')));

        return [
            'kode'       => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'       => trim((string) ($in['nama'] ?? '')),
            'jenis'      => in_array($jenis, LabModel::JENIS, true) ? $jenis : 'komputer',
            'ruang'      => trim((string) ($in['ruang'] ?? '')) ?: null,
            'kapasitas'  => (int) ($in['kapasitas'] ?? 0) ?: null,
            'teknisi_id' => (int) ($in['teknisi_id'] ?? 0) ?: null,
            'keterangan' => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'           => (int) $r['id'],
            'kode'         => $r['kode'],
            'nama'         => $r['nama'],
            'jenis'        => $r['jenis'],
            'ruang'        => $r['ruang'] ?? null,
            'kapasitas'    => ((int) ($r['kapasitas'] ?? 0)) ?: null,
            'teknisi_id'   => ((int) ($r['teknisi_id'] ?? 0)) ?: null,
            'teknisi_nama' => $r['teknisi_nama'] ?? null,
            'keterangan'   => $r['keterangan'] ?? null,
        ];
    }

    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('aset')->whereIn('lab_id', $ids)->update(['lab_id' => null]);
        $db->table('jurnal_lab')->whereIn('lab_id', $ids)->update(['lab_id' => null]);
        $db->table('jadwal_lab')->whereIn('lab_id', $ids)->delete();
    }

    protected function freshRow(int $id): array
    {
        return $this->model->withRelations()->where('lab.id', $id)->first() ?? [];
    }
}
