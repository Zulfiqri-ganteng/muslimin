<?php
/**
 * Favicon halaman = logo resmi sekolah (dari Pengaturan) untuk semua layar
 * backend & frontend. Mengganti ikon default framework (favicon.ico bawaan).
 * Cadangan favicon.ico bila logo belum diunggah ATAU berkasnya tidak ada di disk
 * (mis. data laptop belum lengkap) — supaya tidak memicu galat 404 di setiap halaman.
 * Berkas bersama halaman publik: tanpa kelas CSS.
 */
$favLogo = (string) ((new \App\Models\SettingModel())->get()['logo'] ?? '');
$favHref = $favLogo !== '' && is_file(FCPATH . 'uploads/' . $favLogo)
    ? base_url('uploads/' . rawurlencode($favLogo))
    : base_url('favicon.ico');
?>
<link rel="icon" href="<?= esc($favHref, 'attr') ?>">
<link rel="apple-touch-icon" href="<?= esc($favHref, 'attr') ?>">
