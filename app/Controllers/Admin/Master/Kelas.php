<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\JurusanModel;
use App\Models\KelasModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Kelas extends BaseController
{
    protected KelasModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new KelasModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $q       = trim((string) $this->request->getGet('q'));
        $tingkat = trim((string) $this->request->getGet('tingkat'));
        $shift   = trim((string) $this->request->getGet('shift'));

        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->like('kelas.nama_kelas', $q);
        }
        if (in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $builder = $builder->where('kelas.tingkat', $tingkat);
        }
        if (in_array($shift, ['pagi', 'siang'], true)) {
            $builder = $builder->where('kelas.shift', $shift);
        }

        $rows  = $builder->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')->paginate(10);
        $pager = $this->model->pager;

        return view('admin/master/kelas', [
            'title'       => 'Master Kelas',
            'rows'        => $rows,
            'pager'       => $pager,
            'q'           => $q,
            'tingkat'     => $tingkat,
            'shift'       => $shift,
            'total'       => $pager ? $pager->getTotal() : count($rows),
            'jurusanOpts' => (new JurusanModel())->options(),
            'guruOpts'    => (new GuruModel())->options(),
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'kelas', $this->model->getInsertID(), 'Tambah kelas ' . $data['nama_kelas']);
        cache()->delete('opt_kelas');

        return redirect()->to(site_url('admin/master/kelas'))->with('success', 'Kelas ditambahkan.');
    }

    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();
        $data['id'] = $id; // isi placeholder {id} pada rule is_unique saat edit
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'kelas', $id, 'Ubah kelas ' . $data['nama_kelas']);
        cache()->delete('opt_kelas');

        return redirect()->to(site_url('admin/master/kelas'))->with('success', 'Kelas diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        cache()->delete('opt_kelas');
        $this->audit->record('delete', 'kelas', $id, 'Hapus kelas');

        return redirect()->to(site_url('admin/master/kelas'))->with('success', 'Kelas dihapus.');
    }

    /** Hapus banyak kelas sekaligus: mode 'selected' (ids terpilih) atau 'all' (semua). */
    public function bulkDelete()
    {
        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'all') {
            $ids = array_column($this->model->select('id')->findAll(), 'id');
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if (empty($ids)) {
            return redirect()->to(site_url('admin/master/kelas'))->with('error', 'Tidak ada data yang dipilih untuk dihapus.');
        }

        $this->model->delete($ids);
        cache()->delete('opt_kelas');
        $this->audit->record('delete', 'kelas', null, 'Hapus massal ' . count($ids) . ' kelas (' . $mode . ')');

        return redirect()->to(site_url('admin/master/kelas'))->with('success', count($ids) . ' kelas dihapus.');
    }

    private function collect(): array
    {
        return [
            'nama_kelas'    => trim((string) $this->request->getPost('nama_kelas')),
            'tingkat'       => in_array($this->request->getPost('tingkat'), ['X', 'XI', 'XII'], true) ? $this->request->getPost('tingkat') : 'X',
            'jurusan_id'    => (int) $this->request->getPost('jurusan_id') ?: null,
            'wali_kelas_id' => (int) $this->request->getPost('wali_kelas_id') ?: null,
            'shift'         => in_array($this->request->getPost('shift'), ['pagi', 'siang'], true) ? $this->request->getPost('shift') : 'pagi',
        ];
    }

    // ===================== EXPORT EXCEL =====================
    public function export()
    {
        $rows = $this->model->withRelations()->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Master Kelas');

        $headers = ['No', 'Nama Kelas', 'Tingkat', 'Jurusan', 'Wali Kelas', 'Shift'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['nama_kelas'], $d['tingkat'], $d['jurusan_kode'], $d['wali_nama'], ucfirst($d['shift']),
            ], null, 'A' . $r, true);
            $r++;
        }
        foreach (['A' => 5, 'B' => 18, 'C' => 10, 'D' => 12, 'E' => 28, 'F' => 10] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Master-Kelas-' . date('Ymd-His'));
    }

    // ===================== TEMPLATE IMPORT =====================
    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Template Kelas');

        $headers = ['Nama Kelas', 'Tingkat (X/XI/XII)', 'Kode Jurusan', 'Kode/Nama Wali Kelas', 'Shift (pagi/siang)'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->fromArray(['X TKJT 1', 'X', 'TKJT', '27', 'pagi'], null, 'A2', true);
        foreach (['A' => 18, 'B' => 18, 'C' => 14, 'D' => 22, 'E' => 18] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Template-Import-Kelas');
    }

    // ===================== KONFIG KOLOM IMPORT =====================
    private function importCols(): array
    {
        $jurusanKodes = array_column((new JurusanModel())->select('kode')->orderBy('kode', 'ASC')->findAll(), 'kode');

        return [
            ['key' => 'nama_kelas',   'label' => 'Nama Kelas',       'type' => 'text',   'required' => true, 'width' => 160],
            ['key' => 'tingkat',      'label' => 'Tingkat',          'type' => 'select', 'options' => ['X', 'XI', 'XII'], 'width' => 100],
            ['key' => 'jurusan_kode', 'label' => 'Kode Jurusan',     'type' => 'select', 'options' => $jurusanKodes, 'width' => 130],
            ['key' => 'wali',         'label' => 'Wali (kode/nama)', 'type' => 'text',   'width' => 200],
            ['key' => 'shift',        'label' => 'Shift',            'type' => 'select', 'options' => ['pagi', 'siang'], 'width' => 110],
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
            return redirect()->to(site_url('admin/master/kelas'));
        }
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/kelas'))->with('error', 'File tidak berisi data.');
        }

        $existing = [];
        foreach ($this->model->withDeleted()->select('nama_kelas')->findAll() as $k) {
            $existing[strtoupper((string) $k['nama_kelas'])] = true;
        }
        foreach ($rows as &$r) {
            $r['_status'] = isset($existing[strtoupper($r['nama_kelas'] ?? '')]) ? 'perbarui' : 'baru';
        }
        unset($r);

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor Kelas',
            'subtitle'  => 'Master Kelas',
            'cols'      => $this->importCols(),
            'rows'      => $rows,
            'commitUrl' => site_url('admin/master/kelas/import-commit'),
            'backUrl'   => site_url('admin/master/kelas'),
        ]);
    }

    // ===================== IMPORT: SIMPAN HASIL EDIT =====================
    public function importCommit()
    {
        $rows = (array) $this->request->getPost('rows');
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/kelas'))->with('error', 'Tidak ada data untuk disimpan.');
        }

        [$ins, $upd, $skip, $errors] = $this->upsertRows($rows);

        cache()->delete('opt_kelas');
        $this->audit->record('import', 'kelas', null, "Import kelas: +{$ins} baru, {$upd} update, {$skip} dilewati");

        $msg = "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.";
        if ($errors) {
            return redirect()->to(site_url('admin/master/kelas'))->with('error', $msg . ' | ' . implode(' ', array_slice($errors, 0, 5)));
        }
        return redirect()->to(site_url('admin/master/kelas'))->with('success', $msg);
    }

    /** Upsert kumpulan baris assoc (by nama_kelas, termasuk yg soft-deleted). */
    private function upsertRows(array $rows): array
    {
        // peta lookup (1 query masing-masing)
        $jurusanMap = [];
        foreach ((new JurusanModel())->select('id, kode')->findAll() as $j) {
            $jurusanMap[strtoupper($j['kode'])] = (int) $j['id'];
        }
        $guruByKode = [];
        foreach ((new GuruModel())->select('id, kode_guru, nama')->findAll() as $g) {
            $guruByKode[strtoupper($g['kode_guru'])] = (int) $g['id'];
            $guruByKode[strtoupper($g['nama'])]      = (int) $g['id'];
        }

        $ins = 0; $upd = 0; $skip = 0; $errors = [];
        $db  = db_connect();
        $db->transStart();

        foreach ($rows as $i => $row) {
            $nama = trim((string) ($row['nama_kelas'] ?? ''));
            if ($nama === '') {
                $skip++;
                continue;
            }
            $tingkat = strtoupper(trim((string) ($row['tingkat'] ?? '')));
            $shift   = strtolower(trim((string) ($row['shift'] ?? '')));
            $payload = [
                'nama_kelas'    => $nama,
                'tingkat'       => in_array($tingkat, ['X', 'XI', 'XII'], true) ? $tingkat : 'X',
                'jurusan_id'    => $jurusanMap[strtoupper(trim((string) ($row['jurusan_kode'] ?? '')))] ?? null,
                'wali_kelas_id' => $guruByKode[strtoupper(trim((string) ($row['wali'] ?? '')))] ?? null,
                'shift'         => in_array($shift, ['pagi', 'siang'], true) ? $shift : 'pagi',
            ];

            $existing = $this->model->withDeleted()->where('nama_kelas', $nama)->first();
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
