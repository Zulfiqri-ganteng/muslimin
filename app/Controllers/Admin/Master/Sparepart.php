<?php

namespace App\Controllers\Admin\Master;

use App\Models\SparepartModel;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Sparepart — stok suku cadang lab. Mutasi masuk/keluar dicatat di
 * P4 (penggantian komponen pada perbaikan mengurangi stok otomatis).
 */
class Sparepart extends BaseMaster
{
    protected string $module     = 'sparepart';
    protected string $auditTable = 'sparepart';
    protected string $routeBase  = 'admin/master/sparepart';
    protected string $entity     = 'sparepart';
    protected string $titleLabel = 'Master Sparepart';

    protected function makeModel(): Model
    {
        return new SparepartModel();
    }

    protected function indexUrl(): string
    {
        $qs = array_filter([
            'q'   => trim((string) $this->request->getGet('q')),
            'per' => (string) ((int) $this->request->getGet('per') ?: ''),
        ], static fn ($v) => $v !== '');

        return site_url($this->routeBase) . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    public function index()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = $this->pageNo();

        $kunci = "list|q={$q}|per={$per}|p={$page}";
        $data  = $this->cachedList($kunci, function () use ($q, $per, $page) {
            $builder = $this->model;
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('nama', $q)->orLike('kode', $q)->orLike('kategori', $q)
                    ->groupEnd();
            }
            $rows = $builder->orderBy('nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/sparepart', [
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
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah sparepart ' . $data['nama']);

        return $this->goIndex('Sparepart ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id         = (int) $id;
        $data       = $this->collect();
        $data['id'] = $id;
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah sparepart ' . $data['nama']);

        return $this->goIndex('Sparepart diperbarui.');
    }

    private function collect(): array
    {
        $post  = fn (string $k) => trim((string) $this->request->getPost($k));
        $harga = $post('harga');

        return [
            'kode'         => strtoupper($post('kode')),
            'nama'         => $post('nama'),
            'kategori'     => $post('kategori') ?: null,
            'satuan'       => $post('satuan') ?: 'unit',
            'stok'         => (int) $this->request->getPost('stok'),
            'stok_minimum' => (int) $this->request->getPost('stok_minimum'),
            'harga'        => $harga !== '' ? (float) $harga : null,
            'lokasi'       => $post('lokasi') ?: null,
            'keterangan'   => $post('keterangan') ?: null,
        ];
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows  = $this->model->orderBy('nama', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Sparepart',
            ['No', 'Kode', 'Nama', 'Kategori', 'Satuan', 'Stok', 'Stok Min', 'Harga', 'Lokasi', 'Keterangan'],
            ['A' => 5, 'B' => 14, 'C' => 26, 'D' => 16, 'E' => 10, 'F' => 8, 'G' => 9, 'H' => 14, 'I' => 16, 'J' => 26]
        );
        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['kode'], $d['nama'], $d['kategori'], $d['satuan'],
                (int) $d['stok'], (int) $d['stok_minimum'], $d['harga'], $d['lokasi'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Sparepart-' . date('Ymd-His'), 'J');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Sparepart',
            ['Kode', 'Nama', 'Kategori', 'Satuan', 'Stok', 'Stok Minimum', 'Harga', 'Lokasi', 'Keterangan'],
            ['A' => 14, 'B' => 26, 'C' => 16, 'D' => 10, 'E' => 10, 'F' => 14, 'G' => 14, 'H' => 16, 'I' => 26]
        );
        $sheet->fromArray(['SP01', 'RAM DDR4 8GB', 'RAM', 'keping', 10, 2, 350000, 'Gudang A', ''], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Sparepart');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',         'label' => 'Kode',         'type' => 'text',   'required' => true, 'width' => 120],
            ['key' => 'nama',         'label' => 'Nama',         'type' => 'text',   'required' => true, 'width' => 220],
            ['key' => 'kategori',     'label' => 'Kategori',     'type' => 'text',   'width' => 140],
            ['key' => 'satuan',       'label' => 'Satuan',       'type' => 'text',   'width' => 90],
            ['key' => 'stok',         'label' => 'Stok',         'type' => 'number', 'width' => 80],
            ['key' => 'stok_minimum', 'label' => 'Stok Minimum', 'type' => 'number', 'width' => 100],
            ['key' => 'harga',        'label' => 'Harga',        'type' => 'number', 'width' => 110],
            ['key' => 'lokasi',       'label' => 'Lokasi',       'type' => 'text',   'width' => 140],
            ['key' => 'keterangan',   'label' => 'Keterangan',   'type' => 'text',   'width' => 180],
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
        $harga = trim((string) ($row['harga'] ?? ''));

        return [
            'kode'         => $kode,
            'nama'         => $nama,
            'kategori'     => trim((string) ($row['kategori'] ?? '')) ?: null,
            'satuan'       => trim((string) ($row['satuan'] ?? '')) ?: 'unit',
            'stok'         => (int) ($row['stok'] ?? 0),
            'stok_minimum' => (int) ($row['stok_minimum'] ?? 0),
            'harga'        => $harga !== '' ? (float) $harga : null,
            'lokasi'       => trim((string) ($row['lokasi'] ?? '')) ?: null,
            'keterangan'   => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }
}
