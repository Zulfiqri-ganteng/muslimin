<?php

namespace App\Controllers\Api;

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Libraries\PklKeputusan;
use App\Libraries\PklPeringatan;
use App\Libraries\PklStaf;
use App\Libraries\PklSurat;
use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Peran;

/**
 * PKL / Prakerin untuk aplikasi Android (staf: Operator, Waka Hubin, Admin) — cermin App\Controllers\Admin\Pkl
 * + PklBerkas. Seluruh aturan ada di library bersama (PklKeputusan, PklStaf, PklForm, PklAjuan, PklSurat), jadi
 * hasil web & aplikasi selalu sama. Form pengajuan SISWA tetap web (tautan subdomain).
 *
 * Gerbang: ApiAuthFilter hanya meloloskan peran ber-akses "pkl" (Config\Peran 'api_akses'); Operator/Hubin tidak
 * bisa menyentuh API lain. Hak per aksi dijaga di sini (HakAkses): ACC, tolak, batalkan persetujuan, ACC massal,
 * "langsung disetujui", ubah ajuan yang sudah disetujui → Waka Hubin / Admin saja; Admin wajib wakil=1 (mewakili).
 *
 * Rute (/api/v1, Bearer):
 *   GET    pkl/meta                          label status/fase, aturan, daftar kelas, hak peran ini
 *   GET    pkl/ringkasan                     form, hitungan, terlambat, persiapan, antrean
 *   GET    pkl/ajuan?status=&q=&kelas_id=&page=&per=
 *   GET    pkl/ajuan/{id}                    detail lengkap + hak + peringatan + riwayat
 *   POST   pkl/ajuan                         isi atas nama
 *   POST   pkl/ajuan/{id}/ubah               ubah langsung
 *   DELETE pkl/ajuan/{id}                    hapus
 *   POST   pkl/ajuan/{id}/acc|kembalikan|tolak|batal-acc   {catatan, paham, perusahaan_id, wakil}
 *   POST   pkl/acc-massal                    {mode: terpilih|aman, ids, wakil}
 *   POST   pkl/ajuan/{id}/surat              {tanggal_surat?} → berkas .docx (unduhan)
 *   POST   pkl/surat-massal                  {mode: terpilih|belum|semua, ids} → .docx / .zip
 *   GET    pkl/siswa?kelas_id=&fase=&q=&page=&per=   Status Siswa
 *   GET    pkl/ttd | GET pkl/ttd/gambar | POST pkl/ttd (berkas "ttd") | DELETE pkl/ttd   tanda tangan Waka Hubin (Hubin/Admin)
 */
class Pkl extends BaseApiController
{
    private const LABEL_STATUS = [
        'menunggu'  => 'Menunggu keputusan Waka Hubin',
        'perbaikan' => 'Perlu perbaikan',
        'disetujui' => 'Disetujui',
        'ditolak'   => 'Ditolak',
    ];

    private const LABEL_FASE = [
        'belum' => 'Belum mengisi', 'ditolak' => 'Ditolak (perlu ajukan ulang)', 'menunggu' => 'Menunggu keputusan Waka Hubin', 'perbaikan' => 'Perlu perbaikan',
        'belum_mulai' => 'Disetujui, belum mulai', 'sedang' => 'Sedang PKL', 'selesai' => 'Selesai PKL', 'disetujui' => 'Disetujui',
    ];

    private const LABEL_AKSI = [
        'kirim' => 'Dikirim siswa', 'kirim_ulang' => 'Dikirim ulang (perbaikan)', 'acc' => 'Disetujui', 'kembalikan' => 'Dikembalikan ke siswa',
        'tolak' => 'Ditolak', 'batal_acc' => 'Persetujuan dibatalkan', 'ubah' => 'Data diubah staf', 'isi_atas_nama' => 'Diisi atas nama siswa',
        'tunda' => 'Dikembalikan ke antrean', 'surat' => 'Surat diterbitkan', 'cetak' => 'Surat diunduh', 'impor' => 'Diimpor dari Excel',
    ];

    private const LABEL_PERAN_ACC = [
        'hubin' => 'Waka Hubin', 'admin' => 'Admin — mewakili Waka Hubin', 'operator' => 'Operator (tanpa wewenang ACC; data lama)', 'impor' => 'Data riwayat (diimpor, bukan ACC sistem)',
    ];

    private const MAKS_SURAT_MASSAL = 300;

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

    // ================================================================= meta & ringkasan

    public function meta(): ResponseInterface
    {
        return $this->ok([
            'status'        => self::LABEL_STATUS,
            'fase_siswa'    => self::LABEL_FASE,
            'aksi_riwayat'  => self::LABEL_AKSI,
            'peran_acc'     => self::LABEL_PERAN_ACC,
            'aturan'        => [
                'maks_siswa'           => PklPengaturanModel::maksSiswa($this->p),
                'batas_keputusan_hari' => PklPengaturanModel::batasHari($this->p),
                'alasan_min_huruf'     => 5,
                'maks_acc_massal'      => PklKeputusan::MAKS_ACC_MASSAL,
                'maks_surat_massal'    => self::MAKS_SURAT_MASSAL,
                'ttd_maks_byte'        => PklSurat::MAKS_TTD,
            ],
            'kelas'         => array_map(static fn (array $k) => ['id' => (int) $k['id'], 'nama' => $k['nama_kelas'], 'tingkat' => $k['tingkat']], $this->model->kelasBersiswa()),
            'hak'           => $this->hakUmum(),
        ], 'Kamus PKL.');
    }

    public function ringkasan(): ResponseInterface
    {
        $tingkat = PklPengaturanModel::tingkatBoleh($this->p);
        $alasan  = PklPengaturanModel::alasanTutup($this->p);
        $hari    = PklPengaturanModel::batasHari($this->p);
        [$antrean] = $this->model->daftar('menunggu', '', 0, 8, 1);
        $hak     = $this->hakUmum();

        $persiapan = [
            ['kunci' => 'tingkat', 'siap' => $alasan !== 'belum_siap', 'judul' => 'Tingkat kelas yang boleh mengajukan dipilih'],
            ['kunci' => 'waka_hubin', 'siap' => trim((string) ($this->p['waka_hubin_nama'] ?? '')) !== '', 'judul' => 'Nama Waka Hubin (penanda tangan surat) diisi'],
            ['kunci' => 'form_dibuka', 'siap' => $alasan === null, 'judul' => 'Form dibuka untuk siswa'],
        ];

        return $this->ok([
            'form' => [
                'terbuka'  => $alasan === null,
                'alasan'   => $alasan,
                'tautan'   => config('Pkl')->tautan(),
                'tingkat'  => $tingkat,
                'tutup_pada' => $this->p['form_tutup'] ?? null,
            ],
            'hitung'     => $this->model->hitungStatus(),
            'terlambat'  => $this->model->hitungTerlambat($hari),
            'batas_hari' => $hari,
            'siswa'      => $this->model->ringkasanSiswa($tingkat),
            'persiapan'  => $hak['pengaturan'] ? $persiapan : [],
            'antrean'    => array_map(fn (array $r) => $this->ringkasBaris($r, $hari), $antrean),
            'surat'      => [
                'waka_hubin_nama' => $this->p['waka_hubin_nama'] ?? null,
                'ttd_ada'         => PklSurat::infoTtd($this->p) !== null,
                'template_unggahan' => PklSurat::pathTemplate($this->p) !== null,
            ],
            'hak' => $hak,
        ], 'Ringkasan PKL.');
    }

    // ================================================================= daftar & detail

    public function daftar(): ResponseInterface
    {
        $status = (string) $this->request->getGet('status');
        if ($status === '') {
            $status = 'menunggu';
        }
        if ($status !== 'semua' && ! in_array($status, PklPengajuanModel::STATUS, true)) {
            return $this->invalid(['status' => 'Status harus salah satu: ' . implode(', ', PklPengajuanModel::STATUS) . ', atau "semua".']);
        }
        $q      = IsianBantu::rapikan((string) $this->request->getGet('q'));
        $kelas  = (int) $this->request->getGet('kelas_id');
        $page   = max(1, (int) $this->request->getGet('page'));
        $per    = max(1, min(100, (int) ($this->request->getGet('per') ?: 20)));
        $hari   = PklPengaturanModel::batasHari($this->p);

        [$rows, $total] = $this->model->daftar($status === 'semua' ? '' : $status, $q, $kelas, $per, $page);
        $suratMap = $status === 'disetujui' || $status === 'semua'
            ? (new PklSurat())->statusBanyak(array_map('intval', array_column(array_filter($rows, static fn ($r) => $r['status'] === 'disetujui'), 'id')))
            : [];
        $item = [];
        foreach ($rows as $r) {
            $x = $this->ringkasBaris($r, $hari);
            if ($r['status'] === 'disetujui') {
                $s = $suratMap[(int) $r['id']] ?? null;
                $x['surat'] = $s === null ? null : ['nomor' => $s['nomor'], 'perlu_cetak_ulang' => (bool) $s['perlu_ulang']];
            }
            if ($r['status'] === 'menunggu') {
                $x['pemeriksaan'] = $this->pemeriksaan((int) $r['id']);
            }
            $item[] = $x;
        }

        return $this->collection($item, ['page' => $page, 'perPage' => $per, 'total' => $total], 'Daftar ajuan PKL.');
    }

    public function detail($id): ResponseInterface
    {
        $id = (int) $id;
        $a  = $this->model->detail($id);
        if ($a === null) {
            return $this->missing('Ajuan tidak ditemukan (mungkin sudah dihapus).');
        }
        $anggota    = $this->model->anggotaDetail($id);
        $peringatan = PklPeringatan::untuk($a, $anggota, $this->p);
        $svcSurat   = new PklSurat();
        $surat      = $svcSurat->surat($id);
        $hari       = PklPengaturanModel::batasHari($this->p);
        $kirim      = (string) ($a['diajukan_at'] ?? $a['created_at']);

        $data = $this->ringkasBaris($a + ['pengaju' => $anggota[0]['nama'] ?? null, 'pengaju_kelas' => $anggota[0]['nama_kelas'] ?? null, 'jumlah' => count($anggota)], $hari);
        $data += [
            'perusahaan' => [
                'nama' => $a['perusahaan_nama'], 'alamat' => $a['perusahaan_alamat'], 'kota' => $a['perusahaan_kota'], 'telepon' => $a['perusahaan_telepon'],
                'kontak_nama' => $a['kontak_nama'], 'kontak_jabatan' => $a['kontak_jabatan'], 'master_id' => $a['perusahaan_id'] !== null ? (int) $a['perusahaan_id'] : null, 'master_nama' => $a['master_nama'] ?? null,
            ],
            'periode_lama'  => ($a['tanggal_mulai'] || $a['tanggal_selesai']) ? ['mulai' => $a['tanggal_mulai'], 'selesai' => $a['tanggal_selesai']] : null,
            'anggota'       => array_map(static fn (array $s) => [
                'siswa_id' => (int) $s['siswa_id'], 'nama' => $s['nama'], 'peran' => $s['peran'], 'kelas' => $s['nama_kelas'], 'jurusan' => $s['jurusan_nama'],
                'nis' => $s['nis'], 'nisn' => $s['nisn'], 'hp' => $s['hp'], 'hp_master' => $s['hp_master'], 'status_siswa' => $s['status_siswa'],
            ], $anggota),
            'peringatan'    => $peringatan,
            'ada_bahaya'    => in_array('bahaya', array_column($peringatan, 'tingkat'), true),
            'master'        => PklPeringatan::kandidatMaster((string) $a['perusahaan_norm']),
            'surat_detail'  => $surat === null ? null : [
                'nomor' => $surat['nomor'], 'tanggal_surat' => $surat['tanggal_surat'], 'diunduh_kali' => (int) $surat['cetak_ke'],
                'perlu_cetak_ulang' => PklSurat::sidik($a, $anggota, $this->p) !== (string) $surat['sidik'],
            ],
            'riwayat'       => array_map(static fn (array $r) => [
                'aksi' => $r['aksi'], 'label' => self::LABEL_AKSI[$r['aksi']] ?? $r['aksi'], 'oleh' => $r['oleh'], 'peran' => $r['peran'], 'catatan' => $r['catatan'], 'waktu' => $r['created_at'],
            ], $this->model->riwayat($id)),
            'hak'           => $this->hakAjuan((string) $a['status']),
            'dikirim_pada'  => $kirim,
        ];

        return $this->ok($data, 'Detail ajuan ' . PklPengajuanModel::kode($id) . '.');
    }

    // ================================================================= keputusan

    public function acc($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'acc');
    }

    public function kembalikan($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'kembalikan');
    }

    public function tolak($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'tolak');
    }

    public function batalAcc($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'batal_acc');
    }

    private function putuskan(int $id, string $aksi): ResponseInterface
    {
        $hasil = (new PklKeputusan())->putuskan($id, $aksi, $this->body(), $this->konteks(), $this->p);
        if (! $hasil['ok']) {
            return $hasil['http'] === 422
                ? $this->invalid([$hasil['kode'] => $hasil['pesan']], $hasil['pesan'])
                : $this->failure($hasil['pesan'], $hasil['http'], ['kode' => $hasil['kode']]);
        }

        return $this->ok(['id' => $id, 'kode' => PklPengajuanModel::kode($id), 'status' => (string) $this->model->find($id)['status']], $hasil['pesan']);
    }

    public function accMassal(): ResponseInterface
    {
        $hasil = (new PklKeputusan())->accMassal($this->body(), $this->konteks(), $this->p);
        if (! $hasil['ok'] && ($hasil['http'] ?? 200) !== 200) {
            $kunci = $hasil['kode'] === 'catatan' ? 'ids' : $hasil['kode'];

            return $hasil['http'] === 422 ? $this->invalid([$kunci => $hasil['pesan']], $hasil['pesan']) : $this->failure($hasil['pesan'], $hasil['http'], ['kode' => $hasil['kode']]);
        }

        return $this->ok(['disetujui' => $hasil['disetujui'] ?? 0, 'dilewati' => $hasil['dilewati'] ?? []], $hasil['pesan']);
    }

    // ================================================================= isi atas nama, ubah, hapus

    public function buat(): ResponseInterface
    {
        $in = $this->body();
        [$data, $galat] = PklForm::proses($in, $this->p, ['pernyataan' => false, 'kontak' => false, 'hp_teman' => false]);
        $pengajuId = (int) ($in['siswa_id'] ?? 0);
        [$anggota, $galatAnggota] = PklStaf::periksaAnggota($pengajuId, $data['teman'], null, $data['teman_hp'] ?? []);
        $galat += $galatAnggota;

        $statusAwal = ($in['status_awal'] ?? '') === 'disetujui' ? 'disetujui' : 'menunggu';
        if ($statusAwal === 'disetujui' && ! HakAkses::bolehAcc($this->peran())) {
            return $this->failure('Menyimpan ajuan langsung berstatus DISETUJUI hanya boleh dilakukan Waka Hubin (atau Admin). Simpan sebagai menunggu, lalu minta Waka Hubin meng-ACC.', 403);
        }
        if ($galat !== []) {
            return $this->invalid($galat);
        }

        $hasil = (new PklAjuan())->kirimBaru($data, $anggota, $this->konteks([
            'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => $statusAwal,
            'tahun_ajaran' => (new SettingModel())->get()['academic_year'] ?? null,
        ]));
        if (! $hasil['ok']) {
            return $this->failure((new PklKeputusan())->pesanGagal($hasil), ($hasil['kode'] ?? '') === 'bentrok' ? 409 : 500);
        }
        $kode = PklPengajuanModel::kode((int) $hasil['id']);
        $this->audit->record('create', 'pkl_pengajuan', (int) $hasil['id'], 'PKL ' . $kode . ' diisi atas nama siswa (' . $statusAwal . ') — ' . $data['perusahaan_nama'] . ' (via aplikasi)');

        return $this->created(['id' => (int) $hasil['id'], 'kode' => $kode, 'status' => $statusAwal], 'Ajuan ' . $kode . ' dibuat atas nama siswa.');
    }

    public function ubah($id): ResponseInterface
    {
        $id = (int) $id;
        $a  = $this->model->detail($id);
        if ($a === null) {
            return $this->missing('Ajuan tidak ditemukan.');
        }
        if ($a['status'] === 'disetujui' && ! HakAkses::bolehAcc($this->peran())) {
            return $this->failure('Ajuan yang sudah DISETUJUI hanya boleh diubah Waka Hubin (atau Admin), karena persetujuannya berlaku untuk isi yang sekarang.', 403);
        }
        $lama = $this->model->anggotaDetail($id);
        $in   = $this->body();
        [$data, $galat] = PklForm::proses($in, $this->p, ['pernyataan' => false, 'kontak' => false, 'hp_teman' => false]);

        $pengajuLama = 0;
        foreach ($lama as $s) {
            if ($s['peran'] === 'pengaju') {
                $pengajuLama = (int) $s['siswa_id'];
            }
        }
        [$anggota, $galatAnggota] = PklStaf::periksaAnggota($pengajuLama, $data['teman'], $id, $data['teman_hp'] ?? []);
        $galat += $galatAnggota;
        if ($galat !== []) {
            return $this->invalid($galat);
        }

        $hasil = (new PklAjuan())->ubahIsi($id, $data, $anggota, $this->konteks(['aksi' => 'ubah', 'catatan' => null]));
        if (! $hasil['ok']) {
            return $this->failure((new PklKeputusan())->pesanGagal($hasil), ($hasil['kode'] ?? '') === 'bentrok' ? 409 : 500);
        }
        $kode = PklPengajuanModel::kode($id);
        $this->audit->record('update', 'pkl_pengajuan', $id, 'PKL ' . $kode . ' diubah langsung oleh staf — ' . $data['perusahaan_nama'] . ' (via aplikasi)');

        return $this->ok(['id' => $id, 'kode' => $kode], 'Data ajuan ' . $kode . ' diperbarui.');
    }

    public function hapus($id): ResponseInterface
    {
        $id = (int) $id;
        $a  = $this->model->detail($id);
        if ($a === null) {
            return $this->missing('Ajuan tidak ditemukan (mungkin sudah dihapus).');
        }
        $peran = $this->peran();
        if (! HakAkses::boleh($peran, 'admin/pkl/hapus')) {
            return $this->forbidden('Akun ' . HakAkses::label($peran) . ' tidak boleh menghapus ajuan.');
        }
        if ($a['status'] === 'disetujui' && $peran !== Peran::ADMIN) {
            $this->audit->record('delete', 'pkl_pengajuan', $id, 'DITOLAK: ' . HakAkses::label($peran) . ' mencoba menghapus ajuan yang sudah disetujui, PKL ' . PklPengajuanModel::kode($id) . ' (via aplikasi)');

            return $this->forbidden('Ajuan yang sudah DISETUJUI Waka Hubin hanya boleh dihapus Admin. Minta Waka Hubin membatalkan persetujuannya, atau hubungi Admin.');
        }
        // Konfirmasi boleh lewat body JSON {"paham": true} atau alamat ?paham=1 (DELETE di sebagian klien tak membawa body).
        $paham = $this->body()['paham'] ?? $this->request->getGet('paham');
        if ($a['status'] === 'disetujui' && ! in_array($paham, [true, 1, '1', 'true'], true)) {
            return $this->invalid(['paham' => 'Ajuan ini SUDAH DISETUJUI. Kirim "paham": true (atau ?paham=1) bila benar-benar ingin menghapusnya.']);
        }
        $nama = array_map(static fn (array $s) => $s['nama'], $this->model->anggotaDetail($id));
        $this->model->delete($id); // CASCADE: anggota & riwayat ikut terhapus, siswanya bebas lagi
        $kode = PklPengajuanModel::kode($id);
        $this->audit->record('delete', 'pkl_pengajuan', $id, 'PKL ' . $kode . ' dihapus (' . $a['status'] . ') — ' . $a['perusahaan_nama'] . ' — ' . implode(', ', $nama) . ' (via aplikasi)');

        return $this->ok(['id' => $id], 'Ajuan ' . $kode . ' dihapus. Siswanya bisa mengajukan lagi.');
    }

    // ================================================================= surat

    public function surat($id)
    {
        $id = (int) $id;
        $a  = $this->model->find($id);
        if ($a === null) {
            return $this->missing('Ajuan tidak ditemukan.');
        }
        if ($a['status'] !== 'disetujui') {
            return $this->failure('Surat hanya bisa dibuat untuk ajuan yang sudah DISETUJUI.', 409);
        }
        $tanggal = trim((string) ($this->body()['tanggal_surat'] ?? ''));
        if ($tanggal === '') {
            $tanggal = date('Y-m-d');
        }
        $tgl = \DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
        if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal || (int) $tgl->format('Y') < (int) date('Y') - 1 || (int) $tgl->format('Y') > (int) date('Y') + 2) {
            return $this->invalid(['tanggal_surat' => 'Tanggal surat tidak valid (format YYYY-MM-DD).']);
        }

        return $this->unduh([$id], $tanggal);
    }

    public function suratMassal()
    {
        $in    = $this->body();
        $pilih = (new PklSurat())->pilihUntukUnduh((string) ($in['mode'] ?? ''), (array) ($in['ids'] ?? []), self::MAKS_SURAT_MASSAL);
        if ($pilih['ok'] === false) {
            return $this->invalid(['ids' => $pilih['pesan']], $pilih['pesan']);
        }
        if ($pilih['ids'] === []) {
            return $this->ok(['jumlah' => 0], $pilih['pesan']);
        }

        return $this->unduh($pilih['ids'], date('Y-m-d'));
    }

    /** @param list<int> $ids */
    private function unduh(array $ids, string $tanggal)
    {
        $hasil = (new PklSurat())->bangun($ids, $this->konteks(), $tanggal);
        if (! $hasil['ok']) {
            return $this->failure($hasil['pesan'] ?? 'Surat gagal dibuat.', 422);
        }
        $this->audit->record('update', 'pkl_surat', $ids[0] ?? null, 'Surat PKL diunduh: ' . $hasil['jumlah'] . ' surat (' . $hasil['nama'] . ') (via aplikasi)');

        return $this->response
            ->download($hasil['nama'], $hasil['biner'])
            ->setFileName($hasil['nama'])
            ->setHeader('X-Jumlah-Surat', (string) $hasil['jumlah']);
    }

    // ================================================================= status siswa

    public function siswa(): ResponseInterface
    {
        $tingkat = PklPengaturanModel::tingkatBoleh($this->p);
        $q       = IsianBantu::rapikan((string) $this->request->getGet('q'));
        $kelas   = (int) $this->request->getGet('kelas_id');
        $fase    = (string) $this->request->getGet('fase');
        $page    = max(1, (int) $this->request->getGet('page'));
        $per     = max(1, min(100, (int) ($this->request->getGet('per') ?: 50)));
        $sah     = ['', 'belum_mengisi', 'sudah_mengisi', 'sudah_pkl', 'belum', 'ditolak', 'menunggu', 'perbaikan', 'belum_mulai', 'sedang', 'selesai', 'disetujui'];
        if (! in_array($fase, $sah, true)) {
            return $this->invalid(['fase' => 'Saringan fase tidak dikenal.']);
        }

        [$rows, $total] = $this->model->statusSiswa($tingkat, $kelas, $q, $fase, $per, $page);
        $item = array_map(static fn (array $r) => [
            'siswa_id' => (int) $r['id'], 'nama' => $r['nama'], 'kelas' => $r['nama_kelas'], 'tingkat' => $r['tingkat'],
            'fase' => $r['fase'], 'fase_label' => self::LABEL_FASE[$r['fase']] ?? $r['fase'],
            'ajuan_id' => $r['ajuan_id'] !== null ? (int) $r['ajuan_id'] : null, 'kode' => $r['ajuan_id'] !== null ? PklPengajuanModel::kode((int) $r['ajuan_id']) : null,
            'perusahaan' => $r['perusahaan_nama'], 'kota' => $r['perusahaan_kota'], 'nomor_surat' => $r['nomor_surat'],
            'acc_nama' => $r['acc_nama'], 'acc_waktu' => $r['acc_at'],
        ], $rows);

        return $this->collection($item, ['page' => $page, 'perPage' => $per, 'total' => $total], 'Status PKL siswa.');
    }

    /**
     * GET pkl/siswa-kelas?kelas_id= — siswa aktif satu kelas + status PKL-nya, untuk pemilih siswa di form isi atas nama.
     * Semua tingkat (staf boleh mengisi riwayat tingkat lain). Hanya status, tanpa data perusahaan siswa lain.
     * `status`: belum | ditolak (boleh dipilih) · menunggu | perbaikan | disetujui (sudah terkunci di ajuan lain).
     */
    public function siswaKelas(): ResponseInterface
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId < 1) {
            return $this->invalid(['kelas_id' => 'Pilih kelas dulu.']);
        }
        $data = array_map(static fn (array $r) => [
            'id'     => (int) $r['id'],
            'nama'   => $r['nama'],
            'status' => $r['aktif'] ?? ((int) $r['pernah_ditolak'] === 1 ? 'ditolak' : 'belum'),
        ], $this->model->daftarSiswaKelas($kelasId));

        return $this->ok($data, 'Siswa kelas ini.');
    }

    public function siswaRingkas(): ResponseInterface
    {
        $kelas = (int) $this->request->getGet('kelas_id');

        return $this->ok($this->model->ringkasanSiswa(PklPengaturanModel::tingkatBoleh($this->p), $kelas), 'Ringkasan status siswa.');
    }

    // ================================================================= tanda tangan Waka Hubin

    public function ttd(): ResponseInterface
    {
        if (($tolak = $this->tolakTtd()) !== null) {
            return $tolak;
        }
        $info = PklSurat::infoTtd($this->p);

        return $this->ok([
            'ada'        => $info !== null,
            'jenis'      => $info['ext'] ?? null,
            'versi'      => $info !== null ? (int) @filemtime($info['path']) : null,
            'atas_nama'  => $this->p['waka_hubin_nama'] ?? null,
            'jabatan'    => $this->p['waka_hubin_jabatan'] ?? null,
            'gambar_url' => $info !== null ? site_url('api/v1/pkl/ttd/gambar') : null,
        ], 'Tanda tangan Waka Hubin.');
    }

    public function ttdGambar()
    {
        if (($tolak = $this->tolakTtd()) !== null) {
            return $tolak;
        }
        $info = PklSurat::infoTtd($this->p);
        if ($info === null) {
            return $this->missing('Belum ada tanda tangan.');
        }

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $info['ext'] === 'png' ? 'image/png' : 'image/jpeg')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($info['path']));
    }

    public function ttdUnggah(): ResponseInterface
    {
        if (($tolak = $this->tolakTtd()) !== null) {
            return $tolak;
        }
        $berkas = $this->request->getFile('ttd');
        if ($berkas === null || ! $berkas->isValid() || $berkas->hasMoved()) {
            return $this->invalid(['ttd' => 'Pilih gambar tanda tangan (PNG atau JPG) dulu.']);
        }
        $hasil = PklSurat::simpanTtd($berkas->getTempName(), (int) $berkas->getSize());
        if (! $hasil['ok']) {
            return $this->invalid(['ttd' => $hasil['pesan']], $hasil['pesan']);
        }
        $a = (array) $this->admin();
        $this->audit->record('update', 'pkl_pengaturan', 1, 'Tanda tangan Waka Hubin diunggah oleh ' . ($a['full_name'] ?? '?') . ' (' . ($a['role'] ?? '?') . ') (via aplikasi)');

        return $this->ok(null, $hasil['pesan']);
    }

    public function ttdHapus(): ResponseInterface
    {
        if (($tolak = $this->tolakTtd()) !== null) {
            return $tolak;
        }
        PklSurat::hapusTtd();
        $a = (array) $this->admin();
        $this->audit->record('update', 'pkl_pengaturan', 1, 'Tanda tangan Waka Hubin dihapus oleh ' . ($a['full_name'] ?? '?') . ' (via aplikasi)');

        return $this->ok(null, 'Tanda tangan dihapus. Surat kembali memberi ruang kosong untuk tanda tangan basah.');
    }

    private function tolakTtd(): ?ResponseInterface
    {
        return HakAkses::boleh($this->peran(), 'admin/pkl/ttd')
            ? null
            : $this->forbidden('Tanda tangan Waka Hubin hanya dikelola Waka Hubin atau Admin.');
    }

    // ================================================================= pembantu

    private function peran(): string
    {
        return (string) ($this->admin()['role'] ?? '');
    }

    /** Konteks pencatat riwayat: siapa, perannya, dari IP mana, lewat aplikasi. */
    private function konteks(array $tambah = []): array
    {
        $a = (array) $this->admin();

        return $tambah + [
            'oleh'     => (string) ($a['full_name'] ?? 'Staf'),
            'admin_id' => ((int) ($a['id'] ?? 0)) ?: null,
            'peran'    => (string) ($a['role'] ?? ''),
            'ip'       => $this->request->getIPAddress(),
            'saluran'  => 'aplikasi',
        ];
    }

    /** Hak peran ini (tidak bergantung pada satu ajuan). */
    private function hakUmum(): array
    {
        $peran = $this->peran();

        return [
            'peran'           => $peran,
            'peran_label'     => HakAkses::label($peran),
            'acc'             => HakAkses::bolehAcc($peran),
            'wajib_wakil'     => $peran === Peran::ADMIN,
            'pengaturan'      => HakAkses::boleh($peran, 'admin/pkl/pengaturan'),
            'hapus'           => HakAkses::boleh($peran, 'admin/pkl/hapus'),
            'tanda_tangan'    => HakAkses::boleh($peran, 'admin/pkl/ttd'),
        ];
    }

    /** Hak peran ini atas satu ajuan berstatus $status. */
    private function hakAjuan(string $status): array
    {
        $peran = $this->peran();
        $acc   = HakAkses::bolehAcc($peran);

        return [
            'acc'         => $acc && in_array($status, ['menunggu', 'perbaikan', 'ditolak'], true),
            'kembalikan'  => $status === 'menunggu',
            'tolak'       => $acc && in_array($status, ['menunggu', 'perbaikan'], true),
            'batal_acc'   => $acc && $status === 'disetujui',
            'ubah'        => $status !== 'disetujui' || $acc,
            'hapus'       => HakAkses::boleh($peran, 'admin/pkl/hapus') && ($status !== 'disetujui' || $peran === Peran::ADMIN),
            'surat'       => $status === 'disetujui',
            'wajib_wakil' => $peran === Peran::ADMIN,
        ];
    }

    /** Ringkasan peringatan bahaya/periksa satu ajuan menunggu (untuk badge "Aman"/"⚠"). */
    private function pemeriksaan(int $id): array
    {
        $a = $this->model->detail($id);
        $berat = $a === null ? [] : array_values(array_filter(
            PklPeringatan::untuk($a, $this->model->anggotaDetail($id), $this->p),
            static fn (array $w) => in_array($w['tingkat'], ['bahaya', 'awas'], true)
        ));

        return [
            'aman'    => $berat === [],
            'bahaya'  => count(array_filter($berat, static fn ($w) => $w['tingkat'] === 'bahaya')),
            'awas'    => count(array_filter($berat, static fn ($w) => $w['tingkat'] === 'awas')),
            'pertama' => $berat[0]['teks'] ?? null,
        ];
    }

    /** Bentuk baris daftar (juga dasar detail). */
    private function ringkasBaris(array $r, int $hari): array
    {
        $kirim = (string) ($r['diajukan_at'] ?? $r['created_at']);
        $menunggu = $r['status'] === 'menunggu';
        $sisa  = $menunggu ? PklPengajuanModel::sisaHari($kirim, $hari) : null;
        $peranAcc = (string) ($r['acc_peran'] ?? '');

        return [
            'id'              => (int) $r['id'],
            'kode'            => PklPengajuanModel::kode((int) $r['id']),
            'status'          => $r['status'],
            'status_label'    => self::LABEL_STATUS[$r['status']] ?? $r['status'],
            'sumber'          => $r['sumber'],
            'perusahaan_nama' => $r['perusahaan_nama'],
            'perusahaan_kota' => $r['perusahaan_kota'] ?? null,
            'pengaju'         => $r['pengaju'] ?? null,
            'pengaju_kelas'   => $r['pengaju_kelas'] ?? null,
            'jumlah_siswa'    => isset($r['jumlah']) ? (int) $r['jumlah'] : null,
            'dikirim_pada'    => $kirim,
            'diperbarui_pada' => $r['updated_at'] ?? null,
            'revisi_ke'       => max(0, (int) ($r['kirim_ke'] ?? 1) - 1),
            'batas_keputusan' => $menunggu ? substr((string) PklPengajuanModel::batasKeputusan($kirim, $hari), 0, 10) : null,
            'sisa_hari'       => $sisa,
            'terlambat'       => $sisa !== null && $sisa < 0,
            'catatan_staf'    => in_array($r['status'], ['perbaikan', 'ditolak'], true) ? ($r['catatan_staf'] ?? null) : null,
            'acc'             => $r['status'] === 'disetujui' && ! empty($r['acc_at']) ? [
                'nama'  => $r['acc_nama'] ?? null, 'peran' => $peranAcc !== '' ? $peranAcc : null, 'peran_label' => self::LABEL_PERAN_ACC[$peranAcc] ?? null,
                'waktu' => $r['acc_at'], 'kode_verifikasi' => $r['acc_kode'] ?? null,
            ] : null,
        ];
    }
}
