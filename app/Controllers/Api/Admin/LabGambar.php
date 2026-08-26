<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\LabGambarModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Galeri gambar SIMLAB (API). Cermin App\Controllers\Admin\LabGambar.
 * Semua unggahan auto-konversi ke WEBP (labimage_helper).
 *
 * Rute:
 *   GET    /api/v1/admin/lab-gambar/{entitas}/{id}  → daftar foto
 *   POST   /api/v1/admin/lab-gambar/{entitas}/{id}  → unggah (multipart, field 'gambar')
 *   DELETE /api/v1/admin/lab-gambar/{gid}            → hapus satu foto
 */
class LabGambar extends BaseApiController
{
    protected LabGambarModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new LabGambarModel();
        $this->audit = new AuditModel();
        helper('labimage');
    }

    private function valid(string $entitas): bool
    {
        return in_array($entitas, LabGambarModel::ENTITAS, true);
    }

    public function index($entitas = '', $id = 0): ResponseInterface
    {
        $entitas = (string) $entitas;
        if (! $this->valid($entitas)) {
            return $this->invalid(['entitas' => 'Entitas tidak dikenal.']);
        }

        return $this->ok(array_map([$this, 'transform'], $this->model->forEntitas($entitas, (int) $id)));
    }

    public function upload($entitas = '', $id = 0): ResponseInterface
    {
        $entitas = (string) $entitas;
        $id      = (int) $id;
        if (! $this->valid($entitas)) {
            return $this->invalid(['entitas' => 'Entitas tidak dikenal.']);
        }

        $files = $this->request->getFileMultiple('gambar');
        if (empty($files)) {
            $one = $this->request->getFile('gambar');
            $files = $one ? [$one] : [];
        }
        if (empty($files)) {
            return $this->invalid(['gambar' => 'Tidak ada berkas gambar.']);
        }

        $urut = (int) ($this->model->where('entitas', $entitas)->where('entitas_id', $id)->selectMax('urutan')->first()['urutan'] ?? 0);
        $saved  = [];
        $errors = [];
        foreach ($files as $file) {
            if ($file === null || ! $file->isValid()) {
                continue;
            }
            $err  = null;
            $nama = labimage_save($file, $err);
            if ($nama === null) {
                $errors[] = $err ?? 'gagal';
                continue;
            }
            $this->model->insert(['entitas' => $entitas, 'entitas_id' => $id, 'file' => $nama, 'urutan' => ++$urut]);
            $saved[] = $this->transform($this->model->find($this->model->getInsertID()));
        }

        if ($saved === []) {
            return $this->failure('Semua unggahan gagal. ' . implode(' ', array_slice($errors, 0, 3)), 422);
        }
        $this->audit->record('create', 'lab_gambar', $id, count($saved) . ' foto ' . $entitas . ' (via mobile)');

        return $this->created(['saved' => $saved, 'errors' => $errors], count($saved) . ' foto tersimpan.');
    }

    public function destroy($gid = 0): ResponseInterface
    {
        $row = $this->model->find((int) $gid);
        if (! $row) {
            return $this->missing('Foto tidak ditemukan.');
        }
        labimage_delete($row['file']);
        $this->model->delete((int) $gid);
        $this->audit->record('delete', 'lab_gambar', (int) $row['entitas_id'], 'Hapus foto ' . $row['entitas'] . ' (via mobile)');

        return $this->ok(null, 'Foto dihapus.');
    }

    private function transform(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'entitas'    => $r['entitas'],
            'entitas_id' => (int) $r['entitas_id'],
            'file'       => $r['file'],
            'url'        => labimage_url($r['file']),
            'created_at' => $r['created_at'] ?? null,
        ];
    }
}
