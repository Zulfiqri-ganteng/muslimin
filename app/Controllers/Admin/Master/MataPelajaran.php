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

        $rows  = $builder->orderBy('nama_mapel', 'ASC')->paginate(15);
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

        $ins = 0; $upd = 0; $skip = 0;
        $db  = db_connect();
        $db->transStart();

        foreach ($data as $i => $row) {
            if ($i === 0) {
                continue;
            }
            $kode = strtoupper(trim((string) ($row[0] ?? '')));
            $nama = trim((string) ($row[1] ?? ''));
            if ($kode === '' && $nama === '') {
                continue;
            }
            if ($kode === '' || $nama === '') {
                $skip++;
                continue;
            }

            $payload = [
                'kode_mapel' => $kode,
                'nama_mapel' => $nama,
                'kelompok'   => trim((string) ($row[2] ?? '')) ?: null,
                'jp_default' => (int) ($row[3] ?? 2) ?: 2,
            ];

            $existing = $this->model->withDeleted()->where('kode_mapel', $kode)->first();
            if ($existing) {
                $this->model->protect(false)->update($existing['id'], $payload + ['deleted_at' => null]);
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

        cache()->delete('opt_mapel');
        $this->audit->record('import', 'mata_pelajaran', null, "Import mapel: +{$ins} baru, {$upd} update, {$skip} dilewati");

        return redirect()->to(site_url('admin/master/mapel'))->with('success', "Import selesai: {$ins} baru, {$upd} diperbarui, {$skip} dilewati.");
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
