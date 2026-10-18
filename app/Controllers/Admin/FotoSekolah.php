<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LayananPublik;
use App\Models\AuditModel;
use App\Models\FotoSekolahModel;

/**
 * Foto Sekolah (Arsip & Info) — foto asli sekolah untuk halaman publik: banner beranda (bila "Tampilkan di banner"), pita
 * "Tentang sekolah" di beranda, galeri halaman Profil. Unggahan diperkecil otomatis (sisi terpanjang 1920 px) dan disimpan
 * sebagai WEBP di public/uploads/foto-sekolah/ (helper labimage: format dicek dari isi berkas, maks 5 MB).
 */
class FotoSekolah extends BaseController
{
    protected FotoSekolahModel $model;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model = new FotoSekolahModel();
        $this->audit = new AuditModel();
        helper('labimage');
    }

    public function index()
    {
        $rows = $this->model->terurut()->findAll();
        foreach ($rows as &$r) {
            $r['url'] = LayananPublik::gambar(FotoSekolahModel::FOLDER, $r['berkas']);
        }
        unset($r);

        return view('admin/foto_sekolah/index', [
            'title' => 'Foto Sekolah',
            'rows'  => $rows,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if (! $this->model->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $err    = null;
        $berkas = $this->simpanFoto(true, $err);
        if ($berkas === null) {
            return redirect()->back()->withInput()->with('error', $err);
        }
        $data['berkas'] = $berkas;
        if (! $this->model->insert($data)) {
            labimage_delete($berkas, FotoSekolahModel::FOLDER);

            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        cache()->delete(FotoSekolahModel::CACHE);
        $this->audit->record('create', 'foto_sekolah', $this->model->getInsertID(), 'Tambah foto sekolah "' . $data['judul'] . '"');

        return redirect()->to(site_url('admin/foto-sekolah'))->with('success', 'Foto ditambahkan.');
    }

    public function update($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/foto-sekolah'))->with('error', 'Foto tidak ditemukan.');
        }
        $data = $this->collect();
        if (! $this->model->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $err  = null;
        $baru = $this->simpanFoto(false, $err);
        if ($err !== null) {
            return redirect()->back()->withInput()->with('error', $err);
        }
        if ($baru !== null) {
            $data['berkas'] = $baru;
        }
        if (! $this->model->update($id, $data)) {
            if ($baru !== null) {
                labimage_delete($baru, FotoSekolahModel::FOLDER);
            }

            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        if ($baru !== null) {
            labimage_delete($row['berkas'], FotoSekolahModel::FOLDER);
        }
        cache()->delete(FotoSekolahModel::CACHE);
        $this->audit->record('update', 'foto_sekolah', $id, 'Ubah foto sekolah "' . $data['judul'] . '"' . ($baru !== null ? ' (ganti foto)' : ''));

        return redirect()->to(site_url('admin/foto-sekolah'))->with('success', 'Foto diperbarui.');
    }

    public function delete($id)
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/foto-sekolah'))->with('error', 'Foto tidak ditemukan.');
        }
        $this->model->delete($id);
        labimage_delete($row['berkas'], FotoSekolahModel::FOLDER);
        cache()->delete(FotoSekolahModel::CACHE);
        $this->audit->record('delete', 'foto_sekolah', $id, 'Hapus foto sekolah "' . $row['judul'] . '"');

        return redirect()->to(site_url('admin/foto-sekolah'))->with('success', 'Foto dihapus.');
    }

    private function collect(): array
    {
        $teks = fn (string $k): ?string => ($v = trim((string) $this->request->getPost($k))) !== '' ? $v : null;

        return [
            'judul'         => trim((string) $this->request->getPost('judul')),
            'keterangan'    => $teks('keterangan'),
            'tautan'        => $teks('tautan'),
            'urutan'        => (int) $this->request->getPost('urutan'),
            'tampil_banner' => $this->request->getPost('tampil_banner') ? 1 : 0,
            'is_aktif'      => $this->request->getPost('is_aktif') ? 1 : 0,
        ];
    }

    /**
     * Simpan unggahan "foto" sebagai WEBP. Mengembalikan nama berkas; null bila tidak ada unggahan (boleh saat mengubah) atau
     * gagal — alasan gagal diisi di $err.
     */
    private function simpanFoto(bool $wajib, ?string &$err): ?string
    {
        $err  = null;
        $file = $this->request->getFile('foto');
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            if ($wajib) {
                $err = 'Pilih berkas foto terlebih dahulu.';
            }

            return null;
        }
        if (! $file->isValid()) {
            $err = 'Foto gagal diunggah: ' . $file->getErrorString();

            return null;
        }
        return labimage_save($file, $err, 1920, 82, FotoSekolahModel::FOLDER);
    }
}
