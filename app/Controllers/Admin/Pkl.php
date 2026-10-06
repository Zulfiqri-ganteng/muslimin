<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Libraries\PklNomorSurat;
use App\Libraries\PklPeringatan;
use App\Libraries\PklSurat;
use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PKL / Prakerin — sisi staf (Operator Sekolah, Waka Hubin, Admin). Rancangan: docs/DESAIN-PKL.md.
 *
 * Operator dan Hubin sama-sama boleh memeriksa, ACC, mengembalikan, menolak, mengubah, dan
 * mengisi atas nama. Hanya Operator/Admin yang boleh Pengaturan dan Hapus (pembatas ada di
 * Config\Peran → 'kecuali', dibaca penjaga rute, jadi tombolnya pun disembunyikan untuk Hubin).
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

        return view('admin/pkl/index', $this->dasar('Beranda PKL', 'beranda') + [
            'hitung'  => $this->model->hitungStatus(),
            'siswa'   => $this->model->ringkasanSiswa($tingkat),
            'antrean' => $antrean,
            'alasan'  => PklPengaturanModel::alasanTutup($this->p),
            'tautan'  => $this->tautanSiswa(),
            'tingkat' => $tingkat,
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

        return view('admin/pkl/detail', $this->dasar(PklPengajuanModel::kode((int) $id), 'daftar_' . $ajuan['status']) + [
            'a'          => $ajuan,
            'anggota'    => $anggota,
            'riwayat'    => $this->model->riwayat((int) $id),
            'peringatan' => $peringatan,
            'adaBahaya'  => in_array('bahaya', array_column($peringatan, 'tingkat'), true),
            'master'     => PklPeringatan::kandidatMaster((string) $ajuan['perusahaan_norm']),
            'kode'       => PklPengajuanModel::kode((int) $id),
            'surat'      => $surat,
            'perluUlang' => $surat !== null && PklSurat::sidik($ajuan, $anggota) !== (string) $surat['sidik'],
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

    /** Satu pintu untuk semua keputusan; aturan tiap aksi ada di $aturan. */
    private function putuskan(int $id, string $aksi): RedirectResponse
    {
        $ajuan = $this->model->find($id);
        $balik = 'admin/pkl/' . $id;
        if ($ajuan === null) {
            return $this->ke('admin/pkl/daftar/menunggu', 'error', 'Ajuan tidak ditemukan (mungkin sudah dihapus).');
        }

        // aksi => [status baru, status asal yang boleh, catatan wajib?, kata kerja]
        $aturan = [
            'acc'       => ['disetujui', ['menunggu', 'perbaikan', 'ditolak'], false, 'disetujui'],
            'kembalikan' => ['perbaikan', ['menunggu'], true, 'dikembalikan untuk diperbaiki'],
            'tolak'     => ['ditolak', ['menunggu', 'perbaikan'], true, 'ditolak'],
            'batal_acc' => ['perbaikan', ['disetujui'], true, 'persetujuannya dibatalkan (dikembalikan untuk diperbaiki)'],
        ][$aksi];
        [$baru, $asal, $wajibCatatan, $kata] = $aturan;

        if (! in_array($ajuan['status'], $asal, true)) {
            return $this->ke($balik, 'error', 'Aksi ini tidak bisa dilakukan: status ajuan sudah "' . $ajuan['status'] . '". Muat ulang halaman.');
        }

        $catatan = IsianBantu::rapikan((string) $this->request->getPost('catatan'));
        if ($wajibCatatan && mb_strlen($catatan) < 5) {
            return $this->ke($balik, 'error', 'Alasan wajib diisi (minimal 5 huruf) — siswa akan membacanya.');
        }
        if (mb_strlen($catatan) > 255) {
            return $this->ke($balik, 'error', 'Alasan terlalu panjang (maksimal 255 huruf).');
        }

        $opsi = [];
        if ($aksi === 'acc') {
            $anggota = $this->model->anggotaDetail($id);
            $detail  = $this->model->detail($id);
            $bahaya  = in_array('bahaya', array_column(PklPeringatan::untuk($detail, $anggota, $this->p), 'tingkat'), true);
            if ($bahaya && $this->request->getPost('paham') !== '1') {
                return $this->ke($balik, 'error', 'Ada peringatan BAHAYA pada ajuan ini. Bereskan dulu, atau centang "sudah saya periksa" bila Anda yakin.');
            }
            $pilih = (int) $this->request->getPost('perusahaan_id');
            if ($pilih > 0) {
                $opsi['perusahaan_id'] = $pilih;
            }
        }

        $hasil = (new PklAjuan())->ubahStatus($id, $baru, $this->konteks(['aksi' => $aksi]), $catatan !== '' ? $catatan : null, $opsi);
        if (! $hasil['ok']) {
            return $this->ke($balik, 'error', $this->pesanGagal($hasil));
        }

        $kode = PklPengajuanModel::kode($id);
        $this->audit->record('update', 'pkl_pengajuan', $id, 'PKL ' . $kode . ' ' . $kata . ' — ' . $ajuan['perusahaan_nama'] . ($catatan !== '' ? ' (' . mb_substr($catatan, 0, 80) . ')' : ''));

        return $this->ke($balik, 'success', 'Ajuan ' . $kode . ' ' . $kata . '.');
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
        [$anggota, $galatAnggota] = $this->periksaAnggota($pengajuId, $data['teman'], null);
        $galat += $galatAnggota;
        $statusAwal = ($post['status_awal'] ?? '') === 'disetujui' ? 'disetujui' : 'menunggu';

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

        return $this->tampilForm($ajuan, [], [], $this->model->anggotaDetail((int) $id));
    }

    public function simpanUbah($id)
    {
        $id    = (int) $id;
        $ajuan = $this->model->detail($id);
        if ($ajuan === null) {
            return $this->ke('admin/pkl/daftar/menunggu', 'error', 'Ajuan tidak ditemukan.');
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
        [$anggota, $galatAnggota] = $this->periksaAnggota($pengajuLama, $data['teman'], $id);
        $galat += $galatAnggota;
        if ($galat !== []) {
            return $this->tampilForm($ajuan, $post, $galat, $lama);
        }

        $catatan = ($post['luar_batas'] ?? '') === '1' ? 'tanggal di luar pagar sekolah (dikonfirmasi)' : null;
        $hasil   = (new PklAjuan())->ubahIsi($id, $data, $anggota, $this->konteks(['aksi' => 'ubah', 'catatan' => $catatan]));
        if (! $hasil['ok']) {
            return $this->tampilForm($ajuan, $post, ['umum' => $this->pesanGagal($hasil)], $lama);
        }

        $kode = PklPengajuanModel::kode($id);
        $this->audit->record('update', 'pkl_pengajuan', $id, 'PKL ' . $kode . ' diubah langsung oleh staf — ' . $data['perusahaan_nama'] . ($catatan ? ' [' . $catatan . ']' : ''));

        return $this->ke('admin/pkl/' . $id, 'success', 'Data ajuan ' . $kode . ' diperbarui.');
    }

    /** Opsi PklForm untuk staf: pernyataan tak perlu, HP/tgl lahir opsional, pagar tanggal bisa dilewati dengan konfirmasi. */
    private function opsiForm(array $post): array
    {
        return ['pernyataan' => false, 'kontak' => false, 'batas' => ($post['luar_batas'] ?? '') !== '1'];
    }

    /**
     * Pengaju + teman: aktif, belum terkunci di ajuan lain (selain ajuan ini sendiri). Staf boleh
     * memilih siswa dari tingkat mana pun (riwayat lama), jadi tingkat TIDAK dibatasi di sini.
     *
     * @param list<int> $temanIds
     *
     * @return array{0: list<array{siswa_id:int, kelas_id:?int, peran:string}>, 1: array<string, string>}
     */
    private function periksaAnggota(int $pengajuId, array $temanIds, ?int $ajuanSendiri): array
    {
        $info  = $this->model->siswaUntukDipilih(array_merge([$pengajuId], $temanIds));
        $galat = [];
        $baris = [];

        $pg = $info[$pengajuId] ?? null;
        if ($pg === null || $pg['status'] !== 'aktif') {
            $galat['siswa_id'] = 'Pilih siswa pengaju yang masih aktif.';
        } elseif ($pg['aktif_di'] !== null && (int) $pg['aktif_di'] !== (int) $ajuanSendiri) {
            $galat['siswa_id'] = $pg['nama'] . ' sudah punya ajuan PKL aktif (' . PklPengajuanModel::kode((int) $pg['aktif_di']) . ').';
        } else {
            $baris[] = ['siswa_id' => $pengajuId, 'kelas_id' => (int) $pg['kelas_id'] ?: null, 'peran' => 'pengaju'];
        }

        $masalah = [];
        foreach ($temanIds as $tid) {
            $s = $info[$tid] ?? null;
            if ($tid === $pengajuId) {
                $masalah[] = 'pengaju tak perlu dipilih sebagai teman';
            } elseif ($s === null || $s['status'] !== 'aktif') {
                $masalah[] = 'ada siswa yang tidak ditemukan/tidak aktif';
            } elseif ($s['aktif_di'] !== null && (int) $s['aktif_di'] !== (int) $ajuanSendiri) {
                $masalah[] = $s['nama'] . ' (sudah punya ajuan ' . PklPengajuanModel::kode((int) $s['aktif_di']) . ')';
            } else {
                $baris[] = ['siswa_id' => $tid, 'kelas_id' => (int) $s['kelas_id'] ?: null, 'peran' => 'teman'];
            }
        }
        if ($masalah !== []) {
            $galat['teman'] = 'Teman berikut tidak bisa ditambahkan: ' . implode('; ', $masalah) . '.';
        }

        return [$baris, $galat];
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
                    $orang['teman'][] = $nama[(int) $tid];
                }
            }
        } else {
            foreach ($anggota as $s) {
                $baris = ['id' => (int) $s['siswa_id'], 'nama' => $s['nama'], 'kelas' => (string) ($s['nama_kelas'] ?? '')];
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

    private const MAKS_ACC_MASSAL = 200;

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
     * POST admin/pkl/acc-massal — mode "terpilih" (ids[]) atau "aman" (semua yang menunggu).
     * Hanya ajuan yang BERSIH dari peringatan bahaya/periksa yang di-ACC; sisanya dilewati dan
     * dilaporkan dengan alasannya agar diperiksa satu per satu. Tiap ajuan diproses dalam
     * transaksi sendiri (PklAjuan::ubahStatus), jadi satu yang gagal tak membatalkan yang lain.
     */
    public function accMassal(): RedirectResponse
    {
        $balik = 'admin/pkl/daftar/menunggu';
        if ((string) $this->request->getPost('mode') === 'terpilih') {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->getPost('ids')))));
            if ($ids === []) {
                return $this->ke($balik, 'error', 'Centang dulu ajuan yang mau di-ACC.');
            }
        } else {
            $ids = null; // semua yang menunggu
        }

        $q = db_connect()->table('pkl_pengajuan')->select('id')->where('status', 'menunggu')->orderBy('updated_at', 'ASC')->orderBy('id', 'ASC');
        if ($ids !== null) {
            $q->whereIn('id', $ids);
        }
        $urut = array_map('intval', array_column($q->limit(self::MAKS_ACC_MASSAL + 1)->get()->getResultArray(), 'id'));
        if ($urut === []) {
            return $this->ke($balik, 'success', 'Tidak ada ajuan yang menunggu.');
        }
        if (count($urut) > self::MAKS_ACC_MASSAL) {
            return $this->ke($balik, 'error', 'Terlalu banyak sekaligus (maksimal ' . self::MAKS_ACC_MASSAL . ' ajuan per klik). Pakai pencarian/kelas atau centang sebagian.');
        }

        $svc    = new PklAjuan();
        $ok     = 0;
        $lewat  = [];
        foreach ($urut as $id) {
            $ajuan = $this->model->detail($id);
            if ($ajuan === null || $ajuan['status'] !== 'menunggu') {
                continue;
            }
            $kode  = PklPengajuanModel::kode($id) . ' ' . $ajuan['perusahaan_nama'];
            $berat = array_values(array_filter(
                PklPeringatan::untuk($ajuan, $this->model->anggotaDetail($id), $this->p),
                static fn (array $w) => in_array($w['tingkat'], ['bahaya', 'awas'], true)
            ));
            if ($berat !== []) {
                $lewat[] = $kode . ' — dilewati: ' . mb_substr($berat[0]['teks'], 0, 110) . (count($berat) > 1 ? ' (+' . (count($berat) - 1) . ' peringatan lain)' : '');
                continue;
            }

            $hasil = $svc->ubahStatus($id, 'disetujui', $this->konteks(['aksi' => 'acc']), 'ACC massal');
            if ($hasil['ok']) {
                $ok++;
            } else {
                $lewat[] = $kode . ' — gagal: ' . $this->pesanGagal($hasil);
            }
        }

        $this->audit->record('update', 'pkl_pengajuan', null, 'ACC massal PKL: ' . $ok . ' disetujui, ' . count($lewat) . ' dilewati');
        $pesan = 'ACC massal: ' . $ok . ' ajuan disetujui' . ($lewat !== [] ? ', ' . count($lewat) . ' dilewati (perlu diperiksa satu per satu, lihat daftar merah di bawah).' : '.');
        $redir = $this->ke($balik, $ok > 0 || $lewat === [] ? 'success' : 'error', $pesan);

        return $lewat !== [] ? $redir->with('errors', array_slice($lewat, 0, 40)) : $redir;
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
        ]);
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
            ]);
        }

        $this->pengModel->update(1, $data);
        $this->audit->record('update', 'pkl_pengaturan', 1, 'Pengaturan PKL: form ' . ($data['form_buka'] ? 'DIBUKA' : 'ditutup')
            . ', tingkat ' . $data['tingkat'] . ', ' . ($data['mulai_paling_awal'] ?? '-') . ' s/d ' . ($data['selesai_paling_akhir'] ?? '-')
            . ', lama ' . $data['durasi_min_hari'] . '–' . $data['durasi_maks_hari'] . ' hari, maks ' . $data['maks_anggota'] . ' siswa');

        return $this->ke('admin/pkl/pengaturan', 'success', $data['form_buka'] ? 'Pengaturan disimpan. Form siswa sekarang TERBUKA.' : 'Pengaturan disimpan. Form siswa tertutup.');
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

        $tgl = static fn (string $k): ?string => IsianBantu::tanggal(trim((string) ($post[$k] ?? '')), (int) date('Y') - 1, (int) date('Y') + 3);
        $awal  = $tgl('mulai_paling_awal');
        $akhir = $tgl('selesai_paling_akhir');
        foreach (['mulai_paling_awal' => 'awal', 'selesai_paling_akhir' => 'akhir'] as $k => $nama) {
            $mentah = trim((string) ($post[$k] ?? ''));
            if ($mentah !== '' && $$nama === null) {
                $galat[$k] = 'Tanggal tidak valid atau tahunnya di luar jangkauan.';
            } elseif ($mentah === '' && $buka === 1) {
                $galat[$k] = 'Wajib diisi sebelum form dibuka — ini pagar penjaga salah ketik tanggal siswa.';
            }
        }
        if ($awal !== null && $akhir !== null && $awal >= $akhir) {
            $galat['selesai_paling_akhir'] = 'Tanggal paling akhir harus setelah tanggal paling awal.';
        }
        if ($buka === 1 && $akhir !== null && $akhir < date('Y-m-d')) {
            $galat['selesai_paling_akhir'] = 'Tanggal paling akhir sudah lewat — form tak akan bisa dipakai.';
        }

        $angka = static fn (string $k, int $min, int $maks): ?int => (isset($post[$k]) && ctype_digit(trim((string) $post[$k])) && (int) $post[$k] >= $min && (int) $post[$k] <= $maks) ? (int) $post[$k] : null;
        $dMin  = $angka('durasi_min_hari', 1, 365);
        $dMaks = $angka('durasi_maks_hari', 1, 730);
        $maksA = $angka('maks_anggota', 1, 20);
        if ($dMin === null) {
            $galat['durasi_min_hari'] = 'Isi angka 1–365.';
        }
        if ($dMaks === null) {
            $galat['durasi_maks_hari'] = 'Isi angka 1–730.';
        }
        if ($maksA === null) {
            $galat['maks_anggota'] = 'Isi angka 1–20.';
        }
        if ($dMin !== null && $dMaks !== null && $dMin > $dMaks) {
            $galat['durasi_maks_hari'] = 'Lama maksimal tidak boleh lebih kecil dari lama minimal.';
        }
        if ($dMin !== null && $awal !== null && $akhir !== null && ! isset($galat['selesai_paling_akhir'])) {
            $rentang = IsianBantu::hariInklusif($awal, $akhir);
            if ($rentang < $dMin) {
                $galat['selesai_paling_akhir'] = 'Rentang tanggal hanya ' . $rentang . ' hari, lebih pendek dari lama PKL minimal (' . $dMin . ' hari) — tak ada siswa yang bisa lolos.';
            }
        }

        // ----- Surat: penanda tangan, format & lantai nomor -----
        $wakaNama = IsianBantu::rapikan((string) ($post['waka_hubin_nama'] ?? ''));
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

        return [[
            'form_buka'            => $buka,
            'form_tutup'           => $tutupSql,
            'tingkat'              => implode(',', $tingkat),
            'mulai_paling_awal'    => $awal,
            'selesai_paling_akhir' => $akhir,
            'durasi_min_hari'      => $dMin ?? 30,
            'durasi_maks_hari'     => $dMaks ?? 270,
            'maks_anggota'         => $maksA ?? 5,
            'waka_hubin_nama'      => $wakaNama !== '' ? $wakaNama : null,
            'waka_hubin_nip'       => $wakaNip !== '' ? $wakaNip : null,
            'waka_hubin_jabatan'   => $jabatan,
            'format_nomor'         => $pola,
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
            'hitungTab'      => $this->model->hitungStatus(),
        ];
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
