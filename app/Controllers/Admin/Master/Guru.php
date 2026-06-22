<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\SubmissionModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Guru extends BaseController
{
    protected GuruModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new GuruModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        $builder = $this->model;
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama', $q)->orLike('kode_guru', $q)->orLike('nip', $q)
                ->groupEnd();
        }
        if (in_array($status, ['PNS', 'PPPK', 'GTY', 'GTT'], true)) {
            $builder = $builder->where('status_guru', $status);
        }

        $rows  = $builder->orderBy('nama', 'ASC')->paginate(10);
        $pager = $this->model->pager;

        return view('admin/master/guru', [
            'title'  => 'Master Guru',
            'rows'   => $rows,
            'pager'  => $pager,
            'q'      => $q,
            'status' => $status,
            'total'  => $pager ? $pager->getTotal() : count($rows),
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'guru', $this->model->getInsertID(), 'Tambah guru ' . $data['nama']);
        cache()->delete('opt_guru');

        return redirect()->to(site_url('admin/master/guru'))->with('success', 'Guru ditambahkan.');
    }

    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();
        $data['id'] = $id; // agar placeholder {id} pada rule is_unique terisi saat edit
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'guru', $id, 'Ubah guru ' . $data['nama']);
        cache()->delete('opt_guru');

        return redirect()->to(site_url('admin/master/guru'))->with('success', 'Guru diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        $this->audit->record('delete', 'guru', $id, 'Hapus guru');
        cache()->delete('opt_guru');

        return redirect()->to(site_url('admin/master/guru'))->with('success', 'Guru dihapus.');
    }

    /** Hapus banyak data sekaligus: mode 'selected' (ids terpilih) atau 'all' (semua). */
    public function bulkDelete()
    {
        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'all') {
            $ids = array_column($this->model->select('id')->findAll(), 'id');
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if (empty($ids)) {
            return redirect()->to(site_url('admin/master/guru'))->with('error', 'Tidak ada data yang dipilih untuk dihapus.');
        }

        $this->model->delete($ids);
        cache()->delete('opt_guru');
        $this->audit->record('delete', 'guru', null, 'Hapus massal ' . count($ids) . ' guru (' . $mode . ')');

        return redirect()->to(site_url('admin/master/guru'))->with('success', count($ids) . ' guru dihapus.');
    }

    private function collect(): array
    {
        return [
            'nip'           => trim((string) $this->request->getPost('nip')) ?: null,
            'kode_guru'     => trim((string) $this->request->getPost('kode_guru')),
            'nama'          => trim((string) $this->request->getPost('nama')),
            'jenis_kelamin' => in_array($this->request->getPost('jenis_kelamin'), ['L', 'P'], true) ? $this->request->getPost('jenis_kelamin') : null,
            'status_guru'   => in_array($this->request->getPost('status_guru'), ['PNS', 'PPPK', 'GTY', 'GTT'], true) ? $this->request->getPost('status_guru') : null,
            'max_beban'     => (int) ($this->request->getPost('max_beban') ?: 24),
            'keterangan'    => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];
    }

    // ===================== EXPORT EXCEL =====================
    public function export()
    {
        $rows = $this->model->orderBy('nama', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Master Guru');

        $headers = ['No', 'NIP', 'Kode Guru', 'Nama Guru', 'Jenis Kelamin', 'Status', 'Maks Beban (JP)', 'Keterangan'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, "'" . $d['nip'], $d['kode_guru'], $d['nama'],
                $d['jenis_kelamin'], $d['status_guru'], $d['max_beban'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }
        foreach (['A' => 5, 'B' => 22, 'C' => 12, 'D' => 30, 'E' => 14, 'F' => 10, 'G' => 16, 'H' => 25] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Master-Guru-' . date('Ymd-His'));
    }

    // ===================== TEMPLATE IMPORT =====================
    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Template Guru');

        $headers = ['NIP', 'Kode Guru', 'Nama Guru', 'Jenis Kelamin (L/P)', 'Status (PNS/PPPK/GTY/GTT)', 'Maks Beban (JP)', 'Keterangan'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        // contoh baris
        $sheet->fromArray(['1987...', '27', 'Muslimin, S.Kom', 'L', 'GTY', 24, 'Produktif TKJ'], null, 'A2', true);
        foreach (['A' => 22, 'B' => 12, 'C' => 30, 'D' => 20, 'E' => 26, 'F' => 16, 'G' => 25] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Template-Import-Guru');
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
            $data  = $sheet->toArray(null, true, true, false); // index numerik
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }

        $ins = 0; $upd = 0; $skip = 0; $errors = [];
        $db = db_connect();
        $db->transStart();

        foreach ($data as $i => $row) {
            if ($i === 0) {
                continue; // header
            }
            $nip   = trim((string) ($row[0] ?? ''));
            $kode  = trim((string) ($row[1] ?? ''));
            $nama  = trim((string) ($row[2] ?? ''));
            if ($kode === '' && $nama === '') {
                continue; // baris kosong
            }
            if ($kode === '' || $nama === '') {
                $skip++; $errors[] = 'Baris ' . ($i + 1) . ': kode/nama kosong.';
                continue;
            }

            $payload = [
                'nip'           => $nip ?: null,
                'kode_guru'     => $kode,
                'nama'          => $nama,
                'jenis_kelamin' => in_array(strtoupper(trim((string) ($row[3] ?? ''))), ['L', 'P'], true) ? strtoupper(trim((string) $row[3])) : null,
                'status_guru'   => in_array(strtoupper(trim((string) ($row[4] ?? ''))), ['PNS', 'PPPK', 'GTY', 'GTT'], true) ? strtoupper(trim((string) $row[4])) : null,
                'max_beban'     => (int) ($row[5] ?? 24) ?: 24,
                'keterangan'    => trim((string) ($row[6] ?? '')) ?: null,
            ];

            // Upsert berdasarkan kode_guru (termasuk yg soft-deleted)
            $existing = $this->model->withDeleted()->where('kode_guru', $kode)->first();
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
            return redirect()->back()->with('error', 'Import dibatalkan karena terjadi kesalahan database.');
        }

        cache()->delete('opt_guru');
        $this->audit->record('import', 'guru', null, "Import guru: +{$ins} baru, {$upd} update, {$skip} dilewati");

        $msg = "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.";
        if ($errors) {
            return redirect()->to(site_url('admin/master/guru'))->with('error', $msg . ' | ' . implode(' ', array_slice($errors, 0, 5)));
        }
        return redirect()->to(site_url('admin/master/guru'))->with('success', $msg);
    }

    // ===================== IMPOR DARI DATA KESEDIAAN LAMA =====================
    public function importFromSubmissions()
    {
        $subs = (new SubmissionModel())->select('nama_lengkap, nip_nuptk, status_kepegawaian')
            ->orderBy('nama_lengkap', 'ASC')->findAll();

        // kode awal lanjut dari kode numerik terbesar yg ada
        $maxKode = 0;
        foreach ($this->model->withDeleted()->select('kode_guru')->findAll() as $g) {
            if (ctype_digit((string) $g['kode_guru'])) {
                $maxKode = max($maxKode, (int) $g['kode_guru']);
            }
        }

        $ins = 0; $skip = 0;
        $db = db_connect();
        $db->transStart();

        foreach ($subs as $s) {
            $nama = trim((string) $s['nama_lengkap']);
            $nip  = trim((string) $s['nip_nuptk']);
            if ($nama === '') {
                continue;
            }
            // lewati bila sudah ada (cocokkan NIP bila ada, jika tidak cocokkan nama)
            $dup = $this->model->withDeleted()->groupStart();
            if ($nip !== '') {
                $dup->where('nip', $nip);
            } else {
                $dup->where('nama', $nama);
            }
            if ($dup->groupEnd()->first()) {
                $skip++;
                continue;
            }

            $this->model->insert([
                'nip'         => $nip ?: null,
                'kode_guru'   => (string) (++$maxKode),
                'nama'        => $nama,
                'status_guru' => in_array($s['status_kepegawaian'], ['PNS', 'PPPK', 'GTY', 'GTT'], true) ? $s['status_kepegawaian'] : null,
                'max_beban'   => 24,
            ]);
            $ins++;
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            return redirect()->back()->with('error', 'Impor dibatalkan karena kesalahan database.');
        }

        cache()->delete('opt_guru');
        $this->audit->record('import', 'guru', null, "Impor dari kesediaan: +{$ins} baru, {$skip} dilewati");

        return redirect()->to(site_url('admin/master/guru'))->with('success', "Impor dari data kesediaan selesai: {$ins} guru baru, {$skip} sudah ada (dilewati).");
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
