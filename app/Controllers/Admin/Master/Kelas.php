<?php

namespace App\Controllers\Admin\Master;

use App\Models\FaseModel;
use App\Models\GuruModel;
use App\Models\JurusanModel;
use App\Models\KelasModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Kelas extends BaseMaster
{
    protected string $module     = 'kelas';
    protected string $auditTable = 'kelas';
    protected string $routeBase  = 'admin/master/kelas';
    protected string $entity     = 'kelas';
    protected string $titleLabel = 'Master Kelas';

    /** Peta lookup impor (diisi sekali saat upsert berjalan). */
    private ?array $jurusanMap = null;
    private array $guruByKode  = [];
    private array $createdJurusan = [];
    private ?JurusanModel $jurusanModel = null;

    protected function makeModel(): Model
    {
        return new KelasModel();
    }

    public function index()
    {
        $q       = trim((string) $this->request->getGet('q'));
        $tingkat = trim((string) $this->request->getGet('tingkat'));
        if (! in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $tingkat = '';
        }
        $shift = trim((string) $this->request->getGet('shift'));
        if (! in_array($shift, ['pagi', 'siang'], true)) {
            $shift = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|t={$tingkat}|sh={$shift}|per={$per}|p={$page}", function () use ($q, $tingkat, $shift, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->like('kelas.nama_kelas', $q);
            }
            if ($tingkat !== '') {
                $builder = $builder->where('kelas.tingkat', $tingkat);
            }
            if ($shift !== '') {
                $builder = $builder->where('kelas.shift', $shift);
            }
            $rows = $builder->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/kelas', [
            'title'       => $this->titleLabel,
            'rows'        => $data['rows'],
            'pager'       => $this->storePager($page, $per, $data['total']),
            'q'           => $q,
            'tingkat'     => $tingkat,
            'shift'       => $shift,
            'per'         => $per,
            'total'       => $data['total'],
            'jurusanOpts' => (new JurusanModel())->options(),
            'guruOpts'    => (new GuruModel())->options(),
            'faseOpts'    => (new FaseModel())->options(),
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah kelas ' . $data['nama_kelas']);

        return $this->goIndex('Kelas ditambahkan.');
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
        $this->audit->record('update', $this->auditTable, $id, 'Ubah kelas ' . $data['nama_kelas']);

        return $this->goIndex('Kelas diperbarui.');
    }

    private function collect(): array
    {
        $tingkat = in_array($this->request->getPost('tingkat'), ['X', 'XI', 'XII'], true) ? $this->request->getPost('tingkat') : 'X';

        return [
            'nama_kelas'    => trim((string) $this->request->getPost('nama_kelas')),
            'tingkat'       => $tingkat,
            'fase_id'       => (int) $this->request->getPost('fase_id') ?: $this->autoFaseId($tingkat),
            'jurusan_id'    => (int) $this->request->getPost('jurusan_id') ?: null,
            'wali_kelas_id' => (int) $this->request->getPost('wali_kelas_id') ?: null,
            'shift'         => in_array($this->request->getPost('shift'), ['pagi', 'siang'], true) ? $this->request->getPost('shift') : 'pagi',
        ];
    }

    /** Fase default bila admin tak memilih manual: X->E, XI/XII->F (standar SMK). */
    private function autoFaseId(string $tingkat): ?int
    {
        $kode = $tingkat === 'X' ? 'E' : 'F';
        $row  = db_connect()->table('fase')->select('id')->where('kode', $kode)->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    /**
     * Anti-orphan: hapus kelas ikut membersihkan penugasan kelas itu,
     * jadwal, dan absensi yang menempel padanya.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $pengampuIds = array_map('intval', array_column(
            $db->table('pengampu')->select('id')->whereIn('kelas_id', $ids)->get()->getResultArray(),
            'id'
        ));
        if ($pengampuIds !== []) {
            $db->table('jadwal')->whereIn('pengampu_id', $pengampuIds)->delete();
            $db->table('pengampu')->whereIn('id', $pengampuIds)->where('deleted_at', null)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $db->table('jadwal')->whereIn('kelas_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('kelas_id', $ids)->delete();
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->withRelations()->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Kelas',
            ['No', 'Nama Kelas', 'Tingkat', 'Jurusan', 'Wali Kelas', 'Shift'],
            ['A' => 5, 'B' => 18, 'C' => 10, 'D' => 12, 'E' => 28, 'F' => 10]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['nama_kelas'], $d['tingkat'], $d['jurusan_kode'], $d['wali_nama'], ucfirst($d['shift']),
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Kelas-' . date('Ymd-His'), 'F');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Kelas',
            ['Nama Kelas', 'Tingkat (X/XI/XII)', 'Kode Jurusan', 'Kode/Nama Wali Kelas', 'Shift (pagi/siang)'],
            ['A' => 18, 'B' => 18, 'C' => 14, 'D' => 22, 'E' => 18]
        );
        $sheet->fromArray(['X TKJT 1', 'X', 'TKJT', '27', 'pagi'], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Kelas');
    }

    // ===================== KONFIG IMPOR =====================

    public function importCommit(): \CodeIgniter\HTTP\RedirectResponse
    {
        $result = parent::importCommit();
        if ($this->createdJurusan !== []) {
            master_data_changed('jurusan'); // ada jurusan baru dibuat otomatis saat impor
        }

        return $result;
    }

    protected function importCols(): array
    {
        $jurusanKodes = array_column((new JurusanModel())->select('kode')->orderBy('kode', 'ASC')->findAll(), 'kode');

        return [
            ['key' => 'nama_kelas',   'label' => 'Nama Kelas',       'type' => 'text',     'required' => true, 'width' => 160],
            ['key' => 'tingkat',      'label' => 'Tingkat',          'type' => 'select',   'options' => ['X', 'XI', 'XII'], 'width' => 100],
            ['key' => 'jurusan_kode', 'label' => 'Kode Jurusan',     'type' => 'datalist', 'options' => $jurusanKodes, 'width' => 130],
            ['key' => 'wali',         'label' => 'Wali (kode/nama)', 'type' => 'text',     'width' => 200],
            ['key' => 'shift',        'label' => 'Shift',            'type' => 'select',   'options' => ['pagi', 'siang'], 'width' => 110],
        ];
    }

    protected function matchField(): string
    {
        return 'nama_kelas';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $this->prepareImportMaps();

        $nama = trim((string) ($row['nama_kelas'] ?? ''));
        if ($nama === '') {
            $error = 'Baris ' . $line . ': nama kelas kosong.';

            return null;
        }

        // --- resolusi jurusan: cocokkan kode, buat otomatis bila belum ada ---
        $jurId   = null;
        $jurKode = strtoupper(trim((string) ($row['jurusan_kode'] ?? '')));
        if ($jurKode !== '') {
            if (! isset($this->jurusanMap[$jurKode])) {
                $this->jurusanModel->insert(['kode' => $jurKode, 'nama' => $jurKode]);
                $this->jurusanMap[$jurKode]     = ['id' => (int) $this->jurusanModel->getInsertID(), 'deleted' => false];
                $this->createdJurusan[$jurKode] = true;
            } elseif ($this->jurusanMap[$jurKode]['deleted']) {
                // pulihkan jurusan yang sebelumnya dihapus agar bisa dipakai lagi
                $jid = $this->jurusanMap[$jurKode]['id'];
                $this->jurusanModel->protect(false)->update($jid, ['deleted_at' => null, 'id' => $jid]);
                $this->jurusanModel->protect(true);
                $this->jurusanMap[$jurKode]['deleted'] = false;
                $this->createdJurusan[$jurKode]        = true;
            }
            $jurId = $this->jurusanMap[$jurKode]['id'];
        }
        if ($this->createdJurusan !== []) {
            $this->importNote = 'Jurusan baru dibuat otomatis: ' . implode(', ', array_keys($this->createdJurusan)) . ' (lengkapi namanya di menu Jurusan bila perlu).';
        }

        $tingkat = strtoupper(trim((string) ($row['tingkat'] ?? '')));
        $shift   = strtolower(trim((string) ($row['shift'] ?? '')));

        return [
            'nama_kelas'    => $nama,
            'tingkat'       => in_array($tingkat, ['X', 'XI', 'XII'], true) ? $tingkat : 'X',
            'jurusan_id'    => $jurId,
            'wali_kelas_id' => $this->guruByKode[strtoupper(trim((string) ($row['wali'] ?? '')))] ?? null,
            'shift'         => in_array($shift, ['pagi', 'siang'], true) ? $shift : 'pagi',
        ];
    }

    /** Siapkan peta lookup jurusan & guru sekali saja untuk seluruh baris impor. */
    private function prepareImportMaps(): void
    {
        if ($this->jurusanMap !== null) {
            return;
        }

        $this->jurusanModel = new JurusanModel();
        $this->jurusanMap   = []; // KODE => ['id' => .., 'deleted' => bool]
        foreach ($this->jurusanModel->withDeleted()->select('id, kode, deleted_at')->findAll() as $j) {
            $this->jurusanMap[strtoupper((string) $j['kode'])] = ['id' => (int) $j['id'], 'deleted' => $j['deleted_at'] !== null];
        }

        $this->guruByKode = [];
        foreach ((new GuruModel())->select('id, kode_guru, nama')->findAll() as $g) {
            $this->guruByKode[strtoupper($g['kode_guru'])] = (int) $g['id'];
            $this->guruByKode[strtoupper($g['nama'])]      = (int) $g['id'];
        }
    }
}
