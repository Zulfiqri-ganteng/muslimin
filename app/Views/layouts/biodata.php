<?php
/**
 * Layout form isian biodata siswa — sengaja polos tanpa menu navigasi:
 * siswa datang dari tautan WhatsApp, satu tujuan saja (mengisi biodata).
 *
 * @var array $setting
 */
$schoolName = $setting['school_name'] ?? 'Sekolah';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a3a6b">
    <title><?= esc($title ?? 'Isi Biodata Siswa') ?> &mdash; <?= esc($schoolName) ?></title>
    <?= view('partials/favicon') ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    <?= $this->renderSection('content') ?>

    <footer class="pb-8 pt-2 text-center text-xs text-slate-400 px-4">
        &copy; <?= date('Y') ?> <?= esc($schoolName) ?> &middot; Data pribadimu hanya dipakai untuk administrasi sekolah.
        <?= view('partials/kredit') ?>
    </footer>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
