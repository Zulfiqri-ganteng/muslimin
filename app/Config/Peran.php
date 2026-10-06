<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Peran staf beserta hak aksesnya.
 *
 * Inilah SATU-SATUNYA tempat yang menentukan siapa boleh membuka apa. Dibaca
 * App\Libraries\HakAkses oleh (a) penjaga rute web (AuthFilter), (b) menu samping,
 * dan (c) gerbang API Android. Mengubah hak akses sebuah peran cukup di sini.
 *
 * 'akses'  : awalan alamat (tanpa domain) yang boleh dibuka. '*' = semua.
 *            Awalan 'admin/pkl' mencakup 'admin/pkl' dan turunannya ('admin/pkl/12'),
 *            BUKAN 'admin/pkl-lain'. Alamat di luar daftar DITOLAK (deny by default).
 * 'kecuali': (opsional) awalan alamat yang DITOLAK walau berada di bawah 'akses'.
 *            Dipakai mis. Hubin boleh admin/pkl tetapi bukan admin/pkl/pengaturan.
 * 'beranda': halaman pertama setelah login.
 * 'api'    : boleh memakai aplikasi Android (token API). Dimatikan untuk peran
 *            terbatas karena API admin belum dipilah per peran.
 *
 * Akun lama berperan 'admin' (nilai bawaan kolom admins.role) → akses penuh,
 * jadi menambahkan sistem peran TIDAK mengubah apa pun bagi akun yang sudah ada.
 */
class Peran extends BaseConfig
{
    public const ADMIN    = 'admin';
    public const OPERATOR = 'operator';
    public const HUBIN    = 'hubin';

    /**
     * @var array<string, array{label:string, ringkas:string, beranda:string, akses:list<string>, api:bool}>
     */
    public array $peran = [
        self::ADMIN => [
            'label'   => 'Admin',
            'ringkas' => 'Akses penuh ke semua menu, termasuk Kelola Akun. Untuk pengelola sistem.',
            'beranda' => 'admin/dashboard',
            'akses'   => ['*'],
            'api'     => true,
        ],
        self::OPERATOR => [
            'label'   => 'Operator Sekolah',
            'ringkas' => 'Kerja harian: PKL (periksa, ACC, ubah, isi atas nama, cetak, pengaturan), Isian Biodata, Master Siswa & Kelas, Arsip Dokumen.',
            'beranda' => 'admin/pkl',
            'akses'   => [
                'admin/pkl',
                'admin/biodata',
                'admin/master/siswa',
                'admin/master/kelas',
                'admin/dokumen',
            ],
            'api' => false,
        ],
        self::HUBIN => [
            'label'   => 'Waka Hubin',
            'ringkas' => 'Hanya menu PKL: periksa dan ACC ajuan, ubah data, cetak surat. Tidak bisa menghapus data atau mengatur form.',
            'beranda' => 'admin/pkl',
            // CATATAN KEPUTUSAN (2026-10-07): Hubin TIDAK BOLEH melihat Isian Biodata Siswa
            // (admin/biodata) maupun Master Data (admin/master/*). Cukup dengan tidak mendaftarkannya
            // di 'akses' (tolak-secara-bawaan); jangan menambah awalan itu ke Hubin. Dijaga dev:uji-pkl.
            'akses'   => ['admin/pkl'],
            // Pengecualian dari 'akses': alamat di bawah awalan ini DITOLAK walau induknya boleh.
            'kecuali' => ['admin/pkl/pengaturan', 'admin/pkl/hapus', 'admin/pkl/impor'],
            'api'     => false,
        ],
    ];

    /**
     * Awalan alamat yang boleh dibuka SEMUA peran yang sudah login
     * (login & logout memang di luar penjaga, jadi tak perlu didaftar).
     *
     * @var list<string>
     */
    public array $umum = ['admin/profile'];
}
