<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\JadwalUkkModel;
use App\Models\KelasModel;
use App\Models\PaketSoalUkkModel;
use App\Models\PesertaUkkModel;
use App\Models\SiswaModel;

/**
 * Pendaftaran peserta UKK (workflow, bukan master data).
 *
 * Alur: pilih paket soal + kelas → sistem tampilkan siswa aktif di kelas itu
 * yang BELUM terdaftar pada paket soal tsb → centang yang ikut → daftarkan
 * sekaligus (no_peserta auto per baris, format "UKK-{KODE}-001").
 */
class PesertaUkk extends BaseController
{
    protected PesertaUkkModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PesertaUkkModel();
        $this->audit = new AuditModel();
    }

    private function perPage(): int
    {
        $per = (int) $this->request->getGet('per');

        return in_array($per, [10, 20, 30, 40, 50], true) ? $per : 10;
    }

    public function index()
    {
        $q           = trim((string) $this->request->getGet('q'));
        $paketId     = (int) $this->request->getGet('paket_soal_id');
        $status      = trim((string) $this->request->getGet('status'));
        if (! in_array($status, PesertaUkkModel::STATUS, true)) {
            $status = '';
        }
        $per  = $this->perPage();
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));

        $builder = $this->model->withRelations();
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('siswa.nama', $q)->orLike('siswa.nis', $q)->orLike('peserta_ukk.no_peserta', $q)
                ->groupEnd();
        }
        if ($paketId > 0) {
            $builder = $builder->where('peserta_ukk.paket_soal_id', $paketId);
        }
        if ($status !== '') {
            $builder = $builder->where('peserta_ukk.status', $status);
        }
        $rows  = $builder->orderBy('siswa.nama', 'ASC')->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        $ringkasanBuilder = fn () => $paketId > 0 ? $this->model->where('paket_soal_id', $paketId) : $this->model;
        $lulus     = (clone $ringkasanBuilder())->where('status', 'lulus')->countAllResults();
        $tidakLulus = (clone $ringkasanBuilder())->where('status', 'tidak_lulus')->countAllResults();
        $total     = (clone $ringkasanBuilder())->countAllResults();

        return view('admin/peserta_ukk/index', [
            'title'       => 'Peserta UKK',
            'rows'        => $rows,
            'pager'       => $pager,
            'q'           => $q,
            'paketId'     => $paketId,
            'status'      => $status,
            'per'         => $per,
            'total'       => $total,
            'lulus'       => $lulus,
            'tidakLulus'  => $tidakLulus,
            'paketOpts'   => (new PaketSoalUkkModel())->options(),
            'statusList'  => PesertaUkkModel::STATUS,
        ]);
    }

    /** Form pilih paket soal + kelas, lalu tampilkan checklist siswa yang belum terdaftar. */
    public function daftarkanForm()
    {
        $paketId = (int) $this->request->getGet('paket_soal_id');
        $kelasId = (int) $this->request->getGet('kelas_id');

        $siswaTersedia = [];
        $paket         = null;
        $sudahTerdaftarCount = 0;

        if ($paketId > 0 && $kelasId > 0) {
            $paket = (new PaketSoalUkkModel())->find($paketId);
            if ($paket) {
                $terdaftarIds = array_column(
                    $this->model->select('siswa_id')->where('paket_soal_id', $paketId)->findAll(),
                    'siswa_id'
                );
                $siswaModel = new SiswaModel();
                $b = $siswaModel->where('kelas_id', $kelasId)->where('status', 'aktif');
                if ($terdaftarIds !== []) {
                    $b = $b->whereNotIn('id', $terdaftarIds);
                }
                $siswaTersedia = $b->orderBy('nama', 'ASC')->findAll();
                $sudahTerdaftarCount = count($terdaftarIds);
            }
        }

        return view('admin/peserta_ukk/daftarkan', [
            'title'               => 'Daftarkan Peserta UKK',
            'paketId'             => $paketId,
            'kelasId'             => $kelasId,
            'paket'               => $paket,
            'siswaTersedia'       => $siswaTersedia,
            'sudahTerdaftarCount' => $sudahTerdaftarCount,
            'paketOpts'           => (new PaketSoalUkkModel())->options(),
            'kelasOpts'           => (new KelasModel())->options(),
            'jadwalOpts'          => $paketId > 0 ? (new JadwalUkkModel())->optionsUntukPaket($paketId) : [],
        ]);
    }

    /** Daftarkan siswa terpilih sekaligus. */
    public function daftarkanStore()
    {
        $paketId  = (int) $this->request->getPost('paket_soal_id');
        $jadwalId = (int) $this->request->getPost('jadwal_ukk_id') ?: null;
        $siswaIds = array_values(array_filter(array_map('intval', (array) $this->request->getPost('siswa_ids'))));

        $paket = $paketId ? (new PaketSoalUkkModel())->find($paketId) : null;
        if (! $paket) {
            return redirect()->back()->with('error', 'Paket soal tidak ditemukan.');
        }
        if ($siswaIds === []) {
            return redirect()->back()->with('error', 'Pilih minimal satu siswa untuk didaftarkan.');
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        $inserted = 0;
        $skipped  = 0;
        foreach ($siswaIds as $sid) {
            // Cek termasuk yang soft-deleted, agar pendaftaran lama bisa dipulihkan
            // alih-alih bentrok dengan UNIQUE(siswa_id, paket_soal_id).
            $existing = $this->model->withDeleted()->where(['siswa_id' => $sid, 'paket_soal_id' => $paketId])->first();
            if ($existing && $existing['deleted_at'] === null) {
                $skipped++;
                continue;
            }

            $noPeserta = $this->model->nomorBerikutnya($paket['kode']);
            $data = [
                'siswa_id'        => $sid,
                'paket_soal_id'   => $paketId,
                'jadwal_ukk_id'   => $jadwalId,
                'tahun_ajaran_id' => $paket['tahun_ajaran_id'],
                'no_peserta'      => $noPeserta,
                'status'          => 'terdaftar',
            ];

            if ($existing) {
                // Pulihkan pendaftaran yang dulu dihapus.
                $this->model->protect(false);
                $this->model->update($existing['id'], $data + ['deleted_at' => null]);
                $this->model->protect(true);
            } else {
                $this->model->insert($data);
            }
            $inserted++;
        }

        $db->transComplete();

        master_data_changed('peserta_ukk');
        $this->audit->record('create', 'peserta_ukk', null, "Daftarkan {$inserted} peserta UKK untuk paket {$paket['kode']}" . ($skipped ? " ({$skipped} sudah terdaftar, dilewati)" : ''));

        $msg = "{$inserted} peserta berhasil didaftarkan.";
        if ($skipped > 0) {
            $msg .= " {$skipped} siswa dilewati (sudah terdaftar).";
        }

        return redirect()->to(site_url('admin/peserta-ukk?paket_soal_id=' . $paketId))->with('success', $msg);
    }

    /** Ubah status kehadiran/hasil peserta secara manual. */
    public function status($id)
    {
        $id     = (int) $id;
        $peserta = $this->model->find($id);
        if (! $peserta) {
            return redirect()->to(site_url('admin/peserta-ukk'))->with('error', 'Peserta tidak ditemukan.');
        }

        $status = strtolower(trim((string) $this->request->getPost('status')));
        if (! in_array($status, PesertaUkkModel::STATUS, true)) {
            return redirect()->to(site_url('admin/peserta-ukk'))->with('error', 'Status tidak valid.');
        }

        $this->model->update($id, ['status' => $status]);
        master_data_changed('peserta_ukk');
        $this->audit->record('update', 'peserta_ukk', $id, 'Ubah status peserta UKK jadi ' . $status);

        return redirect()->to(site_url('admin/peserta-ukk'))->with('success', 'Status peserta diperbarui.');
    }

    public function delete($id)
    {
        $id      = (int) $id;
        $peserta = $this->model->find($id);
        if (! $peserta) {
            return redirect()->to(site_url('admin/peserta-ukk'))->with('error', 'Peserta tidak ditemukan.');
        }

        $this->model->delete($id);
        master_data_changed('peserta_ukk');
        $this->audit->record('delete', 'peserta_ukk', $id, 'Hapus pendaftaran peserta UKK');

        return redirect()->to(site_url('admin/peserta-ukk'))->with('success', 'Pendaftaran peserta dihapus.');
    }
}
