<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\UjianJadwalModel;
use App\Models\UjianPengawasModel;
use App\Models\UjianPeriodeModel;
use App\Models\UjianSusulanModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Jadwal ujian & pengawasnya (API).
 * Cermin App\Controllers\Admin\Ujian (tab Jadwal + halaman Pengawas).
 *
 * Rute:
 *   GET    /api/v1/admin/ujian/{slug}/jadwal?tp=&q=&tingkat=&jurusan_id=&per=&page=
 *   POST   /api/v1/admin/ujian/{slug}/jadwal                 (id di body = ubah)
 *   DELETE /api/v1/admin/ujian/{slug}/jadwal/{id}
 *   GET    /api/v1/admin/ujian/{slug}/jadwal/{id}/kelas      kelas sasaran sesi
 *   GET    /api/v1/admin/ujian/{slug}/jadwal/{id}/pengawas
 *   POST   /api/v1/admin/ujian/{slug}/jadwal/{id}/pengawas
 *   DELETE /api/v1/admin/ujian/{slug}/jadwal/{id}/pengawas/{pengawasId}
 */
class UjianJadwal extends BaseApiController
{
    protected UjianJadwalModel $model;
    protected UjianPeriodeModel $periodeModel;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model        = new UjianJadwalModel();
        $this->periodeModel = new UjianPeriodeModel();
        $this->audit        = new AuditModel();
    }

    // ================= Jadwal =================

    public function index(string $slug = ''): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $per  = (int) $this->request->getGet('per');
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 20;
        }
        $page = max(1, (int) $this->request->getGet('page'));

        $q         = trim((string) $this->request->getGet('q'));
        $tingkat   = (string) $this->request->getGet('tingkat');
        $jurusanId = (int) $this->request->getGet('jurusan_id');
        if (! in_array($tingkat, UjianJadwalModel::TINGKAT, true)) {
            $tingkat = '';
        }

        $saring = function () use ($periode, $q, $tingkat, $jurusanId) {
            $b = $this->model->untukPeriode((int) $periode['id']);
            if ($q !== '') {
                $b = $b->groupStart()
                    ->like('mata_pelajaran.nama_mapel', $q)
                    ->orLike('mata_pelajaran.kode_mapel', $q)
                    ->orLike('ujian_jadwal.ruang', $q)
                    ->groupEnd();
            }
            if ($tingkat !== '') {
                $b = $b->where('ujian_jadwal.tingkat', $tingkat);
            }
            if ($jurusanId > 0) {
                $b = $b->where('ujian_jadwal.jurusan_id', $jurusanId);
            }

            return $b;
        };

        $total = $saring()->countAllResults();
        $rows  = $saring()->paginate($per, 'default', $page);

        $ids      = array_column($rows, 'id');
        $pengawas = (new UjianPengawasModel())->countForJadwal($ids);
        $takHadir = (new UjianSusulanModel())->countForJadwal($ids);

        $items = array_map(
            fn ($r) => $this->transform($r) + [
                'jumlah_pengawas'    => (int) ($pengawas[$r['id']] ?? 0),
                'jumlah_tidak_hadir' => (int) ($takHadir[$r['id']] ?? 0),
            ],
            $rows
        );

        return $this->collection($items, ['page' => $page, 'perPage' => $per, 'total' => $total]);
    }

    /** Tambah jadwal baru, atau ubah bila body memuat id. */
    public function store(string $slug = ''): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $in = $this->body();
        $id = (int) ($in['id'] ?? 0);

        if ($id > 0) {
            $lama = $this->model->find($id);
            if (! $lama || (int) $lama['periode_id'] !== (int) $periode['id']) {
                return $this->missing('Jadwal ujian tidak ditemukan.');
            }
        }

        $shift = strtolower(trim((string) ($in['shift'] ?? 'semua')));
        $data  = [
            'periode_id'  => (int) $periode['id'],
            'mapel_id'    => (int) ($in['mapel_id'] ?? 0) ?: null,
            'tingkat'     => strtoupper(trim((string) ($in['tingkat'] ?? ''))),
            'jurusan_id'  => (int) ($in['jurusan_id'] ?? 0) ?: null,
            'shift'       => in_array($shift, UjianJadwalModel::SHIFT, true) ? $shift : 'semua',
            'tanggal'     => $this->tanggal($in['tanggal'] ?? null),
            'jam_mulai'   => $this->jam($in['jam_mulai'] ?? null),
            'jam_selesai' => $this->jam($in['jam_selesai'] ?? null),
            'ruang'       => $this->teks($in['ruang'] ?? null, 100),
            'keterangan'  => $this->teks($in['keterangan'] ?? null, 255),
        ];

        $salah = [];
        if (! $data['mapel_id']) {
            $salah['mapel_id'] = 'Mata pelajaran wajib dipilih.';
        }
        if (! in_array($data['tingkat'], UjianJadwalModel::TINGKAT, true)) {
            $salah['tingkat'] = 'Tingkat wajib diisi X, XI, atau XII.';
        }
        if (! $data['tanggal']) {
            $salah['tanggal'] = 'Tanggal ujian wajib diisi (format YYYY-MM-DD).';
        }
        if ($data['jam_selesai'] && ! $data['jam_mulai']) {
            $salah['jam_mulai'] = 'Jam selesai diisi tapi jam mulai kosong.';
        }
        if ($data['jam_mulai'] && $data['jam_selesai'] && $data['jam_selesai'] <= $data['jam_mulai']) {
            $salah['jam_selesai'] = 'Jam selesai harus lebih besar dari jam mulai.';
        }
        if ($salah !== []) {
            return $this->invalid($salah);
        }

        // Mapel sama di jam sama BUKAN bentrok (ujian paralel beda ruang);
        // yang ditolak hanya dua mapel berbeda yang jamnya beririsan.
        $bentrok = $this->model->bentrok($data, $id > 0 ? $id : null);
        if ($bentrok !== []) {
            return $this->failure('Jadwal bentrok dengan sesi lain pada tingkat, shift, dan jam yang sama.', 409, [
                'bentrok' => array_map([$this, 'transform'], $bentrok),
            ]);
        }

        $simpan = $id > 0 ? $this->model->update($id, $data) : $this->model->insert($data);
        if ($simpan === false) {
            return $this->invalid($this->model->errors() ?: ['jadwal' => 'Jadwal gagal disimpan.']);
        }

        $newId = $id > 0 ? $id : (int) $this->model->getInsertID();
        master_data_changed('ujian_jadwal');
        $this->audit->record(
            $id > 0 ? 'update' : 'create',
            'ujian_jadwal',
            $newId,
            ($id > 0 ? 'Ubah' : 'Tambah') . ' jadwal ujian (via mobile)'
        );

        $baris = $this->transform($this->fresh($newId));

        return $id > 0
            ? $this->ok($baris, 'Jadwal ujian diperbarui.')
            : $this->created($baris, 'Jadwal ujian ditambahkan.');
    }

    /**
     * Hapus jadwal.
     *
     * Soft delete tak memicu foreign key, jadi pembersihan ditulis manual
     * dalam transaksi — sama persis dengan versi web: pengawas dihapus
     * permanen, catatan ketidakhadiran DIPERTAHANKAN dan hanya dilepas.
     */
    public function destroy(string $slug = '', $id = null): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $id     = (int) $id;
        $jadwal = $id > 0 ? $this->model->find($id) : null;
        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return $this->missing('Jadwal ujian tidak ditemukan.');
        }

        $susulan  = new UjianSusulanModel();
        $pengawas = new UjianPengawasModel();

        $jmlSusulan  = $susulan->where('jadwal_id', $id)->countAllResults();
        $jmlPengawas = $pengawas->where('jadwal_id', $id)->countAllResults();

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        $pengawas->where('jadwal_id', $id)->delete();
        $susulan->where('jadwal_id', $id)->set('jadwal_id', null)->update();
        $this->model->delete($id);

        $db->transComplete();

        master_data_changed('ujian_jadwal');
        $this->audit->record('delete', 'ujian_jadwal', $id, 'Hapus jadwal ujian (via mobile)');

        return $this->ok([
            'susulan_dilepas'  => $jmlSusulan,
            'pengawas_dihapus' => $jmlPengawas,
        ], 'Jadwal ujian dihapus.');
    }

    /** Kelas sasaran satu sesi (tingkat + jurusan + shift yang cocok). */
    public function kelas(string $slug = '', $id = null): ResponseInterface
    {
        $ctx = $this->sesi($slug, (int) $id);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [, $jadwal] = $ctx;

        $rows = array_map(static fn ($k) => [
            'id'           => (int) $k['id'],
            'nama_kelas'   => $k['nama_kelas'],
            'tingkat'      => $k['tingkat'],
            'shift'        => $k['shift'],
            'jurusan_kode' => $k['jurusan_kode'] ?? null,
        ], $this->model->kelasSasaran($jadwal));

        return $this->ok($rows, 'Kelas sasaran sesi ujian.');
    }

    // ================= Pengawas (opsional) =================

    public function pengawasIndex(string $slug = '', $id = null): ResponseInterface
    {
        $ctx = $this->sesi($slug, (int) $id);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [, $jadwal] = $ctx;

        return $this->ok(
            array_map([$this, 'transformPengawas'], (new UjianPengawasModel())->untukJadwal((int) $jadwal['id'])),
            'Pengawas sesi ujian.'
        );
    }

    public function pengawasStore(string $slug = '', $id = null): ResponseInterface
    {
        $ctx = $this->sesi($slug, (int) $id);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [, $jadwal] = $ctx;

        $in     = $this->body();
        $guruId = (int) ($in['guru_id'] ?? 0);
        if ($guruId <= 0) {
            return $this->invalid(['guru_id' => 'Guru wajib dipilih.']);
        }
        if (! (new GuruModel())->find($guruId)) {
            return $this->missing('Guru tidak ditemukan.');
        }

        $model = new UjianPengawasModel();
        if ($model->sudahDitugaskan((int) $jadwal['id'], $guruId)) {
            return $this->failure('Guru itu sudah ditugaskan pada sesi ini.', 409);
        }

        // Guru tak bisa mengawasi dua ruang pada jam yang sama — diperiksa
        // lintas periode, sama seperti versi web.
        $bentrok = $model->bentrokGuru($guruId, $jadwal);
        if ($bentrok !== []) {
            return $this->failure('Guru itu sudah mengawasi sesi lain pada jam yang sama.', 409, [
                'bentrok' => array_map(static fn ($b) => [
                    'tanggal'     => $b['tanggal'],
                    'jam_mulai'   => $b['jam_mulai'] ? substr((string) $b['jam_mulai'], 0, 5) : null,
                    'jam_selesai' => $b['jam_selesai'] ? substr((string) $b['jam_selesai'], 0, 5) : null,
                    'nama_mapel'  => $b['nama_mapel'] ?? null,
                    'tingkat'     => $b['tingkat'],
                    'ruang'       => $b['ruang'] ?? null,
                ], $bentrok),
            ]);
        }

        $peran = strtolower(trim((string) ($in['peran'] ?? 'pengawas')));
        $model->insert([
            'jadwal_id'  => (int) $jadwal['id'],
            'guru_id'    => $guruId,
            // Ruang dikosongkan → ikut ruang sesi ujiannya.
            'ruang'      => $this->teks($in['ruang'] ?? null, 100) ?? $jadwal['ruang'],
            'peran'      => in_array($peran, UjianPengawasModel::PERAN, true) ? $peran : 'pengawas',
            'keterangan' => $this->teks($in['keterangan'] ?? null, 255),
        ]);

        $newId = (int) $model->getInsertID();
        master_data_changed('ujian_jadwal');
        $this->audit->record('create', 'ujian_pengawas', $newId, 'Tugaskan pengawas ujian (via mobile)');

        $baris = $model->withRelations()->where('ujian_pengawas.id', $newId)->first() ?? [];

        return $this->created($this->transformPengawas($baris), 'Pengawas ditugaskan.');
    }

    public function pengawasDestroy(string $slug = '', $id = null, $pengawasId = null): ResponseInterface
    {
        $ctx = $this->sesi($slug, (int) $id);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [, $jadwal] = $ctx;

        $model      = new UjianPengawasModel();
        $pengawasId = (int) $pengawasId;
        $baris      = $pengawasId > 0 ? $model->find($pengawasId) : null;
        if (! $baris || (int) $baris['jadwal_id'] !== (int) $jadwal['id']) {
            return $this->missing('Penugasan pengawas tidak ditemukan.');
        }

        $model->delete($pengawasId);
        master_data_changed('ujian_jadwal');
        $this->audit->record('delete', 'ujian_pengawas', $pengawasId, 'Lepas penugasan pengawas ujian (via mobile)');

        return $this->ok(null, 'Penugasan pengawas dilepas.');
    }

    // ================= Util =================

    /**
     * Periode dari slug + ?tp=, atau respons error siap kirim.
     *
     * @return array|ResponseInterface
     */
    private function periode(string $slug)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis ujian tidak dikenal.');
        }

        $periode = $this->periodeModel->untukTahun($jenis, $this->request->getGet('tp'));

        return $periode ?? $this->missing('Periode ujian tidak ditemukan.');
    }

    /**
     * Periode + satu sesi yang WAJIB milik periode itu.
     *
     * @return array{0:array,1:array}|ResponseInterface
     */
    private function sesi(string $slug, int $jadwalId)
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $jadwal = $jadwalId > 0 ? $this->fresh($jadwalId) : [];
        if ($jadwal === [] || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return $this->missing('Sesi ujian tidak ditemukan.');
        }

        return [$periode, $jadwal];
    }

    private function fresh(int $id): array
    {
        return $this->model->withRelations()->where('ujian_jadwal.id', $id)->first() ?? [];
    }

    private function transform(array $r): array
    {
        return [
            'id'           => (int) $r['id'],
            'periode_id'   => (int) $r['periode_id'],
            'mapel_id'     => ((int) ($r['mapel_id'] ?? 0)) ?: null,
            'kode_mapel'   => $r['kode_mapel'] ?? null,
            'nama_mapel'   => $r['nama_mapel'] ?? null,
            'tingkat'      => $r['tingkat'],
            'jurusan_id'   => ((int) ($r['jurusan_id'] ?? 0)) ?: null,
            'jurusan_kode' => $r['jurusan_kode'] ?? null,
            'shift'        => $r['shift'],
            'tanggal'      => $r['tanggal'],
            'jam_mulai'    => $r['jam_mulai'] ? substr((string) $r['jam_mulai'], 0, 5) : null,
            'jam_selesai'  => $r['jam_selesai'] ? substr((string) $r['jam_selesai'], 0, 5) : null,
            'ruang'        => $r['ruang'] ?? null,
            'keterangan'   => $r['keterangan'] ?? null,
        ];
    }

    private function transformPengawas(array $r): array
    {
        return [
            'id'         => (int) ($r['id'] ?? 0),
            'jadwal_id'  => (int) ($r['jadwal_id'] ?? 0),
            'guru_id'    => ((int) ($r['guru_id'] ?? 0)) ?: null,
            'guru_nama'  => $r['guru_nama'] ?? null,
            'kode_guru'  => $r['kode_guru'] ?? null,
            'peran'      => $r['peran'] ?? 'pengawas',
            'ruang'      => $r['ruang'] ?? null,
            'keterangan' => $r['keterangan'] ?? null,
        ];
    }

    private function teks($v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function tanggal($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);

        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }

    /** Terima "HH:MM" maupun "HH:MM:SS"; simpan sebagai "HH:MM:00". */
    private function jam($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v, $m)) {
            return null;
        }

        return $m[1] . ':' . $m[2] . ':00';
    }
}
