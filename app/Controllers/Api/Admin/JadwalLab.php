<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\JadwalLabModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Jadwal pemakaian lab (API). Cermin App\Controllers\Admin\JadwalLab.
 * Rute: /api/v1/admin/jadwal-lab
 *   GET ?lab_id=  → jadwal satu lab; GET ?guru_id= → jadwal praktik guru.
 */
class JadwalLab extends BaseApiController
{
    protected JadwalLabModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JadwalLabModel();
        $this->audit = new AuditModel();
    }

    public function index(): ResponseInterface
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

        return $this->collection(array_map([$this, 'transform'], $rows), ['lab_id' => $labId, 'guru_id' => $guruId]);
    }

    public function store(): ResponseInterface
    {
        $in     = $this->body();
        $labId  = (int) ($in['lab_id'] ?? 0);
        $hariId = (int) ($in['hari_id'] ?? 0);
        $jamId  = (int) ($in['jam_id'] ?? 0);
        $guruId = (int) ($in['guru_id'] ?? 0) ?: null;

        if ($labId <= 0 || $hariId <= 0 || $jamId <= 0) {
            return $this->invalid(['slot' => 'Lab, hari, dan jam wajib dipilih.']);
        }
        if ($this->model->slotTerpakai($labId, $hariId, $jamId)) {
            return $this->failure('Slot lab itu sudah terisi.', 409);
        }
        if ($guruId && $this->model->where('hari_id', $hariId)->where('jam_id', $jamId)
            ->where('guru_id', $guruId)->where('lab_id !=', $labId)->countAllResults() > 0) {
            return $this->failure('Guru sudah dijadwalkan di lab lain pada slot ini.', 409);
        }

        $this->model->insert([
            'lab_id'     => $labId,
            'hari_id'    => $hariId,
            'jam_id'     => $jamId,
            'guru_id'    => $guruId,
            'kelas_id'   => (int) ($in['kelas_id'] ?? 0) ?: null,
            'mapel_id'   => (int) ($in['mapel_id'] ?? 0) ?: null,
            'kegiatan'   => trim((string) ($in['kegiatan'] ?? '')) ?: null,
            'keterangan' => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ]);
        $newId = (int) $this->model->getInsertID();
        $this->audit->record('create', 'jadwal_lab', $newId, 'Tambah jadwal lab (via mobile)');

        return $this->created($this->transform($this->fresh($newId)), 'Jadwal ditambahkan.');
    }

    public function destroy($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Jadwal tidak ditemukan.');
        }
        $this->model->delete($id);
        $this->audit->record('delete', 'jadwal_lab', $id, 'Hapus jadwal lab (via mobile)');

        return $this->ok(null, 'Jadwal dihapus.');
    }

    private function fresh(int $id): array
    {
        return $this->model->withRelations()->where('jadwal_lab.id', $id)->first() ?? [];
    }

    private function transform(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'lab_id'        => ((int) ($r['lab_id'] ?? 0)) ?: null,
            'lab_nama'      => $r['lab_nama'] ?? null,
            'hari_id'       => ((int) ($r['hari_id'] ?? 0)) ?: null,
            'hari_nama'     => $r['hari_nama'] ?? null,
            'jam_id'        => ((int) ($r['jam_id'] ?? 0)) ?: null,
            'jam_ke'        => isset($r['jam_ke']) ? (int) $r['jam_ke'] : null,
            'waktu_mulai'   => isset($r['waktu_mulai']) ? substr((string) $r['waktu_mulai'], 0, 5) : null,
            'waktu_selesai' => isset($r['waktu_selesai']) ? substr((string) $r['waktu_selesai'], 0, 5) : null,
            'guru_id'       => ((int) ($r['guru_id'] ?? 0)) ?: null,
            'guru_nama'     => $r['guru_nama'] ?? null,
            'nama_kelas'    => $r['nama_kelas'] ?? null,
            'nama_mapel'    => $r['nama_mapel'] ?? null,
            'kegiatan'      => $r['kegiatan'] ?? null,
            'keterangan'    => $r['keterangan'] ?? null,
        ];
    }
}
