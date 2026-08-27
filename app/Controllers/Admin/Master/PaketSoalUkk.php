<?php

namespace App\Controllers\Admin\Master;

use App\Models\JurusanModel;
use App\Models\PaketSoalUkkModel;
use App\Models\TahunAjaranModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Master Paket Soal UKK. Memuat bobot 5 komponen penilaian (dipakai
 * NilaiUkkModel::hitungNilaiAkhir) + berkas Kisi-kisi & Jobsheet (PDF).
 */
class PaketSoalUkk extends BaseMaster
{
    protected string $module     = 'paket_soal_ukk';
    protected string $auditTable = 'paket_soal_ukk';
    protected string $routeBase  = 'admin/master/paket-soal-ukk';
    protected string $entity     = 'paket soal';
    protected string $titleLabel = 'Master Paket Soal UKK';

    public function __construct()
    {
        parent::__construct();
        helper('ukkdoc');
    }

    protected function makeModel(): Model
    {
        return new PaketSoalUkkModel();
    }

    public function index()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $per  = $this->perPage();
        $page = $this->pageNo();

        $data = $this->cachedList("list|q={$q}|per={$per}|p={$page}", function () use ($q, $per, $page) {
            $builder = $this->model->withRelations();
            if ($q !== '') {
                $builder = $builder->groupStart()
                    ->like('paket_soal_ukk.nama', $q)->orLike('paket_soal_ukk.kode', $q)
                    ->groupEnd();
            }
            $rows = $builder->orderBy('paket_soal_ukk.nama', 'ASC')->paginate($per, 'default', $page);

            return ['rows' => $rows, 'total' => $this->model->pager->getTotal()];
        });

        return view('admin/master/paket_soal_ukk', [
            'title'       => $this->titleLabel,
            'rows'        => $data['rows'],
            'pager'       => $this->storePager($page, $per, $data['total']),
            'q'           => $q,
            'per'         => $per,
            'total'       => $data['total'],
            'jurusanOpts' => (new JurusanModel())->options(),
            'tahunOpts'   => (new TahunAjaranModel())->options(),
        ]);
    }

    public function store()
    {
        $data = $this->collectDasar();

        $totalBobot = $this->model->totalBobot($data);
        if (abs($totalBobot - 100) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'Total bobot 5 komponen harus 100% (saat ini ' . $totalBobot . '%).');
        }

        [$data, $errUpload] = $this->tambahBerkas($data, null);
        if ($errUpload !== null) {
            return redirect()->back()->withInput()->with('error', $errUpload);
        }

        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('create', $this->auditTable, $this->model->getInsertID(), 'Tambah paket soal ' . $data['nama']);

        return $this->goIndex('Paket soal ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id  = (int) $id;
        $old = $this->model->find($id);
        if (! $old) {
            return $this->goIndex(null, 'Paket soal tidak ditemukan.');
        }

        $data = $this->collectDasar();

        $totalBobot = $this->model->totalBobot($data);
        if (abs($totalBobot - 100) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'Total bobot 5 komponen harus 100% (saat ini ' . $totalBobot . '%).');
        }

        [$data, $errUpload] = $this->tambahBerkas($data, $old);
        if ($errUpload !== null) {
            return redirect()->back()->withInput()->with('error', $errUpload);
        }

        $data['id'] = $id;
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Ubah paket soal ' . $data['nama']);

        return $this->goIndex('Paket soal diperbarui.');
    }

    /** Hapus berkas kisi-kisi ATAU jobsheet dari sebuah paket soal (tanpa mengganti). */
    public function hapusBerkas($id, string $jenis)
    {
        $id    = (int) $id;
        $field = $jenis === 'jobsheet' ? 'jobsheet_file' : 'kisi_kisi_file';
        $row   = $this->model->find($id);
        if (! $row) {
            return $this->goIndex(null, 'Paket soal tidak ditemukan.');
        }

        ukkdoc_delete($row[$field]);
        $this->model->update($id, [$field => null]);
        master_data_changed($this->module);
        $this->audit->record('update', $this->auditTable, $id, 'Hapus berkas ' . $jenis . ' paket soal ' . $row['nama']);

        return $this->goIndex('Berkas ' . ($jenis === 'jobsheet' ? 'jobsheet' : 'kisi-kisi') . ' dihapus.');
    }

    private function collectDasar(): array
    {
        $post   = fn (string $k) => trim((string) $this->request->getPost($k));
        $bobot  = fn (string $k, float $default) => $this->request->getPost($k) !== null && $post($k) !== ''
            ? (float) $post($k) : $default;

        return [
            'kode'            => strtoupper($post('kode')),
            'nama'            => $post('nama'),
            'jurusan_id'      => (int) $this->request->getPost('jurusan_id') ?: null,
            'tahun_ajaran_id' => (int) $this->request->getPost('tahun_ajaran_id') ?: null,
            'deskripsi'       => $post('deskripsi') ?: null,
            'bobot_persiapan' => $bobot('bobot_persiapan', 10),
            'bobot_proses'    => $bobot('bobot_proses', 30),
            'bobot_hasil'     => $bobot('bobot_hasil', 40),
            'bobot_sikap'     => $bobot('bobot_sikap', 10),
            'bobot_waktu'     => $bobot('bobot_waktu', 10),
            'kkm'             => $bobot('kkm', 70),
            'keterangan'      => $post('keterangan') ?: null,
        ];
    }

    /**
     * Tambahkan berkas kisi-kisi/jobsheet ke $data BILA ada unggahan baru
     * (mengganti + menghapus berkas lama bila $old diisi). Tanpa unggahan,
     * kunci field tidak disentuh sama sekali (nilai lama di DB tetap).
     *
     * @return array{0: array, 1: ?string} [$data, $errorMessage]
     */
    private function tambahBerkas(array $data, ?array $old): array
    {
        foreach (['kisi_kisi_file' => 'kisi_kisi', 'jobsheet_file' => 'jobsheet'] as $field => $inputName) {
            $file = $this->request->getFile($inputName);
            if ($file === null || ! $file->isValid() || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $error = null;
            $saved = ukkdoc_save($file, $error);
            if ($saved === null) {
                return [$data, ucfirst(str_replace('_', ' ', $inputName)) . ': ' . $error];
            }
            if ($old !== null && ! empty($old[$field])) {
                ukkdoc_delete($old[$field]);
            }
            $data[$field] = $saved;
        }

        return [$data, null];
    }

    /**
     * Paket soal dipakai jadwal_ukk & peserta_ukk (FK CASCADE — ikut terhapus
     * hanya saat hard delete; di sini paket soal soft delete jadi baris
     * anaknya TETAP ADA, sengaja tidak dibersihkan agar riwayat UKK lama
     * tidak hilang). Hanya hapus berkas fisik agar tak menumpuk di disk.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $rows = $this->model->whereIn('id', $ids)->withDeleted()->findAll();
        foreach ($rows as $r) {
            ukkdoc_delete($r['kisi_kisi_file'] ?? null);
            ukkdoc_delete($r['jobsheet_file'] ?? null);
        }
    }

    // ===================== EXPORT & TEMPLATE =====================

    public function export()
    {
        $rows  = $this->model->withRelations()->orderBy('paket_soal_ukk.nama', 'ASC')->findAll();
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Data Paket Soal UKK',
            ['No', 'Kode', 'Nama', 'Jurusan', 'Tahun Ajaran', 'KKM', 'Bobot P/Pr/H/S/W', 'Keterangan'],
            ['A' => 5, 'B' => 14, 'C' => 28, 'D' => 20, 'E' => 16, 'F' => 8, 'G' => 24, 'H' => 30]
        );
        $r = 2;
        foreach ($rows as $i => $d) {
            $bobot = $d['bobot_persiapan'] . '/' . $d['bobot_proses'] . '/' . $d['bobot_hasil'] . '/' . $d['bobot_sikap'] . '/' . $d['bobot_waktu'];
            $sheet->fromArray([
                $i + 1, $d['kode'], $d['nama'], $d['jurusan_nama'],
                $d['tahun_ajaran_tahun'] ? $d['tahun_ajaran_tahun'] . ' ' . $d['tahun_ajaran_semester'] : null,
                $d['kkm'], $bobot, $d['keterangan'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $this->streamXlsx($ss, 'Data-Paket-Soal-UKK-' . date('Ymd-His'), 'H');
    }

    public function template()
    {
        $ss    = new Spreadsheet();
        $sheet = $this->sheetWithHeader(
            $ss,
            'Template Paket Soal UKK',
            ['Kode', 'Nama', 'Deskripsi', 'KKM', 'Keterangan'],
            ['A' => 14, 'B' => 28, 'C' => 30, 'D' => 8, 'E' => 30]
        );
        $sheet->fromArray(['PS-TKJ-01', 'Instalasi Jaringan LAN', 'Paket soal kompetensi TKJ', 70, ''], null, 'A2', true);

        $this->streamXlsx($ss, 'Template-Import-Paket-Soal-UKK');
    }

    // ===================== KONFIG IMPOR =====================
    // Impor hanya kolom dasar (bobot dipakai default 10/30/40/10/10, KKM diisi manual per baris).

    protected function importCols(): array
    {
        return [
            ['key' => 'kode',       'label' => 'Kode',       'type' => 'text',   'required' => true, 'width' => 120],
            ['key' => 'nama',       'label' => 'Nama',       'type' => 'text',   'required' => true, 'width' => 220],
            ['key' => 'deskripsi',  'label' => 'Deskripsi',  'type' => 'text',   'width' => 240],
            ['key' => 'kkm',        'label' => 'KKM',        'type' => 'number', 'width' => 80],
            ['key' => 'keterangan', 'label' => 'Keterangan', 'type' => 'text',   'width' => 200],
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

        $payload = [
            'kode'       => $kode,
            'nama'       => $nama,
            'deskripsi'  => trim((string) ($row['deskripsi'] ?? '')) ?: null,
            'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];

        // Bobot/KKM hanya diisi DEFAULT untuk paket BARU — paket yang sudah ada
        // (kode dikenali) tidak disentuh agar pengaturan bobot manual di UI tak tertimpa.
        if ($this->model->withDeleted()->where('kode', $kode)->first() === null) {
            $payload['bobot_persiapan'] = 10;
            $payload['bobot_proses']    = 30;
            $payload['bobot_hasil']     = 40;
            $payload['bobot_sikap']     = 10;
            $payload['bobot_waktu']     = 10;
            $payload['kkm']             = (float) ($row['kkm'] ?? 0) ?: 70;
        }

        return $payload;
    }
}
