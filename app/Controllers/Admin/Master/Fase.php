<?php

namespace App\Controllers\Admin\Master;

use App\Models\FaseModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Fase extends BaseMaster
{
    protected string $module     = 'fase';
    protected string $auditTable = 'fase';
    protected string $routeBase  = 'admin/master/fase';
    protected string $entity     = 'fase';
    protected string $titleLabel = 'Master Fase';

    protected function makeModel(): Model
    {
        return new FaseModel();
    }

    public function index()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|per={$per}|p={$page}", function () use ($q, $per, $page) {
            $builder = $this->model;
            if ($q !== '') {
                $builder = $builder->groupStart()->like('nama', $q)->orLike('kode', $q)->groupEnd();
            }
            $rows = $builder->orderBy('urutan', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/fase', [
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
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah fase ' . $data['kode']);

        return $this->goIndex('Fase ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id         = (int) $id;
        $data       = $this->collect();
        $data['id'] = $id; // isi placeholder {id} pada rule is_unique saat edit
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah fase ' . $data['kode']);

        return $this->goIndex('Fase diperbarui.');
    }

    private function collect(): array
    {
        $deskripsi = trim((string) $this->request->getPost('deskripsi'));

        return [
            'kode'      => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama'      => trim((string) $this->request->getPost('nama')),
            'urutan'    => (int) $this->request->getPost('urutan'),
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
        ];
    }

    /** Anti-orphan: kelas yang memakai fase ini dilepas (fase_id = NULL). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('kelas')->whereIn('fase_id', $ids)->update(['fase_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->orderBy('urutan', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Fase',
            ['No', 'Kode', 'Nama Fase', 'Urutan', 'Deskripsi'],
            ['A' => 5, 'B' => 10, 'C' => 16, 'D' => 10, 'E' => 40]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['kode'], $d['nama'], $d['urutan'], $d['deskripsi']], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Fase-' . date('Ymd-His'), 'E');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Fase',
            ['Kode', 'Nama Fase', 'Urutan', 'Deskripsi'],
            ['A' => 10, 'B' => 16, 'C' => 10, 'D' => 40]
        );
        $sheet->fromArray(['G', 'Fase G', 7, 'Contoh fase tambahan'], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Fase');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',      'label' => 'Kode',      'type' => 'text',   'required' => true, 'width' => 90],
            ['key' => 'nama',      'label' => 'Nama Fase', 'type' => 'text',   'required' => true, 'width' => 160],
            ['key' => 'urutan',    'label' => 'Urutan',    'type' => 'number', 'width' => 100],
            ['key' => 'deskripsi', 'label' => 'Deskripsi', 'type' => 'text',   'width' => 260],
        ];
    }

    protected function matchField(): string
    {
        return 'kode';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $kode = strtoupper(trim((string) ($row['kode'] ?? '')));
        $nama = trim((string) ($row['nama'] ?? ''));
        if ($kode === '' && $nama === '') {
            return null;
        }
        if ($kode === '' || $nama === '') {
            $error = 'Baris ' . $line . ': kode/nama kosong.';

            return null;
        }

        $deskripsi = trim((string) ($row['deskripsi'] ?? ''));

        return [
            'kode'      => $kode,
            'nama'      => $nama,
            'urutan'    => (int) ($row['urutan'] ?? $line) ?: $line,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
        ];
    }
}
