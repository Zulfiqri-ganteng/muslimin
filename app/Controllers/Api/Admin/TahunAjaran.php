<?php

namespace App\Controllers\Api\Admin;

use App\Models\TahunAjaranModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Tahun Ajaran (API). Cermin App\Controllers\Admin\Master\TahunAjaran.
 * Unik gabungan tahun+semester (tanpa rule is_unique tunggal) — dicegah manual
 * di store()/update(), sama seperti pola JamPelajaran (shift+jam_ke).
 * Rute: /api/v1/admin/master/tahun-ajaran
 */
class TahunAjaran extends BaseCrud
{
    protected string $module     = 'tahun_ajaran';
    protected string $auditTable = 'tahun_ajaran';
    protected string $entity     = 'tahun ajaran';

    protected function makeModel(): Model
    {
        return new TahunAjaranModel();
    }

    protected function applyFilters($builder)
    {
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder = $builder->like('tahun', $q);
        }
        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('tahun', 'DESC')->orderBy('semester', 'ASC');
    }

    /** POST buat tahun ajaran baru (cegah duplikat tahun+semester, pulihkan bila soft-deleted). */
    public function store(): ResponseInterface
    {
        $data = $this->collect($this->body());

        $existing = $this->model->withDeleted()
            ->where('tahun', $data['tahun'])->where('semester', $data['semester'])->first();
        if ($existing) {
            if (($existing['deleted_at'] ?? null) === null) {
                return $this->failure("Tahun ajaran {$data['tahun']} semester {$data['semester']} sudah ada.", 409);
            }
            $this->model->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->model->protect(true);
            $id = (int) $existing['id'];
        } else {
            if (! $this->model->insert($data)) {
                return $this->invalid($this->model->errors());
            }
            $id = (int) $this->model->getInsertID();
        }

        $this->afterWrite();
        $this->audit->record('create', $this->auditTable, $id, "Tambah tahun ajaran {$data['tahun']} {$data['semester']} (via mobile)");

        return $this->created($this->transform($this->freshRow($id)), 'Tahun ajaran berhasil ditambahkan.');
    }

    /** POST/PUT perbarui tahun ajaran (tangani bentrok tahun+semester). */
    public function update($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Tahun ajaran tidak ditemukan.');
        }
        $data = $this->collect($this->body());

        $dup = $this->model->withDeleted()
            ->where('tahun', $data['tahun'])->where('semester', $data['semester'])
            ->where('id !=', $id)->first();
        if ($dup) {
            if (($dup['deleted_at'] ?? null) === null) {
                return $this->failure("Tahun ajaran {$data['tahun']} semester {$data['semester']} sudah ada.", 409);
            }
            $this->purgeHard((int) $dup['id']);
        }

        if (! $this->model->update($id, $data)) {
            return $this->invalid($this->model->errors());
        }

        $this->afterWrite();
        $this->audit->record('update', $this->auditTable, $id, "Ubah tahun ajaran {$data['tahun']} {$data['semester']} (via mobile)");

        return $this->ok($this->transform($this->freshRow($id)), 'Tahun ajaran berhasil diperbarui.');
    }

    /** POST aktifkan satu tahun ajaran (menonaktifkan semua yang lain). */
    public function aktifkan($id = null): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return $this->missing('Tahun ajaran tidak ditemukan.');
        }

        $db = db_connect();
        $db->transStart();
        $db->table('tahun_ajaran')->where('is_aktif', 1)->update(['is_aktif' => 0]);
        $db->table('tahun_ajaran')->where('id', $id)->update(['is_aktif' => 1]);
        $db->transComplete();

        $this->afterWrite();
        $this->audit->record('update', $this->auditTable, $id, "Aktifkan tahun ajaran {$row['tahun']} {$row['semester']} (via mobile)");

        return $this->ok($this->transform($this->freshRow($id)), 'Tahun ajaran ' . $row['tahun'] . ' ' . $row['semester'] . ' diaktifkan.');
    }

    protected function collect(array $in): array
    {
        $semester = $in['semester'] ?? null;

        return [
            'tahun'    => trim((string) ($in['tahun'] ?? '')),
            'semester' => in_array($semester, ['Ganjil', 'Genap'], true) ? $semester : 'Ganjil',
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'       => (int) $r['id'],
            'tahun'    => $r['tahun'],
            'semester' => $r['semester'],
            'is_aktif' => (int) ($r['is_aktif'] ?? 0) === 1,
        ];
    }

    /** Bersihkan relasi lalu hapus permanen 1 baris (baris soft-deleted yang bentrok). */
    private function purgeHard(int $id): void
    {
        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->cleanupRelations($db, [$id]);
        $this->model->delete($id, true);
        $db->transComplete();
    }

    /** Anti-orphan: jadwal yang memakai tahun ajaran ini dilepas (tahun_ajaran_id = NULL). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal')->whereIn('tahun_ajaran_id', $ids)->update(['tahun_ajaran_id' => null]);
    }
}
