<?php

namespace App\Controllers\Api\Admin;

use App\Models\JamPelajaranModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Jam Pelajaran (API). Cermin App\Controllers\Admin\Master\JamPelajaran.
 * Daftar dikelompokkan per shift (tanpa paginasi). Slot unik = shift + jam_ke.
 * Rute: /api/v1/admin/master/jam
 */
class JamPelajaran extends BaseCrud
{
    protected string $module     = 'jam';
    protected string $auditTable = 'jam_pelajaran';
    protected string $entity     = 'jam pelajaran';

    protected function makeModel(): Model
    {
        return new JamPelajaranModel();
    }

    /** @return JamPelajaranModel */
    private function jm(): JamPelajaranModel
    {
        return $this->model;
    }

    private function shiftParam($value): string
    {
        return in_array($value, ['pagi', 'siang'], true) ? $value : 'pagi';
    }

    /** GET /admin/master/jam?shift=pagi — daftar jam satu shift (urut waktu). */
    public function index(): ResponseInterface
    {
        $shift = $this->shiftParam($this->request->getGet('shift'));
        $rows  = array_map([$this, 'transform'], $this->jm()->urutShift($shift));

        return $this->ok(['shift' => $shift, 'items' => $rows]);
    }

    /** POST buat jam baru (cegah duplikat shift+jam_ke, pulihkan bila soft-deleted). */
    public function store(): ResponseInterface
    {
        $data = $this->collect($this->body());

        $existing = $this->jm()->withDeleted()
            ->where('shift', $data['shift'])->where('jam_ke', $data['jam_ke'])->first();
        if ($existing) {
            if (($existing['deleted_at'] ?? null) === null) {
                return $this->failure("Jam ke-{$data['jam_ke']} shift {$data['shift']} sudah ada.", 409);
            }
            $this->jm()->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->jm()->protect(true);
            $id = (int) $existing['id'];
        } else {
            if (! $this->jm()->insert($data)) {
                return $this->invalid($this->jm()->errors());
            }
            $id = (int) $this->jm()->getInsertID();
        }

        $this->afterWrite();
        $this->audit->record('create', $this->auditTable, $id, "Tambah jam {$data['shift']} ke-{$data['jam_ke']} (via mobile)");

        return $this->created($this->transform($this->freshRow($id)), 'Jam pelajaran berhasil ditambahkan.');
    }

    /** POST/PUT perbarui jam (tangani bentrok slot unik). */
    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->jm()->find($id)) {
            return $this->missing('Jam pelajaran tidak ditemukan.');
        }
        $data = $this->collect($this->body());

        $dup = $this->jm()->withDeleted()
            ->where('shift', $data['shift'])->where('jam_ke', $data['jam_ke'])
            ->where('id !=', $id)->first();
        if ($dup) {
            if (($dup['deleted_at'] ?? null) === null) {
                return $this->failure("Jam ke-{$data['jam_ke']} shift {$data['shift']} sudah ada.", 409);
            }
            $this->purgeHard((int) $dup['id']); // slot bentrok yang sudah terhapus → bersihkan
        }

        if (! $this->jm()->update($id, $data)) {
            return $this->invalid($this->jm()->errors());
        }

        $this->afterWrite();
        $this->audit->record('update', $this->auditTable, $id, "Ubah jam {$data['shift']} ke-{$data['jam_ke']} (via mobile)");

        return $this->ok($this->transform($this->freshRow($id)), 'Jam pelajaran berhasil diperbarui.');
    }

    protected function collect(array $in): array
    {
        $mulai   = (string) ($in['waktu_mulai'] ?? '');
        $selesai = (string) ($in['waktu_selesai'] ?? '');

        return [
            'shift'         => $this->shiftParam($in['shift'] ?? null),
            'jam_ke'        => (int) ($in['jam_ke'] ?? 0),
            'waktu_mulai'   => $mulai,
            'waktu_selesai' => $selesai,
            'durasi'        => $this->durasi($mulai, $selesai),
            'is_istirahat'  => ! empty($in['is_istirahat']) ? 1 : 0,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'shift'         => $r['shift'],
            'jam_ke'        => (int) $r['jam_ke'],
            'waktu_mulai'   => substr((string) $r['waktu_mulai'], 0, 5),
            'waktu_selesai' => substr((string) $r['waktu_selesai'], 0, 5),
            'durasi'        => (int) ($r['durasi'] ?? 0),
            'is_istirahat'  => (int) ($r['is_istirahat'] ?? 0) === 1,
        ];
    }

    private function durasi(string $mulai, string $selesai): int
    {
        if ($mulai === '' || $selesai === '') {
            return 0;
        }
        return (int) max(0, (strtotime($selesai) - strtotime($mulai)) / 60);
    }

    /** Anti-orphan: jadwal/ketersediaan/absensi pada slot jam ini dibersihkan. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal')->whereIn('jam_id', $ids)->delete();
        $db->table('ketersediaan_guru')->whereIn('jam_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('jam_id', $ids)->delete();
    }

    /** Bersihkan relasi lalu hapus permanen 1 baris (slot soft-deleted yang bentrok). */
    private function purgeHard(int $id): void
    {
        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->cleanupRelations($db, [$id]);
        $this->jm()->delete($id, true);
        $db->transComplete();
    }
}
