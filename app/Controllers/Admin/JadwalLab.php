<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalLabModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\LabModel;
use App\Models\MataPelajaranModel;

/**
 * Jadwal pemakaian lab (modul terpisah dari Penjadwalan KBM).
 *
 * Mode "lab"  : pilih lab → kelola slot (anti bentrok: 1 lab 1 slot; guru tak
 *               boleh dijadwalkan di 2 lab pada slot yang sama).
 * Mode "guru" : pilih guru → lihat jadwal praktik guru lintas lab (baca saja).
 */
class JadwalLab extends BaseController
{
    protected JadwalLabModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JadwalLabModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $labId  = (int) $this->request->getGet('lab_id');
        $guruId = (int) $this->request->getGet('guru_id');

        $rows = [];
        if ($labId > 0) {
            $rows = $this->model->withRelations()->where('jadwal_lab.lab_id', $labId)
                ->orderBy('hari.urutan', 'ASC')->orderBy('jam_pelajaran.waktu_mulai', 'ASC')->findAll();
        } elseif ($guruId > 0) {
            $rows = $this->model->withRelations()->where('jadwal_lab.guru_id', $guruId)
                ->orderBy('hari.urutan', 'ASC')->orderBy('jam_pelajaran.waktu_mulai', 'ASC')->findAll();
        }

        return view('admin/jadwal_lab/index', [
            'title'     => 'Jadwal Lab',
            'rows'      => $rows,
            'labId'     => $labId,
            'guruId'    => $guruId,
            'labOpts'   => (new LabModel())->options(),
            'guruOpts'  => (new GuruModel())->options(),
            'kelasOpts' => (new KelasModel())->options(),
            'mapelOpts' => (new MataPelajaranModel())->options(),
            'hariOpts'  => $this->hariOptions(),
            'jamOpts'   => $this->jamOptions(),
        ]);
    }

    public function store()
    {
        $labId  = (int) $this->request->getPost('lab_id');
        $hariId = (int) $this->request->getPost('hari_id');
        $jamId  = (int) $this->request->getPost('jam_id');
        $guruId = (int) $this->request->getPost('guru_id') ?: null;

        $back = redirect()->to(site_url('admin/jadwal-lab') . ($labId ? '?lab_id=' . $labId : ''));

        if ($labId <= 0 || $hariId <= 0 || $jamId <= 0) {
            return $back->with('error', 'Lab, hari, dan jam wajib dipilih.');
        }
        // R-lab: satu lab tak boleh dipakai dua kegiatan di slot yang sama.
        if ($this->model->slotTerpakai($labId, $hariId, $jamId)) {
            return $back->with('error', 'Slot lab itu sudah terisi. Pilih jam/hari lain.');
        }
        // R-guru: guru tak boleh dijadwalkan di lab lain pada slot yang sama.
        if ($guruId && $this->model->where('hari_id', $hariId)->where('jam_id', $jamId)
            ->where('guru_id', $guruId)->where('lab_id !=', $labId)->countAllResults() > 0) {
            return $back->with('error', 'Guru sudah dijadwalkan di lab lain pada slot ini.');
        }

        $this->model->insert([
            'lab_id'     => $labId,
            'hari_id'    => $hariId,
            'jam_id'     => $jamId,
            'guru_id'    => $guruId,
            'kelas_id'   => (int) $this->request->getPost('kelas_id') ?: null,
            'mapel_id'   => (int) $this->request->getPost('mapel_id') ?: null,
            'kegiatan'   => trim((string) $this->request->getPost('kegiatan')) ?: null,
            'keterangan' => trim((string) $this->request->getPost('keterangan')) ?: null,
        ]);
        $this->audit->record('create', 'jadwal_lab', $this->model->getInsertID(), 'Tambah jadwal lab');

        return $back->with('success', 'Jadwal ditambahkan.');
    }

    public function delete($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        $labId = $row['lab_id'] ?? 0;
        if ($row) {
            $this->model->delete($id);
            $this->audit->record('delete', 'jadwal_lab', $id, 'Hapus jadwal lab');
        }

        return redirect()->to(site_url('admin/jadwal-lab') . ($labId ? '?lab_id=' . $labId : ''))
            ->with('success', 'Jadwal dihapus.');
    }

    /** Opsi hari aktif [id => nama]. */
    private function hariOptions(): array
    {
        $out = [];
        foreach ((new HariModel())->where('aktif', 1)->orderBy('urutan', 'ASC')->findAll() as $h) {
            $out[$h['id']] = $h['nama'];
        }

        return $out;
    }

    /** Opsi jam non-istirahat [id => "Jam N (hh:mm–hh:mm) Shift"]. */
    private function jamOptions(): array
    {
        $out = [];
        foreach ((new JamPelajaranModel())->where('is_istirahat', 0)
            ->orderBy('shift', 'ASC')->orderBy('waktu_mulai', 'ASC')->findAll() as $j) {
            $out[$j['id']] = 'Jam ' . $j['jam_ke'] . ' (' . substr((string) $j['waktu_mulai'], 0, 5)
                . '–' . substr((string) $j['waktu_selesai'], 0, 5) . ') ' . ucfirst((string) $j['shift']);
        }

        return $out;
    }
}
