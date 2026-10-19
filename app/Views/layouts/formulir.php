<?php
/**
 * Bingkai formulir publik (rombak publik P6, gaya portal publik.css): sengaja TANPA menu — siswa datang dari tautan WhatsApp,
 * satu tujuan saja. Dipakai: Pengajuan PKL (P6a); menyusul Lapor diterima PKL (P6b) & Biodata siswa (P6c) — sampai saat itu
 * halaman tersebut tetap di layouts/pkl.php & layouts/biodata.php (Tailwind v3 lama, dibuang di P7).
 * Siap CSP: gaya & skrip di berkas, Alpine versi CSP lokal; skrip halaman (section 'scripts', mendaftarkan Alpine.data) dimuat
 * SEBELUM Alpine (defer menjaga urutan).
 *
 * Data dari view: $setting (wajib), $title, section 'content' & 'scripts'.
 */
$setting    = is_array($setting ?? null) ? $setting : [];
$schoolName = (string) ($setting['school_name'] ?? 'Sekolah');
$js         = static fn (string $berkas): string => '<script defer src="' . base_url($berkas) . '?v=' . @filemtime(FCPATH . $berkas) . '" ' . csp_script_nonce() . '></script>';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title><?= esc($title ?? 'Formulir') ?> &mdash; <?= esc($schoolName) ?></title>
    <?= view('partials/favicon') ?>
    <link rel="preload" href="<?= base_url('assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/publik.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/publik.css') ?>" <?= csp_style_nonce() ?>>
</head>
<body class="flex min-h-screen flex-col bg-permukaan">
<main class="flex-1">
    <?= $this->renderSection('content') ?>
</main>

<footer class="formulir-kaki">
    &copy; <?= date('Y') ?> <?= esc($schoolName) ?>
    <?= view('partials/kredit') ?>
</footer>

<?= $this->renderSection('scripts') ?>
<?= $js('assets/js/vendor/alpine-csp.min.js') ?>
</body>
</html>
