<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\JadwalUkkModel;
use App\Models\JadwalUkkPengujiModel;
use App\Models\PaketSoalUkkModel;
use App\Models\PengujiEksternalModel;
use App\Models\TahunAjaranModel;
use App\Models\TempatUjiModel;

/**
 * Jadwal pelaksanaan UKK (workflow) + penugasan penguji (internal/eksternal)
 * per jadwal lewat sub-halaman `penguji`.
 */
class JadwalUkk extends BaseController
{
    protected JadwalUkkModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JadwalUkkModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    public function index()
    {
        $q       = trim((string) $this->request->getGet('q'));
        $paketId = (int) $this->request->getGet('paket_soal_id');
        $per     = $this->perPage();
        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));

        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('paket_soal_ukk.nama', $q)->orLike('paket_soal_ukk.kode', $q)->orLike('tempat_uji.nama', $q)
                ->groupEnd();
        }
        if ($paketId > 0) {
            $builder = $builder->where('jadwal_ukk.paket_soal_id', $paketId);
        }
        $rows  = $builder->orderBy('jadwal_ukk.tanggal_mulai', 'DESC')->orderBy('jadwal_ukk.id', 'DESC')
            ->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        // Jumlah penguji per jadwal (query terpisah, digabung di PHP).
        $jumlahPenguji = [];
        $ids = array_column($rows, 'id');
        if ($ids !== []) {
            foreach ((new JadwalUkkPengujiModel())->select('jadwal_ukk_id, COUNT(*) AS jumlah')
                ->whereIn('jadwal_ukk_id', $ids)->groupBy('jadwal_ukk_id')->findAll() as $r) {
                $jumlahPenguji[(int) $r['jadwal_ukk_id']] = (int) $r['jumlah'];
            }
        }

        return view('admin/jadwal_ukk/index', [
            'title'         => 'Jadwal UKK',
            'rows'          => $rows,
            'jumlahPenguji' => $jumlahPenguji,
            'pager'         => $pager,
            'q'             => $q,
            'paketId'       => $paketId,
            'per'           => $per,
            'paketOpts'     => (new PaketSoalUkkModel())->options(),
            'tempatOpts'    => (new TempatUjiModel())->options(),
            'tahunOpts'     => (new TahunAjaranModel())->options(),
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed('jadwal_ukk');
        $this->audit->record('create', 'jadwal_ukk', $this->model->getInsertID(), 'Tambah jadwal UKK');

        return redirect()->to(site_url('admin/jadwal-ukk'))->with('success', 'Jadwal UKK ditambahkan.');
    }

    /** @param int|string $id */
    public function update($id)
    {
        $id         = (int) $id;
        $data       = $this->collect();
        $data['id'] = $id;
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        master_data_changed('jadwal_ukk');
        $this->audit->record('update', 'jadwal_ukk', $id, 'Ubah jadwal UKK');

        return redirect()->to(site_url('admin/jadwal-ukk'))->with('success', 'Jadwal UKK diperbarui.');
    }

    /** @param int|string $id */
    public function delete($id)
    {
        $id     = (int) $id;
        $jadwal = $this->model->find($id);
        if (! $jadwal) {
            return redirect()->to(site_url('admin/jadwal-ukk'))->with('error', 'Jadwal tidak ditemukan.');
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        // Pivot penugasan penguji: hapus total (tak ada nilai riwayat berdiri sendiri).
        $db->table('jadwal_ukk_penguji')->where('jadwal_ukk_id', $id)->delete();
        // Peserta yang sudah terdaftar TETAP ada, hanya lepas rujukan jadwalnya.
        $db->table('peserta_ukk')->where('jadwal_ukk_id', $id)->update(['jadwal_ukk_id' => null]);
        $this->model->delete($id);
        $db->transComplete();

        master_data_changed('jadwal_ukk');
        $this->audit->record('delete', 'jadwal_ukk', $id, 'Hapus jadwal UKK');

        return redirect()->to(site_url('admin/jadwal-ukk'))->with('success', 'Jadwal UKK dihapus.');
    }

    private function collect(): array
    {
        $post = fn (string $k) => trim((string) $this->request->getPost($k));

        return [
            'paket_soal_id'   => (int) $this->request->getPost('paket_soal_id') ?: null,
            'tempat_uji_id'   => (int) $this->request->getPost('tempat_uji_id') ?: null,
            'tahun_ajaran_id' => (int) $this->request->getPost('tahun_ajaran_id') ?: null,
            'tanggal_mulai'   => $post('tanggal_mulai') ?: date('Y-m-d'),
            'tanggal_selesai' => $post('tanggal_selesai') ?: null,
            'sesi'            => $post('sesi') ?: null,
            'keterangan'      => $post('keterangan') ?: null,
        ];
    }

    // =================================================================
    // Penugasan penguji (internal/eksternal) per jadwal
    // =================================================================

    /** @param int|string $id */
    public function penguji($id)
    {
        $id     = (int) $id;
        $jadwal = $this->model->withRelations()->where('jadwal_ukk.id', $id)->first();
        if (! $jadwal) {
            return redirect()->to(site_url('admin/jadwal-ukk'))->with('error', 'Jadwal tidak ditemukan.');
        }

        return view('admin/jadwal_ukk/penguji', [
            'title'         => 'Penguji — ' . ($jadwal['paket_nama'] ?? 'Jadwal UKK'),
            'jadwal'        => $jadwal,
            'list'          => (new JadwalUkkPengujiModel())->forJadwal($id),
            'guruOpts'      => (new GuruModel())->options(),
            'eksternalOpts' => (new PengujiEksternalModel())->options(),
        ]);
    }

    /** @param int|string $id */
    public function pengujiStore($id)
    {
        $id     = (int) $id;
        $jadwal = $this->model->find($id);
        if (! $jadwal) {
            return redirect()->to(site_url('admin/jadwal-ukk'))->with('error', 'Jadwal tidak ditemukan.');
        }

        $tipe   = strtolower(trim((string) $this->request->getPost('tipe')));
        $tipe   = in_array($tipe, ['internal', 'eksternal'], true) ? $tipe : 'internal';
        $peran  = strtolower(trim((string) $this->request->getPost('peran')));
        $peran  = in_array($peran, ['ketua', 'anggota'], true) ? $peran : 'anggota';
        $guruId = $tipe === 'internal' ? ((int) $this->request->getPost('guru_id') ?: null) : null;
        $eksId  = $tipe === 'eksternal' ? ((int) $this->request->getPost('penguji_eksternal_id') ?: null) : null;

        $backUrl = site_url('admin/jadwal-ukk/penguji/' . $id);
        if ($tipe === 'internal' && ! $guruId) {
            return redirect()->to($backUrl)->with('error', 'Pilih guru untuk penguji internal.');
        }
        if ($tipe === 'eksternal' && ! $eksId) {
            return redirect()->to($backUrl)->with('error', 'Pilih penguji eksternal.');
        }

        $pengujiModel = new JadwalUkkPengujiModel();
        $dup = $pengujiModel->where('jadwal_ukk_id', $id)->where('tipe', $tipe);
        $dup = $tipe === 'internal' ? $dup->where('guru_id', $guruId) : $dup->where('penguji_eksternal_id', $eksId);
        if ($dup->countAllResults() > 0) {
            return redirect()->to($backUrl)->with('error', 'Penguji ini sudah ditugaskan pada jadwal ini.');
        }

        $data = [
            'jadwal_ukk_id'        => $id,
            'tipe'                 => $tipe,
            'guru_id'              => $guruId,
            'penguji_eksternal_id' => $eksId,
            'peran'                => $peran,
        ];
        if (! $pengujiModel->insert($data)) {
            return redirect()->to($backUrl)->with('error', implode(' ', $pengujiModel->errors()));
        }
        $this->audit->record('create', 'jadwal_ukk_penguji', $pengujiModel->getInsertID(), 'Tugaskan penguji ' . $tipe . ' pada jadwal UKK #' . $id);

        return redirect()->to($backUrl)->with('success', 'Penguji ditugaskan.');
    }

    /** @param int|string $id */
    public function pengujiHapus($id, $pengujiId)
    {
        $id        = (int) $id;
        $pengujiId = (int) $pengujiId;
        (new JadwalUkkPengujiModel())->delete($pengujiId);
        $this->audit->record('delete', 'jadwal_ukk_penguji', $pengujiId, 'Lepas penugasan penguji dari jadwal UKK #' . $id);

        return redirect()->to(site_url('admin/jadwal-ukk/penguji/' . $id))->with('success', 'Penugasan penguji dihapus.');
    }
}
