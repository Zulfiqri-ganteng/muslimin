<?php

namespace App\Controllers\Admin\Master;

use App\Models\GuruMapelModel;
use App\Models\GuruModel;
use App\Models\MataPelajaranModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class MataPelajaran extends BaseMaster
{
    protected string $module     = 'mapel';
    protected string $auditTable = 'mata_pelajaran';
    protected string $routeBase  = 'admin/master/mapel';
    protected string $entity     = 'mata pelajaran';
    protected string $titleLabel = 'Master Mata Pelajaran';

    protected GuruMapelModel $kompetensi;

    public function __construct()
    {
        parent::__construct();
        $this->kompetensi = new GuruMapelModel();
    }

    protected function makeModel(): Model
    {
        return new MataPelajaranModel();
    }

    public function index()
    {
        $q        = trim((string) $this->request->getGet('q'));
        $kelompok = trim((string) $this->request->getGet('kelompok'));
        $per      = $this->perPage();
        $page     = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|k={$kelompok}|per={$per}|p={$page}", function () use ($q, $kelompok, $per, $page) {
            $builder = $this->model;
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('nama_mapel', $q)->orLike('kode_mapel', $q)
                    ->groupEnd();
            }
            if ($kelompok !== '') {
                $builder = $builder->where('kelompok', $kelompok);
            }
            $rows = $builder->orderBy('nama_mapel', 'ASC')->paginate($per, 'default', $page);

            // Peta kompetensi hanya untuk mapel yang tampil (1 query, efisien).
            $kompMap = $this->kompetensi->mapForMapelIds(array_column($rows, 'id'));

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal(), 'kompMap' => $kompMap];
        });

        return view('admin/master/mapel', [
            'title'        => $this->titleLabel,
            'rows'         => $data['rows'],
            'pager'        => $this->storePager($page, $per, $data['total']),
            'q'            => $q,
            'kelompok'     => $kelompok,
            'per'          => $per,
            'total'        => $data['total'],
            'allGuru'      => (new GuruModel())->options(),
            'kompMap'      => $data['kompMap'],
            'kelompokList' => $this->kelompokList(),
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah mapel ' . $data['nama_mapel']);

        return $this->goIndex('Mata pelajaran ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id         = (int) $id;
        $data       = $this->collect();
        $data['id'] = $id; // isi placeholder {id} pada rule is_unique saat edit
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah mapel ' . $data['nama_mapel']);

        return $this->goIndex('Mata pelajaran diperbarui.');
    }

    /** Simpan kompetensi (daftar guru yang boleh mengampu) untuk satu mapel. */
    /** @param int|string $id */
    public function kompetensi($id)
    {
        $id      = (int) $id;
        $guruIds = (array) $this->request->getPost('guru_ids');
        $this->kompetensi->sync($id, $guruIds);
        master_data_changed($this->module);
        $this->audit->record('update', 'guru_mapel', $id, 'Atur kompetensi: ' . count($guruIds) . ' guru');

        return $this->goIndex('Kompetensi guru pengampu diperbarui.');
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

    /**
     * Anti-orphan: hapus mapel ikut membersihkan kompetensi guru,
     * penugasan yang memakai mapel ini beserta jadwalnya, dan melepas
     * referensi pada absensi.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('guru_mapel')->whereIn('mapel_id', $ids)->delete();

        $pengampuIds = array_map('intval', array_column(
            $db->table('pengampu')->select('id')->whereIn('mapel_id', $ids)->get()->getResultArray(),
            'id'
        ));
        if ($pengampuIds !== []) {
            $db->table('jadwal')->whereIn('pengampu_id', $pengampuIds)->delete();
            $db->table('pengampu')->whereIn('id', $pengampuIds)->where('deleted_at', null)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $db->table('absensi_guru')->whereIn('mapel_id', $ids)->update(['mapel_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->orderBy('nama_mapel', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Mapel',
            ['No', 'Kode Mapel', 'Nama Mapel', 'Kelompok', 'JP / Minggu'],
            ['A' => 5, 'B' => 14, 'C' => 36, 'D' => 22, 'E' => 12]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([$i + 1, $d['kode_mapel'], $d['nama_mapel'], $d['kelompok'], $d['jp_default']], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Mapel-' . date('Ymd-His'));
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Mapel',
            ['Kode Mapel', 'Nama Mapel', 'Kelompok', 'JP / Minggu'],
            ['A' => 14, 'B' => 36, 'C' => 22, 'D' => 12]
        );
        $sheet->fromArray(['PD', 'Pemrograman Dasar', 'Kejuruan', 8], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Mapel');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode_mapel', 'label' => 'Kode Mapel',  'type' => 'text',     'required' => true, 'width' => 120],
            ['key' => 'nama_mapel', 'label' => 'Nama Mapel',  'type' => 'text',     'required' => true, 'width' => 280],
            ['key' => 'kelompok',   'label' => 'Kelompok',    'type' => 'datalist', 'options' => $this->kelompokList(), 'width' => 180],
            ['key' => 'jp_default', 'label' => 'JP / Minggu', 'type' => 'number',   'width' => 100],
        ];
    }

    protected function matchField(): string
    {
        return 'kode_mapel';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $kode = strtoupper(trim((string) ($row['kode_mapel'] ?? '')));
        $nama = trim((string) ($row['nama_mapel'] ?? ''));
        if ($kode === '' && $nama === '') {
            return null;
        }
        if ($kode === '' || $nama === '') {
            $error = 'Baris ' . $line . ': kode/nama kosong.';

            return null;
        }

        return [
            'kode_mapel' => $kode,
            'nama_mapel' => $nama,
            'kelompok'   => trim((string) ($row['kelompok'] ?? '')) ?: null,
            'jp_default' => (int) ($row['jp_default'] ?? 2) ?: 2,
        ];
    }
}
