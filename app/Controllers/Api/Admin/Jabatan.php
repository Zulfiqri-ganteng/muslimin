<?php

namespace App\Controllers\Api\Admin;

use App\Models\JabatanModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * Master Jabatan (API). Cermin App\Controllers\Admin\Master\Jabatan.
 * Rute: /api/v1/admin/master/jabatan
 */
class Jabatan extends BaseCrud
{
    protected string $module     = 'jabatan';
    protected string $auditTable = 'jabatan';
    protected string $entity     = 'jabatan';

    protected function makeModel(): Model
    {
        return new JabatanModel();
    }

    /** Daftar membawa nama induk & jurusan agar klien tak perlu request lagi. */
    protected function baseBuilder()
    {
        return $this->model->withRelations();
    }

    protected function applyFilters($builder)
    {
        $q        = trim((string) $this->request->getGet('q'));
        $kategori = trim((string) $this->request->getGet('kategori'));

        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('jabatan.nama', $q)->orLike('jabatan.kode', $q)
                ->groupEnd();
        }
        if (in_array($kategori, JabatanModel::KATEGORI, true)) {
            $builder = $builder->where('jabatan.kategori', $kategori);
        }
        // ?struktural=1 → hanya jabatan yang wajib hadir tanpa jadwal
        $struktural = $this->request->getGet('struktural');
        if ($struktural !== null && $struktural !== '') {
            $builder = $builder->where('jabatan.is_struktural', (int) ((string) $struktural === '1'));
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('jabatan.level', 'ASC')->orderBy('jabatan.nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $kategori = strtolower(trim((string) ($in['kategori'] ?? '')));

        return [
            'kode'          => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'          => trim((string) ($in['nama'] ?? '')),
            'kategori'      => in_array($kategori, JabatanModel::KATEGORI, true) ? $kategori : 'lainnya',
            'parent_id'     => (int) ($in['parent_id'] ?? 0) ?: null,
            'jurusan_id'    => (int) ($in['jurusan_id'] ?? 0) ?: null,
            'level'         => max(1, (int) ($in['level'] ?? 5) ?: 5),
            'is_struktural' => ! empty($in['is_struktural']) ? 1 : 0,
            'keterangan'    => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'kode'          => $r['kode'],
            'nama'          => $r['nama'],
            'kategori'      => $r['kategori'] ?? 'lainnya',
            'parent_id'     => ((int) ($r['parent_id'] ?? 0)) ?: null,
            'induk_nama'    => $r['induk_nama'] ?? null,
            'jurusan_id'    => ((int) ($r['jurusan_id'] ?? 0)) ?: null,
            'jurusan_kode'  => $r['jurusan_kode'] ?? null,
            'level'         => (int) ($r['level'] ?? 5),
            'is_struktural' => (bool) ($r['is_struktural'] ?? false),
            'keterangan'    => $r['keterangan'] ?? null,
        ];
    }

    /**
     * Hierarki tidak boleh melingkar — dijaga di API sama seperti di web,
     * karena aplikasi mobile memanggil endpoint ini langsung.
     */
    public function update($id = null): ResponseInterface
    {
        $id     = (int) $id;
        $parent = (int) ($this->body()['parent_id'] ?? 0);

        if ($parent > 0 && $parent === $id) {
            return $this->invalid(['parent_id' => 'Jabatan tidak boleh menjadi induk bagi dirinya sendiri.']);
        }
        if ($parent > 0 && in_array($parent, $this->model->descendantIds($id), true)) {
            return $this->invalid(['parent_id' => 'Induk tidak boleh dipilih dari jabatan di bawahnya (hierarki akan melingkar).']);
        }

        return parent::update($id);
    }

    /**
     * Anti-orphan: sama persis dengan web Jabatan::cleanupRelations.
     * Model memakai soft delete → ON DELETE dari foreign key tidak jalan.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('guru_jabatan')->whereIn('jabatan_id', $ids)->delete();
        $db->table('jabatan')->whereIn('parent_id', $ids)->update(['parent_id' => null]);
    }

    /**
     * Baris segar sesudah tulis diambil lewat relasi agar respons simpan/ubah
     * membawa induk_nama & jurusan_kode persis seperti pada daftar.
     */
    protected function freshRow(int $id): array
    {
        return $this->model->withRelations()->where('jabatan.id', $id)->first() ?? [];
    }

    /** Daftar ringkas untuk dropdown: GET .../jabatan/options */
    public function options(): ResponseInterface
    {
        $rows = $this->model->select('id, kode, nama, is_struktural')
            ->orderBy('level', 'ASC')->orderBy('nama', 'ASC')->findAll();

        return $this->ok(array_map(static fn ($r) => [
            'id'            => (int) $r['id'],
            'label'         => $r['nama'],
            'kode'          => $r['kode'],
            'is_struktural' => (bool) $r['is_struktural'],
        ], $rows));
    }
}
