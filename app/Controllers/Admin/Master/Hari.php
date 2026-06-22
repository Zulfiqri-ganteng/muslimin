<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\HariModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Hari extends BaseController
{
    protected HariModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new HariModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        return view('admin/master/hari', [
            'title' => 'Master Hari',
            'rows'  => $this->model->orderBy('urutan', 'ASC')->findAll(),
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
        $this->audit->record('create', 'hari', $this->model->getInsertID(), 'Tambah hari ' . $data['nama']);

        return redirect()->to(site_url('admin/master/hari'))->with('success', 'Hari ditambahkan.');
    }

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
        $this->audit->record('update', 'hari', $id, 'Ubah hari ' . $data['nama']);

        return redirect()->to(site_url('admin/master/hari'))->with('success', 'Hari diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        $this->audit->record('delete', 'hari', $id, 'Hapus hari');

        return redirect()->to(site_url('admin/master/hari'))->with('success', 'Hari dihapus.');
    }

    /** Hapus banyak hari sekaligus: mode 'selected' atau 'all'. */
    public function bulkDelete()
    {
        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'all') {
            $ids = array_column($this->model->select('id')->findAll(), 'id');
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if (empty($ids)) {
            return redirect()->to(site_url('admin/master/hari'))->with('error', 'Tidak ada data yang dipilih untuk dihapus.');
        }

        $this->model->delete($ids);
        $this->audit->record('delete', 'hari', null, 'Hapus massal ' . count($ids) . ' hari (' . $mode . ')');

        return redirect()->to(site_url('admin/master/hari'))->with('success', count($ids) . ' hari dihapus.');
    }

    // ===================== EXPORT EXCEL =====================
    public function export()
    {
        $rows  = $this->model->orderBy('urutan', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Master Hari');

        $sheet->fromArray(['No', 'Nama Hari', 'Urutan', 'Aktif'], null, 'A1', true);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['nama'], $d['urutan'], $d['aktif'] ? 'Ya' : 'Tidak'], null, 'A' . $r, true);
            $r++;
        }
        foreach (['A' => 5, 'B' => 18, 'C' => 12, 'D' => 10] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Master-Hari-' . date('Ymd-His'));
    }

    // ===================== TEMPLATE IMPORT =====================
    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Template Hari');

        $sheet->fromArray(['Nama Hari', 'Urutan', 'Aktif (Ya/Tidak)'], null, 'A1', true);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->fromArray(['Senin', 1, 'Ya'], null, 'A2', true);
        foreach (['A' => 18, 'B' => 12, 'C' => 18] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Template-Import-Hari');
    }

    // ===================== KONFIG KOLOM IMPORT =====================
    private function importCols(): array
    {
        return [
            ['key' => 'nama',   'label' => 'Nama Hari', 'type' => 'text',   'required' => true, 'width' => 180],
            ['key' => 'urutan', 'label' => 'Urutan',    'type' => 'number', 'width' => 110],
            ['key' => 'aktif',  'label' => 'Aktif',     'type' => 'select', 'options' => ['Ya', 'Tidak'], 'width' => 120],
        ];
    }

    /** Baca file Excel → baris assoc sesuai urutan kolom template. Null bila gagal (flash diset). */
    private function readUpload(): ?array
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
                continue;
            }
            $assoc = [];
            foreach ($keys as $c => $k) {
                $assoc[$k] = trim((string) ($row[$c] ?? ''));
            }
            if (implode('', $assoc) === '') {
                continue;
            }
            $rows[] = $assoc;
        }
        return $rows;
    }

    // ===================== IMPORT: PRATINJAU (dapat diedit) =====================
    public function importPreview()
    {
        $rows = $this->readUpload();
        if ($rows === null) {
            return redirect()->to(site_url('admin/master/hari'));
        }
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/hari'))->with('error', 'File tidak berisi data.');
        }

        $existing = [];
        foreach ($this->model->select('nama')->findAll() as $h) {
            $existing[strtoupper((string) $h['nama'])] = true;
        }
        foreach ($rows as &$r) {
            $r['_status'] = isset($existing[strtoupper($r['nama'] ?? '')]) ? 'perbarui' : 'baru';
        }
        unset($r);

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor Hari',
            'subtitle'  => 'Master Hari',
            'cols'      => $this->importCols(),
            'rows'      => $rows,
            'commitUrl' => site_url('admin/master/hari/import-commit'),
            'backUrl'   => site_url('admin/master/hari'),
        ]);
    }

    // ===================== IMPORT: SIMPAN HASIL EDIT =====================
    public function importCommit()
    {
        $rows = (array) $this->request->getPost('rows');
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/hari'))->with('error', 'Tidak ada data untuk disimpan.');
        }

        [$ins, $upd, $skip, $errors] = $this->upsertRows($rows);

        $this->audit->record('import', 'hari', null, "Import hari: +{$ins} baru, {$upd} update, {$skip} dilewati");

        $msg = "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.";
        if ($errors) {
            return redirect()->to(site_url('admin/master/hari'))->with('error', $msg . ' | ' . implode(' ', array_slice($errors, 0, 5)));
        }
        return redirect()->to(site_url('admin/master/hari'))->with('success', $msg);
    }

    /** Upsert kumpulan baris assoc (by nama hari; tabel hari = hard delete). */
    private function upsertRows(array $rows): array
    {
        $ins = 0; $upd = 0; $skip = 0; $errors = [];
        $db  = db_connect();
        $db->transStart();

        $i = 0;
        foreach ($rows as $row) {
            $i++;
            $nama = trim((string) ($row['nama'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $aktifRaw = strtolower(trim((string) ($row['aktif'] ?? 'ya')));
            $payload  = [
                'nama'   => $nama,
                'urutan' => (int) ($row['urutan'] ?? $i) ?: $i,
                'aktif'  => in_array($aktifRaw, ['tidak', 'no', '0', 'nonaktif'], true) ? 0 : 1,
            ];

            $existing = $this->model->where('nama', $nama)->first();
            if ($existing) {
                $this->model->update($existing['id'], $payload + ['id' => $existing['id']]);
                $upd++;
            } else {
                $this->model->insert($payload);
                $ins++;
            }
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            $errors[] = 'Sebagian gagal disimpan karena kesalahan database.';
        }

        return [$ins, $upd, $skip, $errors];
    }

    // ---------------------------------------------------------------
    private function streamXlsx(Spreadsheet $ss, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }
}
