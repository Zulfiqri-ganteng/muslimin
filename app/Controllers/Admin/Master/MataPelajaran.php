<?php

namespace App\Controllers\Admin\Master;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruMapelModel;
use App\Models\GuruModel;
use App\Models\MataPelajaranModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MataPelajaran extends BaseController
{
    protected MataPelajaranModel $model;
    protected GuruMapelModel $kompetensi;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model      = new MataPelajaranModel();
        $this->kompetensi = new GuruMapelModel();
        $this->audit      = new AuditModel();
    }

    public function index()
    {
        $q        = trim((string) $this->request->getGet('q'));
        $kelompok = trim((string) $this->request->getGet('kelompok'));

        $builder = $this->model;
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama_mapel', $q)->orLike('kode_mapel', $q)
                ->groupEnd();
        }
        if ($kelompok !== '') {
            $builder = $builder->where('kelompok', $kelompok);
        }

        $rows  = $builder->orderBy('nama_mapel', 'ASC')->paginate(10);
        $pager = $this->model->pager;

        // Peta kompetensi hanya untuk mapel yang tampil (1 query, efisien).
        $mapelIds = array_column($rows, 'id');
        $kompMap  = $this->kompetensi->mapForMapelIds($mapelIds);

        return view('admin/master/mapel', [
            'title'    => 'Master Mata Pelajaran',
            'rows'     => $rows,
            'pager'    => $pager,
            'q'        => $q,
            'kelompok' => $kelompok,
            'total'    => $pager ? $pager->getTotal() : count($rows),
            'allGuru'  => (new GuruModel())->options(),
            'kompMap'  => $kompMap,
            'kelompokList' => $this->kelompokList(),
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('create', 'mata_pelajaran', $this->model->getInsertID(), 'Tambah mapel ' . $data['nama_mapel']);
        cache()->delete('opt_mapel');

        return redirect()->to(site_url('admin/master/mapel'))->with('success', 'Mata pelajaran ditambahkan.');
    }

    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();
        $data['id'] = $id; // isi placeholder {id} pada rule is_unique saat edit
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $this->audit->record('update', 'mata_pelajaran', $id, 'Ubah mapel ' . $data['nama_mapel']);
        cache()->delete('opt_mapel');

        return redirect()->to(site_url('admin/master/mapel'))->with('success', 'Mata pelajaran diperbarui.');
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->model->delete($id);
        cache()->delete('opt_mapel');
        $this->audit->record('delete', 'mata_pelajaran', $id, 'Hapus mapel');

        return redirect()->to(site_url('admin/master/mapel'))->with('success', 'Mata pelajaran dihapus.');
    }

    /** Hapus banyak mapel sekaligus: mode 'selected' (ids terpilih) atau 'all' (semua). */
    public function bulkDelete()
    {
        $mode = (string) $this->request->getPost('mode');
        if ($mode === 'all') {
            $ids = array_column($this->model->select('id')->findAll(), 'id');
        } else {
            $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        }
        if (empty($ids)) {
            return redirect()->to(site_url('admin/master/mapel'))->with('error', 'Tidak ada data yang dipilih untuk dihapus.');
        }

        $this->model->delete($ids);
        cache()->delete('opt_mapel');
        $this->audit->record('delete', 'mata_pelajaran', null, 'Hapus massal ' . count($ids) . ' mapel (' . $mode . ')');

        return redirect()->to(site_url('admin/master/mapel'))->with('success', count($ids) . ' mata pelajaran dihapus.');
    }

    /** Simpan kompetensi (daftar guru) untuk satu mapel. */
    public function kompetensi($id)
    {
        $id      = (int) $id;
        $guruIds = (array) $this->request->getPost('guru_ids');
        $this->kompetensi->sync($id, $guruIds);
        $this->audit->record('update', 'guru_mapel', $id, 'Atur kompetensi: ' . count($guruIds) . ' guru');

        return redirect()->to(site_url('admin/master/mapel'))->with('success', 'Kompetensi guru pengampu diperbarui.');
    }

    private function collect(): array
    {
        return [
            'kode_mapel' => strtoupper(trim((string) $this->request->getPost('kode_mapel'))),
            'nama_mapel' => trim((string) $this->request->getPost('nama_mapel')),
            'kelompok'   => trim((string) $this->request->getPost('kelompok')) ?: null,
            'jp_default' => (int) ($this->request->getPost('jp_default') ?: 2),
        ];
    }

    private function kelompokList(): array
    {
        return ['Umum', 'Kejuruan', 'Dasar-dasar Kejuruan', 'Muatan Lokal', 'Pilihan'];
    }

    // ===================== EXPORT EXCEL =====================
    public function export()
    {
        $rows = $this->model->orderBy('nama_mapel', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Master Mapel');

        $headers = ['No', 'Kode Mapel', 'Nama Mapel', 'Kelompok', 'JP / Minggu'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['kode_mapel'], $d['nama_mapel'], $d['kelompok'], $d['jp_default']], null, 'A' . $r, true);
            $r++;
        }
        foreach (['A' => 5, 'B' => 14, 'C' => 36, 'D' => 22, 'E' => 12] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Master-Mapel-' . date('Ymd-His'));
    }

    // ===================== TEMPLATE IMPORT =====================
    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Template Mapel');

        $headers = ['Kode Mapel', 'Nama Mapel', 'Kelompok', 'JP / Minggu'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->fromArray(['PD', 'Pemrograman Dasar', 'Kejuruan', 8], null, 'A2', true);
        foreach (['A' => 14, 'B' => 36, 'C' => 22, 'D' => 12] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $this->streamXlsx($ss, 'Template-Import-Mapel');
    }

    // ===================== KONFIG KOLOM IMPORT =====================
    private function importCols(): array
    {
        return [
            ['key' => 'kode_mapel', 'label' => 'Kode Mapel', 'type' => 'text',   'required' => true, 'width' => 120],
            ['key' => 'nama_mapel', 'label' => 'Nama Mapel', 'type' => 'text',   'required' => true, 'width' => 280],
            ['key' => 'kelompok',   'label' => 'Kelompok',   'type' => 'datalist', 'options' => $this->kelompokList(), 'width' => 180],
            ['key' => 'jp_default', 'label' => 'JP / Minggu', 'type' => 'number', 'width' => 100],
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
            return redirect()->to(site_url('admin/master/mapel'));
        }
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/mapel'))->with('error', 'File tidak berisi data.');
        }

        $existing = [];
        foreach ($this->model->withDeleted()->select('kode_mapel')->findAll() as $m) {
            $existing[strtoupper((string) $m['kode_mapel'])] = true;
        }
        foreach ($rows as &$r) {
            $r['_status'] = isset($existing[strtoupper($r['kode_mapel'] ?? '')]) ? 'perbarui' : 'baru';
        }
        unset($r);

        return view('admin/master/import_preview', [
            'title'     => 'Pratinjau Impor Mata Pelajaran',
            'subtitle'  => 'Master Mata Pelajaran',
            'cols'      => $this->importCols(),
            'rows'      => $rows,
            'commitUrl' => site_url('admin/master/mapel/import-commit'),
            'backUrl'   => site_url('admin/master/mapel'),
        ]);
    }

    // ===================== IMPORT: SIMPAN HASIL EDIT =====================
    public function importCommit()
    {
        $rows = (array) $this->request->getPost('rows');
        if (empty($rows)) {
            return redirect()->to(site_url('admin/master/mapel'))->with('error', 'Tidak ada data untuk disimpan.');
        }

        [$ins, $upd, $skip, $errors] = $this->upsertRows($rows);

        cache()->delete('opt_mapel');
        $this->audit->record('import', 'mata_pelajaran', null, "Import mapel: +{$ins} baru, {$upd} update, {$skip} dilewati");

        $msg = "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.";
        if ($errors) {
            return redirect()->to(site_url('admin/master/mapel'))->with('error', $msg . ' | ' . implode(' ', array_slice($errors, 0, 5)));
        }
        return redirect()->to(site_url('admin/master/mapel'))->with('success', $msg);
    }

    /** Upsert kumpulan baris assoc (by kode_mapel, termasuk yg soft-deleted). */
    private function upsertRows(array $rows): array
    {
        $ins = 0; $upd = 0; $skip = 0; $errors = [];
        $db  = db_connect();
        $db->transStart();

        foreach ($rows as $i => $row) {
            $kode = strtoupper(trim((string) ($row['kode_mapel'] ?? '')));
            $nama = trim((string) ($row['nama_mapel'] ?? ''));
            if ($kode === '' && $nama === '') {
                continue;
            }
            if ($kode === '' || $nama === '') {
                $skip++; $errors[] = 'Baris ' . ($i + 1) . ': kode/nama kosong.';
                continue;
            }

            $payload = [
                'kode_mapel' => $kode,
                'nama_mapel' => $nama,
                'kelompok'   => trim((string) ($row['kelompok'] ?? '')) ?: null,
                'jp_default' => (int) ($row['jp_default'] ?? 2) ?: 2,
            ];

            $existing = $this->model->withDeleted()->where('kode_mapel', $kode)->first();
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
