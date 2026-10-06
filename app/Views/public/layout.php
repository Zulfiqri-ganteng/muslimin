<?php
$schoolName = $setting['school_name'] ?? 'Sistem Akademik Sekolah';
$logo       = ! empty($setting['logo']) ? base_url('uploads/' . $setting['logo']) : null;
$cur        = uri_string();
$formOpen   = ! empty($setting['form_open']);
$urlPkl     = config('Pkl')->tautan(); // form pengajuan PKL (subdomain khusus)
$menu = [
    ['', 'Beranda'],
    ['jadwal-kelas', 'Jadwal Kelas'],
    ['jadwal-guru', 'Jadwal Guru'],
];
if ((int) ($setting['absensi_publik'] ?? 1) === 1) {
    $menu[] = ['absensi', 'Absensi'];
}
if ((int) ($setting['dokumen_publik'] ?? 0) === 1) {
    $menu[] = ['dokumen-publik', 'Dokumen'];
}
// Ikon yang dipakai berulang (satu tempat, agar menu desktop & HP serasi).
$ikonPkl   = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
$ikonLogin = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a3a6b">
    <title><?= esc($title ?? 'Beranda') ?> &mdash; <?= esc($schoolName) ?></title>
    <?php if (! empty($logo)): ?>
        <link rel="icon" href="<?= esc($logo) ?>"><link rel="apple-touch-icon" href="<?= esc($logo) ?>">
    <?php else: ?>
        <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <?php endif; ?>
    <script>document.documentElement.classList.add('js-reveal');</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col" x-data="{ open:false, scrolled:false }" @scroll.window.passive="scrolled = window.scrollY > 8" @keydown.escape.window="open = false">

<!-- Navbar -->
<header class="sticky top-0 z-30 border-b backdrop-blur transition-all duration-300" :class="scrolled ? 'bg-white/95 border-slate-200 shadow-sm' : 'bg-white/80 border-transparent'">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
        <a href="<?= site_url('/') ?>" class="flex items-center gap-2.5 min-w-0 group">
            <?php if ($logo): ?>
                <img src="<?= esc($logo) ?>" alt="Logo" class="h-10 w-10 rounded-xl object-contain shrink-0 transition group-hover:scale-105" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none" class="h-10 w-10 items-center justify-center rounded-xl bg-brand-700 text-white font-extrabold shrink-0"><?= strtoupper(substr($schoolName, 0, 1)) ?></span>
            <?php else: ?>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-700 text-white font-extrabold shrink-0"><?= strtoupper(substr($schoolName, 0, 1)) ?></span>
            <?php endif; ?>
            <span class="min-w-0 leading-tight">
                <span class="block font-extrabold text-slate-800 truncate"><?= esc($schoolName) ?></span>
                <span class="hidden sm:block text-[11px] font-medium text-slate-400 truncate">Sistem Informasi Akademik</span>
            </span>
        </a>

        <!-- desktop menu -->
        <nav class="hidden md:flex items-center gap-0.5" aria-label="Menu utama">
            <?php foreach ($menu as [$url, $label]): $active = $cur === $url; ?>
                <a href="<?= site_url($url) ?>" class="nav-link px-3.5 py-2 text-sm font-semibold transition <?= $active ? 'is-active text-brand-700' : 'text-slate-600 hover:text-brand-700' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
            <span class="mx-2 h-6 w-px bg-slate-200" aria-hidden="true"></span>
            <a href="<?= esc($urlPkl, 'attr') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:border-amber-400 text-sm font-semibold transition active:scale-95"><?= $ikonPkl ?> Ajukan PKL</a>
            <?php if ($formOpen): ?>
                <a href="<?= site_url('isi') ?>" class="ml-1.5 px-3.5 py-2 rounded-xl border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100 text-sm font-semibold transition active:scale-95">Form Kesediaan</a>
            <?php endif; ?>
            <a href="<?= site_url('admin/login') ?>" class="btn-shine ml-1.5 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white text-sm font-bold shadow-sm shadow-brand-700/25 transition active:scale-95"><?= $ikonLogin ?> Login</a>
        </nav>

        <!-- tombol menu HP -->
        <button type="button" @click="open = !open" class="md:hidden flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 transition active:scale-95" :aria-expanded="open" aria-label="Buka menu">
            <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- menu HP -->
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
         class="md:hidden border-t border-slate-100 bg-white px-4 pb-4 pt-2 shadow-lg">
        <nav class="space-y-0.5" aria-label="Menu utama (HP)">
            <?php foreach ($menu as [$url, $label]): $active = $cur === $url; ?>
                <a href="<?= site_url($url) ?>" class="flex items-center justify-between rounded-xl px-3.5 py-3 text-sm font-semibold transition <?= $active ? 'bg-brand-50 text-brand-700' : 'text-slate-700 hover:bg-slate-50' ?>"><?= $label ?><svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            <?php endforeach; ?>
        </nav>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <a href="<?= esc($urlPkl, 'attr') ?>" class="flex items-center justify-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-800 active:scale-95 transition"><?= $ikonPkl ?> Ajukan PKL</a>
            <a href="<?= site_url('admin/login') ?>" class="flex items-center justify-center gap-1.5 rounded-xl bg-brand-700 px-3 py-3 text-sm font-bold text-white shadow-sm active:scale-95 transition"><?= $ikonLogin ?> Login</a>
        </div>
        <?php if ($formOpen): ?>
            <a href="<?= site_url('isi') ?>" class="mt-2 block rounded-xl border border-brand-200 bg-brand-50 px-3 py-3 text-center text-sm font-semibold text-brand-700 active:scale-95 transition">Form Kesediaan Guru</a>
        <?php endif; ?>
    </div>
</header>

<main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
    <?= $this->renderSection('content') ?>
</main>

<footer class="mt-10 bg-brand-900 text-brand-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 grid gap-6 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <div class="flex items-center gap-3">
                <?php if ($logo): ?><img src="<?= esc($logo) ?>" alt="" class="h-10 w-10 rounded-xl bg-white/10 object-contain p-1" aria-hidden="true" onerror="this.remove()"><?php endif; ?>
                <p class="font-extrabold text-white"><?= esc($schoolName) ?></p>
            </div>
            <?php if (! empty($setting['address'])): ?><p class="mt-3 text-sm text-brand-200/90 max-w-md"><?= esc($setting['address']) ?></p><?php endif; ?>
        </div>
        <div class="text-sm">
            <p class="font-bold text-white">Tautan</p>
            <ul class="mt-2 space-y-1.5">
                <li><a href="<?= esc($urlPkl, 'attr') ?>" class="text-brand-200 hover:text-white transition">Ajukan PKL</a></li>
                <li><a href="<?= site_url('jadwal-kelas') ?>" class="text-brand-200 hover:text-white transition">Jadwal Kelas</a></li>
                <li><a href="<?= site_url('admin/login') ?>" class="text-brand-200 hover:text-white transition">Login Staf &amp; Guru</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 text-center text-xs text-brand-300">
            &copy; <?= date('Y') ?> <?= esc($schoolName) ?>
            <?php if (! empty($setting['academic_year'])): ?> &middot; T.P. <?= esc($setting['academic_year']) ?><?php endif; ?>
            &middot; Sistem Informasi Akademik Sekolah (BINUS)
            <?= view('partials/kredit') ?>
        </div>
    </div>
</footer>

<script>
/* Muncul saat digulir + angka berhitung naik. Tanpa JS / dengan "kurangi gerakan" semuanya langsung tampak. */
(function () {
    var kurangi = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var els = document.querySelectorAll('.reveal');
    function hitung(el) {
        var n = el.querySelectorAll('[data-count]');
        n.forEach(function (c) {
            var akhir = parseInt(c.getAttribute('data-count'), 10) || 0, mulai = null;
            if (kurangi || akhir < 2) { c.textContent = akhir.toLocaleString('id-ID'); return; }
            function langkah(t) {
                if (mulai === null) { mulai = t; }
                var p = Math.min(1, (t - mulai) / 900), e = 1 - Math.pow(1 - p, 3);
                c.textContent = Math.round(akhir * e).toLocaleString('id-ID');
                if (p < 1) { requestAnimationFrame(langkah); }
            }
            c.textContent = '0';
            requestAnimationFrame(langkah);
        });
    }
    function tampil(el) { if (!el.classList.contains('is-in')) { el.classList.add('is-in'); hitung(el); } }
    if (!('IntersectionObserver' in window) || kurangi) { els.forEach(function (e) { e.classList.add('is-in'); }); return; }
    var io = new IntersectionObserver(function (items) {
        items.forEach(function (i) { if (i.isIntersecting) { tampil(i.target); io.unobserve(i.target); } });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
})();
</script>
</body>
</html>
