<?php
/**
 * Favicon halaman = logo resmi sekolah (dari Pengaturan) untuk semua layar
 * backend & frontend. Mengganti ikon default framework (favicon.ico bawaan).
 * Fallback ke favicon.ico hanya bila logo belum diunggah.
 */
$favLogo = (new \App\Models\SettingModel())->get()['logo'] ?? null;
$favHref = ! empty($favLogo) ? base_url('uploads/' . $favLogo) : base_url('favicon.ico');
?>
<link rel="icon" href="<?= esc($favHref) ?>">
<link rel="apple-touch-icon" href="<?= esc($favHref) ?>">
