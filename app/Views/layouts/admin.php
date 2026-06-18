<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Admin') ?> &mdash; Panel Admin</title>
    <meta name="robots" content="noindex">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="{ sidebar:false }">

<?php
    $cur  = uri_string();
    // Item link: [url, label, icon-path]. Item string = judul grup.
    $nav  = [
        ['admin/dashboard',   'Dashboard',          'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],

        'MASTER DATA',
        ['admin/master/guru',    'Guru',           'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z'],
        ['admin/master/jurusan', 'Jurusan',        'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-4h.01M9 7h6m-6 4h6m-2 4h2'],
        ['admin/master/hari',    'Hari',           'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['admin/master/jam',     'Jam Pelajaran',  'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],

        'DATA',
        ['admin/submissions', 'Data Kesediaan',     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],

        'PENGATURAN',
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
    <nav class="p-3 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 8rem)">
        <?php foreach ($nav as $item): ?>
            <?php if (is_string($item)): ?>
                <p class="px-3.5 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-brand-300"><?= esc($item) ?></p>
            <?php else: [$url, $label, $icon] = $item; $active = str_starts_with($cur, $url); ?>
                <a href="<?= site_url($url) ?>" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium transition <?= $active ? 'bg-white/15 text-white' : 'text-brand-100 hover:bg-white/10' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                    <?= $label ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <a href="<?= site_url('isi') ?>" target="_blank" class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-brand-100 hover:bg-white/10 transition">
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
