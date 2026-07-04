<?php

namespace App\Controllers\Api\Admin;

use App\Models\PengampuModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Penugasan/Pengampu (API). Cermin App\Controllers\Admin\Master\Pengampu.
 * Daftar difilter per kelas. Slot unik = kelas + mapel.
 * Rute: /api/v1/admin/master/pengampu
 */
class Pengampu extends BaseCrud
{
    protected string $module     = 'pengampu';
    protected string $auditTable = 'pengampu';
    protected string $entity     = 'penugasan';

    protected function makeModel(): Model
    {
        return new PengampuModel();
    }

    /** @return PengampuModel */
    private function pm(): PengampuModel
    {
        return $this->model;
    }

    /** GET /admin/master/pengampu?kelas_id=X — penugasan satu kelas + total JP. */
    public function index(): ResponseInterface
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId === 0) {
            return $this->ok(['kelas_id' => 0, 'items' => [], 'total_jp' => 0]);
        }

        $rows = array_map([$this, 'transform'], $this->pm()->forKelas($kelasId));

        return $this->ok([
            'kelas_id' => $kelasId,
            'items'    => $rows,
            'total_jp' => $this->pm()->totalJpKelas($kelasId),
        ]);
    }

    /** POST buat penugasan (cegah duplikat kelas+mapel; pulihkan bila soft-deleted). */
    public function store(): ResponseInterface
    {
        $data = $this->collect($this->body());
        if ($data['kelas_id'] === 0 || $data['mapel_id'] === 0 || $data['guru_id'] === 0 || $data['jp'] === 0) {
            return $this->invalid([
                'kelas_id' => $data['kelas_id'] === 0 ? 'Kelas wajib dipilih.' : null,
                'mapel_id' => $data['mapel_id'] === 0 ? 'Mata pelajaran wajib dipilih.' : null,
                'guru_id'  => $data['guru_id'] === 0 ? 'Guru wajib dipilih.' : null,
                'jp'       => $data['jp'] === 0 ? 'JP wajib diisi.' : null,
            ]);
        }

        $existing = $this->pm()->withDeleted()
            ->where('kelas_id', $data['kelas_id'])->where('mapel_id', $data['mapel_id'])->first();
        if ($existing) {
            if ($existing['deleted_at'] === null) {
                return $this->failure('Mapel itu sudah ditugaskan di kelas ini. Edit yang ada.', 409);
            }
            $this->pm()->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->pm()->protect(true);
            $id = (int) $existing['id'];
        } else {
            if (! $this->pm()->insert($data)) {
                return $this->invalid($this->pm()->errors());
            }
            $id = (int) $this->pm()->getInsertID();
        }

        $this->afterWrite();
        $this->audit->record('create', $this->auditTable, $id, 'Tambah penugasan kelas#' . $data['kelas_id'] . ' (via mobile)');

        return $this->created($this->transform($this->rowWithNames($id)), 'Penugasan berhasil disimpan.');
    }

    /** POST/PUT perbarui penugasan (hanya guru & JP). */
    public function update($id = null): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->pm()->find($id);
        if (! $row) {
            return $this->missing('Penugasan tidak ditemukan.');
        }

        $in   = $this->body();
        $data = [
            'guru_id' => (int) ($in['guru_id'] ?? 0),
            'jp'      => (int) ($in['jp'] ?? 0),
        ];
        if ($data['guru_id'] === 0 || $data['jp'] === 0) {
            return $this->invalid([
                'guru_id' => $data['guru_id'] === 0 ? 'Guru wajib dipilih.' : null,
                'jp'      => $data['jp'] === 0 ? 'JP wajib diisi.' : null,
            ]);
        }

        if (! $this->pm()->update($id, $data)) {
            return $this->invalid($this->pm()->errors());
        }

        $this->afterWrite();
        $this->audit->record('update', $this->auditTable, $id, 'Ubah penugasan (via mobile)');

        return $this->ok($this->transform($this->rowWithNames($id)), 'Penugasan berhasil diperbarui.');
    }

    protected function collect(array $in): array
    {
        return [
            'kelas_id' => (int) ($in['kelas_id'] ?? 0),
            'mapel_id' => (int) ($in['mapel_id'] ?? 0),
            'guru_id'  => (int) ($in['guru_id'] ?? 0),
            'jp'       => (int) ($in['jp'] ?? 0),
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'kelas_id'   => (int) $r['kelas_id'],
            'mapel_id'   => (int) $r['mapel_id'],
            'guru_id'    => (int) $r['guru_id'],
            'jp'         => (int) $r['jp'],
            'kode_mapel' => $r['kode_mapel'] ?? null,
            'nama_mapel' => $r['nama_mapel'] ?? null,
            'jp_default' => isset($r['jp_default']) ? (int) $r['jp_default'] : null,
            'kode_guru'  => $r['kode_guru'] ?? null,
            'guru_nama'  => $r['guru_nama'] ?? null,
        ];
    }

    /** Ambil satu baris penugasan lengkap dengan nama mapel & guru. */
    private function rowWithNames(int $id): array
    {
        return $this->pm()
            ->select('pengampu.*, mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel, mata_pelajaran.jp_default, guru.kode_guru, guru.nama AS guru_nama')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->join('guru', 'guru.id = pengampu.guru_id')
            ->where('pengampu.id', $id)->first() ?? [];
    }

    /** Anti-orphan: jadwal yang memakai penugasan ini ikut terhapus. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal')->whereIn('pengampu_id', $ids)->delete();
    }
}
