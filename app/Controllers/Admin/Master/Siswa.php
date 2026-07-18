<?php

namespace App\Controllers\Admin\Master;

use App\Models\KelasModel;
use App\Models\SiswaModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Siswa — data siswa lengkap dengan impor/ekspor massal.
 *
 * Tingkat & jurusan TIDAK disimpan di tabel siswa; keduanya diturunkan dari
 * kelas lewat join, sehingga memindahkan kelas ke jurusan lain tidak pernah
 * meninggalkan data siswa yang tidak sinkron.
 */
class Siswa extends BaseMaster
{
    protected string $module     = 'siswa';
    protected string $auditTable = 'siswa';
    protected string $routeBase  = 'admin/master/siswa';
    protected string $entity     = 'siswa';
    protected string $titleLabel = 'Master Siswa';

    protected const TINGKAT = ['X', 'XI', 'XII'];

    /** Peta nama kelas (huruf besar) => id, dibangun sekali saat impor. */
    private ?array $petaKelas = null;

    protected function makeModel(): Model
    {
        return new SiswaModel();
    }

    /** Pertahankan filter aktif saat kembali dari simpan/hapus. */
    protected function indexUrl(): string
    {
        $qs = array_filter([
            'q'        => trim((string) $this->request->getGet('q')),
            'kelas_id' => (string) ((int) $this->request->getGet('kelas_id') ?: ''),
            'tingkat'  => trim((string) $this->request->getGet('tingkat')),
            'status'   => trim((string) $this->request->getGet('status')),
            'per'      => (string) ((int) $this->request->getGet('per') ?: ''),
        ], static fn ($v) => $v !== '');

        return site_url($this->routeBase) . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    public function index()
    {
        $q       = trim((string) $this->request->getGet('q'));
        $kelasId = (int) $this->request->getGet('kelas_id');
        $tingkat = trim((string) $this->request->getGet('tingkat'));
        $status  = trim((string) $this->request->getGet('status'));
        if (! in_array($tingkat, self::TINGKAT, true)) {
            $tingkat = '';
        }
        if (! in_array($status, SiswaModel::STATUS, true)) {
            $status = '';
        }
        $per  = $this->perPage();
        $page = $this->pageNo();

        $kunci = "list|q={$q}|k={$kelasId}|t={$tingkat}|s={$status}|per={$per}|p={$page}";
        $data  = $this->cachedList($kunci, function () use ($q, $kelasId, $tingkat, $status, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('siswa.nama', $q)->orLike('siswa.nis', $q)->orLike('siswa.nisn', $q)
                    ->groupEnd();
            }
            if ($kelasId > 0) {
                $builder = $builder->where('siswa.kelas_id', $kelasId);
            }
            if ($tingkat !== '') {
                $builder = $builder->where('kelas.tingkat', $tingkat);
            }
            if ($status !== '') {
                $builder = $builder->where('siswa.status', $status);
            }
            $rows = $builder->orderBy('siswa.nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/siswa', [
            'title'       => $this->titleLabel,
            'rows'        => $data['rows'],
            'pager'       => $this->storePager($page, $per, $data['total']),
            'q'           => $q,
            'kelasId'     => $kelasId,
            'tingkat'     => $tingkat,
            'status'      => $status,
            'per'         => $per,
            'total'       => $data['total'],
            'kelasOpts'   => (new KelasModel())->options(),
            'tingkatList' => self::TINGKAT,
            'statusList'  => SiswaModel::STATUS,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah siswa ' . $data['nama']);

        return $this->goIndex('Siswa ditambahkan.');
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
        $this->audit->record('update', $this->auditTable, $id, 'Ubah siswa ' . $data['nama']);

        return $this->goIndex('Siswa diperbarui.');
    }

    private function collect(): array
    {
        $req  = $this->request;
        $post = static fn (string $k) => trim((string) $req->getPost($k));

        $jk     = strtoupper($post('jenis_kelamin'));
        $status = $post('status');

        return [
            'nis'  => $post('nis'),
            // NISN unik namun boleh kosong — string kosong WAJIB jadi NULL,
            // kalau tidak beberapa siswa tanpa NISN akan bentrok unique key.
            'nisn'          => $post('nisn') ?: null,
            'nama'          => $post('nama'),
            'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
            'tempat_lahir'  => $post('tempat_lahir') ?: null,
            'tanggal_lahir' => $this->parseTanggal($post('tanggal_lahir')),
            'agama'         => $post('agama') ?: null,
            'alamat'        => $post('alamat') ?: null,
            'no_hp'         => $post('no_hp') ?: null,
            'nama_wali'     => $post('nama_wali') ?: null,
            'no_hp_wali'    => $post('no_hp_wali') ?: null,
            'kelas_id'      => (int) service('request')->getPost('kelas_id') ?: null,
            'tahun_masuk'   => (int) service('request')->getPost('tahun_masuk') ?: null,
            'status'        => in_array($status, SiswaModel::STATUS, true) ? $status : 'aktif',
            'keterangan'    => $post('keterangan') ?: null,
        ];
    }

    /**
     * Ubah beragam penulisan tanggal menjadi Y-m-d (atau NULL).
     * Excel bisa mengirim "31/12/2009", "31-12-2009", "2009-12-31", bahkan
     * angka serial — semuanya diterima agar impor tidak gagal karena format.
     */
    private function parseTanggal(string $nilai): ?string
    {
        $nilai = trim($nilai);
        if ($nilai === '') {
            return null;
        }

        // Angka serial Excel (mis. 40178) — hanya bila murni angka & masuk akal.
        if (ctype_digit($nilai) && (int) $nilai > 1000 && (int) $nilai < 80000) {
            try {
                return ExcelDate::excelToDateTimeObject((int) $nilai)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        // Format eksplisit yang lazim dipakai operator sekolah.
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d', 'm/d/Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $format, $nilai);
            if ($dt !== false) {
                $err = \DateTimeImmutable::getLastErrors();
                if (! $err || (($err['warning_count'] ?? 0) === 0 && ($err['error_count'] ?? 0) === 0)) {
                    return $dt->format('Y-m-d');
                }
            }
        }

        return null; // tak dikenali → dikosongkan, jangan sampai menggagalkan baris
    }

    /** Siswa tidak menjadi induk data lain, jadi tak ada relasi yang perlu dibersihkan. */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        // Ekspor mengikuti filter yang sedang aktif agar admin bisa mengunduh
        // "kelas X TKJ saja" tanpa harus menyaring ulang di Excel.
        $builder = $this->model->withRelations();
        $kelasId = (int) $this->request->getGet('kelas_id');
        $tingkat = trim((string) $this->request->getGet('tingkat'));
        $status  = trim((string) $this->request->getGet('status'));

        if ($kelasId > 0) {
            $builder = $builder->where('siswa.kelas_id', $kelasId);
        }
        if (in_array($tingkat, self::TINGKAT, true)) {
            $builder = $builder->where('kelas.tingkat', $tingkat);
        }
        if (in_array($status, SiswaModel::STATUS, true)) {
            $builder = $builder->where('siswa.status', $status);
        }

        $rows = $builder->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')
            ->orderBy('siswa.nama', 'ASC')->findAll();

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Siswa',
            [
                'No', 'NIS', 'NISN', 'Nama Siswa', 'JK', 'Tempat Lahir', 'Tanggal Lahir',
                'Agama', 'Alamat', 'No HP', 'Nama Orang Tua/Wali', 'No HP Wali',
                'Kelas', 'Tingkat', 'Jurusan', 'Tahun Masuk', 'Status', 'Keterangan',
            ],
            [
                'A' => 5, 'B' => 16, 'C' => 16, 'D' => 30, 'E' => 6, 'F' => 18, 'G' => 14,
                'H' => 12, 'I' => 34, 'J' => 16, 'K' => 26, 'L' => 16,
                'M' => 14, 'N' => 9, 'O' => 12, 'P' => 12, 'Q' => 10, 'R' => 22,
            ]
        );

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->fromArray([
                $i + 1,
                // awalan ' agar NIS/NISN panjang tidak berubah jadi notasi ilmiah
                "'" . $d['nis'], "'" . $d['nisn'], $d['nama'], $d['jenis_kelamin'],
                $d['tempat_lahir'], $d['tanggal_lahir'], $d['agama'], $d['alamat'],
                "'" . $d['no_hp'], $d['nama_wali'], "'" . $d['no_hp_wali'],
                $d['nama_kelas'], $d['tingkat'], $d['jurusan_kode'],
                $d['tahun_masuk'], $d['status'], $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Siswa-' . date('Ymd-His'), 'R');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Siswa',
            [
                'NIS', 'NISN', 'Nama Siswa', 'JK (L/P)', 'Tempat Lahir', 'Tanggal Lahir (dd/mm/yyyy)',
                'Agama', 'Alamat', 'No HP', 'Nama Orang Tua/Wali', 'No HP Wali',
                'Kelas', 'Tahun Masuk', 'Status (aktif/lulus/pindah/keluar)', 'Keterangan',
            ],
            [
                'A' => 16, 'B' => 16, 'C' => 30, 'D' => 10, 'E' => 18, 'F' => 24,
                'G' => 12, 'H' => 34, 'I' => 16, 'J' => 26, 'K' => 16,
                'M' => 14, 'L' => 14, 'N' => 28, 'O' => 22,
            ]
        );
        $sheet->fromArray([
            '2026001', '0091234567', 'Ahmad Fauzi', 'L', 'Bandung', '17/08/2009',
            'Islam', 'Jl. Merdeka No. 10', '081234567890', 'Bapak Sulaiman', '081298765432',
            'X TKJ 1', 2026, 'aktif', '',
        ], null, 'A2', true);

        // Kolom NIS/NISN/HP dibuat teks agar angka panjang tidak rusak saat diketik.
        foreach (['A', 'B', 'I', 'K'] as $kolom) {
            $sheet->getStyle($kolom . '2:' . $kolom . '500')
                ->getNumberFormat()->setFormatCode('@');
        }

        $this->streamXlsx($ss, 'Template-Import-Siswa');
    }

    // ===================== KONFIG IMPOR =====================

    protected function importCols(): array
    {
        return [
            ['key' => 'nis',           'label' => 'NIS',           'type' => 'text',   'required' => true, 'width' => 120],
            ['key' => 'nisn',          'label' => 'NISN',          'type' => 'text',   'width' => 120],
            ['key' => 'nama',          'label' => 'Nama Siswa',    'type' => 'text',   'required' => true, 'width' => 200],
            ['key' => 'jenis_kelamin', 'label' => 'JK',            'type' => 'select', 'options' => ['L', 'P'], 'width' => 80],
            ['key' => 'tempat_lahir',  'label' => 'Tempat Lahir',  'type' => 'text',   'width' => 140],
            ['key' => 'tanggal_lahir', 'label' => 'Tgl Lahir',     'type' => 'text',   'width' => 120],
            ['key' => 'agama',         'label' => 'Agama',         'type' => 'text',   'width' => 100],
            ['key' => 'alamat',        'label' => 'Alamat',        'type' => 'text',   'width' => 220],
            ['key' => 'no_hp',         'label' => 'No HP',         'type' => 'text',   'width' => 120],
            ['key' => 'nama_wali',     'label' => 'Nama Wali',     'type' => 'text',   'width' => 180],
            ['key' => 'no_hp_wali',    'label' => 'No HP Wali',    'type' => 'text',   'width' => 120],
            ['key' => 'kelas',         'label' => 'Kelas',         'type' => 'text',   'width' => 110],
            ['key' => 'tahun_masuk',   'label' => 'Tahun Masuk',   'type' => 'number', 'width' => 100],
            ['key' => 'status',        'label' => 'Status',        'type' => 'select', 'options' => SiswaModel::STATUS, 'width' => 100],
            ['key' => 'keterangan',    'label' => 'Keterangan',    'type' => 'text',   'width' => 160],
        ];
    }

    protected function matchField(): string
    {
        return 'nis';
    }

    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $nis  = trim((string) ($row['nis'] ?? ''));
        $nama = trim((string) ($row['nama'] ?? ''));
        if ($nis === '' && $nama === '') {
            return null; // baris kosong, lewati diam-diam
        }
        if ($nis === '' || $nama === '') {
            $error = 'Baris ' . $line . ': NIS/nama kosong.';

            return null;
        }

        $jk     = strtoupper(trim((string) ($row['jenis_kelamin'] ?? '')));
        $status = strtolower(trim((string) ($row['status'] ?? '')));

        // Kelas dicocokkan dari NAMA kelas (mis. "X TKJ 1"), bukan id.
        $kelasId  = null;
        $namaKelas = trim((string) ($row['kelas'] ?? ''));
        if ($namaKelas !== '') {
            $kelasId = $this->cariKelas($namaKelas);
            if ($kelasId === null) {
                $this->importNote = 'Sebagian nama kelas tidak dikenali dan dikosongkan.';
            }
        }

        return [
            'nis'           => $nis,
            'nisn'          => trim((string) ($row['nisn'] ?? '')) ?: null,
            'nama'          => $nama,
            'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
            'tempat_lahir'  => trim((string) ($row['tempat_lahir'] ?? '')) ?: null,
            'tanggal_lahir' => $this->parseTanggal((string) ($row['tanggal_lahir'] ?? '')),
            'agama'         => trim((string) ($row['agama'] ?? '')) ?: null,
            'alamat'        => trim((string) ($row['alamat'] ?? '')) ?: null,
            'no_hp'         => trim((string) ($row['no_hp'] ?? '')) ?: null,
            'nama_wali'     => trim((string) ($row['nama_wali'] ?? '')) ?: null,
            'no_hp_wali'    => trim((string) ($row['no_hp_wali'] ?? '')) ?: null,
            'kelas_id'      => $kelasId,
            'tahun_masuk'   => (int) ($row['tahun_masuk'] ?? 0) ?: null,
            'status'        => in_array($status, SiswaModel::STATUS, true) ? $status : 'aktif',
            'keterangan'    => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];
    }

    /** Cari id kelas dari namanya (tak peka huruf besar/kecil & spasi ganda). */
    private function cariKelas(string $nama): ?int
    {
        if ($this->petaKelas === null) {
            $this->petaKelas = [];
            foreach ((new KelasModel())->select('id, nama_kelas')->findAll() as $k) {
                $this->petaKelas[$this->kunciKelas($k['nama_kelas'])] = (int) $k['id'];
            }
        }

        return $this->petaKelas[$this->kunciKelas($nama)] ?? null;
    }

    private function kunciKelas(string $nama): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($nama)));
    }
}
