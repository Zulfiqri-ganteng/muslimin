<?php

namespace App\Controllers\Admin\Master;

use App\Models\AsetKomputerModel;
use App\Models\AsetModel;
use App\Models\LabModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Aset / Inventaris. Nomor aset dapat dibuat otomatis
 * (format KODELAB-KAT-001). Aset kategori komputer/laptop punya sub-halaman
 * "Detail Komputer" (spesifikasi + jaringan) di tabel aset_komputer (1:1).
 */
class Aset extends BaseMaster
{
    protected string $module     = 'aset';
    protected string $auditTable = 'aset';
    protected string $routeBase  = 'admin/master/aset';
    protected string $entity     = 'aset';
    protected string $titleLabel = 'Master Aset';

    /** Awalan nomor aset per kategori. */
    private const KAT_PREFIX = [
        'komputer' => 'KOM', 'laptop' => 'LTP', 'printer' => 'PRT', 'proyektor' => 'PRO',
        'jaringan' => 'NET', 'furnitur' => 'FRN', 'lainnya' => 'LNY',
    ];

    protected function makeModel(): Model
    {
        return new AsetModel();
    }

    protected function indexUrl(): string
    {
        $qs = array_filter([
            'q'       => trim((string) $this->request->getGet('q')),
            'lab_id'  => (string) ((int) $this->request->getGet('lab_id') ?: ''),
            'kategori'=> trim((string) $this->request->getGet('kategori')),
            'kondisi' => trim((string) $this->request->getGet('kondisi')),
            'status'  => trim((string) $this->request->getGet('status')),
            'per'     => (string) ((int) $this->request->getGet('per') ?: ''),
        ], static fn ($v) => $v !== '');

        return site_url($this->routeBase) . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    public function index()
    {
        $q        = trim((string) $this->request->getGet('q'));
        $labId    = (int) $this->request->getGet('lab_id');
        $kategori = trim((string) $this->request->getGet('kategori'));
        $kondisi  = trim((string) $this->request->getGet('kondisi'));
        $status   = trim((string) $this->request->getGet('status'));
        if (! in_array($kategori, AsetModel::KATEGORI, true)) {
            $kategori = '';
        }
        if (! in_array($kondisi, AsetModel::KONDISI, true)) {
            $kondisi = '';
        }
        if (! in_array($status, AsetModel::STATUS, true)) {
            $status = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $kunci = "list|q={$q}|lab={$labId}|kat={$kategori}|kon={$kondisi}|st={$status}|per={$per}|p={$page}";
        $data  = $this->cachedList($kunci, function () use ($q, $labId, $kategori, $kondisi, $status, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('aset.nama', $q)->orLike('aset.nomor_aset', $q)->orLike('aset.merk', $q)
                    ->groupEnd();
            }
            if ($labId > 0) {
                $builder = $builder->where('aset.lab_id', $labId);
            }
            if ($kategori !== '') {
                $builder = $builder->where('aset.kategori', $kategori);
            }
            if ($kondisi !== '') {
                $builder = $builder->where('aset.kondisi', $kondisi);
            }
            if ($status !== '') {
                $builder = $builder->where('aset.status', $status);
            }
            $rows = $builder->orderBy('aset.nomor_aset', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/aset', [
            'title'        => $this->titleLabel,
            'rows'         => $data['rows'],
            'pager'        => $this->storePager($page, $per, $data['total']),
            'q'            => $q,
            'labId'        => $labId,
            'kategori'     => $kategori,
            'kondisi'      => $kondisi,
            'status'       => $status,
            'per'          => $per,
            'total'        => $data['total'],
            'labOpts'      => (new LabModel())->options(),
            'kategoriList' => AsetModel::KATEGORI,
            'kondisiList'  => AsetModel::KONDISI,
            'statusList'   => AsetModel::STATUS,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if ($data['nomor_aset'] === '') {
            $data['nomor_aset'] = $this->generateNomor($data['lab_id'], $data['kategori']);
        }
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah aset ' . $data['nomor_aset']);

        return $this->goIndex('Aset ditambahkan (' . $data['nomor_aset'] . ').');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();
        if ($data['nomor_aset'] === '') {
            $data['nomor_aset'] = $this->generateNomor($data['lab_id'], $data['kategori']);
        }
        $data['id'] = $id;
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah aset ' . $data['nomor_aset']);

        return $this->goIndex('Aset diperbarui.');
    }

    private function collect(): array
    {
        $post     = fn (string $k) => trim((string) $this->request->getPost($k));
        $kategori = strtolower($post('kategori'));
        $kondisi  = strtolower($post('kondisi'));
        $status   = strtolower($post('status'));
        $harga    = $post('harga');

        return [
            'nomor_aset'      => strtoupper($post('nomor_aset')),
            'nama'            => $post('nama'),
            'kategori'        => in_array($kategori, AsetModel::KATEGORI, true) ? $kategori : 'komputer',
            'lab_id'          => (int) $this->request->getPost('lab_id') ?: null,
            'merk'            => $post('merk') ?: null,
            'spesifikasi'     => $post('spesifikasi') ?: null,
            'tahun_pengadaan' => (int) $this->request->getPost('tahun_pengadaan') ?: null,
            'sumber_dana'     => $post('sumber_dana') ?: null,
            'harga'           => $harga !== '' ? (float) $harga : null,
            'kondisi'         => in_array($kondisi, AsetModel::KONDISI, true) ? $kondisi : 'baik',
            'status'          => in_array($status, AsetModel::STATUS, true) ? $status : 'tersedia',
            'keterangan'      => $post('keterangan') ?: null,
        ];
    }

    /** Nomor aset otomatis: KODELAB-KAT-### (### = urutan berikutnya). */
    private function generateNomor(?int $labId, string $kategori): string
    {
        $labKode = 'UMUM';
        if ($labId) {
            $lab = (new LabModel())->find($labId);
            if ($lab) {
                $labKode = strtoupper($lab['kode']);
            }
        }
        $prefix = $labKode . '-' . (self::KAT_PREFIX[$kategori] ?? 'LNY') . '-';

        $max = 0;
        foreach ($this->model->withDeleted()->select('nomor_aset')->like('nomor_aset', $prefix, 'after')->findAll() as $r) {
            if (preg_match('/(\d+)$/', (string) $r['nomor_aset'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /** Aset menjadi induk detail komputer; sisanya (peminjaman/kerusakan/perbaikan) arsip. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('aset_komputer')->whereIn('aset_id', $ids)->delete();
        (new \App\Models\LabGambarModel())->hapusUntuk('aset', $ids);
    }

    // ===================== DETAIL KOMPUTER (sub-halaman) =====================

    /** @param int|string $id */
    public function komputer($id)
    {
        $id   = (int) $id;
        $aset = $this->model->find($id);
        if (! $aset) {
            return $this->goIndex(null, 'Aset tidak ditemukan.');
        }

        return view('admin/master/aset_komputer', [
            'title'  => 'Detail Komputer',
            'aset'   => $aset,
            'detail' => (new AsetKomputerModel())->forAset($id) ?? [],
        ]);
    }

    /** @param int|string $id */
    public function komputerSave($id): RedirectResponse
    {
        $id   = (int) $id;
        $aset = $this->model->find($id);
        if (! $aset) {
            return $this->goIndex(null, 'Aset tidak ditemukan.');
        }

        $post = fn (string $k) => trim((string) $this->request->getPost($k)) ?: null;
        $data = [
            'aset_id'     => $id,
            'hostname'    => $post('hostname'),
            'processor'   => $post('processor'),
            'ram'         => $post('ram'),
            'storage'     => $post('storage'),
            'gpu'         => $post('gpu'),
            'os'          => $post('os'),
            'mac_address' => $post('mac_address'),
            'ip_address'  => $post('ip_address'),
            'monitor'     => $post('monitor'),
            'keterangan'  => $post('keterangan'),
        ];

        $km       = new AsetKomputerModel();
        $existing = $km->forAset($id);
        if ($existing) {
            $data['id'] = $existing['id'];
            $ok         = $km->update($existing['id'], $data);
        } else {
            $ok = $km->insert($data);
        }
        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $km->errors());
        }

        master_data_changed($this->module);
        $this->audit->record('update', 'aset_komputer', $id, 'Detail komputer aset ' . $aset['nomor_aset']);

        return redirect()->to(site_url($this->routeBase))->with('success', 'Detail komputer tersimpan.');
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $builder = $this->model->withRelations();
        $labId   = (int) $this->request->getGet('lab_id');
        $kategori = trim((string) $this->request->getGet('kategori'));
        $kondisi  = trim((string) $this->request->getGet('kondisi'));
        $status   = trim((string) $this->request->getGet('status'));
        if ($labId > 0) {
            $builder = $builder->where('aset.lab_id', $labId);
        }
        if (in_array($kategori, AsetModel::KATEGORI, true)) {
            $builder = $builder->where('aset.kategori', $kategori);
        }
        if (in_array($kondisi, AsetModel::KONDISI, true)) {
            $builder = $builder->where('aset.kondisi', $kondisi);
        }
        if (in_array($status, AsetModel::STATUS, true)) {
            $builder = $builder->where('aset.status', $status);
        }

        $rows  = $builder->orderBy('aset.nomor_aset', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Aset',
            ['No', 'Nomor Aset', 'Nama', 'Kategori', 'Lab', 'Merk', 'Spesifikasi', 'Tahun', 'Sumber Dana', 'Harga', 'Kondisi', 'Status', 'Keterangan'],
            ['A' => 5, 'B' => 20, 'C' => 26, 'D' => 12, 'E' => 22, 'F' => 16, 'G' => 28, 'H' => 8, 'I' => 18, 'J' => 14, 'K' => 14, 'L' => 12, 'M' => 24]
        );
        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['nomor_aset'], $d['nama'], ucfirst($d['kategori']), $d['lab_nama'],
                $d['merk'], $d['spesifikasi'], $d['tahun_pengadaan'], $d['sumber_dana'], $d['harga'],
                ucfirst(str_replace('_', ' ', (string) $d['kondisi'])), ucfirst($d['status']), $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Aset-' . date('Ymd-His'), 'M');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Aset',
            ['Nomor Aset (kosongkan=otomatis)', 'Nama', 'Kategori', 'Kode Lab', 'Merk', 'Spesifikasi', 'Tahun', 'Sumber Dana', 'Harga', 'Kondisi', 'Status', 'Keterangan'],
            ['A' => 28, 'B' => 26, 'C' => 14, 'D' => 12, 'E' => 16, 'F' => 28, 'G' => 8, 'H' => 18, 'I' => 14, 'J' => 14, 'K' => 12, 'L' => 24]
        );
        $sheet->fromArray(
            ['', 'PC Client 01', 'komputer', 'LAB01', 'Lenovo', 'Core i5, 8GB, SSD 256GB', 2024, 'BOS', 6500000, 'baik', 'tersedia', ''],
            null,
            'A2',
            true
        );

        $this->streamXlsx($ss, 'Template-Import-Aset');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'nomor_aset',      'label' => 'Nomor Aset',  'type' => 'text',     'width' => 160],
            ['key' => 'nama',            'label' => 'Nama',        'type' => 'text',     'required' => true, 'width' => 200],
            ['key' => 'kategori',        'label' => 'Kategori',    'type' => 'select',   'options' => AsetModel::KATEGORI, 'width' => 120],
            ['key' => 'lab_kode',        'label' => 'Kode Lab',    'type' => 'datalist', 'width' => 110],
            ['key' => 'merk',            'label' => 'Merk',        'type' => 'text',     'width' => 130],
            ['key' => 'spesifikasi',     'label' => 'Spesifikasi', 'type' => 'text',     'width' => 220],
            ['key' => 'tahun_pengadaan', 'label' => 'Tahun',       'type' => 'number',   'width' => 90],
            ['key' => 'sumber_dana',     'label' => 'Sumber Dana', 'type' => 'text',     'width' => 140],
            ['key' => 'harga',           'label' => 'Harga',       'type' => 'number',   'width' => 120],
            ['key' => 'kondisi',         'label' => 'Kondisi',     'type' => 'select',   'options' => AsetModel::KONDISI, 'width' => 120],
            ['key' => 'status',          'label' => 'Status',      'type' => 'select',   'options' => AsetModel::STATUS, 'width' => 110],
            ['key' => 'keterangan',      'label' => 'Keterangan',  'type' => 'text',     'width' => 180],
        ];
    }

    protected function matchField(): string
    {
        return 'nomor_aset';
    }

    /** Peta kode lab (huruf besar) => id untuk resolusi impor. */
    private ?array $petaLab = null;

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $nama = trim((string) ($row['nama'] ?? ''));
        if ($nama === '') {
            return null; // baris tanpa nama dilewati
        }
        $kategori = strtolower(trim((string) ($row['kategori'] ?? '')));
        $kondisi  = strtolower(trim((string) ($row['kondisi'] ?? '')));
        $status   = strtolower(trim((string) ($row['status'] ?? '')));
        $kategori = in_array($kategori, AsetModel::KATEGORI, true) ? $kategori : 'komputer';

        $labId    = null;
        $kodeLab  = strtoupper(trim((string) ($row['lab_kode'] ?? '')));
        if ($kodeLab !== '') {
            $labId = $this->cariLab($kodeLab);
            if ($labId === null) {
                $this->importNote = 'Sebagian kode lab tidak dikenali dan dikosongkan.';
            }
        }

        $nomor = strtoupper(trim((string) ($row['nomor_aset'] ?? '')));
        if ($nomor === '') {
            $nomor = $this->generateNomor($labId, $kategori); // otomatis bila kosong
        }
        $harga = trim((string) ($row['harga'] ?? ''));

        return [
            'nomor_aset'      => $nomor,
            'nama'            => $nama,
            'kategori'        => $kategori,
            'lab_id'          => $labId,
            'merk'            => trim((string) ($row['merk'] ?? '')) ?: null,
            'spesifikasi'     => trim((string) ($row['spesifikasi'] ?? '')) ?: null,
            'tahun_pengadaan' => (int) ($row['tahun_pengadaan'] ?? 0) ?: null,
            'sumber_dana'     => trim((string) ($row['sumber_dana'] ?? '')) ?: null,
            'harga'           => $harga !== '' ? (float) $harga : null,
            'kondisi'         => in_array($kondisi, AsetModel::KONDISI, true) ? $kondisi : 'baik',
            'status'          => in_array($status, AsetModel::STATUS, true) ? $status : 'tersedia',
            'keterangan'      => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }

    private function cariLab(string $kode): ?int
    {
        if ($this->petaLab === null) {
            $this->petaLab = [];
            foreach ((new LabModel())->select('id, kode')->findAll() as $l) {
                $this->petaLab[strtoupper(trim((string) $l['kode']))] = (int) $l['id'];
            }
        }

        return $this->petaLab[$kode] ?? null;
    }
}
