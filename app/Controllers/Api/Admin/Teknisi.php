<?php

namespace App\Controllers\Api\Admin;

use App\Models\TeknisiModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Teknisi (API). Cermin App\Controllers\Admin\Master\Teknisi.
 * Rute: /api/v1/admin/master/teknisi
 */
class Teknisi extends BaseCrud
{
    protected string $module     = 'teknisi';
    protected string $auditTable = 'teknisi';
    protected string $entity     = 'teknisi';

    protected function makeModel(): Model
    {
        return new TeknisiModel();
    }

    protected function baseBuilder()
    {
        return $this->model->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q     = trim((string) $this->request->getGet('q'));
        $peran = strtolower(trim((string) $this->request->getGet('peran')));
        if ($q !== '') {
            $builder = $builder->groupStart()->like('teknisi.nama', $q)->orLike('teknisi.kode', $q)->groupEnd();
        }
        if (in_array($peran, TeknisiModel::PERAN, true)) {
            $builder = $builder->where('teknisi.peran', $peran);
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('teknisi.nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $peran = strtolower(trim((string) ($in['peran'] ?? '')));

        return [
            'kode'       => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'       => trim((string) ($in['nama'] ?? '')),
            'peran'      => in_array($peran, TeknisiModel::PERAN, true) ? $peran : 'teknisi',
            'no_hp'      => trim((string) ($in['no_hp'] ?? '')) ?: null,
            'guru_id'    => (int) ($in['guru_id'] ?? 0) ?: null,
            'keterangan' => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'kode'       => $r['kode'],
            'nama'       => $r['nama'],
            'peran'      => $r['peran'],
            'no_hp'      => $r['no_hp'] ?? null,
            'guru_id'    => ((int) ($r['guru_id'] ?? 0)) ?: null,
            'guru_nama'  => $r['guru_nama'] ?? null,
            'keterangan' => $r['keterangan'] ?? null,
        ];
    }

    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('lab')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
        $db->table('peminjaman')->whereIn('petugas_id', $ids)->update(['petugas_id' => null]);
        $db->table('kerusakan')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
        $db->table('perbaikan')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
        $db->table('jurnal_lab')->whereIn('teknisi_id', $ids)->update(['teknisi_id' => null]);
    }

    protected function freshRow(int $id): array
    {
        return $this->model->withRelations()->where('teknisi.id', $id)->first() ?? [];
    }
}
