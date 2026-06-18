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

        $rows  = $builder->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')->paginate(15);
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

    // ===================== IMPORT EXCEL =====================
    public function import()
    {
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'File tidak valid.');
        }
        if (! in_array($file->getExtension(), ['xlsx', 'xls'], true)) {
            return redirect()->back()->with('error', 'File harus berformat Excel (.xlsx / .xls).');
        }

        try {
            $sheet = IOFactory::load($file->getTempName())->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }

        // peta lookup (1 query masing-masing)
        $jurusanMap = [];
        foreach ((new JurusanModel())->select('id, kode')->findAll() as $j) {
            $jurusanMap[strtoupper($j['kode'])] = (int) $j['id'];
        }
        $guruModel = new GuruModel();
        $guruByKode = [];
        foreach ($guruModel->select('id, kode_guru, nama')->findAll() as $g) {
            $guruByKode[strtoupper($g['kode_guru'])] = (int) $g['id'];
            $guruByKode[strtoupper($g['nama'])]      = (int) $g['id'];
        }

        $ins = 0; $upd = 0; $skip = 0;
        $db  = db_connect();
        $db->transStart();

        foreach ($data as $i => $row) {
            if ($i === 0) {
                continue;
            }
            $nama = trim((string) ($row[0] ?? ''));
            if ($nama === '') {
                continue;
            }
            $tingkat = strtoupper(trim((string) ($row[1] ?? '')));
            $payload = [
                'nama_kelas'    => $nama,
                'tingkat'       => in_array($tingkat, ['X', 'XI', 'XII'], true) ? $tingkat : 'X',
                'jurusan_id'    => $jurusanMap[strtoupper(trim((string) ($row[2] ?? '')))] ?? null,
                'wali_kelas_id' => $guruByKode[strtoupper(trim((string) ($row[3] ?? '')))] ?? null,
                'shift'         => in_array(strtolower(trim((string) ($row[4] ?? ''))), ['pagi', 'siang'], true) ? strtolower(trim((string) $row[4])) : 'pagi',
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
            return redirect()->back()->with('error', 'Import dibatalkan karena kesalahan database.');
        }

        cache()->delete('opt_kelas');
        $this->audit->record('import', 'kelas', null, "Import kelas: +{$ins} baru, {$upd} update, {$skip} dilewati");

        return redirect()->to(site_url('admin/master/kelas'))->with('success', "Import selesai: {$ins} baru, {$upd} diperbarui.");
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
