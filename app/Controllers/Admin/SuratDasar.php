<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\SuratSekolah;

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
}
