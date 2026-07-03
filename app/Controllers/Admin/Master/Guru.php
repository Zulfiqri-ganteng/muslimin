<?php

namespace App\Controllers\Admin\Master;

use App\Models\GuruModel;
use App\Models\SubmissionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Guru extends BaseMaster
{
    protected string $module     = 'guru';
    protected string $auditTable = 'guru';
    protected string $routeBase  = 'admin/master/guru';
    protected string $entity     = 'guru';
    protected string $titleLabel = 'Master Guru';

    protected const STATUS = ['PNS', 'PPPK', 'GTY', 'GTT'];

    protected function makeModel(): Model
    {
        return new GuruModel();
    }

    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, self::STATUS, true)) {
            $status = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|s={$status}|per={$per}|p={$page}", function () use ($q, $status, $per, $page) {
            $builder = $this->model;
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('nama', $q)->orLike('kode_guru', $q)->orLike('nip', $q)
                    ->groupEnd();
            }
            if ($status !== '') {
                $builder = $builder->where('status_guru', $status);
            }
            $rows = $builder->orderBy('nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/guru', [
            'title'      => $this->titleLabel,
            'rows'       => $data['rows'],
            'pager'      => $this->storePager($page, $per, $data['total']),
            'q'          => $q,
            'status'     => $status,
            'per'        => $per,
            'total'      => $data['total'],
            'statusList' => self::STATUS,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah guru ' . $data['nama']);

        return $this->goIndex('Guru ditambahkan.');
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
        $this->audit->record('update', $this->auditTable, $id, 'Ubah guru ' . $data['nama']);

        return $this->goIndex('Guru diperbarui.');
    }

    private function collect(): array
    {
        return [
            'nip'           => trim((string) $this->request->getPost('nip')) ?: null,
            'kode_guru'     => trim((string) $this->request->getPost('kode_guru')),
            'nama'          => trim((string) $this->request->getPost('nama')),
            'jenis_kelamin' => in_array($this->request->getPost('jenis_kelamin'), ['L', 'P'], true) ? $this->request->getPost('jenis_kelamin') : null,
            'status_guru'   => in_array($this->request->getPost('status_guru'), self::STATUS, true) ? $this->request->getPost('status_guru') : null,
            'max_beban'     => (int) ($this->request->getPost('max_beban') ?: 24),
            'keterangan'    => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];
    }

    /**
     * Anti-orphan: saat guru dihapus, seluruh jejaknya ikut dibersihkan
     * (kompetensi, ketersediaan, penugasan + jadwal yang memakainya,
     * absensi, dan jabatan wali kelas) dalam satu transaksi.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('guru_mapel')->whereIn('guru_id', $ids)->delete();
        $db->table('ketersediaan_guru')->whereIn('guru_id', $ids)->delete();

        $pengampuIds = array_map('intval', array_column(
            $db->table('pengampu')->select('id')->whereIn('guru_id', $ids)->get()->getResultArray(),
            'id'
        ));
        if ($pengampuIds !== []) {
            $db->table('jadwal')->whereIn('pengampu_id', $pengampuIds)->delete();
            $db->table('pengampu')->whereIn('id', $pengampuIds)->where('deleted_at', null)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        $db->table('jadwal')->whereIn('guru_id', $ids)->delete();
        $db->table('absensi_guru')->whereIn('guru_id', $ids)->delete();
        $db->table('kelas')->whereIn('wali_kelas_id', $ids)->update(['wali_kelas_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows = $this->model->orderBy('nama', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Guru',
            ['No', 'NIP', 'Kode Guru', 'Nama Guru', 'Jenis Kelamin', 'Status', 'Maks Beban (JP)', 'Keterangan'],
            ['A' => 5, 'B' => 22, 'C' => 12, 'D' => 30, 'E' => 14, 'F' => 10, 'G' => 16, 'H' => 25]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, "'" . $d['nip'], $d['kode_guru'], $d['nama'],
                $d['jenis_kelamin'], $d['status_guru'], $d['max_beban'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Guru-' . date('Ymd-His'), 'H');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Guru',
            ['NIP', 'Kode Guru', 'Nama Guru', 'Jenis Kelamin (L/P)', 'Status (PNS/PPPK/GTY/GTT)', 'Maks Beban (JP)', 'Keterangan'],
            ['A' => 22, 'B' => 12, 'C' => 30, 'D' => 20, 'E' => 26, 'F' => 16, 'G' => 25]
        );
        $sheet->fromArray(['1987...', '27', 'Muslimin, S.Kom', 'L', 'GTY', 24, 'Produktif TKJ'], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Guru');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'nip',           'label' => 'NIP',        'type' => 'text',   'width' => 150],
            ['key' => 'kode_guru',     'label' => 'Kode Guru',  'type' => 'text',   'required' => true, 'width' => 110],
            ['key' => 'nama',          'label' => 'Nama Guru',  'type' => 'text',   'required' => true, 'width' => 220],
            ['key' => 'jenis_kelamin', 'label' => 'JK',         'type' => 'select', 'options' => ['L', 'P'], 'width' => 90],
            ['key' => 'status_guru',   'label' => 'Status',     'type' => 'select', 'options' => self::STATUS, 'width' => 110],
            ['key' => 'max_beban',     'label' => 'Maks JP',    'type' => 'number', 'width' => 90],
            ['key' => 'keterangan',    'label' => 'Keterangan', 'type' => 'text',   'width' => 180],
        ];
    }

    protected function matchField(): string
    {
        return 'kode_guru';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $kode = trim((string) ($row['kode_guru'] ?? ''));
        $nama = trim((string) ($row['nama'] ?? ''));
        if ($kode === '' && $nama === '') {
            return null;
        }
        if ($kode === '' || $nama === '') {
            $error = 'Baris ' . $line . ': kode/nama kosong.';

            return null;
        }

        $jk     = strtoupper(trim((string) ($row['jenis_kelamin'] ?? '')));
        $status = strtoupper(trim((string) ($row['status_guru'] ?? '')));

        return [
            'nip'           => trim((string) ($row['nip'] ?? '')) ?: null,
            'kode_guru'     => $kode,
            'nama'          => $nama,
            'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
            'status_guru'   => in_array($status, self::STATUS, true) ? $status : null,
            'max_beban'     => (int) ($row['max_beban'] ?? 24) ?: 24,
            'keterangan'    => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }

    // ===================== IMPOR DARI DATA KESEDIAAN LAMA =====================

    public function importFromSubmissions()
    {
        $subs = (new SubmissionModel())->select('nama_lengkap, nip_nuptk, status_kepegawaian')
            ->orderBy('nama_lengkap', 'ASC')->findAll();

        // kode awal lanjut dari kode numerik terbesar yang ada
        $maxKode = 0;
        foreach ($this->model->withDeleted()->select('kode_guru')->findAll() as $g) {
            if (ctype_digit((string) $g['kode_guru'])) {
                $maxKode = max($maxKode, (int) $g['kode_guru']);
            }
        }

        $ins  = 0;
        $skip = 0;
        $db   = db_connect();
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
                'status_guru' => in_array($s['status_kepegawaian'], self::STATUS, true) ? $s['status_kepegawaian'] : null,
                'max_beban'   => 24,
            ]);
            $ins++;
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            return redirect()->back()->with('error', 'Impor dibatalkan karena kesalahan database.');
        }

        master_data_changed($this->module);
        $this->audit->record('import', $this->auditTable, null, "Impor dari kesediaan: +{$ins} baru, {$skip} dilewati");

        return $this->goIndex("Impor dari data kesediaan selesai: {$ins} guru baru, {$skip} sudah ada (dilewati).");
    }
}
