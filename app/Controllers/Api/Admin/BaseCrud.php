<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;

/**
 * =====================================================================
 * Induk CRUD untuk seluruh Master Data versi API (butuh token).
 * =====================================================================
 * Menyatukan pola yang identik di semua master data agar controller
 * turunan tetap ringkas & konsisten:
 *   - index()   : daftar + pencarian/filter + paginasi (amplop meta)
 *   - store()   : validasi via model → simpan → invalidasi cache + audit
 *   - update()  : validasi via model → perbarui → invalidasi cache + audit
 *   - destroy() : hapus 1 data DALAM TRANSAKSI + bersih relasi (anti-orphan)
 *   - bulkDestroy(): hapus banyak sekaligus (ids[])
 *
 * Logika pembersihan relasi (cleanupRelations) MENIRU controller web
 * (App\Controllers\Admin\Master\*) agar tidak pernah menyisakan data yatim.
 * Cache modul diinvalidasi lewat master_data_changed() sehingga daftar
 * ter-cache di web ikut menyegar saat data diubah dari aplikasi.
 *
 * Controller turunan WAJIB mengisi: makeModel(), collect(), transform().
 * Opsional override: applyFilters(), orderByList(), cleanupRelations().
 */
abstract class BaseCrud extends BaseApiController
{
    protected Model $model;
    protected AuditModel $audit;

    /** Kunci modul cache, mis. 'guru'. */
    protected string $module = '';
    /** Nama tabel untuk audit log; default = $module. */
    protected string $auditTable = '';
    /** Label entitas untuk pesan, mis. 'guru'. */
    protected string $entity = 'data';
    /** Model memakai soft delete? (untuk pemulihan/keputusan lain bila perlu). */
    protected bool $softDelete = true;

    public function __construct()
    {
        $this->model = $this->makeModel();
        $this->audit = new AuditModel();
        if ($this->auditTable === '') {
            $this->auditTable = $this->module;
        }
    }

    abstract protected function makeModel(): Model;

    /** Susun payload siap simpan dari input JSON. */
    abstract protected function collect(array $in): array;

    /** Ubah satu baris DB menjadi bentuk output untuk klien. */
    abstract protected function transform(array $row): array;

    /** Sumber builder untuk daftar (override bila butuh join relasi). */
    protected function baseBuilder()
    {
        return $this->model;
    }

    /** Terapkan pencarian/filter dari query string. Kembalikan builder. */
    protected function applyFilters($builder)
    {
        return $builder;
    }

    /** Terapkan urutan default daftar. */
    protected function orderByList($builder)
    {
        return $builder;
    }

    /** Bersihkan relasi sebelum induk dihapus (override per entitas). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
    }

    // ===================== ENDPOINTS =====================

    /** GET daftar: ?q=&per=&page= (+ filter khusus entitas). */
    public function index(): ResponseInterface
    {
        $per  = (int) $this->request->getGet('per');
        $page = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }

        $builder = $this->orderByList($this->applyFilters($this->baseBuilder()));
        $rows    = $builder->paginate($per, 'default', $page);
        $total   = $this->model->pager->getTotal();

        return $this->collection(
            array_map([$this, 'transform'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $total]
        );
    }

    /** POST buat data baru. */
    public function store(): ResponseInterface
    {
        $payload = $this->collect($this->body());

        if (! $this->model->insert($payload)) {
            return $this->invalid($this->model->errors());
        }
        $id = (int) $this->model->getInsertID();

        $this->afterWrite();
        $this->audit->record('create', $this->auditTable, $id, 'Tambah ' . $this->entity . ' (via mobile)');

        return $this->created($this->transform($this->freshRow($id)), ucfirst($this->entity) . ' berhasil ditambahkan.');
    }

    /** POST/PUT perbarui data. */
    public function update($id = null): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return $this->missing(ucfirst($this->entity) . ' tidak ditemukan.');
        }

        $payload       = $this->collect($this->body());
        $payload['id'] = $id; // untuk placeholder {id} pada rule is_unique

        if (! $this->model->update($id, $payload)) {
            return $this->invalid($this->model->errors());
        }

        $this->afterWrite();
        $this->audit->record('update', $this->auditTable, $id, 'Ubah ' . $this->entity . ' (via mobile)');

        return $this->ok($this->transform($this->freshRow($id)), ucfirst($this->entity) . ' berhasil diperbarui.');
    }

    /** DELETE hapus satu data (transaksi + bersih relasi). */
    public function destroy($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing(ucfirst($this->entity) . ' tidak ditemukan.');
        }

        $this->removeWithRelations([$id]);
        $this->afterWrite();
        $this->audit->record('delete', $this->auditTable, $id, 'Hapus ' . $this->entity . ' (via mobile)');

        return $this->ok(null, ucfirst($this->entity) . ' berhasil dihapus.');
    }

    /** POST hapus massal: body { ids: [..] }. */
    public function bulkDestroy(): ResponseInterface
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($this->body()['ids'] ?? []))));
        if ($ids === []) {
            return $this->invalid(['ids' => 'Tidak ada data yang dipilih.']);
        }

        $this->removeWithRelations($ids);
        $this->afterWrite();
        $this->audit->record('delete', $this->auditTable, null, 'Hapus massal ' . count($ids) . ' ' . $this->entity . ' (via mobile)');

        return $this->ok(['deleted' => count($ids)], count($ids) . ' ' . $this->entity . ' berhasil dihapus.');
    }

    // ===================== INTERNAL =====================

    /** Hapus + seluruh relasi dalam satu transaksi (anti-orphan). */
    protected function removeWithRelations(array $ids): void
    {
        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->cleanupRelations($db, $ids);
        $this->model->delete($ids);
        $db->transComplete();
    }

    /** Ambil baris segar setelah tulis (untuk dikembalikan ke klien). */
    protected function freshRow(int $id): array
    {
        return $this->model->find($id) ?? [];
    }

    /** Dipanggil setelah setiap perubahan data: invalidasi cache modul. */
    protected function afterWrite(): void
    {
        if ($this->module !== '') {
            master_data_changed($this->module);
        }
    }
}
