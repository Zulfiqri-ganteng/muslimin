<?php

namespace App\Controllers\Admin\Master;

use App\Models\LabModel;
use App\Models\TempatUjiModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Tempat Uji UKK. Lokasi pelaksanaan (bisa lab sekolah, opsional
 * ditautkan ke Master Lab/SIMLAB; bisa juga lokasi luar seperti mitra DUDI).
 */
class TempatUji extends BaseMaster
{
    protected string $module     = 'tempat_uji';
    protected string $auditTable = 'tempat_uji';
    protected string $routeBase  = 'admin/master/tempat-uji';
    protected string $entity     = 'tempat uji';
    protected string $titleLabel = 'Master Tempat Uji';

    protected function makeModel(): Model
    {
        return new TempatUjiModel();
    }

    public function index()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|per={$per}|p={$page}", function () use ($q, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('tempat_uji.nama', $q)->orLike('tempat_uji.kode', $q)->orLike('tempat_uji.alamat', $q)
                    ->groupEnd();
            }
            $rows = $builder->orderBy('tempat_uji.nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/tempat_uji', [
            'title'   => $this->titleLabel,
            'rows'    => $data['rows'],
            'pager'   => $this->storePager($page, $per, $data['total']),
            'q'       => $q,
            'per'     => $per,
            'total'   => $data['total'],
            'labOpts' => (new LabModel())->options(),
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah tempat uji ' . $data['nama']);

        return $this->goIndex('Tempat uji ditambahkan.');
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
        $this->audit->record('update', $this->auditTable, $id, 'Ubah tempat uji ' . $data['nama']);

        return $this->goIndex('Tempat uji diperbarui.');
    }

    private function collect(): array
    {
        $post = fn (string $k) => trim((string) $this->request->getPost($k));

        return [
            'kode'       => strtoupper($post('kode')),
            'nama'       => $post('nama'),
            'alamat'     => $post('alamat') ?: null,
            'kapasitas'  => (int) $this->request->getPost('kapasitas') ?: null,
            'lab_id'     => (int) $this->request->getPost('lab_id') ?: null,
            'keterangan' => $post('keterangan') ?: null,
        ];
    }

    /** Lepas rujukan tempat uji di jadwal UKK (soft delete → FK tak jalan, dibersihkan manual). */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal_ukk')->whereIn('tempat_uji_id', $ids)->update(['tempat_uji_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows  = $this->model->withRelations()->orderBy('tempat_uji.nama', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Tempat Uji',
            ['No', 'Kode', 'Nama', 'Alamat', 'Kapasitas', 'Lab Tertaut', 'Keterangan'],
            ['A' => 5, 'B' => 14, 'C' => 28, 'D' => 30, 'E' => 12, 'F' => 20, 'G' => 30]
        );
        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['kode'], $d['nama'], $d['alamat'], $d['kapasitas'], $d['lab_nama'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Tempat-Uji-' . date('Ymd-His'), 'G');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Tempat Uji',
            ['Kode', 'Nama', 'Alamat', 'Kapasitas', 'Keterangan'],
            ['A' => 14, 'B' => 28, 'C' => 30, 'D' => 12, 'E' => 30]
        );
        $sheet->fromArray(['TU01', 'Lab TKJ 1', 'Jl. Contoh No. 1', 30, ''], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Tempat-Uji');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',       'label' => 'Kode',       'type' => 'text',   'required' => true, 'width' => 120],
            ['key' => 'nama',       'label' => 'Nama',       'type' => 'text',   'required' => true, 'width' => 220],
            ['key' => 'alamat',     'label' => 'Alamat',     'type' => 'text',   'width' => 240],
            ['key' => 'kapasitas',  'label' => 'Kapasitas',  'type' => 'number', 'width' => 100],
            ['key' => 'keterangan', 'label' => 'Keterangan', 'type' => 'text',   'width' => 200],
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

        return [
            'kode'       => $kode,
            'nama'       => $nama,
            'alamat'     => trim((string) ($row['alamat'] ?? '')) ?: null,
            'kapasitas'  => (int) ($row['kapasitas'] ?? 0) ?: null,
            'lab_id'     => null,
            'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }
}
