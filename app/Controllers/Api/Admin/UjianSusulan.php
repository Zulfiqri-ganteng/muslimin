<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\SiswaModel;
use App\Models\UjianJadwalModel;
use App\Models\UjianPeriodeModel;
use App\Models\UjianSusulanModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pendataan ketidakhadiran & pengelolaan ujian susulan (API).
 * Cermin App\Controllers\Admin\Ujian (tab Ketidakhadiran & Susulan).
 *
 * Rute:
 *   GET    /api/v1/admin/ujian/{slug}/ketidakhadiran?jadwal_id=&kelas_id=
 *   POST   /api/v1/admin/ujian/{slug}/ketidakhadiran
 *   GET    /api/v1/admin/ujian/{slug}/susulan?q=&kelas_id=&mapel_id=&status=&alasan=&per=&page=
 *   POST   /api/v1/admin/ujian/{slug}/susulan/jadwalkan
 *   POST   /api/v1/admin/ujian/{slug}/susulan/{id}/status
 *   DELETE /api/v1/admin/ujian/{slug}/susulan/{id}
 */
class UjianSusulan extends BaseApiController
{
    protected UjianSusulanModel $model;
    protected UjianPeriodeModel $periodeModel;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model        = new UjianSusulanModel();
        $this->periodeModel = new UjianPeriodeModel();
        $this->audit        = new AuditModel();
    }

    // ================= Ketidakhadiran =================

    /** Daftar siswa satu kelas pada satu sesi, lengkap dengan yang sudah tercatat. */
    public function ketidakhadiran(string $slug = ''): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $jadwalModel = new UjianJadwalModel();
        $jadwalId    = (int) $this->request->getGet('jadwal_id');
        $kelasId     = (int) $this->request->getGet('kelas_id');

        $jadwal = $jadwalId > 0
            ? $jadwalModel->withRelations()->where('ujian_jadwal.id', $jadwalId)->first()
            : null;
        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return $this->missing('Sesi ujian tidak ditemukan.');
        }

        $sasaran  = $jadwalModel->kelasSasaran($jadwal);
        $tercatat = $this->model->siswaTercatat($jadwalId);

        $kelas = null;
        foreach ($sasaran as $k) {
            if ((int) $k['id'] === $kelasId) {
                $kelas = $k;
                break;
            }
        }
        if ($kelasId > 0 && $kelas === null) {
            return $this->failure('Kelas itu bukan sasaran sesi ujian ini.', 422);
        }

        $siswa = [];
        if ($kelas !== null) {
            foreach ((new SiswaModel())->where('kelas_id', $kelasId)->where('status', 'aktif')
                ->orderBy('nama', 'ASC')->findAll() as $s) {
                $lama    = $tercatat[(int) $s['id']] ?? null;
                $siswa[] = [
                    'siswa_id'    => (int) $s['id'],
                    'nis'         => $s['nis'],
                    'nama'        => $s['nama'],
                    'tidak_hadir' => $lama !== null,
                    'alasan'      => $lama['alasan'] ?? 'alpa',
                    'keterangan'  => $lama['keterangan'] ?? null,
                    'status'      => $lama['status'] ?? null,
                    // Susulan yang sudah selesai tidak boleh dibatalkan dari sini.
                    'terkunci'    => ($lama['status'] ?? '') === 'selesai',
                ];
            }
        }

        return $this->ok([
            'kelas_sasaran'     => array_map(static fn ($k) => [
                'id'         => (int) $k['id'],
                'nama_kelas' => $k['nama_kelas'],
                'shift'      => $k['shift'],
            ], $sasaran),
            'kelas_terpilih'    => $kelas ? (int) $kelas['id'] : null,
            'siswa'             => $siswa,
            'jumlah_tidak_hadir'=> count($tercatat),
            'alasan_tersedia'   => UjianSusulanModel::ALASAN,
        ]);
    }

    /**
     * Simpan pendataan ketidakhadiran satu kelas.
     *
     * Daftar siswa diambil ULANG dari database, bukan dari kiriman klien,
     * sehingga siswa kelas lain yang dititipkan ke payload diabaikan.
     */
    public function simpanKetidakhadiran(string $slug = ''): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $in          = $this->body();
        $jadwalModel = new UjianJadwalModel();
        $jadwalId    = (int) ($in['jadwal_id'] ?? 0);
        $kelasId     = (int) ($in['kelas_id'] ?? 0);

        $jadwal = $jadwalId > 0 ? $jadwalModel->find($jadwalId) : null;
        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return $this->missing('Sesi ujian tidak ditemukan.');
        }

        $sasaran = array_map('intval', array_column($jadwalModel->kelasSasaran($jadwal), 'id'));
        if ($kelasId <= 0 || ! in_array($kelasId, $sasaran, true)) {
            return $this->failure('Kelas itu bukan sasaran sesi ujian ini.', 422);
        }

        $siswaKelas = (new SiswaModel())->where('kelas_id', $kelasId)->where('status', 'aktif')->findAll();
        if ($siswaKelas === []) {
            return $this->failure('Kelas ini belum punya siswa aktif.', 422);
        }

        // Payload: [{siswa_id, alasan?, keterangan?}, ...] — hanya yang TIDAK hadir.
        $kirim = [];
        foreach ((array) ($in['tidak_hadir'] ?? []) as $baris) {
            if (is_array($baris)) {
                $sid = (int) ($baris['siswa_id'] ?? 0);
                if ($sid > 0) {
                    $kirim[$sid] = $baris;
                }
            } elseif ((int) $baris > 0) {
                $kirim[(int) $baris] = [];      // bentuk ringkas: sekadar daftar id
            }
        }

        $tercatat = $this->model->siswaTercatat($jadwalId);
        $tambah   = 0;
        $ubah     = 0;
        $hapus    = 0;
        $kunci    = 0;

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        foreach ($siswaKelas as $s) {
            $sid  = (int) $s['id'];
            $lama = $tercatat[$sid] ?? null;

            if (isset($kirim[$sid])) {
                $alasan = strtolower(trim((string) ($kirim[$sid]['alasan'] ?? 'alpa')));
                if (! in_array($alasan, UjianSusulanModel::ALASAN, true)) {
                    $alasan = 'alpa';
                }

                $data = [
                    'periode_id'    => (int) $periode['id'],
                    'mapel_id'      => $jadwal['mapel_id'],
                    'tanggal_ujian' => $jadwal['tanggal'],
                    'alasan'        => $alasan,
                    'keterangan'    => $this->teks($kirim[$sid]['keterangan'] ?? null, 255),
                ];
                // Status yang sudah maju tidak dimundurkan jadi 'belum'.
                if (! $lama || $lama['status'] === 'belum') {
                    $data['status'] = 'belum';
                }

                $this->model->catat($jadwalId, $sid, $data) === 'update' ? $ubah++ : $tambah++;
                continue;
            }

            if ($lama) {
                if ($lama['status'] === 'selesai') {
                    $kunci++;                    // riwayat pelaksanaan dijaga
                    continue;
                }
                $this->model->delete($lama['id']);
                $hapus++;
            }
        }

        $db->transComplete();

        master_data_changed('ujian_susulan');
        $this->audit->record('update', 'ujian_susulan', null, 'Data ketidakhadiran ujian (via mobile)');

        return $this->ok([
            'baru'          => $tambah,
            'diperbarui'    => $ubah,
            'dibatalkan'    => $hapus,
            'dipertahankan' => $kunci,
        ], "Tersimpan: {$tambah} baru, {$ubah} diperbarui, {$hapus} dibatalkan.");
    }

    // ================= Susulan =================

    public function index(string $slug = ''): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $per = (int) $this->request->getGet('per');
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 20;
        }
        $page = max(1, (int) $this->request->getGet('page'));

        $q       = trim((string) $this->request->getGet('q'));
        $kelasId = (int) $this->request->getGet('kelas_id');
        $mapelId = (int) $this->request->getGet('mapel_id');
        $status  = (string) $this->request->getGet('status');
        $alasan  = (string) $this->request->getGet('alasan');
        if (! in_array($status, UjianSusulanModel::STATUS, true)) {
            $status = '';
        }
        if (! in_array($alasan, UjianSusulanModel::ALASAN, true)) {
            $alasan = '';
        }

        $saring = function () use ($periode, $q, $kelasId, $mapelId, $status, $alasan) {
            $b = $this->model->withRelations()->where('ujian_susulan.periode_id', (int) $periode['id']);
            if ($q !== '') {
                $b = $b->groupStart()->like('siswa.nama', $q)->orLike('siswa.nis', $q)->groupEnd();
            }
            if ($kelasId > 0) {
                $b = $b->where('siswa.kelas_id', $kelasId);
            }
            if ($mapelId > 0) {
                $b = $b->where('ujian_susulan.mapel_id', $mapelId);
            }
            if ($status !== '') {
                $b = $b->where('ujian_susulan.status', $status);
            }
            if ($alasan !== '') {
                $b = $b->where('ujian_susulan.alasan', $alasan);
            }

            return $b->orderBy('ujian_susulan.tanggal_ujian', 'ASC')
                ->orderBy('kelas.nama_kelas', 'ASC')
                ->orderBy('siswa.nama', 'ASC');
        };

        $total = $saring()->countAllResults();
        $rows  = $saring()->paginate($per, 'default', $page);

        return $this->collection(
            array_map([$this, 'transform'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $total],
            'Daftar ujian susulan.'
        );
    }

    /**
     * Jadwalkan susulan untuk satu atau banyak baris sekaligus.
     *
     * Baris berstatus `selesai` DILEWATI — susulannya sudah dilaksanakan,
     * jadi tidak boleh tergeser oleh penjadwalan massal.
     */
    public function jadwalkan(string $slug = ''): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $in  = $this->body();
        $ids = array_values(array_filter(array_map('intval', (array) ($in['ids'] ?? []))));
        if ($ids === []) {
            return $this->invalid(['ids' => 'Pilih minimal satu baris untuk dijadwalkan.']);
        }

        $tanggal = $this->tanggal($in['tanggal_susulan'] ?? null);
        if ($tanggal === null) {
            return $this->invalid(['tanggal_susulan' => 'Tanggal susulan wajib diisi (format YYYY-MM-DD).']);
        }

        $guruId = (int) ($in['pengawas_guru_id'] ?? 0) ?: null;
        if ($guruId !== null && ! (new GuruModel())->find($guruId)) {
            return $this->missing('Guru pengawas tidak ditemukan.');
        }

        $isi = [
            'tanggal_susulan'  => $tanggal,
            'jam_susulan'      => $this->jam($in['jam_susulan'] ?? null),
            'ruang_susulan'    => $this->teks($in['ruang_susulan'] ?? null, 100),
            'pengawas_guru_id' => $guruId,
            'status'           => 'dijadwalkan',
        ];

        $ubah  = 0;
        $lewat = 0;
        $asing = 0;

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        foreach ($ids as $id) {
            $baris = $this->model->find($id);
            if (! $baris || (int) $baris['periode_id'] !== (int) $periode['id']) {
                $asing++;
                continue;
            }
            if ($baris['status'] === 'selesai') {
                $lewat++;
                continue;
            }
            $this->model->update($id, $isi);
            $ubah++;
        }

        $db->transComplete();

        master_data_changed('ujian_susulan');
        $this->audit->record('update', 'ujian_susulan', null, "Jadwalkan {$ubah} ujian susulan (via mobile)");

        return $this->ok([
            'dijadwalkan'     => $ubah,
            'dilewati_selesai'=> $lewat,
            'diabaikan'       => $asing,
        ], "{$ubah} susulan dijadwalkan.");
    }

    public function status(string $slug = '', $id = null): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $id    = (int) $id;
        $baris = $id > 0 ? $this->model->find($id) : null;
        if (! $baris || (int) $baris['periode_id'] !== (int) $periode['id']) {
            return $this->missing('Data susulan tidak ditemukan.');
        }

        $in     = $this->body();
        $status = (string) ($in['status'] ?? '');
        if (! in_array($status, UjianSusulanModel::STATUS, true)) {
            return $this->invalid(['status' => 'Status tidak valid.']);
        }

        // Tanggal pelaksanaan hanya bermakna untuk susulan yang sudah jalan.
        $data = ['status' => $status];
        $data['tanggal_pelaksanaan'] = $status === 'selesai'
            ? ($this->tanggal($in['tanggal_pelaksanaan'] ?? null) ?? date('Y-m-d'))
            : null;

        $this->model->update($id, $data);

        master_data_changed('ujian_susulan');
        $this->audit->record('update', 'ujian_susulan', $id, 'Ubah status susulan jadi ' . $status . ' (via mobile)');

        return $this->ok($this->transform($this->fresh($id)), 'Status susulan diperbarui.');
    }

    public function destroy(string $slug = '', $id = null): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $id    = (int) $id;
        $baris = $id > 0 ? $this->model->find($id) : null;
        if (! $baris || (int) $baris['periode_id'] !== (int) $periode['id']) {
            return $this->missing('Data susulan tidak ditemukan.');
        }

        $this->model->delete($id);
        master_data_changed('ujian_susulan');
        $this->audit->record('delete', 'ujian_susulan', $id, 'Hapus catatan ketidakhadiran/susulan (via mobile)');

        return $this->ok(null, 'Catatan susulan dihapus.');
    }

    // ================= Util =================

    /** @return array|ResponseInterface */
    private function periode(string $slug)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis ujian tidak dikenal.');
        }

        $periode = $this->periodeModel->untukTahun($jenis, $this->request->getGet('tp'));

        return $periode ?? $this->missing('Periode ujian tidak ditemukan.');
    }

    private function fresh(int $id): array
    {
        return $this->model->withRelations()->where('ujian_susulan.id', $id)->first() ?? [];
    }

    private function transform(array $r): array
    {
        return [
            'id'                  => (int) $r['id'],
            'periode_id'          => (int) $r['periode_id'],
            'jadwal_id'           => ((int) ($r['jadwal_id'] ?? 0)) ?: null,
            'siswa_id'            => (int) $r['siswa_id'],
            'nis'                 => $r['nis'] ?? null,
            'siswa_nama'          => $r['siswa_nama'] ?? null,
            'nama_kelas'          => $r['nama_kelas'] ?? null,
            'mapel_id'            => ((int) ($r['mapel_id'] ?? 0)) ?: null,
            'nama_mapel'          => $r['nama_mapel'] ?? null,
            'tanggal_ujian'       => $r['tanggal_ujian'] ?? null,
            'alasan'              => $r['alasan'],
            'keterangan'          => $r['keterangan'] ?? null,
            'status'              => $r['status'],
            'status_label'        => UjianSusulanModel::STATUS_LABEL[$r['status']] ?? $r['status'],
            'tanggal_susulan'     => $r['tanggal_susulan'] ?? null,
            'jam_susulan'         => $r['jam_susulan'] ? substr((string) $r['jam_susulan'], 0, 5) : null,
            'ruang_susulan'       => $r['ruang_susulan'] ?? null,
            'pengawas_guru_id'    => ((int) ($r['pengawas_guru_id'] ?? 0)) ?: null,
            'pengawas_nama'       => $r['pengawas_nama'] ?? null,
            'tanggal_pelaksanaan' => $r['tanggal_pelaksanaan'] ?? null,
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

    private function jam($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v, $m)) {
            return null;
        }

        return $m[1] . ':' . $m[2] . ':00';
    }
}
