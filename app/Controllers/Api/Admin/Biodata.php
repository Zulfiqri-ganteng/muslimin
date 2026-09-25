<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\BiodataForm;
use App\Libraries\BiodataLaporan;
use App\Libraries\BiodataPesan;
use App\Libraries\BiodataVerifikasi;
use App\Libraries\UjianCetak;
use App\Models\AuditModel;
use App\Models\BiodataIsianModel;
use App\Models\KelasModel;
use App\Models\SettingModel;
use App\Models\SiswaModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Isian Biodata Siswa (API) — cermin App\Controllers\Admin\Biodata.
 * Seluruh aturan ada di library bersama (BiodataVerifikasi, BiodataPesan,
 * BiodataLaporan, BiodataForm), jadi hasil web & aplikasi selalu sama.
 *
 * Form isian SISWA tetap web (tautan subdomain) — API ini khusus admin.
 *
 * Rute (/api/v1, Bearer):
 *   GET    admin/biodata                         ringkasan + pengaturan + tautan + pesan WA
 *   GET    admin/biodata/meta                    label, bagian, kolom wajib, pilihan
 *   POST   admin/biodata/pengaturan              {biodata_open, biodata_tutup}
 *   GET    admin/biodata/kelas                   rekap per kelas
 *   GET    admin/biodata/isian?status=&kelas_id=&q=&page=&per=
 *   GET    admin/biodata/belum?kelas_id=&q=&page=&per=
 *   GET    admin/biodata/laporan?kelas_id=&unduh=1   Excel siap cetak
 *   POST   admin/biodata/setujui-massal          {ids:[..]} | {mode:"all", kelas_id, q}
 *   GET    admin/biodata/isian/{id}              detail + pembanding lama vs baru
 *   POST   admin/biodata/isian/{id}/setujui
 *   POST   admin/biodata/isian/{id}/kembalikan   {catatan}
 *   DELETE admin/biodata/isian/{id}
 */
class Biodata extends BaseApiController
{
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** Batas satu kali setujui massal (sama dengan web). */
    private const MAKS_MASSAL = 300;

    private const STATUS_LABEL = [
        'menunggu'  => 'Menunggu verifikasi',
        'disetujui' => 'Disetujui',
        'perbaikan' => 'Perlu perbaikan',
    ];

    protected BiodataIsianModel $isian;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->isian = new BiodataIsianModel();
        $this->audit = new AuditModel();
    }

    // ================= Ringkasan & pengaturan =================

    public function index(): ResponseInterface
    {
        $setting = (new SettingModel())->get();

        return $this->ok([
            'ringkasan'   => $this->isian->ringkasan(),
            'pengaturan'  => $this->pengaturanData($setting),
            'tautan'      => [
                'siswa'    => BiodataPesan::tautan(),
                'cadangan' => BiodataPesan::tautanCadangan(),
            ],
            'pesan_bagikan' => BiodataPesan::bagikan($setting),
            'maks_massal'   => self::MAKS_MASSAL,
        ], 'Ringkasan isian biodata.');
    }

    /** Kamus untuk klien: label kolom, pengelompokan, kolom wajib, pilihan baku. */
    public function meta(): ResponseInterface
    {
        $bagian = [];
        foreach (BiodataForm::BAGIAN as $judul => $kolom) {
            $bagian[] = ['judul' => $judul, 'kolom' => $kolom];
        }

        return $this->ok([
            'label'          => BiodataForm::LABEL,
            'bagian'         => $bagian,
            'wajib'          => BiodataForm::WAJIB,
            'kolom_biodata'  => SiswaModel::KOLOM_BIODATA,
            'status_label'   => self::STATUS_LABEL,
            'pilihan'        => [
                'agama'           => SiswaModel::AGAMA,
                'status_keluarga' => SiswaModel::STATUS_KELUARGA,
                'pekerjaan'       => SiswaModel::PEKERJAAN,
            ],
        ], 'Kamus isian biodata.');
    }

    public function pengaturan(): ResponseInterface
    {
        $in    = $this->body();
        $buka  = filter_var($in['biodata_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasil = BiodataVerifikasi::simpanPengaturan($buka, (string) ($in['biodata_tutup'] ?? ''));
        if (! $hasil['ok']) {
            return $this->invalid(['biodata_tutup' => $hasil['pesan']]);
        }
        $this->audit->record('update', 'settings', 1, 'Isian biodata: ' . ($hasil['buka'] ? 'DIBUKA' : 'DITUTUP')
            . ($hasil['tutup'] !== null ? ' s.d. ' . $hasil['tutup'] : '') . ' (via mobile)');

        return $this->ok([
            'pengaturan' => $this->pengaturanData((new SettingModel())->get()),
            'peringatan' => $hasil['lewat'] ? $hasil['pesan'] : null,
        ], $hasil['pesan']);
    }

    // ================= Daftar =================

    public function kelas(): ResponseInterface
    {
        return $this->ok($this->isian->perKelas(), 'Rekap isian biodata per kelas.');
    }

    public function isian(): ResponseInterface
    {
        $status = (string) $this->request->getGet('status');
        if (! in_array($status, BiodataIsianModel::STATUS, true)) {
            $status = 'menunggu';
        }
        [$per, $page]   = $this->halaman();
        [$rows, $total] = $this->isian->daftar(
            $status,
            (int) $this->request->getGet('kelas_id'),
            trim((string) $this->request->getGet('q')),
            $per,
            $page
        );

        return $this->collection(
            array_map([$this, 'transformIsian'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $total],
            'Daftar isian ' . self::STATUS_LABEL[$status] . '.'
        );
    }

    /**
     * Siswa aktif yang belum mengirim isian. Dengan kelas_id: SEMUA nama kelas
     * itu (tanpa halaman) + pesan WA siap kirim, sama seperti di web.
     */
    public function belum(): ResponseInterface
    {
        $kelasId      = (int) $this->request->getGet('kelas_id');
        $q            = trim((string) $this->request->getGet('q'));
        [$per, $page] = $this->halaman();
        if ($kelasId > 0) {
            [$per, $page] = [0, 1];
        }
        [$rows, $total] = $this->isian->belumMengisi($kelasId, $q, $per, $page);

        $items = array_map(static fn (array $r) => [
            'id'            => (int) $r['id'],
            'nama'          => $r['nama'],
            'jenis_kelamin' => $r['jenis_kelamin'] ?? null,
            'nama_kelas'    => $r['nama_kelas'] ?? null,
        ], $rows);

        $pesan = null;
        if ($kelasId > 0 && $rows !== []) {
            $kelasNama = (new KelasModel())->options()[$kelasId] ?? '';
            $pesan     = BiodataPesan::belumMengisi($kelasNama, array_column($rows, 'nama'));
        }
        $perHalaman = $per > 0 ? $per : max(1, $total);

        return $this->ok($items, 'Daftar siswa yang belum mengisi biodata.', [
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perHalaman,
                'total'       => $total,
                'total_pages' => (int) max(1, ceil($total / $perHalaman)),
            ],
            'pesan_wa' => $pesan,
        ]);
    }

    // ================= Satu isian =================

    /** @param int|string|null $id */
    public function detail($id = null): ResponseInterface
    {
        $row = $this->isian->find((int) $id);
        if ($row === null) {
            return $this->missing('Isian tidak ditemukan.');
        }
        $siswa   = (new SiswaModel())->withDeleted()->withRelations()->where('siswa.id', $row['siswa_id'])->first();
        $banding = BiodataVerifikasi::bandingkan($row, $siswa);

        $bagian = [];
        foreach ($banding['bagian'] as $judul => $kolom) {
            $bagian[] = ['judul' => $judul, 'kolom' => $kolom];
        }
        $admin = null;
        if (! empty($row['diverifikasi_oleh'])) {
            $admin = db_connect()->table('admins')->select('full_name, username')
                ->where('id', (int) $row['diverifikasi_oleh'])->get()->getRowArray();
        }
        $aktif = $siswa !== null && empty($siswa['deleted_at']);

        return $this->ok([
            'isian' => $this->transformIsian($row + ['nama' => $siswa['nama'] ?? null, 'nama_kelas' => $siswa['nama_kelas'] ?? null]) + [
                'ip_address'         => $row['ip_address'] ?? null,
                'dikirim_pertama_at' => $row['created_at'] ?? null,
                'diverifikasi_oleh'  => $admin !== null ? ($admin['full_name'] ?: $admin['username']) : null,
            ],
            'siswa' => $siswa === null ? null : [
                'id'            => (int) $siswa['id'],
                'nis'           => $siswa['nis'],
                'nama'          => $siswa['nama'],
                'jenis_kelamin' => $siswa['jenis_kelamin'] ?? null,
                'nama_kelas'    => $siswa['nama_kelas'] ?? null,
                'terhapus'      => ! empty($siswa['deleted_at']),
            ],
            'judul_lama' => $banding['judul_lama'],
            'bagian'     => $bagian,
            'hitung'     => $banding['hitung'],
            'peringatan' => BiodataVerifikasi::peringatan($row, $siswa),
            'bisa'       => [
                'setujui'    => $aktif && $row['status'] === 'menunggu',
                'kembalikan' => $siswa !== null && $row['status'] !== 'perbaikan',
                'hapus'      => true,
            ],
        ], 'Detail isian biodata.');
    }

    /** @param int|string|null $id */
    public function setujui($id = null): ResponseInterface
    {
        $id    = (int) $id;
        $hasil = (new BiodataVerifikasi())->setujui($id, $this->adminId());
        if (! $hasil['ok']) {
            return $this->failure($hasil['pesan'], $this->isian->find($id) === null ? 404 : 422);
        }
        master_data_changed('siswa');
        $this->audit->record('update', 'siswa', null, 'Setujui biodata: ' . $hasil['nama'] . ' (isian #' . $id . ', via mobile)');

        return $this->ok($this->hasilKeputusan($id), 'Biodata ' . $hasil['nama'] . ' disetujui dan masuk Master Siswa.');
    }

    /** @param int|string|null $id */
    public function kembalikan($id = null): ResponseInterface
    {
        $id    = (int) $id;
        $hasil = (new BiodataVerifikasi())->kembalikan($id, (string) ($this->body()['catatan'] ?? ''));
        if (! $hasil['ok']) {
            if ($this->isian->find($id) === null) {
                return $this->missing($hasil['pesan']);
            }

            return $this->invalid(['catatan' => $hasil['pesan']], $hasil['pesan']);
        }
        $this->audit->record('update', 'biodata_isian', $id, 'Kembalikan isian biodata untuk diperbaiki (via mobile)');

        return $this->ok($this->hasilKeputusan($id), $hasil['pesan']);
    }

    /** @param int|string|null $id */
    public function hapus($id = null): ResponseInterface
    {
        $id    = (int) $id;
        $hasil = (new BiodataVerifikasi())->hapus($id);
        if (! $hasil['ok']) {
            return $this->missing($hasil['pesan']);
        }
        $this->audit->record('delete', 'biodata_isian', $id, 'Hapus isian biodata (via mobile)');

        return $this->ok(null, $hasil['pesan']);
    }

    /** {ids:[..]} atau {mode:"all", kelas_id, q} — maks. 300 per panggilan. */
    public function setujuiMassal(): ResponseInterface
    {
        $in = $this->body();
        if (($in['mode'] ?? '') === 'all') {
            $ids = $this->isian->idMenunggu((int) ($in['kelas_id'] ?? 0), trim((string) ($in['q'] ?? '')), self::MAKS_MASSAL);
        } else {
            $ids = array_slice(array_values(array_filter(array_map('intval', (array) ($in['ids'] ?? [])))), 0, self::MAKS_MASSAL);
        }
        if ($ids === []) {
            return $this->invalid(['ids' => 'Tidak ada isian yang dipilih.']);
        }

        ['ok' => $ok, 'gagal' => $gagal] = (new BiodataVerifikasi())->setujuiBanyak($ids, $this->adminId());
        if ($ok > 0) {
            master_data_changed('siswa');
            $this->audit->record('update', 'siswa', null, "Setujui massal biodata: {$ok} disetujui, " . count($gagal) . ' gagal (via mobile)');
        }
        $sisa = $this->isian->ringkasan()['menunggu'];

        return $this->ok([
            'disetujui'     => $ok,
            'gagal'         => $gagal,
            'sisa_menunggu' => $sisa,
        ], "{$ok} biodata disetujui." . ($gagal !== [] ? ' ' . count($gagal) . ' gagal.' : '') . ($sisa > 0 ? " Masih ada {$sisa} isian menunggu." : ''));
    }

    // ================= Laporan Excel =================

    /** Excel kelengkapan biodata (sama dengan tombol Unduh Laporan di web). */
    public function laporan(): ResponseInterface
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        $kelas   = BiodataLaporan::data($kelasId);
        $isi     = UjianCetak::xlsx(BiodataLaporan::excel($kelas, (new SettingModel())->get()));
        $nama    = BiodataLaporan::namaBerkas($kelas, $kelasId) . '.xlsx';
        $unduh   = in_array((string) $this->request->getGet('unduh'), ['1', 'true', 'ya'], true);

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', self::MIME_XLSX)
            ->setHeader('Content-Disposition', ($unduh ? 'attachment' : 'inline') . '; filename="' . $nama . '"')
            ->setHeader('Content-Length', (string) strlen($isi))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setBody($isi);
    }

    // ================= Pembantu =================

    private function pengaturanData(array $setting): array
    {
        return [
            'biodata_open'  => ! empty($setting['biodata_open']),
            'biodata_tutup' => $setting['biodata_tutup'] ?? null,
            'batas_teks'    => BiodataPesan::batasTeks($setting['biodata_tutup'] ?? null),
            // Keadaan SEBENARNYA bagi siswa (saklar + batas waktu).
            'terbuka'       => BiodataIsianModel::formTerbuka($setting),
        ];
    }

    /** Satu baris isian untuk daftar & detail. */
    private function transformIsian(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'nomor'           => '#' . str_pad((string) (int) $r['id'], 5, '0', STR_PAD_LEFT),
            'siswa_id'        => (int) $r['siswa_id'],
            'nama'            => $r['nama'] ?? null,
            'nama_kelas'      => $r['nama_kelas'] ?? null,
            'nisn'            => $r['nisn'] ?? null,
            'status'          => $r['status'],
            'status_label'    => self::STATUS_LABEL[$r['status']] ?? $r['status'],
            'kirim_ke'        => (int) ($r['kirim_ke'] ?? 1),
            'catatan_admin'   => $r['catatan_admin'] ?? null,
            'dikirim_at'      => $r['updated_at'] ?? null,
            'diverifikasi_at' => $r['diverifikasi_at'] ?? null,
        ];
    }

    /** Status terbaru isian + id isian menunggu berikutnya (untuk tombol "lanjut periksa"). */
    private function hasilKeputusan(int $id): array
    {
        $row  = $this->isian->find($id);
        $next = $this->isian->menungguBerikutnya($id, (int) ($this->body()['kelas_id'] ?? 0));

        return [
            'id'          => $id,
            'status'      => $row['status'] ?? null,
            'berikutnya'  => $next !== null ? (int) $next['id'] : null,
        ];
    }

    /** @return array{0:int, 1:int} [per, page] — per dibatasi 10–50 (default 20). */
    private function halaman(): array
    {
        $per = (int) $this->request->getGet('per');

        return [
            in_array($per, [10, 20, 30, 40, 50], true) ? $per : 20,
            max(1, (int) $this->request->getGet('page')),
        ];
    }
}
