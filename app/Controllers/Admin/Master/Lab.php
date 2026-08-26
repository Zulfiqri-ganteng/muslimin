<?php

namespace App\Controllers\Admin\Master;

use App\Models\LabModel;
use App\Models\TeknisiModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Laboratorium. Penanggung jawab (teknisi_id) diambil dari Master Teknisi.
 * Lab menjadi lokasi bagi Aset dan acuan Jadwal/Jurnal Lab.
 */
class Lab extends BaseMaster
{
    protected string $module     = 'lab';
    protected string $auditTable = 'lab';
    protected string $routeBase  = 'admin/master/lab';
    protected string $entity     = 'lab';
    protected string $titleLabel = 'Master Laboratorium';

    /** Peta kode teknisi (huruf besar) => id, dibangun sekali saat impor. */
    private ?array $petaTeknisi = null;

    protected function makeModel(): Model
    {
        return new LabModel();
    }

    protected function indexUrl(): string
    {
        $qs = array_filter([
            'q'     => trim((string) $this->request->getGet('q')),
            'jenis' => trim((string) $this->request->getGet('jenis')),
            'per'   => (string) ((int) $this->request->getGet('per') ?: ''),
        ], static fn ($v) => $v !== '');

        return site_url($this->routeBase) . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    public function index()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $jenis = trim((string) $this->request->getGet('jenis'));
        if (! in_array($jenis, LabModel::JENIS, true)) {
            $jenis = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $kunci = "list|q={$q}|jenis={$jenis}|per={$per}|p={$page}";
        $data  = $this->cachedList($kunci, function () use ($q, $jenis, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('lab.nama', $q)->orLike('lab.kode', $q)->orLike('lab.ruang', $q)
                    ->groupEnd();
            }
            if ($jenis !== '') {
                $builder = $builder->where('lab.jenis', $jenis);
            }
            $rows = $builder->orderBy('lab.nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/lab', [
            'title'       => $this->titleLabel,
            'rows'        => $data['rows'],
            'pager'       => $this->storePager($page, $per, $data['total']),
            'q'           => $q,
            'jenis'       => $jenis,
            'per'         => $per,
            'total'       => $data['total'],
            'teknisiOpts' => (new TeknisiModel())->options(),
            'jenisList'   => LabModel::JENIS,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah lab ' . $data['nama']);

        return $this->goIndex('Laboratorium ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id         = (int) $id;
        $data       = $this->collect();
        $data['id'] = $id;
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah lab ' . $data['nama']);

        return $this->goIndex('Laboratorium diperbarui.');
    }

    private function collect(): array
    {
        $post  = fn (string $k) => trim((string) $this->request->getPost($k));
        $jenis = strtolower($post('jenis'));

        return [
            'kode'       => strtoupper($post('kode')),
            'nama'       => $post('nama'),
            'jenis'      => in_array($jenis, LabModel::JENIS, true) ? $jenis : 'komputer',
            'ruang'      => $post('ruang') ?: null,
            'kapasitas'  => (int) $this->request->getPost('kapasitas') ?: null,
            'teknisi_id' => (int) $this->request->getPost('teknisi_id') ?: null,
            'keterangan' => $post('keterangan') ?: null,
        ];
    }

    /**
     * Lab jadi lokasi aset & acuan jadwal/jurnal. Soft delete → FK tak jalan,
     * jadi rujukan dibersihkan manual: aset & jurnal dilepas (null), jadwal lab
     * (data rencana) dihapus.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('aset')->whereIn('lab_id', $ids)->update(['lab_id' => null]);
        $db->table('jurnal_lab')->whereIn('lab_id', $ids)->update(['lab_id' => null]);
        $db->table('jadwal_lab')->whereIn('lab_id', $ids)->delete();
        (new \App\Models\LabGambarModel())->hapusUntuk('lab', $ids);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows  = $this->model->withRelations()->orderBy('lab.nama', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Laboratorium',
            ['No', 'Kode', 'Nama Lab', 'Jenis', 'Ruang', 'Kapasitas', 'Penanggung Jawab', 'Keterangan'],
            ['A' => 5, 'B' => 14, 'C' => 28, 'D' => 14, 'E' => 16, 'F' => 12, 'G' => 26, 'H' => 30]
        );
        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['kode'], $d['nama'], ucfirst($d['jenis']),
                $d['ruang'], $d['kapasitas'], $d['teknisi_nama'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Laboratorium-' . date('Ymd-His'), 'H');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Lab',
            ['Kode', 'Nama Lab', 'Jenis (komputer/jaringan/multimedia/lainnya)', 'Ruang', 'Kapasitas', 'Kode Teknisi', 'Keterangan'],
            ['A' => 14, 'B' => 28, 'C' => 42, 'D' => 16, 'E' => 12, 'F' => 14, 'G' => 30]
        );
        $sheet->fromArray(['LAB01', 'Lab Komputer 1', 'komputer', 'R.201', 36, 'TKN01', ''], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Lab');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',         'label' => 'Kode',         'type' => 'text',     'required' => true, 'width' => 120],
            ['key' => 'nama',         'label' => 'Nama Lab',     'type' => 'text',     'required' => true, 'width' => 220],
            ['key' => 'jenis',        'label' => 'Jenis',        'type' => 'select',   'options' => LabModel::JENIS, 'width' => 140],
            ['key' => 'ruang',        'label' => 'Ruang',        'type' => 'text',     'width' => 120],
            ['key' => 'kapasitas',    'label' => 'Kapasitas',    'type' => 'number',   'width' => 100],
            ['key' => 'teknisi_kode', 'label' => 'Kode Teknisi', 'type' => 'datalist', 'width' => 120],
            ['key' => 'keterangan',   'label' => 'Keterangan',   'type' => 'text',     'width' => 200],
        ];
    }

    protected function matchField(): string
    {
        return 'kode';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $kode = strtoupper(trim((string) ($row['kode'] ?? '')));
        $nama = trim((string) ($row['nama'] ?? ''));
        if ($kode === '' && $nama === '') {
            return null;
        }
        if ($kode === '' || $nama === '') {
            $error = 'Baris ' . $line . ': kode/nama kosong.';

            return null;
        }
        $jenis = strtolower(trim((string) ($row['jenis'] ?? '')));

        // Teknisi dicocokkan dari KODE (opsional). Tak ketemu → dikosongkan.
        $teknisiId  = null;
        $kodeTeknisi = strtoupper(trim((string) ($row['teknisi_kode'] ?? '')));
        if ($kodeTeknisi !== '') {
            $teknisiId = $this->cariTeknisi($kodeTeknisi);
            if ($teknisiId === null) {
                $this->importNote = 'Sebagian kode teknisi tidak dikenali dan dikosongkan.';
            }
        }

        return [
            'kode'       => $kode,
            'nama'       => $nama,
            'jenis'      => in_array($jenis, LabModel::JENIS, true) ? $jenis : 'komputer',
            'ruang'      => trim((string) ($row['ruang'] ?? '')) ?: null,
            'kapasitas'  => (int) ($row['kapasitas'] ?? 0) ?: null,
            'teknisi_id' => $teknisiId,
            'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }

    /** Cari id teknisi dari kodenya (tak peka huruf besar/kecil). */
    private function cariTeknisi(string $kode): ?int
    {
        if ($this->petaTeknisi === null) {
            $this->petaTeknisi = [];
            foreach ((new TeknisiModel())->select('id, kode')->findAll() as $t) {
                $this->petaTeknisi[strtoupper(trim((string) $t['kode']))] = (int) $t['id'];
            }
        }

        return $this->petaTeknisi[$kode] ?? null;
    }
}
