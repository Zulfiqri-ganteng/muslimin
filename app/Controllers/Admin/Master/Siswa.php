<?php

namespace App\Controllers\Admin\Master;

use App\Libraries\BiodataForm;
use App\Models\KelasModel;
use App\Models\SiswaModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Siswa — data siswa lengkap dengan impor/ekspor massal.
 *
 * Tingkat & jurusan TIDAK disimpan di tabel siswa; keduanya diturunkan dari
 * kelas lewat join, sehingga memindahkan kelas ke jurusan lain tidak pernah
 * meninggalkan data siswa yang tidak sinkron.
 *
 * Sejak modul Isian Biodata (2026-09-25) tabel siswa juga memuat biodata
 * buku induk (alamat terstruktur, orang tua, wali, sekolah asal). Kolom itu
 * biasanya terisi dari isian siswa yang disetujui, tetapi tetap bisa diubah
 * admin di sini dan lewat impor Excel.
 */
class Siswa extends BaseMaster
{
    protected string $module     = 'siswa';
    protected string $auditTable = 'siswa';
    protected string $routeBase  = 'admin/master/siswa';
    protected string $entity     = 'siswa';
    protected string $titleLabel = 'Master Siswa';

    protected const TINGKAT = ['X', 'XI', 'XII'];

    /** Saringan kelengkapan biodata (dari kolom biodata_at). */
    protected const BIODATA = ['lengkap', 'belum'];

    /** Kolom biodata buku induk — satu daftar bersama API (SiswaModel::KOLOM_BIODATA). */
    private const KOLOM_BIODATA = SiswaModel::KOLOM_BIODATA;

    /**
     * Kolom yang ditulis sebagai TEKS di Excel: nomor panjang / berawalan 0
     * tidak boleh berubah jadi angka (0812… → 812…, atau notasi ilmiah).
     */
    private const KOLOM_TEKS = ['nis', 'nisn', 'no_hp', 'no_hp_wali', 'ortu_telepon', 'rt', 'rw', 'ortu_rt', 'ortu_rw'];

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
            'biodata'  => trim((string) $this->request->getGet('biodata')),
            'per'      => (string) ((int) $this->request->getGet('per') ?: ''),
        ], static fn ($v) => $v !== '');

        return site_url($this->routeBase) . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    public function index()
    {
        [$kelasId, $tingkat, $status, $biodata] = $this->saringan();
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = $this->pageNo();

        // "v2": bentuk baris berubah (kolom biodata) — jangan membaca cache bentuk lama.
        $kunci = "list|v2|q={$q}|k={$kelasId}|t={$tingkat}|s={$status}|b={$biodata}|per={$per}|p={$page}";
        $data  = $this->cachedList($kunci, function () use ($q, $kelasId, $tingkat, $status, $biodata, $per, $page) {
            $builder = $this->terapkanSaringan($this->model->withRelations(), $kelasId, $tingkat, $status, $biodata);
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('siswa.nama', $q)->orLike('siswa.nis', $q)->orLike('siswa.nisn', $q)
                    ->groupEnd();
            }
            $rows = $builder->orderBy('siswa.nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/siswa', [
            'title'       => $this->titleLabel,
            'rows'        => $data['rows'] ?? [],
            'pager'       => $this->storePager($page, $per, (int) ($data['total'] ?? 0)),
            'q'           => $q,
            'kelasId'     => $kelasId,
            'tingkat'     => $tingkat,
            'status'      => $status,
            'biodata'     => $biodata,
            'per'         => $per,
            'total'       => (int) ($data['total'] ?? 0),
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

    /** Isi form tambah/edit (form lengkap: kolom kosong memang berarti dikosongkan). */
    private function collect(): array
    {
        $req  = $this->request;
        $post = static fn (string $k) => trim((string) $req->getPost($k));

        $jk     = strtoupper($post('jenis_kelamin'));
        $status = $post('status');

        $data = [
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
            'kelas_id'      => (int) $req->getPost('kelas_id') ?: null,
            'tahun_masuk'   => (int) $req->getPost('tahun_masuk') ?: null,
            'status'        => in_array($status, SiswaModel::STATUS, true) ? $status : 'aktif',
            'keterangan'    => $post('keterangan') ?: null,
        ];
        foreach (self::KOLOM_BIODATA as $k) {
            $data[$k] = $post($k) ?: null;
        }
        $data['anak_ke']          = $this->anakKe($post('anak_ke'));
        $data['diterima_tanggal'] = $this->parseTanggal($post('diterima_tanggal'));

        return $data;
    }

    /** Anak ke-: bilangan 1–99, selain itu NULL. */
    private function anakKe(string $nilai): ?int
    {
        return ctype_digit($nilai) && (int) $nilai >= 1 && (int) $nilai <= 99 ? (int) $nilai : null;
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

    // ===================== SARINGAN =====================

    /** @return array{0:int, 1:string, 2:string, 3:string} [kelas_id, tingkat, status, biodata] yang sudah disahkan */
    private function saringan(): array
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        $tingkat = trim((string) $this->request->getGet('tingkat'));
        $status  = trim((string) $this->request->getGet('status'));
        $biodata = trim((string) $this->request->getGet('biodata'));

        return [
            $kelasId,
            in_array($tingkat, self::TINGKAT, true) ? $tingkat : '',
            in_array($status, SiswaModel::STATUS, true) ? $status : '',
            in_array($biodata, self::BIODATA, true) ? $biodata : '',
        ];
    }

    /** Terapkan saringan yang sama untuk daftar & ekspor. */
    private function terapkanSaringan($builder, int $kelasId, string $tingkat, string $status, string $biodata)
    {
        if ($kelasId > 0) {
            $builder = $builder->where('siswa.kelas_id', $kelasId);
        }
        if ($tingkat !== '') {
            $builder = $builder->where('kelas.tingkat', $tingkat);
        }
        if ($status !== '') {
            $builder = $builder->where('siswa.status', $status);
        }
        if ($biodata === 'lengkap') {
            $builder = $builder->where('siswa.biodata_at IS NOT NULL');
        } elseif ($biodata === 'belum') {
            $builder = $builder->where('siswa.biodata_at', null);
        }

        return $builder;
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        // Ekspor mengikuti filter yang sedang aktif agar admin bisa mengunduh
        // "kelas X TKJ saja" tanpa harus menyaring ulang di Excel.
        [$kelasId, $tingkat, $status, $biodata] = $this->saringan();
        $rows = $this->terapkanSaringan($this->model->withRelations(), $kelasId, $tingkat, $status, $biodata)
            ->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')
            ->orderBy('siswa.nama', 'ASC')->findAll();

        // [judul, lebar, kunci kolom | closure]. Kolom "No" ditambahkan di depan.
        $kolom = [
            ['NIS', 16, 'nis'], ['NISN', 16, 'nisn'], ['Nama Siswa', 30, 'nama'], ['JK', 6, 'jenis_kelamin'],
            ['Tempat Lahir', 18, 'tempat_lahir'], ['Tanggal Lahir', 14, 'tanggal_lahir'], ['Agama', 12, 'agama'],
        ];
        $lebar = [
            'status_keluarga' => 18, 'anak_ke' => 8, 'rt' => 6, 'rw' => 6, 'kelurahan' => 18, 'kecamatan' => 16, 'kota' => 16,
            'sekolah_asal' => 26, 'diterima_kelas' => 14, 'diterima_tanggal' => 14,
            'nama_ayah' => 24, 'pekerjaan_ayah' => 18, 'nama_ibu' => 24, 'pekerjaan_ibu' => 18,
            'ortu_alamat' => 34, 'ortu_rt' => 8, 'ortu_rw' => 8, 'ortu_kelurahan' => 18, 'ortu_kecamatan' => 16,
            'ortu_kota' => 16, 'ortu_telepon' => 26, 'alamat_wali' => 30, 'pekerjaan_wali' => 18,
        ];
        foreach (['status_keluarga', 'anak_ke'] as $k) {
            $kolom[] = [BiodataForm::LABEL[$k], $lebar[$k], $k];
        }
        $kolom[] = ['Alamat', 34, 'alamat'];
        foreach (['rt', 'rw', 'kelurahan', 'kecamatan', 'kota'] as $k) {
            $kolom[] = [BiodataForm::LABEL[$k], $lebar[$k], $k];
        }
        $kolom[] = ['No HP Siswa', 16, 'no_hp'];
        foreach (array_slice(self::KOLOM_BIODATA, 7, 14) as $k) { // sekolah_asal … ortu_telepon
            $kolom[] = [BiodataForm::LABEL[$k], $lebar[$k], $k];
        }
        $kolom[] = [BiodataForm::LABEL['nama_wali'], 24, 'nama_wali'];
        $kolom[] = [BiodataForm::LABEL['alamat_wali'], $lebar['alamat_wali'], 'alamat_wali'];
        $kolom[] = [BiodataForm::LABEL['no_hp_wali'], 16, 'no_hp_wali'];
        $kolom[] = [BiodataForm::LABEL['pekerjaan_wali'], $lebar['pekerjaan_wali'], 'pekerjaan_wali'];
        array_push(
            $kolom,
            ['Kelas', 14, 'nama_kelas'],
            ['Tingkat', 9, 'tingkat'],
            ['Jurusan', 12, 'jurusan_kode'],
            ['Tahun Masuk', 12, 'tahun_masuk'],
            ['Status', 10, 'status'],
            ['Biodata', 18, static fn (array $d) => ! empty($d['biodata_at']) ? 'Lengkap ' . date('d/m/Y', strtotime($d['biodata_at'])) : 'Belum'],
            ['Keterangan', 22, 'keterangan'],
        );

        $judul  = array_merge(['No'], array_column($kolom, 0));
        $widths = ['A' => 5];
        foreach ($kolom as $i => $k) {
            $widths[Coordinate::stringFromColumnIndex($i + 2)] = $k[1];
        }

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader($ss, 'Data Siswa', $judul, $widths);

        $r = 2;
        foreach ($rows as $i => $d) {
            $sheet->setCellValue('A' . $r, $i + 1);
            foreach ($kolom as $c => [, , $sumber]) {
                $sel   = Coordinate::stringFromColumnIndex($c + 2) . $r;
                $nilai = is_callable($sumber) ? $sumber($d) : ($d[$sumber] ?? null);
                if ($nilai === null || $nilai === '') {
                    continue;
                }
                if (is_string($sumber) && in_array($sumber, self::KOLOM_TEKS, true)) {
                    // Tulis sebagai teks murni — TANPA awalan ' (petik itu ikut tersimpan & tampil).
                    $sheet->setCellValueExplicit($sel, (string) $nilai, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($sel, $nilai);
                }
            }
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Siswa-' . date('Ymd-His'), Coordinate::stringFromColumnIndex(count($judul)));
    }

    public function template()
    {
        $kolom  = $this->kolomImpor();
        $widths = [];
        foreach ($kolom as $i => $k) {
            $widths[Coordinate::stringFromColumnIndex($i + 1)] = max(10, (int) round(($k['width'] ?? 120) / 7));
        }

        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader($ss, 'Template Siswa', array_column($kolom, 'template'), $widths);

        // Baris contoh (dari contoh biodata asli) supaya operator paham formatnya.
        foreach ($kolom as $i => $k) {
            $sel = Coordinate::stringFromColumnIndex($i + 1) . '2';
            $sheet->setCellValueExplicit($sel, (string) $k['contoh'], DataType::TYPE_STRING);
        }

        // Kolom nomor dibuat format teks agar angka panjang tidak rusak saat diketik.
        foreach ($kolom as $i => $k) {
            if (in_array($k['key'], self::KOLOM_TEKS, true)) {
                $huruf = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getStyle($huruf . '2:' . $huruf . '2000')->getNumberFormat()->setFormatCode('@');
            }
        }

        $this->streamXlsx($ss, 'Template-Import-Siswa');
    }

    // ===================== KONFIG IMPOR =====================

    /**
     * SATU daftar kolom impor: urutan template = urutan pembacaan impor.
     * 15 kolom pertama TIDAK BOLEH diubah urutannya — file template lama
     * yang sudah beredar tetap terbaca benar; kolom biodata ditambahkan DI
     * BELAKANG (file lama cukup tidak punya kolom itu → data tidak diubah).
     *
     * @return list<array<string, mixed>>
     */
    private function kolomImpor(): array
    {
        $k = static fn (string $key, string $label, string $template, string $contoh, array $x = []) => [
            'key' => $key, 'label' => $label, 'template' => $template, 'contoh' => $contoh,
        ] + $x + ['type' => 'text', 'width' => 130];

        $kolom = [
            $k('nis', 'NIS', 'NIS', '252610280', ['required' => true, 'width' => 120]),
            $k('nisn', 'NISN', 'NISN', '0094624339', ['width' => 120]),
            $k('nama', 'Nama Siswa', 'Nama Siswa', 'Akbar Azam Albana', ['required' => true, 'width' => 200]),
            $k('jenis_kelamin', 'JK', 'JK (L/P)', 'L', ['type' => 'select', 'options' => ['L', 'P'], 'width' => 80]),
            $k('tempat_lahir', 'Tempat Lahir', 'Tempat Lahir', 'Jakarta', ['width' => 140]),
            $k('tanggal_lahir', 'Tgl Lahir', 'Tanggal Lahir (dd/mm/yyyy)', '24/09/2009', ['width' => 120]),
            $k('agama', 'Agama', 'Agama', 'Islam', ['type' => 'datalist', 'options' => SiswaModel::AGAMA, 'width' => 100]),
            $k('alamat', 'Alamat', 'Alamat', 'VGH Jl. Padjajaran Blok AK 10 No.19', ['width' => 220]),
            $k('no_hp', 'No HP', 'No HP Siswa', '087863419679', ['width' => 120]),
            $k('nama_wali', 'Nama Wali', 'Nama Wali', '', ['width' => 180]),
            $k('no_hp_wali', 'No HP Wali', 'No HP Wali', '', ['width' => 120]),
            $k('kelas', 'Kelas', 'Kelas', 'XI TKJ 8', ['width' => 110]),
            $k('tahun_masuk', 'Tahun Masuk', 'Tahun Masuk', '2025', ['type' => 'number', 'width' => 100]),
            $k('status', 'Status', 'Status (aktif/lulus/pindah/keluar)', 'aktif', ['type' => 'select', 'options' => SiswaModel::STATUS, 'width' => 100]),
            $k('keterangan', 'Keterangan', 'Keterangan', '', ['width' => 160]),
        ];

        $contoh = [
            'status_keluarga' => 'Anak Kandung', 'anak_ke' => '1', 'rt' => '18', 'rw' => '22',
            'kelurahan' => 'Kebalen', 'kecamatan' => 'Babelan', 'kota' => 'Bekasi',
            'sekolah_asal' => 'SMP NEGERI 6 BABELAN', 'diterima_kelas' => 'X TKJ 8', 'diterima_tanggal' => '14/07/2025',
            'nama_ayah' => 'Edy Purwanto', 'pekerjaan_ayah' => 'Karyawan Swasta',
            'nama_ibu' => 'Fitri Rusmiyanti', 'pekerjaan_ibu' => 'Ibu Rumah Tangga',
            'ortu_alamat' => 'VGH Jl. Padjajaran Blok AK 10 No.19', 'ortu_rt' => '18', 'ortu_rw' => '22',
            'ortu_kelurahan' => 'Kebalen', 'ortu_kecamatan' => 'Babelan', 'ortu_kota' => 'Bekasi',
            'ortu_telepon' => '081319918778 / 085216154014', 'alamat_wali' => '', 'pekerjaan_wali' => '',
        ];
        foreach (self::KOLOM_BIODATA as $key) {
            $x = match ($key) {
                'status_keluarga' => ['type' => 'datalist', 'options' => SiswaModel::STATUS_KELUARGA],
                'pekerjaan_ayah', 'pekerjaan_ibu', 'pekerjaan_wali' => ['type' => 'datalist', 'options' => SiswaModel::PEKERJAAN],
                'anak_ke' => ['type' => 'number', 'width' => 80],
                'rt', 'rw', 'ortu_rt', 'ortu_rw' => ['width' => 70],
                'alamat_wali', 'ortu_alamat' => ['width' => 220],
                default => [],
            };
            $template = BiodataForm::LABEL[$key] . ($key === 'diterima_tanggal' ? ' (dd/mm/yyyy)' : '');
            $kolom[]  = $k($key, BiodataForm::LABEL[$key], $template, $contoh[$key], $x);
        }

        return $kolom;
    }

    protected function importCols(): array
    {
        return array_map(
            static fn (array $c) => array_diff_key($c, ['template' => 0, 'contoh' => 0]),
            $this->kolomImpor()
        );
    }

    protected function matchField(): string
    {
        return 'nis';
    }

    /**
     * Normalisasi satu baris impor.
     *
     * SEL KOSONG = TIDAK DIUBAH: hanya kolom yang berisi yang ditulis. Dengan
     * begitu mengimpor ulang daftar lama (mis. hanya NIS + nama + kelas) tidak
     * menghapus biodata yang sudah diisi siswa. Untuk siswa BARU, kolom yang
     * tidak dikirim otomatis kosong (dan status = aktif dari default tabel).
     */
    protected function normalizeImportRow(array $row, int $line, ?string &$error): ?array
    {
        $teks = static fn (string $k): string => trim((string) ($row[$k] ?? ''));
        $nis  = $teks('nis');
        $nama = $teks('nama');
        if ($nis === '' && $nama === '') {
            return null; // baris kosong, lewati diam-diam
        }
        if ($nis === '' || $nama === '') {
            $error = 'Baris ' . $line . ': NIS/nama kosong.';

            return null;
        }

        $payload = ['nis' => $nis, 'nama' => $nama];
        $isi     = static function (string $k, $v) use (&$payload): void {
            if ($v !== null && $v !== '') {
                $payload[$k] = $v;
            }
        };

        $jk = strtoupper($teks('jenis_kelamin'));
        $isi('jenis_kelamin', in_array($jk, ['L', 'P'], true) ? $jk : null);
        $status = strtolower($teks('status'));
        $isi('status', in_array($status, SiswaModel::STATUS, true) ? $status : null);
        $isi('tanggal_lahir', $this->parseTanggal($teks('tanggal_lahir')));
        $isi('diterima_tanggal', $this->parseTanggal($teks('diterima_tanggal')));
        $isi('anak_ke', $this->anakKe($teks('anak_ke')));
        $tahun = (int) $teks('tahun_masuk');
        $isi('tahun_masuk', $tahun > 0 ? $tahun : null);

        foreach (['nisn', 'tempat_lahir', 'agama', 'alamat', 'no_hp', 'nama_wali', 'no_hp_wali', 'keterangan'] as $k) {
            $isi($k, $teks($k));
        }
        foreach (self::KOLOM_BIODATA as $k) {
            if (! in_array($k, ['anak_ke', 'diterima_tanggal'], true)) {
                $isi($k, $teks($k));
            }
        }

        // Kelas dicocokkan dari NAMA kelas (mis. "X TKJ 1"), bukan id.
        $namaKelas = $teks('kelas');
        if ($namaKelas !== '') {
            $kelasId = $this->cariKelas($namaKelas);
            if ($kelasId === null) {
                $this->importNote = 'Sebagian nama kelas tidak dikenali — kelas siswa tersebut tidak diubah.';
            }
            $isi('kelas_id', $kelasId);
        }

        return $payload;
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
