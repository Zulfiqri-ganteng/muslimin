<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\JurusanModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Jurusan extends BaseController
{
    protected JurusanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JurusanModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        return view('admin/master/jurusan', [
            'title' => 'Master Jurusan',
            'rows'  => $this->model->orderBy('kode', 'ASC')->findAll(),
        ]);
    }

    public function store()
    {
        $data = [
            'kode' => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama' => trim((string) $this->request->getPost('nama')),
        ];

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'jurusan', $this->model->getInsertID(), 'Tambah jurusan ' . $data['kode']);
        cache()->delete('opt_jurusan');

        return redirect()->to(site_url('admin/master/jurusan'))->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function update($id)
    {
        $id   = (int) $id;
        $data = [
            'kode' => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama' => trim((string) $this->request->getPost('nama')),
            'id'   => $id, // isi placeholder {id} pada rule is_unique saat edit
        ];

        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'jurusan', $id, 'Ubah jurusan ' . $data['kode']);
        cache()->delete('opt_jurusan');

        return redirect()->to(site_url('admin/master/jurusan'))->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        $this->audit->record('delete', 'jurusan', $id, 'Hapus jurusan');
        cache()->delete('opt_jurusan');

        return redirect()->to(site_url('admin/master/jurusan'))->with('success', 'Jurusan dihapus.');
    }

    /** Hapus banyak jurusan sekaligus: mode 'selected' atau 'all'. */
    public function bulkDelete()
    {
        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'all') {
            $ids = array_column($this->model->select('id')->findAll(), 'id');
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if (empty($ids)) {
            return redirect()->to(site_url('admin/master/jurusan'))->with('error', 'Tidak ada data yang dipilih untuk dihapus.');
        }

        $this->model->delete($ids);
        cache()->delete('opt_jurusan');
        $this->audit->record('delete', 'jurusan', null, 'Hapus massal ' . count($ids) . ' jurusan (' . $mode . ')');

        return redirect()->to(site_url('admin/master/jurusan'))->with('success', count($ids) . ' jurusan dihapus.');
    }

    // ===================== EXPORT EXCEL =====================
    public function export()
    {
        $rows  = $this->model->orderBy('kode', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Master Jurusan');

        $sheet->fromArray(['No', 'Kode', 'Nama Jurusan'], null, 'A1', true);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['kode'], $d['nama']], null, 'A' . $r, true);
            $r++;
        }
        foreach (['A' => 5, 'B' => 14, 'C' => 40] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Master-Jurusan-' . date('Ymd-His'));
    }

    // ===================== TEMPLATE IMPORT =====================
    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Template Jurusan');

        $sheet->fromArray(['Kode', 'Nama Jurusan'], null, 'A1', true);
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->fromArray(['TKJT', 'Teknik Komputer Jaringan & Telekomunikasi'], null, 'A2', true);
        foreach (['A' => 14, 'B' => 40] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Template-Import-Jurusan');
    }

    // ===================== KONFIG KOLOM IMPORT =====================
    private function importCols(): array
    {
        return [
            ['key' => 'kode', 'label' => 'Kode',         'type' => 'text', 'required' => true, 'width' => 130],
            ['key' => 'nama', 'label' => 'Nama Jurusan', 'type' => 'text', 'required' => true, 'width' => 320],
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
            return redirect()->to(site_url('admin/master/jurusan'));
        }
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/jurusan'))->with('error', 'File tidak berisi data.');
        }

        $existing = [];
        foreach ($this->model->withDeleted()->select('kode')->findAll() as $j) {
            $existing[strtoupper((string) $j['kode'])] = true;
        }
        foreach ($rows as &$r) {
            $r['_status'] = isset($existing[strtoupper($r['kode'] ?? '')]) ? 'perbarui' : 'baru';
        }
        unset($r);

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor Jurusan',
            'subtitle'  => 'Master Jurusan',
            'cols'      => $this->importCols(),
            'rows'      => $rows,
            'commitUrl' => site_url('admin/master/jurusan/import-commit'),
            'backUrl'   => site_url('admin/master/jurusan'),
        ]);
    }

    // ===================== IMPORT: SIMPAN HASIL EDIT =====================
    public function importCommit()
    {
        $rows = (array) $this->request->getPost('rows');
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/jurusan'))->with('error', 'Tidak ada data untuk disimpan.');
        }

        [$ins, $upd, $skip, $errors] = $this->upsertRows($rows);

        cache()->delete('opt_jurusan');
        $this->audit->record('import', 'jurusan', null, "Import jurusan: +{$ins} baru, {$upd} update, {$skip} dilewati");

        $msg = "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.";
        if ($errors) {
            return redirect()->to(site_url('admin/master/jurusan'))->with('error', $msg . ' | ' . implode(' ', array_slice($errors, 0, 5)));
        }
        return redirect()->to(site_url('admin/master/jurusan'))->with('success', $msg);
    }

    /** Upsert kumpulan baris assoc (by kode, termasuk yg soft-deleted). */
    private function upsertRows(array $rows): array
    {
        $ins = 0; $upd = 0; $skip = 0; $errors = [];
        $db  = db_connect();
        $db->transStart();

        foreach ($rows as $i => $row) {
            $kode = strtoupper(trim((string) ($row['kode'] ?? '')));
            $nama = trim((string) ($row['nama'] ?? ''));
            if ($kode === '' && $nama === '') {
                continue;
            }
            if ($kode === '' || $nama === '') {
                $skip++; $errors[] = 'Baris ' . ($i + 1) . ': kode/nama kosong.';
                continue;
            }

            $payload = ['kode' => $kode, 'nama' => $nama];
            $existing = $this->model->withDeleted()->where('kode', $kode)->first();
            if ($existing) {
                $this->model->protect(false)->update($existing['id'], $payload + ['deleted_at' => null, 'id' => $existing['id']]);
                $this->model->protect(true);
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
