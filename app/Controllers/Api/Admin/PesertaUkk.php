<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\UkkAlur;
use App\Models\AuditModel;
use App\Models\JadwalUkkModel;
use App\Models\NilaiUkkModel;
use App\Models\PaketSoalUkkModel;
use App\Models\PesertaUkkModel;
use App\Models\SiswaModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Peserta UKK (API, fase UKK A2). Cermin App\Controllers\Admin\PesertaUkk; aturan pendaftaran bersama web di
 * App\Libraries\UkkAlur::daftarkan. Rute /api/v1/admin/peserta-ukk:
 *   GET    ?q=&paket_soal_id=&jadwal_ukk_id=&status=&per=&page=  daftar + meta.ringkasan (jumlah per status)
 *   GET    calon?paket_soal_id=&kelas_id=   siswa aktif di kelas itu yang BELUM terdaftar + pilihan jadwal paket
 *   POST   daftarkan {paket_soal_id, jadwal_ukk_id?, siswa_ids[]}
 *   GET    {id}                             detail + nilai dari tiap penguji
 *   POST   {id}/status {status}
 *   DELETE {id}
 */
class PesertaUkk extends BaseApiController
{
    protected PesertaUkkModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PesertaUkkModel();
        $this->audit = new AuditModel();
    }

    public function index(): ResponseInterface
    {
        $per  = (int) $this->request->getGet('per');
        $page = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }
        $q        = trim((string) $this->request->getGet('q'));
        $paketId  = (int) $this->request->getGet('paket_soal_id');
        $jadwalId = (int) $this->request->getGet('jadwal_ukk_id');
        $status   = trim((string) $this->request->getGet('status'));

        $b = $this->model->withRelations();
        if ($q !== '') {
            $b = $b->groupStart()
                ->like('siswa.nama', $q)->orLike('siswa.nis', $q)->orLike('peserta_ukk.no_peserta', $q)->groupEnd();
        }
        if ($paketId > 0) {
            $b = $b->where('peserta_ukk.paket_soal_id', $paketId);
        }
        if ($jadwalId > 0) {
            $b = $b->where('peserta_ukk.jadwal_ukk_id', $jadwalId);
        }
        if (in_array($status, PesertaUkkModel::STATUS, true)) {
            $b = $b->where('peserta_ukk.status', $status);
        }
        $rows  = $b->orderBy('siswa.nama', 'ASC')->paginate($per, 'default', $page);
        $total = $this->model->pager->getTotal();

        // Ringkasan seperti kartu di web: per paket bila disaring paket, selain itu seluruh peserta.
        $ringkasan = ['total' => 0] + array_fill_keys(PesertaUkkModel::STATUS, 0);
        $rb        = (new PesertaUkkModel())->select('status, COUNT(*) AS jumlah');
        if ($paketId > 0) {
            $rb = $rb->where('paket_soal_id', $paketId);
        }
        foreach ($rb->groupBy('status')->findAll() as $r) {
            $ringkasan[$r['status']] = (int) $r['jumlah'];
            $ringkasan['total'] += (int) $r['jumlah'];
        }

        return $this->collection(
            array_map([self::class, 'data'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $total],
            'Berhasil',
            ['ringkasan' => $ringkasan]
        );
    }

    /** Siswa aktif sekelas yang BELUM terdaftar pada paket soal (bahan centang "Daftarkan Peserta" di web). */
    public function calon(): ResponseInterface
    {
        $paketId = (int) $this->request->getGet('paket_soal_id');
        $kelasId = (int) $this->request->getGet('kelas_id');
        $paket   = $paketId > 0 ? (new PaketSoalUkkModel())->find($paketId) : null;
        if (! $paket) {
            return $this->missing('Paket soal tidak ditemukan.');
        }
        if ($kelasId <= 0) {
            return $this->invalid(['kelas_id' => 'Pilih kelas.']);
        }

        $terdaftar = array_column($this->model->select('siswa_id')->where('paket_soal_id', $paketId)->findAll(), 'siswa_id');
        $b         = (new SiswaModel())->select('id, nis, nama')->where('kelas_id', $kelasId)->where('status', 'aktif');
        if ($terdaftar !== []) {
            $b = $b->whereNotIn('id', $terdaftar);
        }
        $jadwal = [];
        foreach ((new JadwalUkkModel())->optionsUntukPaket($paketId) as $id => $label) {
            $jadwal[] = ['id' => (int) $id, 'label' => $label];
        }

        return $this->ok([
            'paket'           => ['id' => (int) $paket['id'], 'kode' => $paket['kode'], 'nama' => $paket['nama']],
            'sudah_terdaftar' => count($terdaftar),
            'siswa'           => array_map(static fn ($s) => ['id' => (int) $s['id'], 'nis' => $s['nis'], 'nama' => $s['nama']], $b->orderBy('nama', 'ASC')->findAll()),
            'jadwal'          => $jadwal,
        ]);
    }

    /** POST daftarkan banyak siswa sekaligus. */
    public function daftarkan(): ResponseInterface
    {
        $in    = $this->body();
        $hasil = UkkAlur::daftarkan(
            (int) ($in['paket_soal_id'] ?? 0),
            ((int) ($in['jadwal_ukk_id'] ?? 0)) ?: null,
            (array) ($in['siswa_ids'] ?? [])
        );
        if (! $hasil['ok']) {
            return $this->tolakHasil($hasil);
        }
        $n     = $hasil['didaftarkan'];
        $lewat = $hasil['dilewati'];
        $this->audit->record('create', 'peserta_ukk', null, "Daftarkan {$n} peserta UKK untuk paket {$hasil['paket']['kode']}" . ($lewat ? " ({$lewat} sudah terdaftar, dilewati)" : '') . ' (via mobile)');

        return $this->ok(
            ['didaftarkan' => $n, 'dilewati' => $lewat],
            "{$n} peserta berhasil didaftarkan." . ($lewat ? " {$lewat} siswa dilewati (sudah terdaftar)." : '')
        );
    }

    /** GET detail peserta + nilai dari tiap penguji. */
    public function show($id = null): ResponseInterface
    {
        $row = $this->model->withRelations()->where('peserta_ukk.id', (int) $id)->first();
        if (! $row) {
            return $this->missing('Peserta tidak ditemukan.');
        }

        return $this->ok(self::data($row) + [
            'nilai' => array_map([PenilaianUkk::class, 'nilaiData'], (new NilaiUkkModel())->untukPeserta((int) $id)),
        ]);
    }

    /** POST ubah status kehadiran/hasil secara manual. */
    public function status($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Peserta tidak ditemukan.');
        }
        $status = strtolower(trim((string) ($this->body()['status'] ?? '')));
        if (! in_array($status, PesertaUkkModel::STATUS, true)) {
            return $this->invalid(['status' => 'Status harus salah satu: ' . implode(', ', PesertaUkkModel::STATUS) . '.']);
        }

        $this->model->update($id, ['status' => $status]);
        master_data_changed('peserta_ukk');
        $this->audit->record('update', 'peserta_ukk', $id, 'Ubah status peserta UKK jadi ' . $status . ' (via mobile)');

        return $this->ok(self::data($this->model->withRelations()->where('peserta_ukk.id', $id)->first()), 'Status peserta diperbarui.');
    }

    /** DELETE pendaftaran (soft delete, sama dengan web: nilai & sertifikat tidak ikut dihapus). */
    public function destroy($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Peserta tidak ditemukan.');
        }
        $this->model->delete($id);
        master_data_changed('peserta_ukk');
        $this->audit->record('delete', 'peserta_ukk', $id, 'Hapus pendaftaran peserta UKK (via mobile)');

        return $this->ok(null, 'Pendaftaran peserta dihapus.');
    }

    /** Bentuk satu baris peserta (withRelations) untuk klien. */
    public static function data(array $r): array
    {
        $angka = static fn ($v) => $v === null ? null : (float) $v;

        return [
            'id'              => (int) $r['id'],
            'no_peserta'      => $r['no_peserta'],
            'status'          => $r['status'],
            'status_label'    => ucfirst(str_replace('_', ' ', (string) $r['status'])),
            'siswa_id'        => (int) $r['siswa_id'],
            'siswa_nama'      => $r['siswa_nama'] ?? null,
            'nis'             => $r['nis'] ?? null,
            'kelas'           => $r['nama_kelas'] ?? null,
            'paket_soal_id'   => (int) $r['paket_soal_id'],
            'paket_kode'      => $r['paket_kode'] ?? null,
            'paket_nama'      => $r['paket_nama'] ?? null,
            'kkm'             => $angka($r['kkm'] ?? null),
            'jadwal_ukk_id'   => ((int) ($r['jadwal_ukk_id'] ?? 0)) ?: null,
            'jadwal_tanggal'  => $r['jadwal_tanggal'] ?? null,
            'tahun_ajaran_id' => ((int) ($r['tahun_ajaran_id'] ?? 0)) ?: null,
            'nilai_akhir'     => $angka($r['nilai_akhir'] ?? null),
            'predikat'        => $r['predikat'] ?? null,
            'keterangan'      => $r['keterangan'] ?? null,
        ];
    }
}
