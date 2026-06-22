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
        $per    = (int) $this->request->getGet('per');
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }

        $builder = $this->model;
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama', $q)->orLike('kode_guru', $q)->orLike('nip', $q)
                ->groupEnd();
        }
        if (in_array($status, ['PNS', 'PPPK', 'GTY', 'GTT'], true)) {
            $builder = $builder->where('status_guru', $status);
        }

        $rows  = $builder->orderBy('nama', 'ASC')->paginate($per);
        $pager = $this->model->pager;

        return view('admin/master/guru', [
            'title'  => 'Master Guru',
            'rows'   => $rows,
            'pager'  => $pager,
            'q'      => $q,
            'status' => $status,
            'per'    => $per,
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

    // ===================== KONFIG KOLOM IMPORT =====================
    private function importCols(): array
    {
        return [
            ['key' => 'nip',           'label' => 'NIP',         'type' => 'text',   'width' => 150],
            ['key' => 'kode_guru',     'label' => 'Kode Guru',   'type' => 'text',   'required' => true, 'width' => 110],
            ['key' => 'nama',          'label' => 'Nama Guru',   'type' => 'text',   'required' => true, 'width' => 220],
            ['key' => 'jenis_kelamin', 'label' => 'JK',          'type' => 'select', 'options' => ['L', 'P'], 'width' => 90],
            ['key' => 'status_guru',   'label' => 'Status',      'type' => 'select', 'options' => ['PNS', 'PPPK', 'GTY', 'GTT'], 'width' => 110],
            ['key' => 'max_beban',     'label' => 'Maks JP',     'type' => 'number', 'width' => 90],
            ['key' => 'keterangan',    'label' => 'Keterangan',  'type' => 'text',   'width' => 180],
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
                continue; // header
            }
            $assoc = [];
            foreach ($keys as $c => $k) {
                $assoc[$k] = trim((string) ($row[$c] ?? ''));
            }
            // lewati baris yang seluruh selnya kosong
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
            return redirect()->to(site_url('admin/master/guru'));
        }
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/guru'))->with('error', 'File tidak berisi data.');
        }

        // tandai baru / perbarui berdasarkan kode_guru yang sudah ada
        $existing = [];
        foreach ($this->model->withDeleted()->select('kode_guru')->findAll() as $g) {
            $existing[strtoupper((string) $g['kode_guru'])] = true;
        }
        foreach ($rows as &$r) {
            $r['_status'] = isset($existing[strtoupper($r['kode_guru'] ?? '')]) ? 'perbarui' : 'baru';
        }
        unset($r);

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor Guru',
            'subtitle'  => 'Master Guru',
            'cols'      => $this->importCols(),
            'rows'      => $rows,
            'commitUrl' => site_url('admin/master/guru/import-commit'),
            'backUrl'   => site_url('admin/master/guru'),
        ]);
    }

    // ===================== IMPORT: SIMPAN HASIL EDIT =====================
    public function importCommit()
    {
        $rows = (array) $this->request->getPost('rows');
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/guru'))->with('error', 'Tidak ada data untuk disimpan.');
        }

        [$ins, $upd, $skip, $errors] = $this->upsertRows($rows);

        cache()->delete('opt_guru');
        $this->audit->record('import', 'guru', null, "Import guru: +{$ins} baru, {$upd} update, {$skip} dilewati");

        $msg = "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.";
        if ($errors) {
            return redirect()->to(site_url('admin/master/guru'))->with('error', $msg . ' | ' . implode(' ', array_slice($errors, 0, 5)));
        }
        return redirect()->to(site_url('admin/master/guru'))->with('success', $msg);
    }

    /** Upsert kumpulan baris assoc (by kode_guru, termasuk yg soft-deleted). */
    private function upsertRows(array $rows): array
    {
        $ins = 0; $upd = 0; $skip = 0; $errors = [];
        $db = db_connect();
        $db->transStart();

        foreach ($rows as $i => $row) {
            $kode = trim((string) ($row['kode_guru'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));
            if ($kode === '' && $nama === '') {
                continue;
            }
            if ($kode === '' || $nama === '') {
                $skip++; $errors[] = 'Baris ' . ($i + 1) . ': kode/nama kosong.';
                continue;
            }

            $jk     = strtoupper(trim((string) ($row['jenis_kelamin'] ?? '')));
            $status = strtoupper(trim((string) ($row['status_guru'] ?? '')));
            $payload = [
                'nip'           => trim((string) ($row['nip'] ?? '')) ?: null,
                'kode_guru'     => $kode,
                'nama'          => $nama,
                'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
                'status_guru'   => in_array($status, ['PNS', 'PPPK', 'GTY', 'GTT'], true) ? $status : null,
                'max_beban'     => (int) ($row['max_beban'] ?? 24) ?: 24,
                'keterangan'    => trim((string) ($row['keterangan'] ?? '')) ?: null,
            ];

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
            $errors[] = 'Sebagian gagal disimpan karena kesalahan database.';
        }

        return [$ins, $upd, $skip, $errors];
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
