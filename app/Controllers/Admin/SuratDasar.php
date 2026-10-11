<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\SuratSekolah;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Dasar controller halaman staf Surat Sekolah (menu SURAT SEKOLAH): data umum tampilan dan hak peran. Pembantu respons
 * (konteks pencatat riwayat, redirect, JSON) diwarisi dari PklDasar. Aturan hak diputuskan HakAkses/PklHak — controller
 * turunan tetap memeriksa lagi hak yang dituntut aksinya (pagar kedua setelah filter rute). Rancangan: docs/DESAIN-SURAT-SEKOLAH.md.
 */
abstract class SuratDasar extends PklDasar
{
    protected SuratSekolah $surat;

    public function __construct()
    {
        parent::__construct();
        $this->surat = new SuratSekolah();
    }

    /** Data umum semua halaman Surat Sekolah. */
    protected function dasarSurat(string $judul, string $tab): array
    {
        $peran = $this->peranSaya();

        return [
            'title'      => $judul,
            'tab'        => $tab,
            'peran'      => $peran,
            'peranLabel' => HakAkses::label($peran),
            'bolehBuat'  => HakAkses::bolehPkl($peran, 'surat_sekolah'),
            'bolehAcc'   => HakAkses::bolehAcc($peran),
            'hitung'     => $this->surat->hitungStatus(),
        ];
    }

    /**
     * Kirim berkas unduhan. Ikut menyetel cookie penanda selesai (nilainya = token dari halaman) supaya layar "Memproses…"
     * di halaman bisa ditutup — unduhan tidak memuat ulang halaman. Cookie dikirim lewat header (respons unduhan tak
     * memproses cookie CI4). Sama dengan Admin\PklBerkas::berkas.
     */
    protected function kirimBerkas(string $nama, string $biner)
    {
        $respons = $this->response->download($nama, $biner)->setFileName($nama);
        $token   = (string) $this->request->getPost('unduh_token');
        if (preg_match('/^[0-9]{1,40}$/', $token) === 1) {
            $respons->setHeader('Set-Cookie', 'unduh_selesai=' . $token . '; Path=/; Max-Age=120; SameSite=Lax');
        }

        return $respons;
    }

    /** Tolak (dengan pesan di halaman) bila peran saya tak memegang hak $hak; null bila boleh. */
    protected function tolakHalaman(string $hak, string $balik = 'admin/surat')
    {
        if (HakAkses::bolehPkl($this->peranSaya(), $hak)) {
            return null;
        }

        return $this->ke($balik, 'error', 'Akun ' . HakAkses::label($this->peranSaya()) . ' tidak punya hak untuk aksi ini. Admin bisa mengaturnya di PKL → Hak Akses.');
    }

    /**
     * Surat jenis $jenis yang BOLEH diubah datanya (belum bernomor, status menunggu/dikembalikan, hak 'surat_sekolah').
     *
     * @return array{0: array<string, mixed>|null, 1: RedirectResponse|null} surat termuat (SuratSekolah::muat), atau pengalihan galat
     */
    protected function muatBisaDiubah(string $jenis, int $id): array
    {
        if (($tolak = $this->tolakHalaman('surat_sekolah', 'admin/surat/' . $id)) !== null) {
            return [null, $tolak];
        }
        $m = $this->surat->muat($id);
        if ($m === null || $m['surat']['jenis'] !== $jenis) {
            return [null, $this->ke('admin/surat', 'error', 'Surat tidak ditemukan.')];
        }
        if ((string) ($m['surat']['nomor'] ?? '') !== '') {
            return [null, $this->ke('admin/surat/' . $id, 'error', 'Surat ini sudah bernomor, jadi datanya tidak bisa diubah. Bila salah, batalkan surat lalu buat yang baru.')];
        }
        if (! in_array($m['surat']['status'], ['menunggu', 'dikembalikan'], true)) {
            return [null, $this->ke('admin/surat/' . $id, 'error', 'Surat berstatus "' . (SuratSekolahModel::TAMPIL_STATUS[$m['surat']['status']][0] ?? $m['surat']['status']) . '" tidak bisa diubah. Surat yang sudah disetujui harus dibatalkan persetujuannya dulu.')];
        }

        return [$m, null];
    }

    /**
     * Isian formulir: nilai awal, ditimpa isian lama (old()) bila formulir baru saja ditolak.
     *
     * @param array<string, string> $awal
     *
     * @return array<string, string>
     */
    protected function nilaiAwal(array $awal): array
    {
        $nilai = [];
        foreach ($awal as $k => $v) {
            $nilai[$k] = (string) old($k, $v);
        }

        return $nilai;
    }

    /** Daftar id (angka > 0, tanpa ganda) dari isian lama old($kunci) atau nilai bawaan. @return list<int> */
    protected function idLama(string $kunci, array $bawaan = []): array
    {
        $v = old($kunci);

        return array_values(array_unique(array_filter(array_map('intval', is_array($v) ? $v : $bawaan), static fn (int $x) => $x > 0)));
    }
}
