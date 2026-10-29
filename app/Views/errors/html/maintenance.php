<?php

/**
 * Halaman Mode Maintenance (docs/DESAIN-ALAT-SISTEM.md F2) untuk pengunjung situs, form siswa, dan staf selain Admin.
 * Kerangka bersama _galat.php: tanpa database, tanpa layout, siap CSP. Dirender ModeMaintenance::respons() (HTTP 503) dan
 * pratinjau di halaman Admin.
 *
 * @var array $info      ModeMaintenance::untukApi()
 * @var bool  $stafMasuk staf (bukan Admin) yang sedang masuk panel admin → tautan Keluar, bukan Masuk Admin
 */
$alamatM    = static fn (string $jalur): string => function_exists('site_url') ? site_url($jalur) : '/' . ltrim($jalur, '/');
$ikonM      = static fn (string $nama): string => function_exists('ikon') ? ikon($nama) : '';
$galatKode  = '';
$galatIkon  = 'tools';
$galatJudul = 'Sedang dalam perawatan';
$galatTeks  = (string) ($info['pesan'] ?? '');
$galatInfo  = ($info['selesai_label'] ?? '') !== ''
    ? 'Perkiraan selesai: ' . $info['selesai_label'] . '.'
    : 'Silakan buka kembali halaman ini beberapa saat lagi.';
$galatPesan = null;
$galatAksi  = '<a href="' . esc((string) ($_SERVER['REQUEST_URI'] ?? '/'), 'attr') . '" class="tombol tombol-utama">' . $ikonM('clock-arrow') . 'Coba lagi</a>';
$galatKaki  = ! empty($stafMasuk)
    ? 'Selama perawatan hanya Admin yang bisa bekerja. <a href="' . esc($alamatM('admin/logout'), 'attr') . '">Keluar dari akun</a>'
    : '<a href="' . esc($alamatM('admin/login'), 'attr') . '">Masuk Admin</a>';

include __DIR__ . DIRECTORY_SEPARATOR . '_galat.php';
