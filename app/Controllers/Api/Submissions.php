<?php

namespace App\Controllers\Api;

use App\Models\SubmissionModel;

/**
 * Kelola data kesediaan guru (butuh token). Cermin App\Controllers\Admin\Submissions.
 *
 *   GET    /api/v1/admin/submissions?q=&status=&per=&page=
 *   GET    /api/v1/admin/submissions/{id}
 *   POST   /api/v1/admin/submissions/{id}/status   { status, catatan_admin }
 *   DELETE /api/v1/admin/submissions/{id}
 */
class Submissions extends BaseApiController
{
    protected SubmissionModel $model;

    /** Status verifikasi yang valid (sama dgn pilihan di web). */
    private const STATUSES = ['baru', 'diverifikasi', 'ditolak'];

    public function __construct()
    {
        $this->model = new SubmissionModel();
    }

    /** Daftar kesediaan: pencarian, filter status kepegawaian, paginasi. */
    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $per    = (int) $this->request->getGet('per');
        $page   = max(1, (int) $this->request->getGet('page'));
        if (! in_array($per, [10, 20, 30, 40, 50], true)) {
            $per = 10;
        }

        $builder = $this->model;
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama_lengkap', $q)
                ->orLike('nip_nuptk', $q)
                ->orLike('guru_mapel', $q)
                ->groupEnd();
        }
        if (in_array($status, ['PNS', 'PPPK', 'GTY', 'GTT'], true)) {
            $builder = $builder->where('status_kepegawaian', $status);
        }

        $rows  = $builder->orderBy('created_at', 'DESC')->paginate($per, 'default', $page);
        $pager = $this->model->pager;

        return $this->collection(
            array_map([$this, 'listItem'], $rows),
            ['page' => $page, 'perPage' => $per, 'total' => $pager ? $pager->getTotal() : count($rows)]
        );
    }

    /** Detail satu kesediaan (field JSON ter-decode). */
    public function view($id = null)
    {
        $row = $this->model->find((int) $id);
        if (! $row) {
            return $this->missing('Data kesediaan tidak ditemukan.');
        }
        return $this->ok($this->detail(SubmissionModel::decode($row)));
    }

    /** Ubah status verifikasi + catatan admin (logika token revisi sama dgn web). */
    public function updateStatus($id = null)
    {
        $row = $this->model->find((int) $id);
        if (! $row) {
            return $this->missing('Data kesediaan tidak ditemukan.');
        }

        $in     = $this->body();
        $status = (string) ($in['status'] ?? 'baru');
        if (! in_array($status, self::STATUSES, true)) {
            return $this->invalid(['status' => 'Status tidak valid.']);
        }

        $update = [
            'status'        => $status,
            'catatan_admin' => trim((string) ($in['catatan_admin'] ?? '')),
        ];

        // "Perlu Revisi" (ditolak) → buat token revisi bila belum ada; selain itu hapus token.
        if ($status === 'ditolak') {
            if (empty($row['edit_token'])) {
                $update['edit_token'] = bin2hex(random_bytes(20));
            }
        } else {
            $update['edit_token'] = null;
        }

        $this->model->update((int) $id, $update);

        return $this->ok(
            $this->detail(SubmissionModel::decode($this->model->find((int) $id))),
            'Status kesediaan berhasil diperbarui.'
        );
    }

    /** Hapus kesediaan. */
    public function delete($id = null)
    {
        if (! $this->model->find((int) $id)) {
            return $this->missing('Data kesediaan tidak ditemukan.');
        }
        $this->model->delete((int) $id);
        return $this->ok(null, 'Data kesediaan berhasil dihapus.');
    }

    // ===================== FORMATTER =====================

    /** Ringkas untuk daftar. */
    private function listItem(array $r): array
    {
        return [
            'id'                 => (int) $r['id'],
            'nama_lengkap'       => $r['nama_lengkap'],
            'guru_mapel'         => $r['guru_mapel'] ?? null,
            'status_kepegawaian' => $r['status_kepegawaian'] ?? null,
            'nomor_hp'           => $r['nomor_hp'] ?? null,
            'bersedia'           => (int) ($r['bersedia_mengajar'] ?? 0) === 1,
            'status'             => $r['status'],
            'created_at'         => $r['created_at'] ?? null,
        ];
    }

    /** Lengkap untuk detail. $r sudah ter-decode (JSON → array). */
    private function detail(array $r): array
    {
        return [
            'id'                  => (int) $r['id'],
            'nama_lengkap'        => $r['nama_lengkap'],
            'nip_nuptk'           => $r['nip_nuptk'] ?? null,
            'tempat_lahir'        => $r['tempat_lahir'] ?? null,
            'tanggal_lahir'       => $r['tanggal_lahir'] ?? null,
            'pendidikan_terakhir' => $r['pendidikan_terakhir'] ?? null,
            'guru_mapel'          => $r['guru_mapel'] ?? null,
            'status_kepegawaian'  => $r['status_kepegawaian'] ?? null,
            'nomor_hp'            => $r['nomor_hp'] ?? null,
            'bersedia'            => (int) ($r['bersedia_mengajar'] ?? 0) === 1,
            'komitmen_setuju'     => (int) ($r['komitmen_setuju'] ?? 0) === 1,
            'mapel_diampu'        => $r['mapel_diampu'] ?? [],
            'total_jam'           => (int) ($r['total_jam'] ?? 0),
            'tugas_tambahan'      => $r['tugas_tambahan'] ?? [],
            'tugas_lainnya'       => $r['tugas_lainnya'] ?? null,
            'kesediaan_jam'       => $r['kesediaan_jam'] ?? [],
            'preferensi'          => $r['preferensi'] ?? [],
            'ketersediaan_hari'   => $r['ketersediaan_hari'] ?? [],
            'keterangan_tambahan' => $r['keterangan_tambahan'] ?? null,
            'status'              => $r['status'],
            'catatan_admin'       => $r['catatan_admin'] ?? null,
            'edit_token'          => $r['edit_token'] ?? null,
            'revisi_url'          => ! empty($r['edit_token']) ? site_url('revisi/' . $r['edit_token']) : null,
            'created_at'          => $r['created_at'] ?? null,
            'updated_at'          => $r['updated_at'] ?? null,
        ];
    }
}
