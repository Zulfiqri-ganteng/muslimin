<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\DokumenStream;
use App\Models\DokumenAksesLogModel;
use App\Models\DokumenModel;

/**
 * Penyaji berkas dokumen untuk admin yang sudah login.
 *
 * Controller ini SENGAJA tipis: seluruh urusan Range/ETag/pengamanan tipe
 * ada di App\Libraries\DokumenStream supaya perilakunya persis sama saat
 * berkas yang sama disajikan lewat tautan berbagi publik (D5) maupun lewat
 * API Android (D7). Tugas di sini cuma dua: pastikan berkasnya boleh
 * diakses, lalu catat jejaknya.
 *
 * Rute ada di grup ber-filter 'auth', jadi tamu tak pernah sampai ke sini.
 */
class DokumenFile extends BaseController
{
    protected DokumenModel $model;
    protected DokumenAksesLogModel $log;

    public function __construct()
    {
        $this->model = new DokumenModel();
        $this->log   = new DokumenAksesLogModel();
        helper('dokumen');
    }

    /** Tampilkan berkas di dalam browser (pratinjau PDF/gambar/audio/teks). */
    public function lihat(int $id)
    {
        return $this->sajikan($id, true, 'pratinjau');
    }

    /** Paksa unduh berkas. */
    public function unduh(int $id)
    {
        return $this->sajikan($id, false, 'unduh');
    }

    /**
     * Sajikan thumbnail gambar. Tak dicatat ke jejak akses — ini elemen
     * tampilan daftar, bukan pembukaan dokumen, dan akan dipanggil puluhan
     * kali dalam satu halaman.
     */
    public function thumb(int $id)
    {
        $row = $this->model->find($id);

        if ($row === null || trim((string) $row['thumb']) === '') {
            return $this->response->setStatusCode(404)->setBody('Thumbnail tidak ada.');
        }

        $path = dokumen_path($row['thumb']);
        if ($path === null) {
            return $this->response->setStatusCode(404)->setBody('Thumbnail tidak ditemukan.');
        }

        DokumenStream::kirim($path, [
            'nama'   => 'thumb-' . $id . '.webp',
            'mime'   => str_ends_with($path, '.jpg') ? 'image/jpeg' : 'image/webp',
            'inline' => true,
            'publik' => false,
        ]);
    }

    // -----------------------------------------------------------------

    /**
     * Jalur bersama lihat() & unduh().
     *
     * Dokumen di tempat sampah tetap boleh diunduh admin — justru saat mau
     * memulihkan, orang perlu memastikan isinya benar sebelum dipulihkan.
     */
    private function sajikan(int $id, bool $inline, string $aksi)
    {
        $row = $this->model->withDeleted()->find($id);

        if ($row === null) {
            return $this->response->setStatusCode(404)->setBody('Dokumen tidak ditemukan.');
        }

        if (($row['tipe'] ?? 'berkas') !== 'berkas') {
            // Dokumen bertipe tautan tak punya berkas fisik — arahkan saja.
            return $this->response->redirect((string) $row['url_eksternal']);
        }

        $path = dokumen_path($row['path_rel']);
        if ($path === null) {
            log_message('error', 'Berkas dokumen #' . $id . ' hilang dari disk: ' . (string) $row['path_rel']);

            return $this->response->setStatusCode(404)->setBody('Berkas sudah tidak ada di penyimpanan.');
        }

        $adminId = $this->adminId();

        DokumenStream::kirim($path, [
            'nama'   => (string) ($row['nama_asli'] ?: $row['judul']),
            'mime'   => (string) ($row['mime'] ?: 'application/octet-stream'),
            'inline' => $inline,
            'hash'   => (string) $row['hash_sha256'],
            'publik' => false,
            // Dijalankan hanya kalau berkas sungguh-sungguh terkirim utuh —
            // bukan saat HEAD, 304, atau potongan Range.
            'onKirim' => function () use ($id, $aksi, $adminId) {
                $this->model->tambahHitung($id, $aksi === 'unduh' ? 'jml_unduh' : 'jml_lihat');
                $this->log->catat($aksi, $id, null, $adminId);
            },
        ]);
    }

    /** Id admin yang sedang login — sesi menyimpannya di session('admin')['id']. */
    private function adminId(): ?int
    {
        $id = session('admin')['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }
}
