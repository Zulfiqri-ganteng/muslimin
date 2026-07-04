<?php

namespace App\Controllers\Api\Admin;

use App\Models\GuruMapelModel;
use App\Models\MataPelajaranModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Mata Pelajaran (API). Cermin App\Controllers\Admin\Master\MataPelajaran.
 * Rute: /api/v1/admin/master/mapel
 * Tambahan: kompetensi (guru yang boleh mengampu suatu mapel).
 */
class Mapel extends BaseCrud
{
    protected string $module     = 'mapel';
    protected string $auditTable = 'mata_pelajaran';
    protected string $entity     = 'mata pelajaran';

    private const KELOMPOK = ['Umum', 'Kejuruan', 'Dasar-dasar Kejuruan', 'Muatan Lokal', 'Pilihan'];

    protected function makeModel(): Model
    {
        return new MataPelajaranModel();
    }

    protected function applyFilters($builder)
    {
        $q        = trim((string) $this->request->getGet('q'));
        $kelompok = trim((string) $this->request->getGet('kelompok'));

        if ($q !== '') {
            $builder = $builder->groupStart()->like('nama_mapel', $q)->orLike('kode_mapel', $q)->groupEnd();
        }
        if ($kelompok !== '') {
            $builder = $builder->where('kelompok', $kelompok);
        }
        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('nama_mapel', 'ASC');
    }

    protected function collect(array $in): array
    {
        return [
            'kode_mapel' => strtoupper(trim((string) ($in['kode_mapel'] ?? ''))),
            'nama_mapel' => trim((string) ($in['nama_mapel'] ?? '')),
            'kelompok'   => trim((string) ($in['kelompok'] ?? '')) ?: null,
            'jp_default' => (int) ($in['jp_default'] ?? 2) ?: 2,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'kode_mapel' => $r['kode_mapel'],
            'nama_mapel' => $r['nama_mapel'],
            'kelompok'   => $r['kelompok'] ?? null,
            'jp_default' => (int) ($r['jp_default'] ?? 0),
        ];
    }

    /** Daftar kelompok baku (untuk dropdown di form). */
    public function kelompokList(): ResponseInterface
    {
        return $this->ok(self::KELOMPOK);
    }

    /** GET kompetensi: daftar guru_id yang boleh mengampu mapel ini. */
    public function kompetensiGet($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Mata pelajaran tidak ditemukan.');
        }
        return $this->ok(['guru_ids' => (new GuruMapelModel())->guruIds($id)]);
    }

    /** POST kompetensi: set ulang daftar guru pengampu. Body { guru_ids: [..] }. */
    public function kompetensiSet($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Mata pelajaran tidak ditemukan.');
        }
        $guruIds = array_values(array_filter(array_map('intval', (array) ($this->body()['guru_ids'] ?? []))));
        (new GuruMapelModel())->sync($id, $guruIds);
        $this->afterWrite();
        $this->audit->record('update', 'guru_mapel', $id, 'Atur kompetensi: ' . count($guruIds) . ' guru (via mobile)');

        return $this->ok(['guru_ids' => $guruIds], 'Kompetensi guru pengampu diperbarui.');
    }

    /** Anti-orphan: sama persis dengan web MataPelajaran::cleanupRelations. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('guru_mapel')->whereIn('mapel_id', $ids)->delete();

        $pengampuIds = array_map('intval', array_column(
            $db->table('pengampu')->select('id')->whereIn('mapel_id', $ids)->get()->getResultArray(),
            'id'
        ));
        if ($pengampuIds !== []) {
            $db->table('jadwal')->whereIn('pengampu_id', $pengampuIds)->delete();
            $db->table('pengampu')->whereIn('id', $pengampuIds)->where('deleted_at', null)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $db->table('absensi_guru')->whereIn('mapel_id', $ids)->update(['mapel_id' => null]);
    }
}
