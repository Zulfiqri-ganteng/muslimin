<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UjianReport;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\JurusanModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\SiswaModel;
use App\Models\UjianJadwalModel;
use App\Models\UjianPengawasModel;
use App\Models\UjianPeriodeModel;
use App\Models\UjianSusulanModel;

/**
 * Menu Ujian — satu controller melayani keempat jenis asesmen sumatif
 * (ASTS 1, ASAS, ASTS 2, ASAT) lewat slug di URL: `admin/ujian/asts1`.
 *
 * Tiap halaman punya tab: Periode | Jadwal | Ketidakhadiran | Susulan | Rekap.
 * Sub-menu "ujian susulan" yang diminta user diwujudkan sebagai tab di sini,
 * bukan item sidebar terpisah (lihat keputusan #1 docs/DESAIN-UJIAN.md).
 *
 * Periode untuk tahun pelajaran berjalan dibuat OTOMATIS saat halaman dibuka,
 * jadi keempat menu selalu langsung bisa dipakai tanpa langkah setup.
 */
class Ujian extends BaseController
{
    /** Tab yang tersedia: kunci = potongan URL, nilai = label tampil. */
    public const TAB = [
        'periode'        => 'Periode',
        'jadwal'         => 'Jadwal Ujian',
        'ketidakhadiran' => 'Ketidakhadiran',
        'susulan'        => 'Ujian Susulan',
        'rekap'          => 'Rekap',
    ];

    protected UjianPeriodeModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new UjianPeriodeModel();
        $this->audit = new AuditModel();
    }

    /** Tanpa jenis → buka ASTS 1 (gelombang pertama tahun pelajaran). */
    public function index()
    {
        return redirect()->to(site_url('admin/ujian/asts1'));
    }

    /**
     * Halaman satu jenis ujian.
     *
     * @param string $slug asts1 | asas | asts2 | asat
     * @param string $tab  salah satu kunci self::TAB
     */
    public function jenis(string $slug = 'asts1', string $tab = 'periode')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))
                ->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        if (! isset(self::TAB[$tab])) {
            return redirect()->to(site_url('admin/ujian/' . $slug));
        }

        $periode = $this->periodeTerpilih($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode tahun pelajaran itu belum pernah dibuat.');
        }

        $data = [
            'title'    => 'Ujian ' . (UjianPeriodeModel::JENIS_LABEL[$jenis] ?? $jenis),
            'jenis'    => $jenis,
            'slug'     => $slug,
            'tab'      => $tab,
            'tabs'     => self::TAB,
            'periode'  => $periode,
            'label'    => $this->model->label($periode),
            'panjang'  => UjianPeriodeModel::JENIS_PANJANG[$jenis] ?? '',
            'riwayat'  => $this->model->riwayat($jenis),
            'berjalan' => $this->model->tahunBerjalan(),
            'ringkas'  => $this->ringkasan((int) $periode['id']),
        ];

        if ($tab === 'jadwal') {
            $data += $this->dataJadwal((int) $periode['id']);
        }
        if ($tab === 'ketidakhadiran') {
            $data += $this->dataKetidakhadiran($periode);
        }
        if ($tab === 'susulan') {
            $data += $this->dataSusulan($periode);
        }
        if ($tab === 'rekap') {
            // Agregasi dipinjam dari library yang sama dengan cetakan PDF/Excel
            // supaya angka di layar & di berkas tak mungkin berbeda.
            $data += UjianReport::hitung((int) $periode['id']);
        }

        return view('admin/ujian/index', $data);
    }

    // =================================================================
    // Tab Jadwal Ujian
    // =================================================================

    /** Simpan jadwal baru atau perubahan jadwal yang ada. */
    public function simpanJadwal(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        $periode = $this->periodeDariPost($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode ujian tidak ditemukan.');
        }
        $kembali = $this->urlTab($slug, 'jadwal', $periode['tahun_ajaran']);

        $model = new UjianJadwalModel();
        $id    = (int) $this->request->getPost('id');

        // Saat mengubah: pastikan barisnya benar-benar milik periode ini.
        $lama = null;
        if ($id > 0) {
            $lama = $model->find($id);
            if (! $lama || (int) $lama['periode_id'] !== (int) $periode['id']) {
                return redirect()->to($kembali)->with('error', 'Jadwal ujian tidak ditemukan.');
            }
        }

        $data = [
            'periode_id'  => (int) $periode['id'],
            'mapel_id'    => (int) $this->request->getPost('mapel_id') ?: null,
            'tingkat'     => (string) $this->request->getPost('tingkat'),
            'jurusan_id'  => (int) $this->request->getPost('jurusan_id') ?: null,
            'shift'       => (string) $this->request->getPost('shift'),
            'tanggal'     => $this->tanggal('tanggal'),
            'jam_mulai'   => $this->jam('jam_mulai'),
            'jam_selesai' => $this->jam('jam_selesai'),
            'ruang'       => $this->teks('ruang', 100),
            'keterangan'  => $this->teks('keterangan', 255),
        ];

        if (! in_array($data['shift'], UjianJadwalModel::SHIFT, true)) {
            $data['shift'] = 'semua';
        }

        $salah = $this->periksaJadwal($data);
        if ($salah !== []) {
            return redirect()->to($kembali)->withInput()->with('errors', $salah);
        }

        $bentrok = $model->bentrok($data, $id > 0 ? $id : null);
        if ($bentrok !== []) {
            return redirect()->to($kembali)->withInput()
                ->with('errors', $this->pesanBentrok($bentrok));
        }

        $simpan = $id > 0 ? $model->update($id, $data) : $model->insert($data);
        if ($simpan === false) {
            return redirect()->to($kembali)->withInput()
                ->with('errors', $model->errors() ?: ['Jadwal gagal disimpan.']);
        }

        master_data_changed('ujian_jadwal');
        $this->audit->record(
            $id > 0 ? 'update' : 'create',
            'ujian_jadwal',
            $id > 0 ? $id : (int) $model->getInsertID(),
            ($id > 0 ? 'Ubah' : 'Tambah') . ' jadwal ujian ' . $this->model->label($periode)
                . ' — ' . $data['tingkat'] . ' ' . $data['tanggal']
        );

        return redirect()->to($kembali)
            ->with('success', $id > 0 ? 'Jadwal ujian diperbarui.' : 'Jadwal ujian ditambahkan.');
    }

    /**
     * Hapus satu jadwal.
     *
     * Soft delete TIDAK memicu foreign key, jadi pembersihan ditulis manual
     * dalam transaksi: penugasan pengawas dihapus permanen (pivot, tak punya
     * riwayat sendiri), sedangkan catatan ketidakhadiran DIPERTAHANKAN dan
     * hanya dilepas dari jadwal ini — mapel & tanggal ujian sudah tersimpan
     * di barisnya sendiri sehingga tetap terbaca.
     */
    public function hapusJadwal(string $slug = '', $id = 0)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        $periode = $this->periodeDariPost($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode ujian tidak ditemukan.');
        }
        $kembali = $this->urlTab($slug, 'jadwal', $periode['tahun_ajaran']);

        $model  = new UjianJadwalModel();
        $id     = (int) $id;
        $jadwal = $id > 0 ? $model->find($id) : null;
        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return redirect()->to($kembali)->with('error', 'Jadwal ujian tidak ditemukan.');
        }

        $susulan  = new UjianSusulanModel();
        $pengawas = new UjianPengawasModel();

        $jmlSusulan  = $susulan->where('jadwal_id', $id)->countAllResults();
        $jmlPengawas = $pengawas->where('jadwal_id', $id)->countAllResults();

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        $pengawas->where('jadwal_id', $id)->delete();       // pivot: hapus permanen
        $susulan->where('jadwal_id', $id)->set('jadwal_id', null)->update();
        $model->delete($id);                                 // soft delete

        $db->transComplete();

        master_data_changed('ujian_jadwal');
        $this->audit->record(
            'delete',
            'ujian_jadwal',
            $id,
            'Hapus jadwal ujian ' . $this->model->label($periode)
                . " (lepas {$jmlSusulan} catatan ketidakhadiran, hapus {$jmlPengawas} pengawas)"
        );

        $pesan = 'Jadwal ujian dihapus.';
        if ($jmlSusulan > 0) {
            $pesan .= " {$jmlSusulan} catatan ketidakhadiran tetap tersimpan, hanya dilepas dari jadwal ini.";
        }

        return redirect()->to($kembali)->with('success', $pesan);
    }

    // =================================================================
    // Tab Ketidakhadiran — INTI MODUL
    // =================================================================

    /**
     * Simpan pendataan ketidakhadiran satu kelas pada satu sesi ujian.
     *
     * Yang dicatat HANYA siswa yang dicentang tidak hadir; siswa hadir tidak
     * pernah masuk tabel (lihat keputusan #2 docs/DESAIN-UJIAN.md). Daftar
     * siswa diambil ulang dari database, bukan dari kiriman form, supaya
     * tidak bisa dititipi siswa dari kelas lain.
     */
    public function simpanKetidakhadiran(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        $periode = $this->periodeDariPost($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode ujian tidak ditemukan.');
        }

        $jadwalModel = new UjianJadwalModel();
        $jadwalId    = (int) $this->request->getPost('jadwal_id');
        $kelasId     = (int) $this->request->getPost('kelas_id');

        $kembali = $this->urlTab($slug, 'ketidakhadiran', $periode['tahun_ajaran'])
            . '&jadwal_id=' . $jadwalId . '&kelas_id=' . $kelasId;

        $jadwal = $jadwalId > 0 ? $jadwalModel->find($jadwalId) : null;
        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return redirect()->to($this->urlTab($slug, 'ketidakhadiran', $periode['tahun_ajaran']))
                ->with('error', 'Sesi ujian tidak ditemukan.');
        }

        // Kelas wajib termasuk sasaran sesi ini (tingkat/jurusan/shift cocok).
        $sasaran = array_column($jadwalModel->kelasSasaran($jadwal), 'id');
        if ($kelasId <= 0 || ! in_array($kelasId, array_map('intval', $sasaran), true)) {
            return redirect()->to($this->urlTab($slug, 'ketidakhadiran', $periode['tahun_ajaran']) . '&jadwal_id=' . $jadwalId)
                ->with('error', 'Kelas itu bukan sasaran sesi ujian ini.');
        }

        $siswaKelas = (new SiswaModel())->where('kelas_id', $kelasId)->where('status', 'aktif')->findAll();
        if ($siswaKelas === []) {
            return redirect()->to($kembali)->with('error', 'Kelas ini belum punya siswa aktif.');
        }

        $dicentang  = array_map('intval', (array) $this->request->getPost('tidak_hadir'));
        $alasanPost = (array) $this->request->getPost('alasan');
        $ketPost    = (array) $this->request->getPost('keterangan');

        $susulan  = new UjianSusulanModel();
        $tercatat = $susulan->siswaTercatat($jadwalId);

        $tambah = 0;
        $ubah   = 0;
        $hapus  = 0;
        $kunci  = 0;

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        foreach ($siswaKelas as $s) {
            $sid  = (int) $s['id'];
            $lama = $tercatat[$sid] ?? null;

            if (in_array($sid, $dicentang, true)) {
                $alasan = (string) ($alasanPost[$sid] ?? 'alpa');
                if (! in_array($alasan, UjianSusulanModel::ALASAN, true)) {
                    $alasan = 'alpa';
                }
                $ket = trim((string) ($ketPost[$sid] ?? ''));

                $data = [
                    'periode_id'    => (int) $periode['id'],
                    'mapel_id'      => $jadwal['mapel_id'],
                    'tanggal_ujian' => $jadwal['tanggal'],
                    'alasan'        => $alasan,
                    'keterangan'    => $ket !== '' ? mb_substr($ket, 0, 255) : null,
                ];

                // Susulan yang sudah dijadwalkan/selesai JANGAN dimundurkan
                // statusnya hanya karena pendataan disimpan ulang.
                if (! $lama || $lama['status'] === 'belum') {
                    $data['status'] = 'belum';
                }

                $hasil = $susulan->catat($jadwalId, $sid, $data);
                $hasil === 'update' ? $ubah++ : $tambah++;
                continue;
            }

            // Tidak dicentang tapi pernah tercatat → siswa ternyata hadir.
            if ($lama) {
                if ($lama['status'] === 'selesai') {
                    $kunci++;                       // susulannya sudah dilaksanakan: riwayat dijaga
                    continue;
                }
                $susulan->delete($lama['id']);
                $hapus++;
            }
        }

        $db->transComplete();

        master_data_changed('ujian_susulan');
        $this->audit->record(
            'update',
            'ujian_susulan',
            null,
            'Data ketidakhadiran ' . $this->model->label($periode)
                . ": +{$tambah} baru, {$ubah} diperbarui, {$hapus} dibatalkan"
        );

        $pesan = "Tersimpan: {$tambah} siswa baru dicatat tidak hadir, {$ubah} diperbarui, {$hapus} dibatalkan.";
        if ($kunci > 0) {
            $pesan .= " {$kunci} dipertahankan karena susulannya sudah selesai.";
        }

        return redirect()->to($kembali)->with('success', $pesan);
    }

    /** Data untuk tab Ketidakhadiran: pilih sesi → pilih kelas → daftar siswa. */
    private function dataKetidakhadiran(array $periode): array
    {
        $jadwalModel = new UjianJadwalModel();
        $susulan     = new UjianSusulanModel();

        $jadwalId = (int) $this->request->getGet('jadwal_id');
        $kelasId  = (int) $this->request->getGet('kelas_id');

        $jadwal  = null;
        $kelas   = null;
        $sasaran = [];
        $siswa   = [];
        $tercatat = [];

        if ($jadwalId > 0) {
            $baris = $jadwalModel->withRelations()->where('ujian_jadwal.id', $jadwalId)->first();
            if ($baris && (int) $baris['periode_id'] === (int) $periode['id']) {
                $jadwal   = $baris;
                $sasaran  = $jadwalModel->kelasSasaran($baris);
                $tercatat = $susulan->siswaTercatat($jadwalId);

                foreach ($sasaran as $k) {
                    if ((int) $k['id'] === $kelasId) {
                        $kelas = $k;
                        break;
                    }
                }
                if ($kelas) {
                    $siswa = (new SiswaModel())
                        ->where('kelas_id', $kelasId)
                        ->where('status', 'aktif')
                        ->orderBy('nama', 'ASC')
                        ->findAll();
                }
            }
        }

        return [
            'jadwalId'    => $jadwalId,
            'kelasId'     => $kelasId,
            'jadwalPilih' => $jadwal,
            'kelasPilih'  => $kelas,
            'kelasSasaran' => $sasaran,
            'siswa'       => $siswa,
            'tercatat'    => $tercatat,
            'jadwalOpts'  => $jadwalModel->optionsUntukPeriode((int) $periode['id']),
            'alasanList'  => UjianSusulanModel::ALASAN,
            'jmlTakHadirSesi' => $jadwalId > 0 ? count($tercatat) : 0,
        ];
    }

    // =================================================================
    // Tab Ujian Susulan — kelola tindak lanjut ketidakhadiran
    // =================================================================

    /**
     * Jadwalkan susulan untuk satu atau banyak siswa sekaligus.
     *
     * Baris berstatus `selesai` sengaja DILEWATI: susulannya sudah benar-benar
     * dilaksanakan, jadi tidak boleh tergeser hanya karena ikut tercentang
     * pada penjadwalan massal. Untuk menjadwalkan ulang, kembalikan dulu
     * statusnya lewat tombol Ubah Status.
     */
    public function jadwalkanSusulan(string $slug = '')
    {
        $ctx = $this->konteksSusulan($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode, $kembali] = $ctx;

        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if ($ids === []) {
            return redirect()->to($kembali)->with('error', 'Pilih minimal satu baris untuk dijadwalkan.');
        }

        $tanggal = $this->tanggal('tanggal_susulan');
        if ($tanggal === null) {
            return redirect()->to($kembali)->with('error', 'Tanggal susulan wajib diisi.');
        }

        $guruId = (int) $this->request->getPost('pengawas_guru_id') ?: null;
        if ($guruId !== null && ! (new GuruModel())->find($guruId)) {
            return redirect()->to($kembali)->with('error', 'Guru pengawas tidak ditemukan.');
        }

        $isi = [
            'tanggal_susulan'  => $tanggal,
            'jam_susulan'      => $this->jam('jam_susulan'),
            'ruang_susulan'    => $this->teks('ruang_susulan', 100),
            'pengawas_guru_id' => $guruId,
            'status'           => 'dijadwalkan',
        ];

        $model  = new UjianSusulanModel();
        $ubah   = 0;
        $lewat  = 0;
        $asing  = 0;

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        foreach ($ids as $id) {
            $baris = $model->find($id);
            if (! $baris || (int) $baris['periode_id'] !== (int) $periode['id']) {
                $asing++;
                continue;
            }
            if ($baris['status'] === 'selesai') {
                $lewat++;
                continue;
            }
            $model->update($id, $isi);
            $ubah++;
        }

        $db->transComplete();

        master_data_changed('ujian_susulan');
        $this->audit->record('update', 'ujian_susulan', null, "Jadwalkan {$ubah} ujian susulan pada {$tanggal}");

        $pesan = "{$ubah} susulan dijadwalkan pada " . date('d/m/Y', strtotime($tanggal)) . '.';
        if ($lewat > 0) {
            $pesan .= " {$lewat} dilewati karena sudah selesai.";
        }
        if ($asing > 0) {
            $pesan .= " {$asing} diabaikan karena bukan milik periode ini.";
        }

        return redirect()->to($kembali)->with($ubah > 0 ? 'success' : 'error', $pesan);
    }

    /** Ubah status satu baris susulan (selesai / batal / kembalikan). */
    public function statusSusulan(string $slug = '', $id = 0)
    {
        $ctx = $this->konteksSusulan($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode, $kembali] = $ctx;

        $model = new UjianSusulanModel();
        $id    = (int) $id;
        $baris = $id > 0 ? $model->find($id) : null;
        if (! $baris || (int) $baris['periode_id'] !== (int) $periode['id']) {
            return redirect()->to($kembali)->with('error', 'Data susulan tidak ditemukan.');
        }

        $status = (string) $this->request->getPost('status');
        if (! in_array($status, UjianSusulanModel::STATUS, true)) {
            return redirect()->to($kembali)->with('error', 'Status tidak valid.');
        }

        // Tanggal pelaksanaan hanya bermakna untuk susulan yang benar-benar
        // sudah dijalankan; status lain membersihkannya supaya tak menyesatkan.
        $data = ['status' => $status];
        if ($status === 'selesai') {
            $data['tanggal_pelaksanaan'] = $this->tanggal('tanggal_pelaksanaan') ?? date('Y-m-d');
        } else {
            $data['tanggal_pelaksanaan'] = null;
        }

        $model->update($id, $data);

        master_data_changed('ujian_susulan');
        $this->audit->record('update', 'ujian_susulan', $id, 'Ubah status susulan jadi ' . $status);

        return redirect()->to($kembali)->with('success', 'Status susulan diperbarui.');
    }

    /** Hapus satu baris susulan. */
    public function hapusSusulan(string $slug = '', $id = 0)
    {
        $ctx = $this->konteksSusulan($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode, $kembali] = $ctx;

        $model = new UjianSusulanModel();
        $id    = (int) $id;
        $baris = $id > 0 ? $model->find($id) : null;
        if (! $baris || (int) $baris['periode_id'] !== (int) $periode['id']) {
            return redirect()->to($kembali)->with('error', 'Data susulan tidak ditemukan.');
        }

        $model->delete($id);

        master_data_changed('ujian_susulan');
        $this->audit->record('delete', 'ujian_susulan', $id, 'Hapus catatan ketidakhadiran/susulan');

        return redirect()->to($kembali)->with('success', 'Catatan susulan dihapus.');
    }

    /**
     * Slug + periode + URL kembali untuk aksi-aksi tab Susulan.
     *
     * Filter yang sedang aktif ikut dibawa pulang lewat query `kembali`
     * supaya halaman tidak melompat ke daftar penuh setelah menyimpan.
     *
     * @return array{0:string,1:array,2:string}|\CodeIgniter\HTTP\RedirectResponse
     */
    private function konteksSusulan(string $slug)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        $periode = $this->periodeDariPost($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode ujian tidak ditemukan.');
        }

        $kembali = $this->urlTab($slug, 'susulan', $periode['tahun_ajaran']);
        $filter  = trim((string) $this->request->getPost('kembali'));
        if ($filter !== '' && ! str_contains($filter, '://')) {
            $kembali .= '&' . ltrim($filter, '&');
        }

        return [$slug, $periode, $kembali];
    }

    /** Data untuk tab Susulan: baris terfilter + opsi + ringkasan status. */
    private function dataSusulan(array $periode): array
    {
        $model = new UjianSusulanModel();

        $filter = [
            'q'        => trim((string) $this->request->getGet('q')),
            'kelas_id' => (int) $this->request->getGet('kelas_id'),
            'mapel_id' => (int) $this->request->getGet('mapel_id'),
            'status'   => (string) $this->request->getGet('status'),
            'alasan'   => (string) $this->request->getGet('alasan'),
        ];
        if (! in_array($filter['status'], UjianSusulanModel::STATUS, true)) {
            $filter['status'] = '';
        }
        if (! in_array($filter['alasan'], UjianSusulanModel::ALASAN, true)) {
            $filter['alasan'] = '';
        }

        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        $total = $this->builderSusulan($model, (int) $periode['id'], $filter)->countAllResults();
        $rows  = $this->builderSusulan($model, (int) $periode['id'], $filter)->paginate($per, 'default', $page);

        return [
            'rows'        => $rows,
            'pager'       => $model->pager,
            'filter'      => $filter,
            'per'         => $per,
            'totalSusulan' => $total,
            'ringkasStatus' => $model->ringkasanStatus((int) $periode['id']),
            'ringkasAlasan' => $model->ringkasanAlasan((int) $periode['id']),
            'kelasOpts'   => (new KelasModel())->options(),
            'mapelOpts'   => (new MataPelajaranModel())->options(),
            'guruOpts'    => (new GuruModel())->options(),
            'statusList'  => UjianSusulanModel::STATUS,
            'alasanList'  => UjianSusulanModel::ALASAN,
            'statusLabel' => UjianSusulanModel::STATUS_LABEL,
        ];
    }

    /** Builder segar bertfilter untuk daftar susulan. */
    private function builderSusulan(UjianSusulanModel $model, int $periodeId, array $filter)
    {
        $b = $model->withRelations()->where('ujian_susulan.periode_id', $periodeId);

        if ($filter['q'] !== '') {
            $b = $b->groupStart()
                ->like('siswa.nama', $filter['q'])
                ->orLike('siswa.nis', $filter['q'])
                ->groupEnd();
        }
        if ($filter['kelas_id'] > 0) {
            $b = $b->where('siswa.kelas_id', $filter['kelas_id']);
        }
        if ($filter['mapel_id'] > 0) {
            $b = $b->where('ujian_susulan.mapel_id', $filter['mapel_id']);
        }
        if ($filter['status'] !== '') {
            $b = $b->where('ujian_susulan.status', $filter['status']);
        }
        if ($filter['alasan'] !== '') {
            $b = $b->where('ujian_susulan.alasan', $filter['alasan']);
        }

        return $b->orderBy('ujian_susulan.tanggal_ujian', 'ASC')
            ->orderBy('kelas.nama_kelas', 'ASC')
            ->orderBy('siswa.nama', 'ASC');
    }

    // =================================================================
    // Pengawas ujian — OPSIONAL, jadwal tetap sah tanpa satu pun pengawas
    // =================================================================

    /** Halaman kelola pengawas satu sesi ujian. */
    public function pengawas(string $slug = '', $jadwalId = 0)
    {
        $konteks = $this->konteksJadwal($slug, (int) $jadwalId);
        if (! is_array($konteks)) {
            return $konteks;                       // sudah berupa RedirectResponse
        }
        [$slug, $periode, $jadwal] = $konteks;

        return view('admin/ujian/pengawas', [
            'title'    => 'Pengawas Ujian',
            'slug'     => $slug,
            'periode'  => $periode,
            'label'    => $this->model->label($periode),
            'jadwal'   => $jadwal,
            'list'     => (new UjianPengawasModel())->untukJadwal((int) $jadwal['id']),
            'guruOpts' => (new GuruModel())->options(),
            'peranList' => UjianPengawasModel::PERAN,
            'kembali'  => $this->urlTab($slug, 'jadwal', $periode['tahun_ajaran']),
        ]);
    }

    /** Tugaskan seorang guru sebagai pengawas sesi ini. */
    public function simpanPengawas(string $slug = '', $jadwalId = 0)
    {
        $konteks = $this->konteksJadwal($slug, (int) $jadwalId);
        if (! is_array($konteks)) {
            return $konteks;
        }
        [$slug, $periode, $jadwal] = $konteks;

        $url   = site_url('admin/ujian/' . $slug . '/pengawas/' . $jadwal['id']);
        $model = new UjianPengawasModel();

        $guruId = (int) $this->request->getPost('guru_id');
        if ($guruId <= 0) {
            return redirect()->to($url)->with('error', 'Guru wajib dipilih.');
        }
        if (! (new GuruModel())->find($guruId)) {
            return redirect()->to($url)->with('error', 'Guru tidak ditemukan.');
        }
        if ($model->sudahDitugaskan((int) $jadwal['id'], $guruId)) {
            return redirect()->to($url)->with('error', 'Guru itu sudah ditugaskan pada sesi ini.');
        }

        $peran = (string) $this->request->getPost('peran');
        if (! in_array($peran, UjianPengawasModel::PERAN, true)) {
            $peran = 'pengawas';
        }

        // Guru tak bisa mengawasi dua ruang pada jam yang sama — dicek lintas
        // periode, bukan cuma di gelombang ujian ini.
        $bentrok = $model->bentrokGuru($guruId, $jadwal);
        if ($bentrok !== []) {
            return redirect()->to($url)->with('errors', $this->pesanBentrokPengawas($bentrok));
        }

        $model->insert([
            'jadwal_id'  => (int) $jadwal['id'],
            'guru_id'    => $guruId,
            // Ruang dikosongkan → ikut ruang sesi ujiannya.
            'ruang'      => $this->teks('ruang', 100) ?? $jadwal['ruang'],
            'peran'      => $peran,
            'keterangan' => $this->teks('keterangan', 255),
        ]);

        master_data_changed('ujian_jadwal');
        $this->audit->record('create', 'ujian_pengawas', (int) $model->getInsertID(), 'Tugaskan pengawas ujian');

        return redirect()->to($url)->with('success', 'Pengawas ditugaskan.');
    }

    /** Lepas satu penugasan pengawas (hard delete, tabel pivot). */
    public function hapusPengawas(string $slug = '', $jadwalId = 0, $id = 0)
    {
        $konteks = $this->konteksJadwal($slug, (int) $jadwalId);
        if (! is_array($konteks)) {
            return $konteks;
        }
        [$slug, $periode, $jadwal] = $konteks;

        $url   = site_url('admin/ujian/' . $slug . '/pengawas/' . $jadwal['id']);
        $model = new UjianPengawasModel();

        $id    = (int) $id;
        $baris = $id > 0 ? $model->find($id) : null;
        if (! $baris || (int) $baris['jadwal_id'] !== (int) $jadwal['id']) {
            return redirect()->to($url)->with('error', 'Penugasan pengawas tidak ditemukan.');
        }

        $model->delete($id);
        master_data_changed('ujian_jadwal');
        $this->audit->record('delete', 'ujian_pengawas', $id, 'Lepas penugasan pengawas ujian');

        return redirect()->to($url)->with('success', 'Penugasan pengawas dilepas.');
    }

    /**
     * Selesaikan slug + periode + jadwal sekaligus untuk aksi pengawas.
     *
     * @return array{0:string,1:array,2:array}|\CodeIgniter\HTTP\RedirectResponse
     */
    private function konteksJadwal(string $slug, int $jadwalId)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        $periode = $this->periodeTerpilih($jenis);
        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode ujian tidak ditemukan.');
        }

        $kembali = $this->urlTab($slug, 'jadwal', $periode['tahun_ajaran']);
        $jadwal  = $jadwalId > 0
            ? (new UjianJadwalModel())->withRelations()->where('ujian_jadwal.id', $jadwalId)->first()
            : null;

        // Jadwal harus milik periode yang sedang dibuka — bukan sekadar ada.
        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return redirect()->to($kembali)->with('error', 'Jadwal ujian tidak ditemukan.');
        }

        return [$slug, $periode, $jadwal];
    }

    /** @return array<int, string> keterangan tugas pengawas yang bertabrakan */
    private function pesanBentrokPengawas(array $bentrok): array
    {
        $pesan = ['Guru itu sudah mengawasi sesi lain pada jam yang sama:'];

        foreach (array_slice($bentrok, 0, 5) as $b) {
            $jam = $b['jam_mulai']
                ? substr((string) $b['jam_mulai'], 0, 5) . '–' . substr((string) $b['jam_selesai'], 0, 5)
                : 'sehari penuh';
            $pesan[] = '• ' . date('d/m/Y', strtotime((string) $b['tanggal'])) . ' ' . $jam
                . ' · ' . ($b['nama_mapel'] ?? 'Tanpa mapel')
                . ' · ' . $b['tingkat']
                . ($b['ruang'] ? ' · ruang ' . $b['ruang'] : '');
        }

        return $pesan;
    }

    /** Data untuk tab Jadwal: baris terfilter + opsi dropdown + ringkasan. */
    private function dataJadwal(int $periodeId): array
    {
        $model = new UjianJadwalModel();

        $filter = [
            'q'          => trim((string) $this->request->getGet('q')),
            'tingkat'    => (string) $this->request->getGet('tingkat'),
            'jurusan_id' => (int) $this->request->getGet('jurusan_id'),
        ];
        if (! in_array($filter['tingkat'], UjianJadwalModel::TINGKAT, true)) {
            $filter['tingkat'] = '';
        }

        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        $total = $this->builderJadwal($model, $periodeId, $filter)->countAllResults();
        $rows  = $this->builderJadwal($model, $periodeId, $filter)->paginate($per, 'default', $page);
        $pager = $model->pager;

        $ids = array_column($rows, 'id');

        return [
            'rows'         => $rows,
            'pager'        => $pager,
            'filter'       => $filter,
            'per'          => $per,
            'totalJadwal'  => $total,
            'jmlPengawas'  => (new UjianPengawasModel())->countForJadwal($ids),
            'jmlTakHadir'  => (new UjianSusulanModel())->countForJadwal($ids),
            'mapelOpts'    => (new MataPelajaranModel())->options(),
            'jurusanOpts'  => (new JurusanModel())->options(),
            'tingkatList'  => UjianJadwalModel::TINGKAT,
            'shiftList'    => UjianJadwalModel::SHIFT,
        ];
    }

    /** Builder segar bertfilter — dipakai dua kali (hitung total & paginasi). */
    private function builderJadwal(UjianJadwalModel $model, int $periodeId, array $filter)
    {
        $b = $model->untukPeriode($periodeId);

        if ($filter['q'] !== '') {
            $b = $b->groupStart()
                ->like('mata_pelajaran.nama_mapel', $filter['q'])
                ->orLike('mata_pelajaran.kode_mapel', $filter['q'])
                ->orLike('ujian_jadwal.ruang', $filter['q'])
                ->groupEnd();
        }
        if ($filter['tingkat'] !== '') {
            $b = $b->where('ujian_jadwal.tingkat', $filter['tingkat']);
        }
        if ($filter['jurusan_id'] > 0) {
            $b = $b->where('ujian_jadwal.jurusan_id', $filter['jurusan_id']);
        }

        return $b;
    }

    /** @return array<int, string> pesan kesalahan isian jadwal */
    private function periksaJadwal(array $data): array
    {
        $salah = [];

        if (! $data['mapel_id']) {
            $salah[] = 'Mata pelajaran wajib dipilih.';
        }
        if (! in_array($data['tingkat'], UjianJadwalModel::TINGKAT, true)) {
            $salah[] = 'Tingkat wajib dipilih (X, XI, atau XII).';
        }
        if (! $data['tanggal']) {
            $salah[] = 'Tanggal ujian wajib diisi.';
        }
        if ($data['jam_selesai'] && ! $data['jam_mulai']) {
            $salah[] = 'Jam selesai diisi tapi jam mulai kosong.';
        }
        if ($data['jam_mulai'] && $data['jam_selesai'] && $data['jam_selesai'] <= $data['jam_mulai']) {
            $salah[] = 'Jam selesai harus lebih besar dari jam mulai.';
        }

        return $salah;
    }

    /** @return array<int, string> keterangan jadwal yang bertabrakan */
    private function pesanBentrok(array $bentrok): array
    {
        $pesan = ['Jadwal bentrok dengan yang sudah ada — siswa tidak bisa ikut dua ujian sekaligus:'];

        foreach (array_slice($bentrok, 0, 5) as $b) {
            $jam = $b['jam_mulai']
                ? substr((string) $b['jam_mulai'], 0, 5) . '–' . substr((string) $b['jam_selesai'], 0, 5)
                : 'sehari penuh';
            $pesan[] = '• ' . date('d/m/Y', strtotime((string) $b['tanggal'])) . ' ' . $jam
                . ' · ' . ($b['nama_mapel'] ?? 'Tanpa mapel')
                . ' · ' . $b['tingkat'] . ($b['jurusan_kode'] ? ' ' . $b['jurusan_kode'] : '')
                . ' (' . $b['shift'] . ')';
        }

        return $pesan;
    }

    /** Periode yang dikirim form; dipastikan milik jenis yang sedang dibuka. */
    private function periodeDariPost(string $jenis): ?array
    {
        $id      = (int) $this->request->getPost('periode_id');
        $periode = $id > 0 ? $this->model->find($id) : null;

        return ($periode && $periode['jenis'] === $jenis) ? $periode : null;
    }

    /** URL satu tab lengkap dengan tahun pelajaran yang sedang dilihat. */
    private function urlTab(string $slug, string $tab, string $tahun): string
    {
        return site_url('admin/ujian/' . $slug . ($tab === 'periode' ? '' : '/' . $tab))
            . '?tp=' . rawurlencode($tahun);
    }

    /** Baris per halaman yang diizinkan. */
    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 20;
    }

    /** Simpan pengaturan periode (tanggal pelaksanaan, tanggal susulan, status). */
    public function simpanPeriode(string $slug = '')
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))
                ->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        $id      = (int) $this->request->getPost('id');
        $periode = $id > 0 ? $this->model->find($id) : null;

        // Jangan percaya id dari form begitu saja: pastikan miliknya jenis ini.
        if (! $periode || $periode['jenis'] !== $jenis) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode ujian tidak ditemukan.');
        }

        $kembali = $this->urlTab($slug, 'periode', $periode['tahun_ajaran']);

        $data = [
            'nama'            => $this->teks('nama', 100),
            'tanggal_mulai'   => $this->tanggal('tanggal_mulai'),
            'tanggal_selesai' => $this->tanggal('tanggal_selesai'),
            'susulan_mulai'   => $this->tanggal('susulan_mulai'),
            'susulan_selesai' => $this->tanggal('susulan_selesai'),
            'status'          => (string) $this->request->getPost('status'),
            'keterangan'      => $this->teks('keterangan', 255),
        ];

        if (! in_array($data['status'], UjianPeriodeModel::STATUS, true)) {
            $data['status'] = $periode['status'];
        }

        $salah = $this->periksaUrutanTanggal($data);
        if ($salah !== []) {
            return redirect()->to($kembali)->withInput()->with('errors', $salah);
        }

        if (! $this->model->update($periode['id'], $data)) {
            return redirect()->to($kembali)->withInput()
                ->with('errors', $this->model->errors() ?: ['Periode gagal disimpan.']);
        }

        master_data_changed('ujian_periode');
        $this->audit->record(
            'update',
            'ujian_periode',
            (int) $periode['id'],
            'Ubah pengaturan periode ' . $this->model->label($periode)
        );

        return redirect()->to($kembali)->with('success', 'Pengaturan periode disimpan.');
    }

    // =================================================================
    // Util internal
    // =================================================================

    /**
     * Periode yang sedang dilihat.
     *
     * Tanpa `?tp=` (atau bila yang diminta = tahun berjalan) periode dibuat
     * otomatis. Tahun pelajaran lama hanya DIBACA — tidak pernah dibuat
     * otomatis, supaya riwayat tidak terisi baris kosong tak sengaja.
     */
    private function periodeTerpilih(string $jenis): ?array
    {
        return $this->model->untukTahun($jenis, $this->request->getGet('tp'));
    }

    /** Angka ringkasan untuk kartu di tab Periode. */
    private function ringkasan(int $periodeId): array
    {
        $susulan = new UjianSusulanModel();
        $status  = $susulan->ringkasanStatus($periodeId);

        return [
            'jadwal'      => (new UjianJadwalModel())->where('periode_id', $periodeId)->countAllResults(),
            'siswaAktif'  => (new SiswaModel())->where('status', 'aktif')->countAllResults(),
            'tidakHadir'  => array_sum($status),
            'statusList'  => $status,
            'alasanList'  => $susulan->ringkasanAlasan($periodeId),
        ];
    }

    /** Ambil field teks, dipangkas; string kosong disimpan sebagai NULL. */
    private function teks(string $field, int $max): ?string
    {
        $v = trim((string) $this->request->getPost($field));

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /** Ambil field tanggal; kosong/tidak valid jadi NULL. */
    private function tanggal(string $field): ?string
    {
        $v = trim((string) $this->request->getPost($field));
        if ($v === '') {
            return null;
        }

        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);

        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }

    /** Ambil field jam "HH:MM"; kosong/tidak valid jadi NULL, disimpan "HH:MM:00". */
    private function jam(string $field): ?string
    {
        $v = trim((string) $this->request->getPost($field));
        if ($v === '') {
            return null;
        }
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v, $m)) {
            return null;
        }

        return $m[1] . ':' . $m[2] . ':00';
    }

    /**
     * Tanggal selesai tidak boleh mendahului tanggal mulai.
     *
     * @return array<int, string> daftar pesan kesalahan (kosong = lolos)
     */
    private function periksaUrutanTanggal(array $data): array
    {
        $salah = [];

        if ($data['tanggal_mulai'] && $data['tanggal_selesai']
            && $data['tanggal_selesai'] < $data['tanggal_mulai']) {
            $salah[] = 'Tanggal selesai ujian tidak boleh lebih awal dari tanggal mulai.';
        }

        if ($data['susulan_mulai'] && $data['susulan_selesai']
            && $data['susulan_selesai'] < $data['susulan_mulai']) {
            $salah[] = 'Tanggal selesai susulan tidak boleh lebih awal dari tanggal mulai susulan.';
        }

        return $salah;
    }
}
