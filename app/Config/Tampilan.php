<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Peta rombak tampilan admin (docs/RENCANA-UPGRADE-TAMPILAN.md): satu grup = satu fase. Satu sumber untuk
 * - layouts/admin.php → memuat Alpine versi CSP pada alamat yang grupnya sudah ada di $alpineCsp (App\Libraries\Tampilan);
 * - php spark dev:cek-ui → memeriksa view per grup (aturan tampilan + aturan siap-CSP);
 * - tools/uji-peramban.mjs --grup=… → membuka halaman grup itu saja.
 *
 * 'view' = berkas/folder relatif dari app/Views (berkas bersama halaman publik ditandai di 'bersama' — jangan pakai nama kelas
 * khas Tailwind v4 di sana selama publik masih Tailwind v3). 'url' = awalan alamat per segmen; bila beberapa grup cocok, awalan
 * terpanjang yang menang (admin/kurikulum/dashboard → dashboard, admin/kurikulum/bentrok → kesiswaan).
 */
class Tampilan extends BaseConfig
{
    /**
     * @var array<string, array{fase: string, label: string, view: list<string>, url: list<string>, bersama?: list<string>}>
     */
    public array $grup = [
        'login' => [
            'fase' => 'F2', 'label' => 'Halaman login',
            'view' => ['admin/login.php'],
            'url'  => ['admin/login'],
        ],
        'kit' => [
            'fase' => 'F1', 'label' => 'Kit komponen: partial ui_*(), paginasi admin, halaman ui-kit (khusus laptop)',
            'view' => ['admin/partials/ui', 'admin/ui_kit', 'pager/admin.php'],
            'url'  => ['admin/ui-kit'],
        ],
        'kerangka' => [
            'fase' => 'F3', 'label' => 'Kerangka: layout & partial bersama',
            'view'    => ['layouts/admin.php', 'admin/partials', 'partials/kredit.php', 'partials/favicon.php'],
            'url'     => [],
            'bersama' => ['partials/kredit.php', 'partials/favicon.php'],
        ],
        'dashboard' => [
            'fase' => 'F4', 'label' => 'Dashboard',
            'view' => ['admin/dashboard.php'], // admin/kurikulum/dashboard.php (view mati + Chart.js CDN) dihapus di F4; alamatnya hanya mengalihkan
            'url'  => ['admin/dashboard', 'admin/kurikulum/dashboard'],
        ],
        'master' => [
            'fase' => 'F5', 'label' => 'Data Master',
            'view' => ['admin/master'],
            'url'  => ['admin/master'],
        ],
        'kesiswaan' => [
            'fase' => 'F6', 'label' => 'Kesiswaan, Guru, Jadwal & Absensi',
            'view' => ['admin/biodata', 'admin/submissions', 'admin/jadwal', 'admin/kurikulum/bentrok.php', 'admin/kurikulum/rekap.php', 'admin/absensi'],
            'url'  => ['admin/biodata', 'admin/submissions', 'admin/export', 'admin/jadwal', 'admin/jadwal-guru', 'admin/cetak', 'admin/kurikulum', 'admin/absensi'],
        ],
        'ujian' => [
            'fase' => 'F7', 'label' => 'Ujian, Honor/Koreksi & SKBM',
            'view' => ['admin/ujian', 'admin/honor', 'admin/skbm'],
            'url'  => ['admin/ujian', 'admin/honor', 'admin/skbm'],
        ],
        'ukk-lab' => [
            'fase' => 'F8', 'label' => 'UKK & Laboratorium',
            'view' => ['admin/peserta_ukk', 'admin/penilaian_ukk', 'admin/jadwal_ukk', 'admin/sertifikat_ukk', 'admin/laporan_ukk', 'admin/berita_acara_ukk',
                'admin/peminjaman', 'admin/kerusakan', 'admin/perbaikan', 'admin/jadwal_lab', 'admin/jurnal_lab', 'admin/laporan_lab', 'admin/lab_gambar'],
            'url'  => ['admin/peserta-ukk', 'admin/penilaian-ukk', 'admin/jadwal-ukk', 'admin/sertifikat-ukk', 'admin/laporan-ukk', 'admin/berita-acara-ukk',
                'admin/peminjaman', 'admin/kerusakan', 'admin/perbaikan', 'admin/jadwal-lab', 'admin/jurnal-lab', 'admin/laporan-lab', 'admin/lab-gambar'],
        ],
        'pkl-surat' => [
            'fase' => 'F9', 'label' => 'PKL & Surat Sekolah',
            'view' => ['admin/pkl', 'admin/surat'],
            'url'  => ['admin/pkl', 'admin/surat'],
        ],
        'sistem' => [
            'fase' => 'F10', 'label' => 'Arsip, Info, Sistem & halaman galat',
            'view'    => ['admin/dokumen', 'admin/pengumuman', 'admin/akun', 'admin/hak_akses', 'admin/audit', 'admin/profile.php', 'admin/settings.php',
                'partials/dokumen_penampil.php', 'errors/html/error_404.php', 'errors/html/error_400.php', 'errors/html/production.php'],
            'url'     => ['admin/dokumen', 'admin/pengumuman', 'admin/akun', 'admin/hak-akses', 'admin/audit', 'admin/profile', 'admin/settings'],
            'bersama' => ['partials/dokumen_penampil.php', 'errors/html/error_404.php', 'errors/html/error_400.php', 'errors/html/production.php'],
        ],
    ];

    /**
     * Grup yang halamannya sudah memakai Alpine versi CSP (@alpinejs/csp). Diisi saat fase grup itu selesai; di F12 semua grup
     * masuk, lalu Alpine biasa dan pemilih ini dibuang. ('login' = halaman masuk: berdiri sendiri, selalu versi CSP. 'kit' = halaman
     * ui-kit, sejak F3 di dalam layouts/admin → kerangka ikut diuji di versi CSP. 'kerangka' = layout & partial bersama (F3): tidak
     * punya alamat sendiri, versinya mengikuti grup halaman — dicantumkan karena sudah siap kedua versi, agar dev:cek-ui melaporkannya.)
     *
     * @var list<string>
     */
    public array $alpineCsp = ['kit', 'login', 'kerangka', 'dashboard', 'master', 'kesiswaan', 'ujian', 'ukk-lab', 'pkl-surat'];
}
