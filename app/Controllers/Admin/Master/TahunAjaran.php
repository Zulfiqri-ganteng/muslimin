<?php

namespace App\Controllers\Admin\Master;

use App\Models\TahunAjaranModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TahunAjaran extends BaseMaster
{
    protected string $module     = 'tahun_ajaran';
    protected string $auditTable = 'tahun_ajaran';
    protected string $routeBase  = 'admin/master/tahun-ajaran';
    protected string $entity     = 'tahun ajaran';
    protected string $titleLabel = 'Master Tahun Ajaran';

    protected function makeModel(): Model
    {
        return new TahunAjaranModel();
    }

    public function index()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|per={$per}|p={$page}", function () use ($q, $per, $page) {
            $builder = $this->model;
            if ($q !== '') {
                $builder = $builder->like('tahun', $q);
            }
            $rows = $builder->orderBy('tahun', 'DESC')->orderBy('semester', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/tahun_ajaran', [
            'title' => $this->titleLabel,
            'rows'  => $data['rows'],
            'pager' => $this->storePager($page, $per, $data['total']),
            'q'     => $q,
            'per'   => $per,
            'total' => $data['total'],
        ]);
    }

    public function store()
    {
        $data = $this->collect();

        // cegah duplikat tahun+semester (termasuk baris yang pernah dihapus)
        $existing = $this->model->withDeleted()
            ->where('tahun', $data['tahun'])->where('semester', $data['semester'])->first();
        if ($existing) {
            if (($existing['deleted_at'] ?? null) === null) {
                return redirect()->back()->withInput()->with('error', "Tahun ajaran {$data['tahun']} semester {$data['semester']} sudah ada.");
            }
            $this->model->protect(false)->update($existing['id'], $data + ['deleted_at' => null]);
            $this->model->protect(true);
            master_data_changed($this->module);
            $this->audit->record('create', $this->auditTable, (int) $existing['id'], "Pulihkan tahun ajaran {$data['tahun']} {$data['semester']}");

            return $this->goIndex('Tahun ajaran ditambahkan.');
        }

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), "Tambah tahun ajaran {$data['tahun']} {$data['semester']}");

        return $this->goIndex('Tahun ajaran ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();

        // bentrok tahun+semester dengan baris LAIN?
        $dup = $this->model->withDeleted()
            ->where('tahun', $data['tahun'])->where('semester', $data['semester'])
            ->where('id !=', $id)->first();
        if ($dup) {
            if (($dup['deleted_at'] ?? null) === null) {
                return redirect()->back()->withInput()->with('error', "Tahun ajaran {$data['tahun']} semester {$data['semester']} sudah ada.");
            }
            $this->purgeHard((int) $dup['id']);
        }

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, "Ubah tahun ajaran {$data['tahun']} {$data['semester']}");

        return $this->goIndex('Tahun ajaran diperbarui.');
    }

    /**
     * Aktifkan satu tahun ajaran (menonaktifkan semua yang lain). Tepat satu
     * tahun ajaran aktif pada satu waktu — dipakai modul Pembelajaran sebagai
     * default konteks waktu.
     *
     * @param int|string $id
     */
    public function aktifkan($id): RedirectResponse
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return $this->goIndex(null, 'Tahun ajaran tidak ditemukan.');
        }

        $db = db_connect();
        $db->transStart();
        $db->table('tahun_ajaran')->where('is_aktif', 1)->update(['is_aktif' => 0]);
        $db->table('tahun_ajaran')->where('id', $id)->update(['is_aktif' => 1]);
        $db->transComplete();

        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, "Aktifkan tahun ajaran {$row['tahun']} {$row['semester']}");

        return $this->goIndex('Tahun ajaran ' . $row['tahun'] . ' ' . $row['semester'] . ' diaktifkan.');
    }

    /** Ambil & normalisasi input tahun+semester. */
    private function collect(): array
    {
        $semester = (string) $this->request->getPost('semester');

        return [
            'tahun'    => trim((string) $this->request->getPost('tahun')),
            'semester' => in_array($semester, ['Ganjil', 'Genap'], true) ? $semester : 'Ganjil',
        ];
    }

    /** Bersihkan relasi lalu hapus permanen satu baris (untuk baris soft-deleted yang bentrok). */
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

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->orderBy('tahun', 'DESC')->orderBy('semester', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Tahun Ajaran',
            ['No', 'Tahun Ajaran', 'Semester', 'Aktif'],
            ['A' => 5, 'B' => 16, 'C' => 12, 'D' => 10]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['tahun'], $d['semester'], $d['is_aktif'] ? 'Ya' : 'Tidak'], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Tahun-Ajaran-' . date('Ymd-His'), 'D');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Tahun Ajaran',
            ['Tahun Ajaran', 'Semester (Ganjil/Genap)'],
            ['A' => 16, 'B' => 22]
        );
        $sheet->fromArray(['2026/2027', 'Ganjil'], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Tahun-Ajaran');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'tahun',    'label' => 'Tahun Ajaran', 'type' => 'text',   'required' => true, 'width' => 140],
            ['key' => 'semester', 'label' => 'Semester',     'type' => 'select', 'options' => ['Ganjil', 'Genap'], 'required' => true, 'width' => 120],
        ];
    }

    protected function previewKey(array $row): string
    {
        return trim((string) ($row['tahun'] ?? '')) . '|' . trim((string) ($row['semester'] ?? ''));
    }

    protected function existingKeys(): array
    {
        $keys = [];
        foreach ($this->model->withDeleted()->select('tahun, semester')->findAll() as $r) {
            $keys[$r['tahun'] . '|' . $r['semester']] = true;
        }

        return $keys;
    }

    protected function findExisting(array $payload): ?array
    {
        return $this->model->withDeleted()
            ->where('tahun', $payload['tahun'])
            ->where('semester', $payload['semester'])
            ->first();
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $tahun    = trim((string) ($row['tahun'] ?? ''));
        $semester = trim((string) ($row['semester'] ?? ''));
        if ($tahun === '' && $semester === '') {
            return null;
        }
        if ($tahun === '' || ! in_array($semester, ['Ganjil', 'Genap'], true)) {
            $error = 'Baris ' . $line . ': tahun ajaran/semester tidak valid.';

            return null;
        }

        return ['tahun' => $tahun, 'semester' => $semester];
    }
}
