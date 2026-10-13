<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\PklHak;
use App\Libraries\SuratJenis;
use App\Libraries\SuratKeputusan;
use App\Libraries\SuratSekolah;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Dasar controller halaman staf Surat Sekolah (menu SURAT SEKOLAH): data umum tampilan dan hak peran. Pembantu respons
 * (konteks pencatat riwayat, redirect, JSON) diwarisi dari PklDasar. Hak diatur Admin PER JENIS surat (Buat / ACC / Unduh,
 * Sistem → Hak Akses; PklHak) — controller turunan tetap memeriksa lagi hak yang dituntut aksinya (pagar kedua setelah filter
 * rute). Rancangan: docs/DESAIN-SURAT-SEKOLAH.md, docs/DESAIN-HAK-AKSES.md.
 */
abstract class SuratDasar extends PklDasar
{
    protected SuratSekolah $surat;

    public function __construct()
    {
        parent::__construct();
        $this->surat = new SuratSekolah();
    }

    /** Data umum semua halaman Surat Sekolah. bolehBuat/Acc/Unduh = boleh untuk jenis APA PUN; hakSurat = rincian per jenis. */
    protected function dasarSurat(string $judul, string $tab): array
    {
        $peran = $this->peranSaya();

        return [
            'title'      => $judul,
            'tab'        => $tab,
            'peran'      => $peran,
            'peranLabel' => HakAkses::label($peran),
            'bolehBuat'  => PklHak::boleh($peran, 'surat_buat:*'),
            'bolehAcc'   => PklHak::boleh($peran, 'surat_acc:*'),
            'bolehUnduh' => PklHak::boleh($peran, 'surat_unduh:*'),
            'hakSurat'   => PklHak::petaSurat($peran),
            'hitung'     => $this->surat->hitungStatus(),
        ];
    }

    /** Data formulir satu jenis: wajib ACC?, boleh "Simpan & setujui"?, siapa pemegang ACC (untuk teks). */
    protected function dasarJenis(string $jenis): array
    {
        $perlu = SuratJenis::perluAcc($jenis, $this->p);

        return [
            'perluAcc'        => $perlu,
            'bisaLangsungAcc' => $perlu && PklHak::bolehSurat($this->peranSaya(), 'acc', $jenis),
            'pemegangAcc'     => PklHak::teksPemegang(PklHak::kunciSurat('acc', $jenis)),
        ];
    }

    /** Tolak (dengan pesan di halaman) bila peran saya tak memegang hak $aksi (buat|acc|unduh) untuk jenis $jenis. */
    protected function tolakJenis(string $aksi, string $jenis, string $balik = 'admin/surat')
    {
        if (PklHak::bolehSurat($this->peranSaya(), $aksi, $jenis)) {
            return null;
        }
        $kata = ['buat' => 'membuat/mengubah', 'acc' => 'meng-ACC', 'unduh' => 'mengunduh'][$aksi] ?? $aksi;

        return $this->ke($balik, 'error', 'Akun ' . HakAkses::label($this->peranSaya()) . ' tidak punya hak ' . $kata . ' ' . SuratJenis::label($jenis) . '. Admin bisa mengaturnya di Sistem → Hak Akses.');
    }

    /**
     * Setelah membuat surat: bila tombol "Simpan & setujui" ditekan (langsung_acc=1) dan peran ini memegang hak ACC jenis itu,
     * surat langsung di-ACC atas nama pembuatnya lewat SuratKeputusan (aturan, peringatan BAHAYA, dan pencatatan tetap berlaku).
     *
     * @param list<int> $ids
     *
     * @return array{acc: int, catatan: list<string>}
     */
    protected function langsungAcc(array $ids, string $jenis): array
    {
        if ((string) $this->request->getPost('langsung_acc') !== '1') {
            return ['acc' => 0, 'catatan' => []];
        }

        return (new SuratKeputusan($this->surat))->accSetelahBuat($ids, $jenis, $this->konteks(['saluran' => 'web']), $this->p);
    }

    /** Kalimat penutup pesan setelah membuat $dibuat surat jenis $jenis. */
    protected function akhirBuat(string $jenis, int $dibuat, array $acc): string
    {
        if (! SuratJenis::perluAcc($jenis, $this->p)) {
            return ' Surat langsung siap diunduh.';
        }
        if ($acc['acc'] > 0 && $acc['acc'] >= $dibuat) {
            return ' Surat langsung disetujui (ACC) atas nama Anda dan siap diunduh.';
        }
        if ($acc['acc'] > 0) {
            return ' ' . $acc['acc'] . ' surat langsung disetujui; sisanya menunggu ACC.';
        }

        return ' Surat menunggu ACC (' . PklHak::teksPemegang(PklHak::kunciSurat('acc', $jenis)) . ').';
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

    /** Tolak (dengan pesan di halaman) bila peran saya tak memegang hak $hak (kunci / pola PklHak); null bila boleh. */
    protected function tolakHalaman(string $hak, string $balik = 'admin/surat')
    {
        if (PklHak::boleh($this->peranSaya(), $hak)) {
            return null;
        }

        return $this->ke($balik, 'error', 'Akun ' . HakAkses::label($this->peranSaya()) . ' tidak punya hak untuk aksi ini. Admin bisa mengaturnya di Sistem → Hak Akses.');
    }

    /**
     * Surat jenis $jenis yang BOLEH diubah datanya (belum bernomor, status menunggu/dikembalikan, hak Buat jenis itu).
     *
     * @return array{0: array<string, mixed>|null, 1: RedirectResponse|null} surat termuat (SuratSekolah::muat), atau pengalihan galat
     */
    protected function muatBisaDiubah(string $jenis, int $id): array
    {
        if (($tolak = $this->tolakJenis('buat', $jenis, 'admin/surat/' . $id)) !== null) {
            return [null, $tolak];
        }
        $m = $this->surat->muat($id);
        if ($m === null || $m['surat']['jenis'] !== $jenis) {
            return [null, $this->ke('admin/surat', 'error', 'Surat tidak ditemukan.')];
        }
        if (($alasan = SuratSekolah::alasanTakBisaUbah($m['surat'])) !== null) {
            return [null, $this->ke('admin/surat/' . $id, 'error', $alasan)];
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
