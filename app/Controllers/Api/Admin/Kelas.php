<?php

namespace App\Controllers\Api\Admin;

use App\Models\KelasModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Kelas (API). Cermin App\Controllers\Admin\Master\Kelas.
 * Rute: /api/v1/admin/master/kelas
 */
class Kelas extends BaseCrud
{
    protected string $module     = 'kelas';
    protected string $auditTable = 'kelas';
    protected string $entity     = 'kelas';

    protected function makeModel(): Model
    {
        return new KelasModel();
    }

    /** @var KelasModel */
    private function km(): KelasModel
    {
        return $this->model;
    }

    protected function baseBuilder()
    {
        return $this->km()->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q       = trim((string) $this->request->getGet('q'));
        $tingkat = trim((string) $this->request->getGet('tingkat'));
        $shift   = trim((string) $this->request->getGet('shift'));

        if ($q !== '') {
            $builder = $builder->like('kelas.nama_kelas', $q);
        }
        if (in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $builder = $builder->where('kelas.tingkat', $tingkat);
        }
        if (in_array($shift, ['pagi', 'siang'], true)) {
            $builder = $builder->where('kelas.shift', $shift);
        }
        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC');
    }

    protected function collect(array $in): array
    {
        $tingkatRaw = strtoupper(trim((string) ($in['tingkat'] ?? '')));
        $tingkat    = in_array($tingkatRaw, ['X', 'XI', 'XII'], true) ? $tingkatRaw : 'X';
        $shift      = strtolower(trim((string) ($in['shift'] ?? '')));

        return [
            'nama_kelas'    => trim((string) ($in['nama_kelas'] ?? '')),
            'tingkat'       => $tingkat,
            'fase_id'       => (int) ($in['fase_id'] ?? 0) ?: $this->autoFaseId($tingkat),
            'jurusan_id'    => (int) ($in['jurusan_id'] ?? 0) ?: null,
            'wali_kelas_id' => (int) ($in['wali_kelas_id'] ?? 0) ?: null,
            'shift'         => in_array($shift, ['pagi', 'siang'], true) ? $shift : 'pagi',
        ];
    }

    /** Fase default bila app tak mengirim fase_id: X->E, XI/XII->F (standar SMK). */
    private function autoFaseId(string $tingkat): ?int
    {
        $kode = $tingkat === 'X' ? 'E' : 'F';
        $row  = db_connect()->table('fase')->select('id')->where('kode', $kode)->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    protected function transform(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'nama_kelas'    => $r['nama_kelas'],
            'tingkat'       => $r['tingkat'],
            'shift'         => $r['shift'],
            'fase_id'       => isset($r['fase_id']) ? ((int) $r['fase_id'] ?: null) : null,
            'fase_kode'     => $r['fase_kode'] ?? null,
            'fase_nama'     => $r['fase_nama'] ?? null,
            'jurusan_id'    => isset($r['jurusan_id']) ? ((int) $r['jurusan_id'] ?: null) : null,
            'jurusan_kode'  => $r['jurusan_kode'] ?? null,
            'jurusan_nama'  => $r['jurusan_nama'] ?? null,
            'wali_kelas_id' => isset($r['wali_kelas_id']) ? ((int) $r['wali_kelas_id'] ?: null) : null,
            'wali_nama'     => $r['wali_nama'] ?? null,
        ];
    }

    /** Ambil baris segar LENGKAP dengan relasi (untuk respons create/update). */
    protected function freshRow(int $id): array
    {
        return $this->km()->withRelations()->where('kelas.id', $id)->first() ?? [];
    }

    /** Anti-orphan: sama persis dengan web Kelas::cleanupRelations. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $pengampuIds = array_map('intval', array_column(
            $db->table('pengampu')->select('id')->whereIn('kelas_id', $ids)->get()->getResultArray(),
            'id'
        ));
        if ($pengampuIds !== []) {
            $db->table('jadwal')->whereIn('pengampu_id', $pengampuIds)->delete();
            $db->table('pengampu')->whereIn('id', $pengampuIds)->where('deleted_at', null)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $db->table('jadwal')->whereIn('kelas_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('kelas_id', $ids)->delete();
    }
}
