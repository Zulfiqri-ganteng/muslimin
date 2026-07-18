<?php

namespace App\Controllers\Admin\Master;

use App\Models\JabatanModel;
use App\Models\JurusanModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Jabatan — jabatan dibuat bebas oleh admin, namun tetap berelasi:
 *  - parent_id  : hierarki (Kepala Sekolah → Wakasek → Ketua Program)
 *  - jurusan_id : jabatan yang melekat pada satu jurusan (mis. Kaprog TKJ)
 *  - is_struktural : penyandangnya wajib hadir walau tak punya jadwal KBM,
 *                    sehingga otomatis muncul di panel Kehadiran Kerja.
 */
class Jabatan extends BaseMaster
{
    protected string $module     = 'jabatan';
    protected string $auditTable = 'jabatan';
    protected string $routeBase  = 'admin/master/jabatan';
    protected string $entity     = 'jabatan';
    protected string $titleLabel = 'Master Jabatan';

    protected function makeModel(): Model
    {
        return new JabatanModel();
    }

    public function index()
    {
        $q        = trim((string) $this->request->getGet('q'));
        $kategori = trim((string) $this->request->getGet('kategori'));
        if (! in_array($kategori, JabatanModel::KATEGORI, true)) {
            $kategori = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|k={$kategori}|per={$per}|p={$page}", function () use ($q, $kategori, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('jabatan.nama', $q)->orLike('jabatan.kode', $q)
                    ->groupEnd();
            }
            if ($kategori !== '') {
                $builder = $builder->where('jabatan.kategori', $kategori);
            }
            $rows = $builder->orderBy('jabatan.level', 'ASC')->orderBy('jabatan.nama', 'ASC')
                ->paginate($per, 'default', $page);

            return [
                'rows'   => $rows,
                'total'  => $this->model->pager->getTotal(),
                'jumlah' => $this->jumlahGuru(),
            ];
        });

        return view('admin/master/jabatan', [
            'title'        => $this->titleLabel,
            'rows'         => $data['rows'],
            'jumlahGuru'   => $data['jumlah'],
            'pager'        => $this->storePager($page, $per, $data['total']),
            'q'            => $q,
            'kategori'     => $kategori,
            'per'          => $per,
            'total'        => $data['total'],
            'kategoriList' => JabatanModel::KATEGORI,
            'indukOpts'    => $this->model->options(),
            'jurusanOpts'  => (new JurusanModel())->options(),
        ]);
    }

    /** Jumlah guru penyandang tiap jabatan (jabatan_id => jumlah), satu query. */
    private function jumlahGuru(): array
    {
        $rows = db_connect()->table('guru_jabatan')
            ->select('jabatan_id, COUNT(*) AS jumlah')
            ->groupBy('jabatan_id')->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['jabatan_id']] = (int) $r['jumlah'];
        }

        return $out;
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah jabatan ' . $data['nama']);

        return $this->goIndex('Jabatan ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id   = (int) $id;
        $data = $this->collect();

        // Hierarki tidak boleh melingkar: induk tak boleh diri sendiri maupun
        // salah satu keturunannya (Wakasek tak bisa "diinduki" Kaprog di bawahnya).
        $parent = (int) ($data['parent_id'] ?? 0);
        if ($parent === $id) {
            return redirect()->back()->withInput()->with('error', 'Jabatan tidak boleh menjadi induk bagi dirinya sendiri.');
        }
        if ($parent > 0 && in_array($parent, $this->model->descendantIds($id), true)) {
            return redirect()->back()->withInput()->with('error', 'Induk tidak boleh dipilih dari jabatan yang berada di bawahnya (hierarki akan melingkar).');
        }

        $data['id'] = $id; // isi placeholder {id} pada rule is_unique
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah jabatan ' . $data['nama']);

        return $this->goIndex('Jabatan diperbarui.');
    }

    private function collect(): array
    {
        $kategori = (string) $this->request->getPost('kategori');

        return [
            'kode'          => strtoupper(trim((string) $this->request->getPost('kode'))),
            'nama'          => trim((string) $this->request->getPost('nama')),
            'kategori'      => in_array($kategori, JabatanModel::KATEGORI, true) ? $kategori : 'lainnya',
            'parent_id'     => (int) $this->request->getPost('parent_id') ?: null,
            'jurusan_id'    => (int) $this->request->getPost('jurusan_id') ?: null,
            'level'         => max(1, (int) ($this->request->getPost('level') ?: 5)),
            'is_struktural' => $this->request->getPost('is_struktural') ? 1 : 0,
            'keterangan'    => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];
    }

    /**
     * Anti-orphan. Model memakai soft delete sehingga ON DELETE dari foreign key
     * TIDAK ikut jalan — relasi wajib dibereskan manual di sini:
     *  - penyandang jabatan ini dilepas (baris pivot dihapus)
     *  - jabatan anak dinaikkan menjadi tanpa induk agar tidak menggantung
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('guru_jabatan')->whereIn('jabatan_id', $ids)->delete();
        $db->table('jabatan')->whereIn('parent_id', $ids)->update(['parent_id' => null]);
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows   = $this->model->withRelations()
            ->orderBy('jabatan.level', 'ASC')->orderBy('jabatan.nama', 'ASC')->findAll();
        $jumlah = $this->jumlahGuru();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Master Jabatan',
            ['No', 'Kode', 'Nama Jabatan', 'Kategori', 'Induk Jabatan', 'Jurusan', 'Level', 'Struktural', 'Jumlah Guru', 'Keterangan'],
            ['A' => 5, 'B' => 12, 'C' => 34, 'D' => 14, 'E' => 28, 'F' => 12, 'G' => 8, 'H' => 12, 'I' => 13, 'J' => 25]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1, $d['kode'], $d['nama'], $d['kategori'],
                $d['induk_nama'] ?? '-', $d['jurusan_kode'] ?? '-',
                $d['level'], $d['is_struktural'] ? 'Ya' : 'Tidak',
                $jumlah[(int) $d['id']] ?? 0, $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Master-Jabatan-' . date('Ymd-His'), 'J');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Jabatan',
            ['Kode', 'Nama Jabatan', 'Kategori', 'Kode Induk', 'Kode Jurusan', 'Level', 'Struktural (Ya/Tidak)', 'Keterangan'],
            ['A' => 12, 'B' => 34, 'C' => 16, 'D' => 14, 'E' => 14, 'F' => 8, 'G' => 20, 'H' => 25]
        );
        $sheet->fromArray(['KAPROG-TKJ', 'Ketua Program Keahlian TKJ', 'struktural', 'KS', 'TKJ', 3, 'Ya', 'Membawahi jurusan TKJ'], null, 'A2', true);
        $sheet->fromArray(['PEMBINA-OSIS', 'Pembina OSIS', 'pembina', '', '', 4, 'Tidak', ''], null, 'A3', true);

        $this->streamXlsx($ss, 'Template-Import-Jabatan');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',          'label' => 'Kode',         'type' => 'text',   'required' => true, 'width' => 120],
            ['key' => 'nama',          'label' => 'Nama Jabatan', 'type' => 'text',   'required' => true, 'width' => 260],
            ['key' => 'kategori',      'label' => 'Kategori',     'type' => 'select', 'options' => JabatanModel::KATEGORI, 'width' => 130],
            ['key' => 'kode_induk',    'label' => 'Kode Induk',   'type' => 'text',   'width' => 110],
            ['key' => 'kode_jurusan',  'label' => 'Kode Jurusan', 'type' => 'text',   'width' => 110],
            ['key' => 'level',         'label' => 'Level',        'type' => 'number', 'width' => 80],
            ['key' => 'is_struktural', 'label' => 'Struktural',   'type' => 'select', 'options' => ['Ya', 'Tidak'], 'width' => 100],
            ['key' => 'keterangan',    'label' => 'Keterangan',   'type' => 'text',   'width' => 180],
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

        $kategori = strtolower(trim((string) ($row['kategori'] ?? '')));

        // Induk dicari SAAT ITU JUGA agar induk yang berada di baris lebih atas
        // pada file yang sama tetap dikenali. Induk = diri sendiri diabaikan.
        $parentId   = null;
        $kodeInduk  = strtoupper(trim((string) ($row['kode_induk'] ?? '')));
        if ($kodeInduk !== '' && $kodeInduk !== $kode) {
            $induk    = $this->model->withDeleted()->select('id')->where('kode', $kodeInduk)->first();
            $parentId = $induk ? (int) $induk['id'] : null;
            if ($parentId === null) {
                $this->importNote = 'Sebagian kode induk tidak ditemukan dan dikosongkan.';
            }
        }

        $jurusanId   = null;
        $kodeJurusan = strtoupper(trim((string) ($row['kode_jurusan'] ?? '')));
        if ($kodeJurusan !== '') {
            $jur       = (new JurusanModel())->select('id')->where('kode', $kodeJurusan)->first();
            $jurusanId = $jur ? (int) $jur['id'] : null;
        }

        $struktural = strtolower(trim((string) ($row['is_struktural'] ?? '')));

        return [
            'kode'          => $kode,
            'nama'          => $nama,
            'kategori'      => in_array($kategori, JabatanModel::KATEGORI, true) ? $kategori : 'lainnya',
            'parent_id'     => $parentId,
            'jurusan_id'    => $jurusanId,
            'level'         => max(1, (int) ($row['level'] ?? 5) ?: 5),
            'is_struktural' => in_array($struktural, ['ya', 'y', '1', 'true'], true) ? 1 : 0,
            'keterangan'    => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }
}
