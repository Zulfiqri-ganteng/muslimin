<?php

namespace App\Controllers\Admin\Master;

use App\Models\HariModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Hari extends BaseMaster
{
    protected string $module     = 'hari';
    protected string $auditTable = 'hari';
    protected string $routeBase  = 'admin/master/hari';
    protected string $entity     = 'hari';
    protected string $titleLabel = 'Master Hari';
    protected bool $softDelete   = false; // tabel hari dihapus permanen

    protected function makeModel(): Model
    {
        return new HariModel();
    }

    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, ['1', '0'], true)) {
            $status = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|s={$status}|per={$per}|p={$page}", function () use ($q, $status, $per, $page) {
            $builder = $this->model;
            if ($q !== '') {
                $builder = $builder->like('nama', $q);
            }
            if ($status !== '') {
                $builder = $builder->where('aktif', (int) $status);
            }
            $rows = $builder->orderBy('urutan', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/hari', [
            'title'  => $this->titleLabel,
            'rows'   => $data['rows'],
            'pager'  => $this->storePager($page, $per, $data['total']),
            'q'      => $q,
            'status' => $status,
            'per'    => $per,
            'total'  => $data['total'],
        ]);
    }

    public function store()
    {
        $data = [
            'nama'   => trim((string) $this->request->getPost('nama')),
            'urutan' => (int) $this->request->getPost('urutan'),
            'aktif'  => $this->request->getPost('aktif') ? 1 : 0,
        ];

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah hari ' . $data['nama']);

        return $this->goIndex('Hari ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id   = (int) $id;
        $data = [
            'nama'   => trim((string) $this->request->getPost('nama')),
            'urutan' => (int) $this->request->getPost('urutan'),
            'aktif'  => $this->request->getPost('aktif') ? 1 : 0,
            'id'     => $id, // isi placeholder {id} pada rule is_unique saat edit
        ];

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah hari ' . $data['nama']);

        return $this->goIndex('Hari diperbarui.');
    }

    /**
     * Anti-orphan: tabel hari dihapus permanen, jadi jadwal, ketersediaan,
     * dan absensi pada hari itu harus dibersihkan dulu (FK RESTRICT).
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal')->whereIn('hari_id', $ids)->delete();
        $db->table('ketersediaan_guru')->whereIn('hari_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('hari_id', $ids)->delete();
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->orderBy('urutan', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Hari',
            ['No', 'Nama Hari', 'Urutan', 'Aktif'],
            ['A' => 5, 'B' => 18, 'C' => 12, 'D' => 10]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['nama'], $d['urutan'], $d['aktif'] ? 'Ya' : 'Tidak'], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Hari-' . date('Ymd-His'));
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Hari',
            ['Nama Hari', 'Urutan', 'Aktif (Ya/Tidak)'],
            ['A' => 18, 'B' => 12, 'C' => 18]
        );
        $sheet->fromArray(['Senin', 1, 'Ya'], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Hari');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'nama',   'label' => 'Nama Hari', 'type' => 'text',   'required' => true, 'width' => 180],
            ['key' => 'urutan', 'label' => 'Urutan',    'type' => 'number', 'width' => 110],
            ['key' => 'aktif',  'label' => 'Aktif',     'type' => 'select', 'options' => ['Ya', 'Tidak'], 'width' => 120],
        ];
    }

    protected function matchField(): string
    {
        return 'nama';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $nama = trim((string) ($row['nama'] ?? ''));
        if ($nama === '') {
            return null;
        }

        $aktifRaw = strtolower(trim((string) ($row['aktif'] ?? 'ya')));

        return [
            'nama'   => $nama,
            'urutan' => (int) ($row['urutan'] ?? $line) ?: $line,
            'aktif'  => in_array($aktifRaw, ['tidak', 'no', '0', 'nonaktif'], true) ? 0 : 1,
        ];
    }
}
