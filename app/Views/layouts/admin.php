<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Admin') ?> &mdash; Panel Admin</title>
    <meta name="robots" content="noindex">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: {
            50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',500:'#1e6fd6',600:'#1b5fb8',700:'#1a3a6b',800:'#15315a',900:'#0f2545'
        }, gold: { 400:'#fcc419', 500:'#f5a623' } }, fontFamily: { sans: ['Inter','Segoe UI','system-ui','sans-serif'] } } } };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family:'Inter',system-ui,sans-serif; } [x-cloak]{display:none!important;} </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="{ sidebar:false }">

<?php
    $cur  = uri_string();
    $nav  = [
        ['admin/dashboard',   'Dashboard',          'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['admin/submissions', 'Data Kesediaan',     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['admin/settings',    'Pengaturan Sekolah', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        ['admin/profile',     'Profil Saya',        'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ];
    $admin = session('admin') ?? [];
?>

<!-- Overlay mobile -->
<div x-show="sidebar" x-cloak @click="sidebar=false" class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

<!-- ===================== SIDEBAR ===================== -->
<aside class="fixed inset-y-0 left-0 z-40 w-64 bg-brand-800 text-white transform transition-transform lg:translate-x-0"
       :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
    <div class="h-16 flex items-center gap-2.5 px-5 border-b border-white/10">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gold-400 text-brand-900 font-extrabold">K</div>
        <div class="leading-tight">
            <p class="font-bold text-sm">Panel Admin</p>
            <p class="text-[11px] text-brand-200">Kesediaan Guru</p>
        </div>
    </div>
    <nav class="p-3 space-y-1">
        <?php foreach ($nav as [$url, $label, $icon]): $active = str_starts_with($cur, $url); ?>
            <a href="<?= site_url($url) ?>" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium transition <?= $active ? 'bg-white/15 text-white' : 'text-brand-100 hover:bg-white/10' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                <?= $label ?>
            </a>
        <?php endforeach; ?>
        <a href="<?= site_url('/') ?>" target="_blank" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-brand-100 hover:bg-white/10 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            Lihat Form Publik
        </a>
    </nav>
    <div class="absolute bottom-0 inset-x-0 p-3 border-t border-white/10">
        <a href="<?= site_url('admin/logout') ?>" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-red-200 hover:bg-red-500/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Keluar
        </a>
    </div>
</aside>

<!-- ===================== MAIN ===================== -->
<div class="lg:ml-64 min-h-screen flex flex-col">
    <!-- Topbar -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-20">
        <div class="flex items-center gap-3">
            <button @click="sidebar=true" class="lg:hidden text-slate-500"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <h1 class="font-bold text-slate-800 text-lg"><?= esc($title ?? 'Dashboard') ?></h1>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block leading-tight">
                <p class="text-sm font-semibold text-slate-700"><?= esc($admin['full_name'] ?? 'Admin') ?></p>
                <p class="text-xs text-slate-400">@<?= esc($admin['username'] ?? '') ?></p>
            </div>
            <a href="<?= site_url('admin/profile') ?>" class="block h-9 w-9 rounded-full overflow-hidden bg-brand-100 ring-2 ring-brand-200">
                <?php if (! empty($admin['photo'])): ?>
                    <img src="<?= base_url('uploads/' . esc($admin['photo'])) ?>" class="h-full w-full object-cover" alt="">
                <?php else: ?>
                    <span class="flex h-full w-full items-center justify-center text-brand-700 font-bold text-sm"><?= strtoupper(substr($admin['full_name'] ?? 'A', 0, 1)) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6">
        <?php if (session('success')): ?>
            <div class="mb-5 rounded-xl bg-green-50 border border-green-200 px-5 py-3.5 text-sm text-green-700 flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <?= esc(session('success')) ?>
            </div>
        <?php endif; ?>
        <?php if (session('error')): ?>
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-5 py-3.5 text-sm text-red-700"><?= esc(session('error')) ?></div>
        <?php endif; ?>
        <?php if (session('errors')): ?>
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-5 py-3.5 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-0.5"><?php foreach (session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>

<?= $this->renderSection('scripts') ?>
</body>
</html>
