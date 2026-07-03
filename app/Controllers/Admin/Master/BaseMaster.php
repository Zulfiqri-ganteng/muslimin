<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;
use CodeIgniter\Pager\Pager;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * =====================================================================
 * Kelas dasar seluruh controller Master Data.
 * =====================================================================
 * Menyatukan logika yang sebelumnya diduplikasi di tiap modul:
 *  - hapus satu / massal (selected & all) DALAM TRANSAKSI + pembersihan
 *    relasi (anti data yatim/orphan) lewat hook cleanupRelations()
 *  - alur impor Excel: baca file → pratinjau editable → commit (upsert)
 *  - util ekspor Excel (header berwarna, stream unduhan)
 *  - cache daftar (helper cachedList) + invalidasi terpusat
 *  - pencatatan audit log
 *
 * Controller turunan cukup mengisi properti modul & meng-override hook
 * yang relevan (importCols, normalizeImportRow, cleanupRelations, dst).
 */
abstract class BaseMaster extends BaseController
{
    protected Model $model;
    protected AuditModel $audit;

    /** Kunci modul untuk cache/versi, mis. 'guru'. */
    protected string $module = '';

    /** Nama tabel untuk audit log, mis. 'mata_pelajaran'. */
    protected string $auditTable = '';

    /** Rute dasar modul, mis. 'admin/master/guru'. */
    protected string $routeBase = '';

    /** Label entitas untuk pesan ke user, mis. 'guru'. */
    protected string $entity = 'data';

    /** Judul halaman master, mis. 'Master Guru'. */
    protected string $titleLabel = '';

    /** Model memakai soft delete? */
    protected bool $softDelete = true;

    /** Catatan tambahan hasil impor (diisi normalizeImportRow bila perlu). */
    protected string $importNote = '';

    public function __construct()
    {
        $this->model = $this->makeModel();
        $this->audit = new AuditModel();
        if ($this->auditTable === '') {
            $this->auditTable = $this->module;
        }
    }

    abstract protected function makeModel(): Model;

    // =================================================================
    // Util umum
    // =================================================================

    /** URL halaman indeks modul (override bila butuh query tambahan). */
    protected function indexUrl(): string
    {
        return site_url($this->routeBase);
    }

    protected function goIndex(?string $success = null, ?string $error = null): RedirectResponse
    {
        $r = redirect()->to($this->indexUrl());
        if ($success !== null) {
            $r = $r->with('success', $success);
        }
        if ($error !== null) {
            $r = $r->with('error', $error);
        }

        return $r;
    }

    /** Baris per halaman dari query string (dibatasi pilihan valid). */
    protected function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    /** Nomor halaman aktif dari query string. */
    protected function pageNo(): int
    {
        return max(1, (int) ($this->request->getGet('page') ?: 1));
    }

    /** Cache daftar milik modul ini (invalid otomatis saat data berubah). */
    protected function cachedList(string $key, callable $producer)
    {
        return master_cache($this->module, $key, 3600, $producer);
    }

    /** Daftarkan info paginasi ke Pager service (untuk render tautan halaman). */
    protected function storePager(int $page, int $per, int $total): Pager
    {
        $pager = service('pager');
        $pager->store('default', $page, $per, $total);

        return $pager;
    }

    // =================================================================
    // Hapus (satu & massal) — selalu dalam transaksi + bersih relasi
    // =================================================================

    /** @param int|string $id */
    public function delete($id): RedirectResponse
    {
        $id = (int) $id;
        $this->removeWithRelations([$id]);
        master_data_changed($this->module);
        $this->audit->record('delete', $this->auditTable, $id, 'Hapus ' . $this->entity);

        return $this->goIndex(ucfirst($this->entity) . ' dihapus.');
    }

    /** Hapus banyak data sekaligus: mode 'selected' (ids terpilih) atau 'all' (semua). */
    public function bulkDelete(): RedirectResponse
    {
        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'all') {
            $ids = array_map('intval', array_column($this->model->select('id')->findAll(), 'id'));
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if ($ids === []) {
            return $this->goIndex(null, 'Tidak ada data yang dipilih untuk dihapus.');
        }

        $this->removeWithRelations($ids);
        master_data_changed($this->module);
        $this->audit->record('delete', $this->auditTable, null, 'Hapus massal ' . count($ids) . ' ' . $this->entity . ' (' . $mode . ')');

        return $this->goIndex(count($ids) . ' ' . $this->entity . ' dihapus.');
    }

    /**
     * Hapus data + seluruh relasinya dalam SATU transaksi sehingga tidak
     * pernah menyisakan data yatim (orphan) walau proses gagal di tengah.
     */
    protected function removeWithRelations(array $ids): void
    {
        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $this->cleanupRelations($db, $ids);
        $this->model->delete($ids);
        $db->transComplete();
    }

    /** Bersihkan tabel relasi sebelum induknya dihapus. Override per modul. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
    }

    // =================================================================
    // Impor Excel: unggah → pratinjau (editable) → commit (upsert)
    // =================================================================

    /**
     * Definisi kolom impor (urut sesuai template Excel):
     * [['key','label','type'(text|number|select|datalist),'options'?,'required'?,'width'?], ...]
     * Kembalikan [] bila modul tidak mendukung impor.
     */
    protected function importCols(): array
    {
        return [];
    }

    /** Kolom kunci pencocokan upsert, mis. 'kode_guru'. */
    protected function matchField(): string
    {
        return 'id';
    }

    /**
     * Normalisasi satu baris impor menjadi payload siap simpan.
     * Kembalikan null untuk melewati baris; isi $error bila baris tidak valid.
     */
    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        return null;
    }

    /** Kunci pratinjau (untuk menandai baris "baru" / "perbarui"). */
    protected function previewKey(array $row): string
    {
        return mb_strtoupper(trim((string) ($row[$this->matchField()] ?? '')));
    }

    /** Himpunan kunci data yang SUDAH ada (termasuk soft-deleted). */
    protected function existingKeys(): array
    {
        $field = $this->matchField();
        $model = $this->softDelete ? $this->model->withDeleted() : $this->model;
        $keys  = [];
        foreach ($model->select($field)->findAll() as $r) {
            $keys[mb_strtoupper(trim((string) $r[$field]))] = true;
        }

        return $keys;
    }

    /** Cari baris yang sudah ada untuk payload ini (termasuk soft-deleted). */
    protected function findExisting(array $payload): ?array
    {
        $field = $this->matchField();
        $model = $this->softDelete ? $this->model->withDeleted() : $this->model;

        return $model->where($field, $payload[$field])->first();
    }

    /** Baca file Excel yang diunggah → array baris assoc. Null bila gagal (flash diset). */
    protected function readUpload(): ?array
    {
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            session()->setFlashdata('error', 'File tidak valid.');

            return null;
        }
        if (! in_array($file->getExtension(), ['xlsx', 'xls'], true)) {
            session()->setFlashdata('error', 'File harus berformat Excel (.xlsx / .xls).');

            return null;
        }

        try {
            $sheet = IOFactory::load($file->getTempName())->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'Gagal membaca file: ' . $e->getMessage());

            return null;
        }

        $keys = array_column($this->importCols(), 'key');
        $rows = [];
        foreach ($data as $i => $row) {
            if ($i === 0) {
                continue; // baris header
            }
            $assoc = [];
            foreach ($keys as $c => $k) {
                $assoc[$k] = trim((string) ($row[$c] ?? ''));
            }
            if (implode('', $assoc) === '') {
                continue; // lewati baris yang seluruh selnya kosong
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    public function importPreview()
    {
        $rows = $this->readUpload();
        if ($rows === null) {
            return $this->goIndex();
        }
        if ($rows === []) {
            return $this->goIndex(null, 'File tidak berisi data.');
        }

        $existing = $this->existingKeys();
        foreach ($rows as &$r) {
            $r['_status'] = isset($existing[$this->previewKey($r)]) ? 'perbarui' : 'baru';
        }
        unset($r);

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor ' . ucfirst($this->entity),
            'subtitle'  => $this->titleLabel,
            'cols'      => $this->importCols(),
            'rows'      => $rows,
            'commitUrl' => site_url($this->routeBase . '/import-commit'),
            'backUrl'   => $this->indexUrl(),
        ]);
    }

    public function importCommit(): RedirectResponse
    {
        $rows = (array) $this->request->getPost('rows');
        if ($rows === []) {
            return $this->goIndex(null, 'Tidak ada data untuk disimpan.');
        }

        [$ins, $upd, $skip, $errors] = $this->upsertRows($rows);

        master_data_changed($this->module);
        $this->audit->record('import', $this->auditTable, null, "Import {$this->entity}: +{$ins} baru, {$upd} update, {$skip} dilewati");

        $msg = "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.";
        if ($this->importNote !== '') {
            $msg .= ' ' . $this->importNote;
        }
        if ($errors !== []) {
            return $this->goIndex(null, $msg . ' | ' . implode(' ', array_slice($errors, 0, 5)));
        }

        return $this->goIndex($msg);
    }

    /** Upsert kumpulan baris impor (cocokkan via matchField, termasuk soft-deleted). */
    protected function upsertRows(array $rows): array
    {
        $ins = 0;
        $upd = 0;
        $skip = 0;
        $errors = [];

        $db = db_connect();
        $db->transStart();

        $line = 0;
        foreach ($rows as $row) {
            $line++;
            $error   = null;
            $payload = $this->normalizeImportRow((array) $row, $line, $error);
            if ($payload === null) {
                if ($error !== null) {
                    $skip++;
                    $errors[] = $error;
                }
                continue;
            }

            $existing = $this->findExisting($payload);
            if ($existing) {
                $data = $payload + ['id' => $existing['id']]; // isi placeholder {id} rule is_unique
                if ($this->softDelete) {
                    $data['deleted_at'] = null; // pulihkan bila sebelumnya terhapus
                    $this->model->protect(false);
                }
                $ok = $this->model->update($existing['id'], $data);
                if ($this->softDelete) {
                    $this->model->protect(true);
                }
                if ($ok) {
                    $upd++;
                } else {
                    $skip++;
                    $errors[] = 'Baris ' . $line . ': ' . implode(' ', $this->model->errors());
                }
            } elseif ($this->model->insert($payload)) {
                $ins++;
            } else {
                $skip++;
                $errors[] = 'Baris ' . $line . ': ' . implode(' ', $this->model->errors());
            }
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            $errors[] = 'Sebagian gagal disimpan karena kesalahan database.';
        }

        return [$ins, $upd, $skip, $errors];
    }

    // =================================================================
    // Util ekspor Excel
    // =================================================================

    /** Buat sheet baru + baris header bergaya standar (biru brand, teks putih). */
    protected function sheetWithHeader(Spreadsheet $ss, string $title, array $headers, array $widths = [])
    {
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1', true);

        $last  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $range = 'A1:' . $last . '1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return $sheet;
    }

    /** Stream file .xlsx ke browser sebagai unduhan. */
    protected function streamXlsx(Spreadsheet $ss, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }
}
