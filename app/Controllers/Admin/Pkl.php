<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Libraries\PklKeputusan;
use App\Libraries\PklNamaBerkas;
use App\Libraries\PklNomorSurat;
use App\Libraries\PklPeringatan;
use App\Libraries\PklStaf;
use App\Libraries\PklSurat;
use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Peran;

/**
 * PKL / Prakerin — sisi staf (Operator Sekolah, Waka Hubin, Admin). Rancangan: docs/DESAIN-PKL.md.
 *
 * ATURAN SEKOLAH (2026-10-07): yang berhak MENYETUJUI (ACC), menolak, dan mencabut persetujuan
 * hanyalah Waka Hubin. Admin web boleh sebagai CADANGAN bila Hubin berhalangan (wajib mencentang
 * "mewakili Waka Hubin"; tercatat sebagai peran admin di kaki surat). Operator TIDAK boleh —
 * ia memeriksa, mengembalikan untuk diperbaiki, mengubah, mengisi atas nama, dan mencetak surat.
 * Hak ini dibaca dari Config\Peran ('acc') lewat HakAkses::bolehAcc() dan dijaga DI SINI (bukan
 * hanya disembunyikan di tampilan); percobaan yang ditolak masuk Audit Log.
 * Hanya Operator/Admin yang boleh Pengaturan dan Hapus (Config\Peran → 'kecuali').
 *
 * Pengaman kesalahan manusia:
 *   - kembalikan / tolak / batalkan persetujuan WAJIB beralasan (alasan dibaca siswa & tercatat);
 *   - ACC dengan peringatan "bahaya" menuntut centang "sudah saya periksa";
 *   - tanggal di luar pagar sekolah hanya lewat centang konfirmasi, dan tercatat di riwayat;
 *   - ACC otomatis menautkan perusahaan ke master (atau memakai pilihan staf) — tanpa data ganda;
 *   - hapus ajuan disetujui menuntut konfirmasi tambahan, dan ringkasannya masuk Audit Log;
 *   - semua aksi POST dilindungi CSRF (filter pada rute), seluruhnya tercatat di pkl_riwayat + Audit Log.
 */
class Pkl extends BaseController
{
    private const PER = 20;
    private const PER_SISWA = 50; // Status Siswa: daftar lebih panjang, jadi halamannya lebih besar

    private PklPengajuanModel $model;
    private PklPengaturanModel $pengModel;
    private AuditModel $audit;
    private array $p;

    public function __construct()
    {
        $this->model     = new PklPengajuanModel();
        $this->pengModel = new PklPengaturanModel();
        $this->audit     = new AuditModel();
        $this->p         = $this->pengModel->ambil();
    }

    // =================================================================
    // Beranda
    // =================================================================

    public function index()
    {
        $tingkat = PklPengaturanModel::tingkatBoleh($this->p);
        [$antrean] = $this->model->daftar('menunggu', '', 0, 8, 1);

        $peranIni = $this->peranSaya();
        $suratRingkas = null;
        if (HakAkses::bolehPkl($peranIni, 'surat') || HakAkses::bolehPkl($peranIni, 'laporan')) {
            $db = db_connect();
            $suratRingkas = [
                'belum_bernomor' => (int) $db->query("SELECT COUNT(*) n FROM pkl_pengajuan p WHERE p.status = 'disetujui' AND NOT EXISTS (SELECT 1 FROM pkl_surat s WHERE s.pengajuan_id = p.id)")->getRowArray()['n'],
                'belum_dikabari' => (int) $db->query("SELECT COUNT(*) n FROM pkl_anggota a JOIN pkl_pengajuan p ON p.id = a.pengajuan_id AND p.status = 'disetujui' JOIN pkl_surat s ON s.pengajuan_id = p.id WHERE a.dikabari_at IS NULL")->getRowArray()['n'],
                'uang_bulan_ini' => (int) ($db->query('SELECT COALESCE(SUM(nominal), 0) n FROM pkl_pembayaran WHERE created_at >= ?', [date('Y-m-01 00:00:00')])->getRowArray()['n'] ?? 0),
            ];
        }

        return view('admin/pkl/index', $this->dasar('Beranda PKL', 'beranda') + [
            'suratRingkas' => $suratRingkas,
            'hitung'  => $this->model->hitungStatus(),
            'siswa'   => $this->model->ringkasanSiswa($tingkat),
            'antrean' => $antrean,
            'alasan'  => PklPengaturanModel::alasanTutup($this->p),
            'tautan'  => $this->tautanSiswa(),
            'tingkat' => $tingkat,
            'terlambat' => $this->model->hitungTerlambat(PklPengaturanModel::batasHari($this->p)),
        ]);
    }

    // =================================================================
    // Kotak masuk
    // =================================================================

    public function daftar(string $status = 'menunggu')
    {
        if (! in_array($status, PklPengajuanModel::STATUS, true)) {
            return redirect()->to(site_url('admin/pkl/daftar/menunggu'));
        }

        $q      = IsianBantu::rapikan((string) $this->request->getGet('q'));
        $kelas  = (int) $this->request->getGet('kelas_id');
        $page   = max(1, (int) $this->request->getGet('page'));
        [$rows, $total] = $this->model->daftar($status, $q, $kelas, self::PER, $page);

        return view('admin/pkl/daftar', $this->dasar(self::JUDUL_STATUS[$status], 'daftar_' . $status) + [
            'status'  => $status,
            'rows'    => $rows,
            'total'   => $total,
            'q'       => $q,
            'kelasId' => $kelas,
            'kelas'   => $this->model->kelasBersiswa(),
            'page'    => $page,
            'jmlHal'  => max(1, (int) ceil($total / self::PER)),
            'cek'     => $status === 'menunggu' ? $this->ringkasCek($rows) : [],
            'statusSurat' => $status === 'disetujui' ? (new PklSurat())->statusBanyak(array_map('intval', array_column($rows, 'id'))) : [],
        ]);
    }

    private const JUDUL_STATUS = [
        'menunggu'  => 'Menunggu ACC',
        'perbaikan' => 'Perlu Perbaikan',
        'disetujui' => 'Disetujui',
        'ditolak'   => 'Ditolak',
    ];

    // =================================================================
    // Detail + keputusan
    // =================================================================

    public function detail($id)
    {
        $ajuan = $this->model->detail((int) $id);
        if ($ajuan === null) {
            return $this->ke('admin/pkl/daftar/menunggu', 'error', 'Ajuan tidak ditemukan (mungkin sudah dihapus).');
        }

        $anggota    = $this->model->anggotaDetail((int) $id);
        $peringatan = PklPeringatan::untuk($ajuan, $anggota, $this->p);
        $surat      = (new PklSurat())->surat((int) $id);
        $peranIni   = $this->peranSaya();
        // Catatan biaya hanya dilihat yang memegang hak unduh surat atau laporan pembayaran (data keuangan siswa).
        $pembayaran = ($ajuan['status'] === 'disetujui' && (HakAkses::bolehPkl($peranIni, 'surat') || HakAkses::bolehPkl($peranIni, 'laporan')))
            ? (new \App\Libraries\PklBiaya())->untukAjuan((int) $id) : null;

        return view('admin/pkl/detail', $this->dasar(PklPengajuanModel::kode((int) $id), 'daftar_' . $ajuan['status']) + [
            'pembayaran' => $pembayaran,
            'a'          => $ajuan,
            'anggota'    => $anggota,
            'riwayat'    => $this->model->riwayat((int) $id),
            'peringatan' => $peringatan,
            'adaBahaya'  => in_array('bahaya', array_column($peringatan, 'tingkat'), true),
            'master'     => PklPeringatan::kandidatMaster((string) $ajuan['perusahaan_norm']),
            'kode'       => PklPengajuanModel::kode((int) $id),
            'surat'      => $surat,
            'perluUlang' => $surat !== null && PklSurat::sidik($ajuan, $anggota, $this->p) !== (string) $surat['sidik'],
            'sisaHari'   => $ajuan['status'] === 'menunggu' ? PklPengajuanModel::sisaHari($ajuan['diajukan_at'] ?? $ajuan['created_at'], PklPengaturanModel::batasHari($this->p)) : null,
            'batasKeputusan' => $ajuan['status'] === 'menunggu' ? PklPengajuanModel::batasKeputusan($ajuan['diajukan_at'] ?? $ajuan['created_at'], PklPengaturanModel::batasHari($this->p)) : null,
        ]);
    }

    public function acc($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'acc');
    }

    public function kembalikan($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'kembalikan');
    }

    public function tolak($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'tolak');
    }

    public function batalAcc($id): RedirectResponse
    {
        return $this->putuskan((int) $id, 'batal_acc');
    }

    /**
     * Satu pintu untuk semua keputusan. Seluruh aturan (hak ACC, Admin "mewakili", status asal, alasan wajib,
     * peringatan bahaya, pencatatan) ada di Libraries\PklKeputusan — dipakai juga oleh API Android.
     */
    private function putuskan(int $id, string $aksi): RedirectResponse
    {
        $hasil = (new PklKeputusan())->putuskan($id, $aksi, (array) $this->request->getPost(), $this->konteks(['saluran' => 'web']), $this->p);
        if ($hasil['kode'] === 'tidak_ada') {
            return $this->ke('admin/pkl/daftar/menunggu', 'error', $hasil['pesan']);
        }

        return $this->ke('admin/pkl/' . $id, $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }
    // =================================================================
    // Isi atas nama & ubah langsung
    // =================================================================

    public function baru()
    {
        return $this->tampilForm(null, [], [], []);
    }

    public function simpanBaru()
    {
        $post = $this->request->getPost();
        [$data, $galat] = PklForm::proses($post, $this->p, $this->opsiForm($post));

        $pengajuId = (int) ($post['siswa_id'] ?? 0);
        [$anggota, $galatAnggota] = $this->periksaAnggota($pengajuId, $data['teman'], null, $data['teman_hp'] ?? []);
        $galat += $galatAnggota;
        $statusAwal = ($post['status_awal'] ?? '') === 'disetujui' ? 'disetujui' : 'menunggu';
        if ($statusAwal === 'disetujui' && ! HakAkses::bolehAcc($this->peranSaya())) {
            $galat['umum'] = 'Menyimpan ajuan langsung berstatus DISETUJUI hanya boleh dilakukan Waka Hubin (atau Admin). Simpan sebagai menunggu, lalu minta Waka Hubin meng-ACC.';
        }

        if ($galat !== []) {
            return $this->tampilForm(null, $post, $galat, []);
        }

        $hasil = (new PklAjuan())->kirimBaru($data, $anggota, $this->konteks([
            'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => $statusAwal,
            'tahun_ajaran' => (new SettingModel())->get()['academic_year'] ?? null,
        ]));
        if (! $hasil['ok']) {
            $galat = ['umum' => $this->pesanGagal($hasil)];

            return $this->tampilForm(null, $post, $galat, []);
        }

        $kode = PklPengajuanModel::kode((int) $hasil['id']);
        $this->audit->record('create', 'pkl_pengajuan', (int) $hasil['id'], 'PKL ' . $kode . ' diisi atas nama siswa (' . $statusAwal . ') — ' . $data['perusahaan_nama']);

        return $this->ke('admin/pkl/' . $hasil['id'], 'success', 'Ajuan ' . $kode . ' dibuat atas nama siswa' . ($statusAwal === 'disetujui' ? ' dan langsung disetujui.' : '.'));
    }

    public function ubah($id)
    {
        $ajuan = $this->model->detail((int) $id);
        if ($ajuan === null) {
            return $this->ke('admin/pkl/daftar/menunggu', 'error', 'Ajuan tidak ditemukan.');
        }
        if ($ajuan['status'] === 'disetujui' && ! HakAkses::bolehAcc($this->peranSaya())) {
            return $this->ke('admin/pkl/' . (int) $id, 'error', 'Ajuan yang sudah DISETUJUI hanya boleh diubah Waka Hubin (atau Admin), karena persetujuannya berlaku untuk isi yang sekarang.');
        }

        return $this->tampilForm($ajuan, [], [], $this->model->anggotaDetail((int) $id));
    }

    public function simpanUbah($id)
    {
        $id    = (int) $id;
        $ajuan = $this->model->detail($id);
        if ($ajuan === null) {
            return $this->ke('admin/pkl/daftar/menunggu', 'error', 'Ajuan tidak ditemukan.');
        }
        if ($ajuan['status'] === 'disetujui' && ! HakAkses::bolehAcc($this->peranSaya())) {
            return $this->ke('admin/pkl/' . $id, 'error', 'Ajuan yang sudah DISETUJUI hanya boleh diubah Waka Hubin (atau Admin), karena persetujuannya berlaku untuk isi yang sekarang.');
        }
        $lama = $this->model->anggotaDetail($id);

        $post = $this->request->getPost();
        [$data, $galat] = PklForm::proses($post, $this->p, $this->opsiForm($post));

        $pengajuLama = 0;
        foreach ($lama as $a) {
            if ($a['peran'] === 'pengaju') {
                $pengajuLama = (int) $a['siswa_id'];
            }
        }
        [$anggota, $galatAnggota] = $this->periksaAnggota($pengajuLama, $data['teman'], $id, $data['teman_hp'] ?? []);
        $galat += $galatAnggota;
        if ($galat !== []) {
            return $this->tampilForm($ajuan, $post, $galat, $lama);
        }

        $catatan = null;
        $hasil   = (new PklAjuan())->ubahIsi($id, $data, $anggota, $this->konteks(['aksi' => 'ubah', 'catatan' => $catatan]));
        if (! $hasil['ok']) {
            return $this->tampilForm($ajuan, $post, ['umum' => $this->pesanGagal($hasil)], $lama);
        }

        $kode = PklPengajuanModel::kode($id);
        $this->audit->record('update', 'pkl_pengajuan', $id, 'PKL ' . $kode . ' diubah langsung oleh staf — ' . $data['perusahaan_nama'] . ($catatan ? ' [' . $catatan . ']' : ''));

        return $this->ke('admin/pkl/' . $id, 'success', 'Data ajuan ' . $kode . ' diperbarui.');
    }

    /** Opsi PklForm untuk staf: pernyataan tak perlu; HP pengaju & tiap teman TETAP wajib (tercetak di surat), sama seperti form siswa. */
    private function opsiForm(array $post): array
    {
        return ['pernyataan' => false];
    }

    /** Pengaju + teman: aktif & belum terkunci di ajuan lain (aturan di Libraries\PklStaf, dipakai juga API). */
    private function periksaAnggota(int $pengajuId, array $temanIds, ?int $ajuanSendiri, array $hpTeman = []): array
    {
        return PklStaf::periksaAnggota($pengajuId, $temanIds, $ajuanSendiri, $hpTeman);
    }
    /** Form isi-atas-nama ($ajuan null) / ubah-langsung ($ajuan terisi). */
    private function tampilForm(?array $ajuan, array $post, array $galat, array $anggota)
    {
        $ubah = $ajuan !== null;

        // Pilihan siswa yang ditampilkan di form: dari isian yang gagal tadi, atau dari anggota ajuan.
        $orang = ['pengaju' => null, 'teman' => []];
        if ($post !== []) {
            $nama = $this->model->ringkasSiswa(array_merge([(int) ($post['siswa_id'] ?? 0)], array_map('intval', (array) ($post['teman'] ?? []))));
            $orang['pengaju'] = $nama[(int) ($post['siswa_id'] ?? 0)] ?? null;
            foreach ((array) ($post['teman'] ?? []) as $tid) {
                if (isset($nama[(int) $tid])) {
                    $orang['teman'][] = $nama[(int) $tid] + ['hp' => (string) (((array) ($post['teman_hp'] ?? []))[(int) $tid] ?? '')];
                }
            }
        } else {
            foreach ($anggota as $s) {
                $baris = ['id' => (int) $s['siswa_id'], 'nama' => $s['nama'], 'kelas' => (string) ($s['nama_kelas'] ?? ''), 'hp' => (string) ($s['hp'] ?? '')];
                if ($s['peran'] === 'pengaju') {
                    $orang['pengaju'] = $baris;
                } else {
                    $orang['teman'][] = $baris;
                }
            }
        }

        return view('admin/pkl/form', $this->dasar($ubah ? 'Ubah ' . PklPengajuanModel::kode((int) $ajuan['id']) : 'Isi atas Nama Siswa', $ubah ? 'daftar_' . $ajuan['status'] : 'baru') + [
            'a'       => $ajuan,
            'anggota' => $anggota,
            'orang'   => $orang,
            'old'     => $post,
            'galat'   => $galat,
            'kelas'   => $this->model->kelasBersiswa(),
            'ubah'    => $ubah,
        ]);
    }

    /** GET admin/pkl/siswa-kelas?kelas_id= — daftar nama + status PKL untuk pemilih siswa di form staf. */
    public function siswaKelas(): ResponseInterface
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        $data    = array_map(static fn (array $r) => [
            'id'     => (int) $r['id'],
            'nama'   => $r['nama'],
            'status' => $r['aktif'] ?? ((int) $r['pernah_ditolak'] === 1 ? 'ditolak' : 'belum'),
        ], $kelasId > 0 ? $this->model->daftarSiswaKelas($kelasId) : []);

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['ok' => true, 'data' => $data]);
    }

    // =================================================================
    // ACC massal
    // =================================================================

    /**
     * Ringkasan peringatan tiap baris (untuk kolom "Pemeriksaan" di tab Menunggu).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<int, array{bahaya: int, awas: int, pertama: string}>
     */
    private function ringkasCek(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $id     = (int) $r['id'];
            $ajuan  = $this->model->detail($id);
            $berat  = $ajuan === null ? [] : array_values(array_filter(
                PklPeringatan::untuk($ajuan, $this->model->anggotaDetail($id), $this->p),
                static fn (array $w) => in_array($w['tingkat'], ['bahaya', 'awas'], true)
            ));
            $out[$id] = [
                'bahaya'  => count(array_filter($berat, static fn ($w) => $w['tingkat'] === 'bahaya')),
                'awas'    => count(array_filter($berat, static fn ($w) => $w['tingkat'] === 'awas')),
                'pertama' => $berat[0]['teks'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * POST admin/pkl/acc-massal — mode "terpilih" (ids[]) atau "aman" (semua yang menunggu). Hanya Waka Hubin
     * (Admin cadangan, wajib "mewakili"); aturan lengkap di Libraries\PklKeputusan::accMassal.
     */
    public function accMassal(): RedirectResponse
    {
        $hasil = (new PklKeputusan())->accMassal((array) $this->request->getPost(), $this->konteks(['saluran' => 'web']), $this->p);
        $redir = $this->ke('admin/pkl/daftar/menunggu', $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);

        return ($hasil['dilewati'] ?? []) !== [] ? $redir->with('errors', $hasil['dilewati']) : $redir;
    }
    // =================================================================
    // Hapus (Operator/Admin — Hubin ditolak oleh Config\Peran)
    // =================================================================

    public function hapus($id): RedirectResponse
    {
        $id    = (int) $id;
        $ajuan = $this->model->detail($id);
        if ($ajuan === null) {
            return $this->ke('admin/pkl/daftar/menunggu', 'error', 'Ajuan tidak ditemukan (mungkin sudah dihapus).');
        }
        // Ajuan yang sudah DISETUJUI Waka Hubin hanya boleh dihapus Admin (Operator tidak boleh menghapus keputusan Hubin).
        if ($ajuan['status'] === 'disetujui' && $this->peranSaya() !== Peran::ADMIN) {
            $this->audit->record('delete', 'pkl_pengajuan', $id, 'DITOLAK: ' . HakAkses::label($this->peranSaya()) . ' mencoba menghapus ajuan yang sudah disetujui, PKL ' . PklPengajuanModel::kode($id));

            return $this->ke('admin/pkl/' . $id, 'error', 'Ajuan yang sudah DISETUJUI Waka Hubin hanya boleh dihapus Admin. Minta Waka Hubin membatalkan persetujuannya, atau hubungi Admin.');
        }
        if ($ajuan['status'] === 'disetujui' && $this->request->getPost('paham') !== '1') {
            return $this->ke('admin/pkl/' . $id, 'error', 'Ajuan ini SUDAH DISETUJUI. Centang konfirmasi bila benar-benar ingin menghapusnya.');
        }

        $anggota = array_map(static fn (array $a) => $a['nama'], $this->model->anggotaDetail($id));
        $this->model->delete($id); // CASCADE: anggota & riwayat ikut terhapus, siswanya bebas lagi

        $kode = PklPengajuanModel::kode($id);
        $this->audit->record('delete', 'pkl_pengajuan', $id, 'PKL ' . $kode . ' dihapus (' . $ajuan['status'] . ') — ' . $ajuan['perusahaan_nama'] . ' — ' . implode(', ', $anggota));

        return $this->ke('admin/pkl/daftar/' . $ajuan['status'], 'success', 'Ajuan ' . $kode . ' dihapus. Siswanya bisa mengajukan lagi.');
    }

    // =================================================================
    // Status Siswa
    // =================================================================

    public function siswa()
    {
        $tingkat = PklPengaturanModel::tingkatBoleh($this->p);
        $q       = IsianBantu::rapikan((string) $this->request->getGet('q'));
        $kelas   = (int) $this->request->getGet('kelas_id');
        $fase    = (string) $this->request->getGet('fase');
        $page    = max(1, (int) $this->request->getGet('page'));
        if (! in_array($fase, self::FASE_SARING, true)) {
            $fase = '';
        }
        [$rows, $total] = $this->model->statusSiswa($tingkat, $kelas, $q, $fase, self::PER_SISWA, $page);

        return view('admin/pkl/siswa', $this->dasar('Status Siswa', 'siswa') + [
            'ringkas' => $this->model->ringkasanSiswa($tingkat, $kelas),
            'rows'    => $rows,
            'total'   => $total,
            'q'       => $q,
            'kelasId' => $kelas,
            'fase'    => $fase,
            'kelas'   => $this->model->kelasBersiswa($tingkat),
            'tingkat' => $tingkat,
            'page'    => $page,
            'jmlHal'  => max(1, (int) ceil($total / self::PER_SISWA)),
        ]);
    }

    /** Nilai saringan fase yang sah (kosong = semua). */
    private const FASE_SARING = [
        '', 'belum_mengisi', 'sudah_mengisi', 'sudah_pkl', 'belum', 'ditolak', 'menunggu', 'perbaikan',
        'belum_mulai', 'sedang', 'selesai', 'disetujui',
    ];

    // =================================================================
    // Pengaturan PKL (Operator/Admin)
    // =================================================================

    public function pengaturan()
    {
        return view('admin/pkl/pengaturan', $this->dasar('Pengaturan PKL', 'pengaturan') + [
            'galat'   => [],
            'old'     => [],
            'tautan'  => $this->tautanSiswa(),
            'alasan'  => PklPengaturanModel::alasanTutup($this->p),
            'siswa'   => $this->model->ringkasanSiswa(PklPengaturanModel::tingkatBoleh($this->p)),
        ] + $this->dataBiaya());
    }

    public function simpanPengaturan()
    {
        $post = $this->request->getPost();
        [$data, $galat] = $this->periksaPengaturan($post);

        if ($galat !== []) {
            return view('admin/pkl/pengaturan', $this->dasar('Pengaturan PKL', 'pengaturan') + [
                'galat'  => $galat,
                'old'    => $post,
                'tautan' => $this->tautanSiswa(),
                'alasan' => PklPengaturanModel::alasanTutup($this->p),
                'siswa'  => $this->model->ringkasanSiswa(PklPengaturanModel::tingkatBoleh($this->p)),
            ] + $this->dataBiaya());
        }

        $this->pengModel->update(1, $data);
        $this->audit->record('update', 'pkl_pengaturan', 1, 'Pengaturan PKL: form ' . ($data['form_buka'] ? 'DIBUKA' : 'ditutup')
            . ', tingkat ' . $data['tingkat'] . ', maks ' . $data['maks_anggota'] . ' siswa, batas keputusan ' . $data['batas_keputusan_hari'] . ' hari'
            . ', Waka Hubin ' . ($data['waka_hubin_nama'] ?? '(kosong)') . ', format nomor ' . $data['format_nomor']);

        return $this->ke('admin/pkl/pengaturan', 'success', $data['form_buka'] ? 'Pengaturan disimpan. Form siswa sekarang TERBUKA.' : 'Pengaturan disimpan. Form siswa tertutup.');
    }

    /** Jenis biaya + pesan WhatsApp untuk bagian bawah halaman Pengaturan PKL. */
    private function dataBiaya(): array
    {
        return [
            'jenisBiaya'  => (new \App\Libraries\PklBiaya())->jenis(true),
            'waPesan'     => (string) ($this->p['wa_pesan'] ?? ''),
            'sekolahNama' => (string) ((new SettingModel())->get()['school_name'] ?? ''),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function periksaPengaturan(array $post): array
    {
        $galat = [];
        $buka  = ($post['form_buka'] ?? '0') === '1' ? 1 : 0;

        $tingkat = array_values(array_intersect(PklPengaturanModel::TINGKAT, array_map('strval', (array) ($post['tingkat'] ?? []))));
        if ($buka === 1 && $tingkat === []) {
            $galat['tingkat'] = 'Pilih minimal satu tingkat yang boleh mengajukan.';
        }

        $angka = static fn (string $k, int $min, int $maks): ?int => (isset($post[$k]) && ctype_digit(trim((string) $post[$k])) && (int) $post[$k] >= $min && (int) $post[$k] <= $maks) ? (int) $post[$k] : null;
        $maksA = $angka('maks_anggota', 1, PklForm::MAKS_SISWA);
        if ($maksA === null) {
            $galat['maks_anggota'] = 'Isi angka 1–' . PklForm::MAKS_SISWA . ' (aturan sekolah: maksimal ' . PklForm::MAKS_SISWA . ' siswa per ajuan).';
        }
        $hariKeputusan = $angka('batas_keputusan_hari', 1, 30);
        if ($hariKeputusan === null) {
            $galat['batas_keputusan_hari'] = 'Isi angka 1–30 (hari).';
        }

        // ----- Surat: penanda tangan, format & lantai nomor -----
        $wakaNama = IsianBantu::rapikanGelar(IsianBantu::rapikan((string) ($post['waka_hubin_nama'] ?? '')));
        if ($wakaNama !== '' && (! IsianBantu::namaOrangSah($wakaNama) || mb_strlen($wakaNama) > 150)) {
            $galat['waka_hubin_nama'] = 'Nama hanya boleh berisi huruf (titik/koma untuk gelar), maksimal 150 huruf.';
        }
        $wakaNip = trim((string) preg_replace('/[^0-9 ]/', '', (string) ($post['waka_hubin_nip'] ?? '')));
        $jabatan = IsianBantu::rapikan((string) ($post['waka_hubin_jabatan'] ?? ''));
        if ($jabatan === '') {
            $jabatan = 'Wakil Kepala Sekolah Bidang Hubungan Industri';
        }
        if (mb_strlen($jabatan) > 150) {
            $galat['waka_hubin_jabatan'] = 'Jabatan terlalu panjang (maksimal 150 huruf).';
        }

        $kepsek = IsianBantu::rapikanGelar(IsianBantu::rapikan((string) ($post['kepsek_nama'] ?? '')));
        if ($kepsek !== '' && (! IsianBantu::namaOrangSah($kepsek) || mb_strlen($kepsek) > 150)) {
            $galat['kepsek_nama'] = 'Nama Kepala Sekolah hanya boleh berisi huruf (titik/koma untuk gelar), maksimal 150 huruf.';
        }
        $kontakNama = IsianBantu::rapikanGelar(IsianBantu::rapikan((string) ($post['kontak_surat_nama'] ?? '')));
        if ($kontakNama !== '' && (! IsianBantu::namaOrangSah($kontakNama) || mb_strlen($kontakNama) > 150)) {
            $galat['kontak_surat_nama'] = 'Nama kontak hanya boleh berisi huruf (titik/koma untuk gelar), maksimal 150 huruf.';
        }
        $kontakHp = trim((string) preg_replace('/[^0-9+ \-]/', '', (string) ($post['kontak_surat_hp'] ?? '')));
        if (mb_strlen($kontakHp) > 30) {
            $galat['kontak_surat_hp'] = 'Nomor kontak terlalu panjang (maksimal 30 karakter).';
        }

        $pola = trim((string) ($post['format_nomor'] ?? ''));
        if ($pola === '') {
            $pola = PklNomorSurat::BAWAAN;
        }
        if (($g = PklNomorSurat::periksa($pola)) !== null) {
            $galat['format_nomor'] = $g;
        }
        $nomorAwal = (isset($post['nomor_awal']) && ctype_digit(trim((string) $post['nomor_awal'])) && (int) $post['nomor_awal'] >= 1 && (int) $post['nomor_awal'] <= 99999) ? (int) $post['nomor_awal'] : null;
        if ($nomorAwal === null) {
            $galat['nomor_awal'] = 'Isi angka 1-99999.';
        }
        // Lantai nomor berlaku untuk TAHUN ia diisi; bila angkanya tak diubah, tahun lama dipertahankan.
        $tahunAwal = ($nomorAwal !== null && $nomorAwal !== (int) ($this->p['nomor_awal'] ?? 1)) ? (int) date('Y') : ($this->p['nomor_awal_tahun'] ?? null);

        $polaBerkas = trim((string) ($post['format_nama_berkas'] ?? ''));
        if ($polaBerkas === '') {
            $polaBerkas = PklNamaBerkas::BAWAAN;
        }
        if (($g = PklNamaBerkas::periksa($polaBerkas)) !== null) {
            $galat['format_nama_berkas'] = $g;
        }

        $tutup = trim((string) ($post['form_tutup'] ?? ''));
        $tutupSql = null;
        if ($tutup !== '') {
            $t = \DateTime::createFromFormat('Y-m-d\TH:i', $tutup) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $tutup);
            if ($t === false) {
                $galat['form_tutup'] = 'Waktu tutup tidak valid.';
            } else {
                $tutupSql = $t->format('Y-m-d H:i:00');
                if ($buka === 1 && strtotime($tutupSql) <= time()) {
                    $galat['form_tutup'] = 'Waktu tutup sudah lewat — form akan langsung tertutup. Kosongkan atau pilih waktu ke depan.';
                }
            }
        }

        // Kolom pagar tanggal & lama PKL lama TIDAK disentuh (tak dipakai lagi; nilainya dibiarkan).
        return [[
            'form_buka'            => $buka,
            'form_tutup'           => $tutupSql,
            'tingkat'              => implode(',', $tingkat),
            'maks_anggota'         => $maksA ?? PklForm::MAKS_SISWA,
            'batas_keputusan_hari' => $hariKeputusan ?? 5,
            'waka_hubin_nama'      => $wakaNama !== '' ? $wakaNama : null,
            'waka_hubin_nip'       => $wakaNip !== '' ? $wakaNip : null,
            'waka_hubin_jabatan'   => $jabatan,
            'kepsek_nama'          => $kepsek !== '' ? $kepsek : null,
            'kontak_surat_nama'    => $kontakNama !== '' ? $kontakNama : null,
            'kontak_surat_hp'      => $kontakHp !== '' ? $kontakHp : null,
            'format_nomor'         => $pola,
            'format_nama_berkas'   => $polaBerkas,
            'nomor_awal'           => $nomorAwal ?? 1,
            'nomor_awal_tahun'     => $tahunAwal,
        ], $galat];
    }
    // =================================================================
    // Pembantu
    // =================================================================

    /** Data umum semua halaman: judul, tab aktif, dan wewenang peran. */
    private function dasar(string $judul, string $tab): array
    {
        $peran = (string) (session('admin')['role'] ?? '');

        return [
            'title'          => $judul,
            'tab'            => $tab,
            'peran'          => $peran,
            'peranLabel'     => HakAkses::label($peran),
            'p'              => $this->p,
            'bolehPengaturan' => HakAkses::boleh($peran, 'admin/pkl/pengaturan'),
            'bolehHapus'     => HakAkses::boleh($peran, 'admin/pkl/hapus'),
            'bolehAcc'       => HakAkses::bolehAcc($peran),
            'bolehTtd'       => HakAkses::boleh($peran, 'admin/pkl/ttd'),
            'bolehSurat'     => HakAkses::bolehPkl($peran, 'surat'),
            'bolehLaporan'   => HakAkses::bolehPkl($peran, 'laporan'),
            'bolehUbah'      => HakAkses::bolehPkl($peran, 'ubah'),
            'batasHari'      => PklPengaturanModel::batasHari($this->p),
            'hitungTab'      => $this->model->hitungStatus(),
        ];
    }

    private function peranSaya(): string
    {
        return (string) (session('admin')['role'] ?? '');
    }

    /** Konteks pencatat riwayat: siapa, perannya, dari IP mana. */
    private function konteks(array $tambah = []): array
    {
        $a = (array) session('admin');

        return $tambah + [
            'oleh'     => (string) ($a['full_name'] ?? 'Staf'),
            'admin_id' => ((int) ($a['id'] ?? 0)) ?: null,
            'peran'    => (string) ($a['role'] ?? ''),
            'ip'       => $this->request->getIPAddress(),
        ];
    }

    /** Pesan galat ramah dari hasil PklAjuan. */
    private function pesanGagal(array $hasil): string
    {
        switch ($hasil['kode'] ?? 'galat') {
            case 'bentrok':
                $nama = [];
                foreach ($hasil['siswa_ids'] ?? [] as $sid) {
                    $s = db_connect()->table('siswa')->select('nama')->where('id', (int) $sid)->get()->getRowArray();
                    $nama[] = $s['nama'] ?? ('#' . $sid);
                }

                return 'Gagal: ' . implode(', ', $nama) . ' sudah punya ajuan PKL aktif lain. Selesaikan/tolak ajuan itu dulu.';
            case 'status':
            case 'tidak_ada':
                return 'Ajuan sudah berubah (mungkin diubah staf lain). Muat ulang halaman.';
            case 'pengaju_beda':
                return 'Pengaju tidak boleh diganti. Hapus ajuan lalu isi ulang bila pengajunya salah.';
            default:
                return 'Penyimpanan gagal. Coba lagi sebentar.';
        }
    }

    private function ke(string $alamat, string $jenis, string $pesan): RedirectResponse
    {
        return redirect()->to(site_url($alamat))->with($jenis, $pesan);
    }

    /** Alamat form siswa untuk dibagikan (lihat Config\Pkl::tautan). */
    private function tautanSiswa(): string
    {
        return config('Pkl')->tautan();
    }
}
