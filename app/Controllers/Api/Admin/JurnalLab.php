<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\JurnalLabModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Jurnal pemakaian lab (API). Cermin App\Controllers\Admin\JurnalLab.
 * Rute: /api/v1/admin/jurnal-lab
 */
class JurnalLab extends BaseApiController
{
    protected JurnalLabModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new JurnalLabModel();
        $this->audit = new AuditModel();
    }

    public function index(): ResponseInterface
    {
        $per  = (int) $this->request->getGet('per');
        $page = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }
        $q      = trim((string) $this->request->getGet('q'));
        $labId  = (int) $this->request->getGet('lab_id');
        $dari   = trim((string) $this->request->getGet('dari'));
        $sampai = trim((string) $this->request->getGet('sampai'));

        $b = $this->model->withRelations();
        if ($q !== '') {
            $b = $b->groupStart()->like('jurnal_lab.kegiatan', $q)->orLike('guru.nama', $q)->orLike('lab.nama', $q)->groupEnd();
        }
        if ($labId > 0) {
            $b = $b->where('jurnal_lab.lab_id', $labId);
        }
        if ($dari !== '') {
            $b = $b->where('jurnal_lab.tanggal >=', $dari);
        }
        if ($sampai !== '') {
            $b = $b->where('jurnal_lab.tanggal <=', $sampai);
        }
        $rows  = $b->orderBy('jurnal_lab.tanggal', 'DESC')->orderBy('jurnal_lab.id', 'DESC')->paginate($per, 'default', $page);
        $total = $this->model->pager->getTotal();

        return $this->collection(array_map([$this, 'transform'], $rows), ['page' => $page, 'perPage' => $per, 'total' => $total]);
    }

    public function store(): ResponseInterface
    {
        $in      = $this->body();
        $kondisi = strtolower((string) ($in['kondisi_setelah'] ?? 'baik'));
        $data = [
            'lab_id'          => (int) ($in['lab_id'] ?? 0) ?: null,
            'tanggal'         => trim((string) ($in['tanggal'] ?? '')) ?: date('Y-m-d'),
            'jam_mulai'       => trim((string) ($in['jam_mulai'] ?? '')) ?: null,
            'jam_selesai'     => trim((string) ($in['jam_selesai'] ?? '')) ?: null,
            'guru_id'         => (int) ($in['guru_id'] ?? 0) ?: null,
            'kelas_id'        => (int) ($in['kelas_id'] ?? 0) ?: null,
            'kegiatan'        => trim((string) ($in['kegiatan'] ?? '')) ?: null,
            'jumlah_hadir'    => (int) ($in['jumlah_hadir'] ?? 0) ?: null,
            'kondisi_setelah' => in_array($kondisi, JurnalLabModel::KONDISI_SETELAH, true) ? $kondisi : 'baik',
            'kendala'         => trim((string) ($in['kendala'] ?? '')) ?: null,
            'teknisi_id'      => (int) ($in['teknisi_id'] ?? 0) ?: null,
            'keterangan'      => trim((string) ($in['keterangan'] ?? '')) ?: null,
        ];
        if (! $this->model->insert($data)) {
            return $this->invalid($this->model->errors());
        }
        $newId = (int) $this->model->getInsertID();
        $this->audit->record('create', 'jurnal_lab', $newId, 'Tambah jurnal lab (via mobile)');

        return $this->created($this->transform($this->fresh($newId)), 'Jurnal pemakaian dicatat.');
    }

    public function destroy($id = null): ResponseInterface
    {
        $id = (int) $id;
        if (! $this->model->find($id)) {
            return $this->missing('Jurnal tidak ditemukan.');
        }
        $this->model->delete($id);
        $this->audit->record('delete', 'jurnal_lab', $id, 'Hapus jurnal lab (via mobile)');

        return $this->ok(null, 'Jurnal dihapus.');
    }

    private function fresh(int $id): array
    {
        return $this->model->withRelations()->where('jurnal_lab.id', $id)->first() ?? [];
    }

    private function transform(array $r): array
    {
        return [
            'id'              => (int) $r['id'],
            'lab_id'          => ((int) ($r['lab_id'] ?? 0)) ?: null,
            'lab_nama'        => $r['lab_nama'] ?? null,
            'tanggal'         => $r['tanggal'] ?? null,
            'jam_mulai'       => isset($r['jam_mulai']) ? substr((string) $r['jam_mulai'], 0, 5) : null,
            'jam_selesai'     => isset($r['jam_selesai']) ? substr((string) $r['jam_selesai'], 0, 5) : null,
            'guru_id'         => ((int) ($r['guru_id'] ?? 0)) ?: null,
            'guru_nama'       => $r['guru_nama'] ?? null,
            'nama_kelas'      => $r['nama_kelas'] ?? null,
            'kegiatan'        => $r['kegiatan'] ?? null,
            'jumlah_hadir'    => isset($r['jumlah_hadir']) ? ((int) $r['jumlah_hadir'] ?: null) : null,
            'kondisi_setelah' => $r['kondisi_setelah'] ?? 'baik',
            'kendala'         => $r['kendala'] ?? null,
            'teknisi_nama'    => $r['teknisi_nama'] ?? null,
            'keterangan'      => $r['keterangan'] ?? null,
        ];
    }
}
