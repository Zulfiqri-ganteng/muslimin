<?php

namespace App\Controllers\Api\Admin;

use App\Models\GuruJabatanModel;
use App\Models\GuruModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Guru (API). Cermin App\Controllers\Admin\Master\Guru.
 * Rute: /api/v1/admin/master/guru
 */
class Guru extends BaseCrud
{
    protected string $module     = 'guru';
    protected string $auditTable = 'guru';
    protected string $entity     = 'guru';

    private const STATUS = ['PNS', 'PPPK', 'GTY', 'GTT'];

    protected function makeModel(): Model
    {
        return new GuruModel();
    }

    protected function applyFilters($builder)
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama', $q)->orLike('kode_guru', $q)->orLike('nip', $q)
                ->groupEnd();
        }
        if (in_array($status, self::STATUS, true)) {
            $builder = $builder->where('status_guru', $status);
        }
        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $jk     = strtoupper(trim((string) ($in['jenis_kelamin'] ?? '')));
        $status = strtoupper(trim((string) ($in['status_guru'] ?? '')));

        return [
            'nip'           => trim((string) ($in['nip'] ?? '')) ?: null,
            'kode_guru'     => trim((string) ($in['kode_guru'] ?? '')),
            'nama'          => trim((string) ($in['nama'] ?? '')),
            'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
            'status_guru'   => in_array($status, self::STATUS, true) ? $status : null,
            'max_beban'     => (int) ($in['max_beban'] ?? 24) ?: 24,
            'keterangan'    => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        $jbt = $this->jabatanMap()[(int) $r['id']] ?? [];

        return [
            'id'            => (int) $r['id'],
            'nip'           => $r['nip'] ?? null,
            'kode_guru'     => $r['kode_guru'],
            'nama'          => $r['nama'],
            'jenis_kelamin' => $r['jenis_kelamin'] ?? null,
            'status_guru'   => $r['status_guru'] ?? null,
            'max_beban'     => (int) ($r['max_beban'] ?? 0),
            'keterangan'    => $r['keterangan'] ?? null,
            // Jabatan (utama lebih dulu). 'jabatan' = ringkas untuk daftar.
            'jabatan'       => $jbt !== [] ? $jbt[0]['nama'] : null,
            'jabatan_list'  => $jbt,
        ];
    }

    /**
     * Peta jabatan seluruh guru, diambil SEKALI per request lalu dipakai
     * ulang oleh transform() — mencegah query berulang per baris (N+1).
     */
    private ?array $jabatanCache = null;

    private function jabatanMap(): array
    {
        return $this->jabatanCache ??= (new GuruJabatanModel())->mapByGuru();
    }

    /** GET daftar jabatan seorang guru. */
    public function jabatanGet($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Guru tidak ditemukan.');
        }

        return $this->ok((new GuruJabatanModel())->forGuru($id));
    }

    /**
     * POST atur jabatan seorang guru — body { jabatan_ids: [..], utama_id: n }.
     * Mengganti seluruh jabatan guru tersebut (sinkron penuh, bukan menambah).
     */
    public function jabatanSet($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Guru tidak ditemukan.');
        }

        $in         = $this->body();
        $jabatanIds = array_values(array_filter(array_map('intval', (array) ($in['jabatan_ids'] ?? []))));
        $utamaId    = (int) ($in['utama_id'] ?? 0) ?: null;

        $pivot = new GuruJabatanModel();
        $pivot->syncForGuru($id, $jabatanIds, $utamaId);

        master_data_changed($this->module);
        $this->audit->record('update', 'guru_jabatan', $id, 'Atur jabatan: ' . count($jabatanIds) . ' jabatan (via mobile)');

        return $this->ok($pivot->forGuru($id), 'Jabatan guru berhasil diperbarui.');
    }

    /** Anti-orphan: sama persis dengan web Guru::cleanupRelations. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('guru_mapel')->whereIn('guru_id', $ids)->delete();
        // Guru memakai soft delete → ON DELETE CASCADE tidak jalan, lepas manual.
        $db->table('guru_jabatan')->whereIn('guru_id', $ids)->delete();
        $db->table('ketersediaan_guru')->whereIn('guru_id', $ids)->delete();

        $pengampuIds = array_map('intval', array_column(
            $db->table('pengampu')->select('id')->whereIn('guru_id', $ids)->get()->getResultArray(),
            'id'
        ));
        if ($pengampuIds !== []) {
            $db->table('jadwal')->whereIn('pengampu_id', $pengampuIds)->delete();
            $db->table('pengampu')->whereIn('id', $pengampuIds)->where('deleted_at', null)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $db->table('jadwal')->whereIn('guru_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('guru_id', $ids)->delete();
        $db->table('kelas')->whereIn('wali_kelas_id', $ids)->update(['wali_kelas_id' => null]);
    }
}
