<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LayananPublik;
use App\Models\AuditModel;
use App\Models\PengumumanModel;

/**
 * Pengumuman — tampil di situs publik (kartu "Informasi untuk Anda" di beranda, /pengumuman, banner sorotan terbaru).
 * P2b rombak publik: + kategori, + gambar opsional (helper labimage: format dicek dari isi berkas, maks 5 MB, diperkecil ke
 * 1600 px, disimpan WEBP di public/uploads/pengumuman/); ganti/hapus gambar ikut menghapus berkas lama.
 */
class Pengumuman extends BaseController
{
    protected PengumumanModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new PengumumanModel();
        $this->audit = new AuditModel();
        helper('labimage');
    }

    public function index()
    {
        $rows = $this->model->orderBy('id', 'DESC')->findAll();
        foreach ($rows as &$r) {
            $r['url'] = LayananPublik::gambar(PengumumanModel::FOLDER, $r['gambar'] ?? null);
        }
        unset($r);

        return view('admin/pengumuman/index', [
            'title'    => 'Pengumuman',
            'rows'     => $rows,
            'kategori' => PengumumanModel::KATEGORI,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $err    = null;
        $gambar = $this->simpanGambar($err);
        if ($err !== null) {
            return redirect()->back()->withInput()->with('error', $err);
        }
        $data['gambar'] = $gambar;
        if (! $this->model->insert($data)) {
            labimage_delete($gambar, PengumumanModel::FOLDER);

            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        cache()->delete('pengumuman_aktif');
        $this->audit->record('create', 'pengumuman', $this->model->getInsertID(), 'Tambah pengumuman');

        return redirect()->to(site_url('admin/pengumuman'))->with('success', 'Pengumuman ditambahkan.');
    }

    public function update($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/pengumuman'))->with('error', 'Pengumuman tidak ditemukan.');
        }
        $data = $this->collect();
        if (! $this->model->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $err  = null;
        $baru = $this->simpanGambar($err);
        if ($err !== null) {
            return redirect()->back()->withInput()->with('error', $err);
        }
        // Gambar baru menggantikan yang lama; tanpa unggahan + centang "Hapus gambar" = pengumuman tanpa gambar.
        $buangLama = $baru !== null || $this->request->getPost('hapus_gambar');
        if ($buangLama) {
            $data['gambar'] = $baru;
        }
        if (! $this->model->update($id, $data)) {
            labimage_delete($baru, PengumumanModel::FOLDER);

            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        if ($buangLama) {
            labimage_delete($row['gambar'] ?? null, PengumumanModel::FOLDER);
        }
        cache()->delete('pengumuman_aktif');
        $this->audit->record('update', 'pengumuman', $id, 'Ubah pengumuman');

        return redirect()->to(site_url('admin/pengumuman'))->with('success', 'Pengumuman diperbarui.');
    }

    public function delete($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        $this->model->delete($id);
        if ($row) {
            labimage_delete($row['gambar'] ?? null, PengumumanModel::FOLDER);
        }
        cache()->delete('pengumuman_aktif');
        $this->audit->record('delete', 'pengumuman', $id, 'Hapus pengumuman');

        return redirect()->to(site_url('admin/pengumuman'))->with('success', 'Pengumuman dihapus.');
    }

    private function collect(): array
    {
        $kategori = (string) $this->request->getPost('kategori');

        return [
            'judul'    => trim((string) $this->request->getPost('judul')),
            'isi'      => trim((string) $this->request->getPost('isi')),
            'kategori' => $kategori !== '' ? $kategori : PengumumanModel::KATEGORI[0],
            'is_aktif' => $this->request->getPost('is_aktif') ? 1 : 0,
        ];
    }

    /** Simpan unggahan "gambar" (opsional) sebagai WEBP. Null bila tidak ada unggahan atau gagal (alasan gagal di $err). */
    private function simpanGambar(?string &$err): ?string
    {
        $err  = null;
        $file = $this->request->getFile('gambar');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $file->isValid()) {
            $err = 'Gambar gagal diunggah: ' . $file->getErrorString();

            return null;
        }

        return labimage_save($file, $err, 1600, 82, PengumumanModel::FOLDER);
    }
}
