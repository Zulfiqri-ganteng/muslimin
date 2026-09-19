<?php

namespace App\Controllers;

use App\Libraries\DokumenStream;
use App\Libraries\LoginThrottle;
use App\Models\DokumenAksesLogModel;
use App\Models\DokumenFolderModel;
use App\Models\DokumenModel;
use App\Models\DokumenShareModel;

/**
 * Halaman penerima tautan berbagi — DIAKSES TANPA LOGIN.
 *
 * Guru di sistem ini tidak punya akun (guru = data master, bukan pengguna),
 * jadi berbagi dokumen ke mereka dilakukan lewat tautan bertoken. Semua
 * pemeriksaan izin terjadi di sini: token sah, belum dicabut, belum
 * kedaluwarsa, jatah unduhan belum habis, dan kata sandi (bila dipasang)
 * sudah dimasukkan.
 *
 * Pengiriman berkasnya memakai App\Libraries\DokumenStream yang sama persis
 * dengan sisi admin, sehingga perilaku Range/ETag/pengamanan tipe identik.
 */
class Berbagi extends BaseController
{
    protected DokumenShareModel $share;
    protected DokumenModel $dokumen;
    protected DokumenFolderModel $folder;
    protected DokumenAksesLogModel $log;

    public function __construct()
    {
        $this->share   = new DokumenShareModel();
        $this->dokumen = new DokumenModel();
        $this->folder  = new DokumenFolderModel();
        $this->log     = new DokumenAksesLogModel();
        helper('dokumen');
    }

    // =================================================================
    //  Halaman utama tautan
    // =================================================================

    public function lihat(string $token)
    {
        $cek = $this->share->periksa($token);

        if (! $cek['ok']) {
            return $this->halamanTolak($cek['alasan']);
        }

        $row = $cek['row'];

        // Terkunci sandi & belum dibuka di peramban ini.
        if ($this->share->pakaiSandi($row) && ! $this->sudahDibuka($token)) {
            return $this->halamanSandi($token, null);
        }

        $this->share->tambahHitung((int) $row['id'], 'jml_akses');

        return $row['folder_id'] !== null
            ? $this->tampilFolder($row)
            : $this->tampilDokumen($row);
    }

    /** Terima kiriman kata sandi tautan. */
    public function buka(string $token)
    {
        $cek = $this->share->periksa($token);
        if (! $cek['ok']) {
            return $this->halamanTolak($cek['alasan']);
        }

        $row      = $cek['row'];
        $ip       = $this->request->getIPAddress();
        $throttle = new LoginThrottle();

        // Tautan publik adalah sasaran empuk tebak-sandi, jadi dibatasi
        // memakai pembatas yang sama dengan halaman masuk admin.
        $tunggu = $throttle->retryAfter('share:' . $token, $ip);
        if ($tunggu > 0) {
            return $this->halamanSandi($token, 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil($tunggu / 60) . ' menit.');
        }

        $sandi = (string) $this->request->getPost('sandi');

        if (! $this->share->sandiCocok($row, $sandi)) {
            $throttle->hit('share:' . $token, $ip, 'share');

            return $this->halamanSandi($token, 'Kata sandi salah.');
        }

        $throttle->clear('share:' . $token, $ip);
        session()->set($this->kunciSesi($token), true);

        return redirect()->to(site_url('d/' . $token));
    }

    // =================================================================
    //  Penyajian berkas lewat tautan
    // =================================================================

    /** Tampilkan berkas di peramban (pratinjau). */
    public function berkas(string $token, ?int $dokumenId = null)
    {
        return $this->sajikan($token, $dokumenId, true);
    }

    /** Unduh berkas lewat tautan. */
    public function unduh(string $token, ?int $dokumenId = null)
    {
        return $this->sajikan($token, $dokumenId, false);
    }

    private function sajikan(string $token, ?int $dokumenId, bool $inline)
    {
        $cek = $this->share->periksa($token);
        if (! $cek['ok']) {
            return $this->halamanTolak($cek['alasan']);
        }

        $row = $cek['row'];

        if ($this->share->pakaiSandi($row) && ! $this->sudahDibuka($token)) {
            return redirect()->to(site_url('d/' . $token));
        }

        // Mengunduh bisa dimatikan oleh pembuat tautan (hanya boleh dilihat).
        if (! $inline && (int) $row['boleh_unduh'] !== 1) {
            return $this->halamanTolak('unduh_mati');
        }

        $dok = $this->dokumenDalamTautan($row, $dokumenId);
        if ($dok === null) {
            return $this->halamanTolak(DokumenShareModel::TOLAK_TIDAK_ADA);
        }

        if (($dok['tipe'] ?? 'berkas') !== 'berkas') {
            return redirect()->to((string) $dok['url_eksternal']);
        }

        $path = dokumen_path($dok['path_rel']);
        if ($path === null) {
            return $this->halamanTolak('berkas_hilang');
        }

        $shareId = (int) $row['id'];
        $dokId   = (int) $dok['id'];

        DokumenStream::kirim($path, [
            'nama'   => (string) ($dok['nama_asli'] ?: $dok['judul']),
            'mime'   => (string) ($dok['mime'] ?: 'application/octet-stream'),
            'inline' => $inline,
            'hash'   => (string) $dok['hash_sha256'],
            // Berkas publik boleh disinggahi cache peramban lebih lama;
            // yang dibagikan lewat tautan tetap diperlakukan tertutup.
            'publik' => $dok['visibilitas'] === 'publik',
            'onKirim' => function () use ($dokId, $shareId, $inline) {
                $this->dokumen->tambahHitung($dokId, $inline ? 'jml_lihat' : 'jml_unduh');
                if (! $inline) {
                    $this->share->tambahHitung($shareId, 'jml_unduh');
                }
                $this->log->catat($inline ? 'pratinjau' : 'unduh', $dokId, $shareId, null);
            },
        ]);
    }

    // =================================================================
    //  Daftar dokumen publik (opsional, digerbangi pengaturan)
    // =================================================================

    public function publik()
    {
        $setting = (new \App\Models\SettingModel())->get();

        if ((int) ($setting['dokumen_publik'] ?? 0) !== 1) {
            return $this->halamanTolak('publik_mati');
        }

        $q = trim((string) $this->request->getGet('q'));

        $builder = $this->dokumen->cariSemua(['q' => $q, 'visibilitas' => 'publik', 'urut' => 'created_at', 'arah' => 'DESC']);

        return $this->tampil('public/dokumen', [
            'title' => 'Dokumen Sekolah',
            'rows'  => $builder->paginate(20, 'pub'),
            'pager' => $this->dokumen->pager,
            'q'     => $q,
        ]);
    }

    /** Berkas dokumen publik — tanpa token, tapi wajib bervisibilitas publik. */
    public function berkasPublik(int $id)
    {
        return $this->sajikanPublik($id, true);
    }

    public function unduhPublik(int $id)
    {
        return $this->sajikanPublik($id, false);
    }

    private function sajikanPublik(int $id, bool $inline)
    {
        $setting = (new \App\Models\SettingModel())->get();
        if ((int) ($setting['dokumen_publik'] ?? 0) !== 1) {
            return $this->halamanTolak('publik_mati');
        }

        $dok = $this->dokumen->find($id);

        // Penjagaan utama: hanya yang benar-benar ditandai publik.
        if ($dok === null || $dok['visibilitas'] !== 'publik') {
            return $this->halamanTolak(DokumenShareModel::TOLAK_TIDAK_ADA);
        }

        if (($dok['tipe'] ?? 'berkas') !== 'berkas') {
            return redirect()->to((string) $dok['url_eksternal']);
        }

        $path = dokumen_path($dok['path_rel']);
        if ($path === null) {
            return $this->halamanTolak('berkas_hilang');
        }

        DokumenStream::kirim($path, [
            'nama'    => (string) ($dok['nama_asli'] ?: $dok['judul']),
            'mime'    => (string) ($dok['mime'] ?: 'application/octet-stream'),
            'inline'  => $inline,
            'hash'    => (string) $dok['hash_sha256'],
            'publik'  => true,
            'onKirim' => function () use ($id, $inline) {
                $this->dokumen->tambahHitung($id, $inline ? 'jml_lihat' : 'jml_unduh');
                $this->log->catat($inline ? 'pratinjau' : 'unduh', $id, null, null);
            },
        ]);
    }

    // =================================================================
    //  Pembantu
    // =================================================================

    /**
     * Ambil dokumen yang boleh diakses lewat tautan ini.
     *
     * Untuk tautan per-dokumen: hanya dokumen itu. Untuk tautan folder:
     * dokumen mana pun DI DALAM folder itu (termasuk subfolder) — dan
     * pembatasan ini wajib diperiksa di server, bukan cuma disembunyikan
     * di tampilan.
     */
    private function dokumenDalamTautan(array $share, ?int $dokumenId): ?array
    {
        if ($share['dokumen_id'] !== null) {
            $dok = $this->dokumen->find((int) $share['dokumen_id']);

            // Bila id tertentu diminta, ia harus sama dengan dokumen tautan.
            if ($dokumenId !== null && $dok !== null && (int) $dok['id'] !== $dokumenId) {
                return null;
            }

            return $dok;
        }

        if ($share['folder_id'] === null || $dokumenId === null) {
            return null;
        }

        $dok = $this->dokumen->find($dokumenId);
        if ($dok === null || $dok['folder_id'] === null) {
            return null;
        }

        $cakupan = $this->folder->keturunan((int) $share['folder_id']);

        return in_array((int) $dok['folder_id'], $cakupan, true) ? $dok : null;
    }

    private function tampilDokumen(array $share)
    {
        $dok = $this->dokumen->find((int) $share['dokumen_id']);

        if ($dok === null) {
            return $this->halamanTolak(DokumenShareModel::TOLAK_TIDAK_ADA);
        }

        $this->log->catat('lihat', (int) $dok['id'], (int) $share['id'], null);

        return $this->tampil('public/berbagi_dokumen', [
            'title' => $dok['judul'],
            'd'     => $dok,
            'share' => $share,
        ]);
    }

    private function tampilFolder(array $share)
    {
        $folder = $this->folder->find((int) $share['folder_id']);
        if ($folder === null) {
            return $this->halamanTolak(DokumenShareModel::TOLAK_TIDAK_ADA);
        }

        $cakupan = $this->folder->keturunan((int) $folder['id']);

        $rows = $this->dokumen
            ->whereIn('folder_id', $cakupan)
            ->orderBy('judul', 'ASC')
            ->findAll();

        $this->log->catat('lihat', null, (int) $share['id'], null);

        return $this->tampil('public/berbagi_folder', [
            'title'  => $folder['nama'],
            'folder' => $folder,
            'rows'   => $rows,
            'share'  => $share,
        ]);
    }

    private function halamanSandi(string $token, ?string $galat)
    {
        return $this->tampil('public/berbagi_sandi', [
            'title' => 'Tautan Terkunci',
            'token' => $token,
            'galat' => $galat,
        ]);
    }

    /**
     * Satu halaman penolakan dengan alasan yang jelas. Token yang tak
     * dikenal sengaja disamakan dengan "tidak ada" supaya tak bisa dipakai
     * menebak-nebak tautan yang masih hidup.
     */
    private function halamanTolak(?string $alasan)
    {
        $pesan = match ($alasan) {
            DokumenShareModel::TOLAK_DICABUT     => ['Tautan sudah dicabut', 'Pemilik dokumen menonaktifkan tautan ini. Hubungi bagian kesiswaan bila masih memerlukannya.'],
            DokumenShareModel::TOLAK_KEDALUWARSA => ['Tautan sudah kedaluwarsa', 'Masa berlaku tautan ini sudah lewat. Mintakan tautan baru ke bagian kesiswaan.'],
            DokumenShareModel::TOLAK_HABIS       => ['Jatah unduhan habis', 'Tautan ini sudah mencapai batas jumlah unduhan yang ditetapkan.'],
            'unduh_mati'                         => ['Unduhan dimatikan', 'Dokumen ini hanya boleh dilihat, tidak boleh diunduh.'],
            'berkas_hilang'                      => ['Berkas tidak ditemukan', 'Berkasnya sudah tidak ada di penyimpanan. Hubungi bagian kesiswaan.'],
            'publik_mati'                        => ['Halaman tidak tersedia', 'Daftar dokumen publik sedang dinonaktifkan oleh sekolah.'],
            default                              => ['Tautan tidak ditemukan', 'Tautan yang Anda buka tidak dikenali. Pastikan alamatnya tersalin lengkap.'],
        };

        return $this->response->setStatusCode(404)->setBody(
            $this->tampil('public/berbagi_tolak', ['title' => $pesan[0], 'judul' => $pesan[0], 'pesan' => $pesan[1]])
        );
    }

    /**
     * Semua view publik memakai layout yang membutuhkan identitas sekolah,
     * jadi disisipkan di satu tempat saja.
     *
     * @param array<string, mixed> $data
     */
    private function tampil(string $view, array $data): string
    {
        return view($view, $data + ['setting' => (new \App\Models\SettingModel())->get()]);
    }

    private function kunciSesi(string $token): string
    {
        return 'share_ok_' . substr(preg_replace('/[^a-f0-9]/', '', $token) ?? '', 0, 64);
    }

    private function sudahDibuka(string $token): bool
    {
        return session($this->kunciSesi($token)) === true;
    }
}
