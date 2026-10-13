<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Models\AuditModel;
use App\Models\LabGambarModel;

/**
 * Galeri gambar SIMLAB (web). Satu halaman generik dipakai semua entitas
 * (aset/kerusakan/perbaikan/lab/sparepart/peminjaman): lihat, unggah (banyak,
 * auto-WEBP), hapus. Foto disimpan di public/uploads/lab/.
 */
class LabGambar extends BaseController
{
    protected LabGambarModel $model;
    protected AuditModel $audit;

    private const LABEL = [
        'aset'       => 'Aset',
        'kerusakan'  => 'Kerusakan',
        'perbaikan'  => 'Perbaikan',
        'lab'        => 'Laboratorium',
        'sparepart'  => 'Sparepart',
        'peminjaman' => 'Peminjaman',
    ];

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

    public function index($entitas = '', $id = 0)
    {
        $entitas = (string) $entitas;
        $id      = (int) $id;
        if (! $this->valid($entitas)) {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Entitas tidak dikenal.');
        }

        return view('admin/lab_gambar/index', [
            'title'   => 'Foto ' . (self::LABEL[$entitas] ?? $entitas),
            'entitas' => $entitas,
            'id'      => $id,
            'label'   => self::LABEL[$entitas] ?? $entitas,
            'rows'    => $this->model->forEntitas($entitas, $id),
        ]);
    }

    public function upload($entitas = '', $id = 0)
    {
        $entitas = (string) $entitas;
        $id      = (int) $id;
        if (! $this->valid($entitas)) {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Entitas tidak dikenal.');
        }

        $files  = $this->request->getFileMultiple('gambar');
        $ok     = 0;
        $errors = [];
        $urut   = (int) ($this->model->where('entitas', $entitas)->where('entitas_id', $id)->selectMax('urutan')->first()['urutan'] ?? 0);

        foreach ((array) $files as $file) {
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
            $ok++;
        }

        $back = redirect()->to(site_url("admin/lab-gambar/{$entitas}/{$id}"));
        if ($ok > 0) {
            $this->audit->record('create', 'lab_gambar', $id, "Unggah {$ok} foto {$entitas}");
        }
        if ($errors !== []) {
            return $back->with($ok > 0 ? 'success' : 'error', "{$ok} foto tersimpan. Gagal: " . implode(' ', array_slice($errors, 0, 3)));
        }

        return $back->with('success', "{$ok} foto tersimpan.");
    }

    public function delete($gid = 0)
    {
        $row = $this->model->find((int) $gid);
        if (! $row) {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Foto tidak ditemukan.');
        }
        // Penjaga rute hanya tahu "admin/lab-gambar/hapus/{id}" (boleh bila memegang salah satu menu ber-foto); di sini dicek hak
        // KELOLA menu pemilik foto ini (mis. Aset / Peminjaman), diatur Admin di Sistem → Hak Akses.
        $peran   = (string) (session('admin')['role'] ?? '');
        $halaman = 'admin/lab-gambar/' . $row['entitas'] . '/' . (int) $row['entitas_id'];
        if (! HakAkses::boleh($peran, $halaman, 'POST')) {
            [$ke, $pesan] = HakAkses::tolak($peran, $halaman, 'foto ' . (self::LABEL[$row['entitas']] ?? $row['entitas']));

            return redirect()->to(site_url($ke))->with('error', $pesan);
        }
        labimage_delete($row['file']);
        $this->model->delete((int) $gid);
        $this->audit->record('delete', 'lab_gambar', (int) $row['entitas_id'], 'Hapus foto ' . $row['entitas']);

        return redirect()->to(site_url("admin/lab-gambar/{$row['entitas']}/{$row['entitas_id']}"))->with('success', 'Foto dihapus.');
    }
}
