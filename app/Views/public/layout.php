<?php
$schoolName = $setting['school_name'] ?? 'Sistem Akademik Sekolah';
$logo       = ! empty($setting['logo']) ? base_url('uploads/' . $setting['logo']) : null;
$cur        = uri_string();
$formOpen   = ! empty($setting['form_open']);
$menu = [
    ['', 'Beranda'],
    ['jadwal-kelas', 'Jadwal Kelas'],
    ['jadwal-guru', 'Jadwal Guru'],
    ['absensi', 'Absensi'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Beranda') ?> &mdash; <?= esc($schoolName) ?></title>
    <?php if (! empty($logo)): ?>
        <link rel="icon" href="<?= esc($logo) ?>"><link rel="apple-touch-icon" href="<?= esc($logo) ?>">
    <?php else: ?>
        <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="bg-slate-50 text-slate-800 antialiased" x-data="{ open:false }">

<!-- Navbar -->
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="<?= site_url('/') ?>" class="flex items-center gap-2.5 min-w-0">
            <?php if ($logo): ?>
                <img src="<?= esc($logo) ?>" alt="Logo" class="h-9 w-9 rounded-lg object-cover bg-slate-100 shrink-0">
            <?php else: ?>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-700 text-white font-extrabold shrink-0"><?= strtoupper(substr($schoolName, 0, 1)) ?></span>
            <?php endif; ?>
            <span class="font-bold text-slate-800 truncate"><?= esc($schoolName) ?></span>
        </a>

        <!-- desktop menu -->
        <nav class="hidden md:flex items-center gap-1">
            <?php foreach ($menu as [$url, $label]): $active = $cur === $url; ?>
                <a href="<?= site_url($url) ?>" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition <?= $active ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <?php if ($formOpen): ?>
                <a href="<?= site_url('isi') ?>" class="ml-1 px-4 py-2 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition">Form Kesediaan</a>
            <?php endif; ?>
        </nav>

        <!-- mobile toggle -->
        <button @click="open=!open" class="md:hidden text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <!-- mobile menu -->
    <div x-show="open" x-cloak class="md:hidden border-t border-slate-100 bg-white px-4 py-2 space-y-1">
        <?php foreach ($menu as [$url, $label]): ?>
            <a href="<?= site_url($url) ?>" class="block px-3 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100"><?= $label ?></a>
        <?php endforeach; ?>
        <?php if ($formOpen): ?>
            <a href="<?= site_url('isi') ?>" class="block px-3 py-2 rounded-lg bg-brand-700 text-white text-sm font-semibold text-center">Form Kesediaan</a>
        <?php endif; ?>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <?= $this->renderSection('content') ?>
</main>

<footer class="border-t border-slate-200 bg-white mt-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 text-center text-sm text-slate-400">
        &copy; <?= date('Y') ?> <?= esc($schoolName) ?>
        <?php if (! empty($setting['academic_year'])): ?> &middot; T.P. <?= esc($setting['academic_year']) ?><?php endif; ?>
    </div>
</footer>
</body>
</html>
