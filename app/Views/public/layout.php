<?php
/**
 * Tata letak halaman publik (beranda, jadwal, absensi, dokumen, tautan berbagi).
 * Tema lilac + mint (kelas tema-lilac, variabel di resources/css/app.css) — sama dengan panel admin.
 * Gaya sengaja tenang seperti portal sekolah resmi: tanpa gradasi, cahaya, kilau, atau animasi hiasan.
 */
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
$kontak = array_filter([
    $setting['address'] ?? '',
    ! empty($setting['phone']) ? 'Telp. ' . $setting['phone'] : '',
    $setting['email'] ?? '',
]);
?>
<!DOCTYPE html>
<html lang="id" class="tema-lilac">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4e3778">
    <title><?= esc($title ?? 'Beranda') ?> &mdash; <?= esc($schoolName) ?></title>
    <?php if (! empty($logo)): ?>
        <link rel="icon" href="<?= esc($logo) ?>"><link rel="apple-touch-icon" href="<?= esc($logo) ?>">
    <?php else: ?>
        <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-slate-800 antialiased" x-data="{ open: false }" @keydown.escape.window="open = false">

<?php if ($kontak !== []): ?>
<!-- Baris kontak (layar lebar) -->
<div class="hidden bg-brand-900 text-xs text-white/75 md:block">
    <div class="mx-auto flex h-8 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6">
        <p class="truncate"><?= esc(implode('  ·  ', $kontak)) ?></p>
        <?php if (! empty($setting['academic_year'])): ?><p class="shrink-0">Tahun Pelajaran <?= esc($setting['academic_year']) ?></p><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Kepala -->
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="<?= site_url('/') ?>" class="flex min-w-0 items-center gap-3">
            <?php if ($logo): ?>
                <img src="<?= esc($logo) ?>" alt="Logo <?= esc($schoolName, 'attr') ?>" class="h-10 w-10 shrink-0 object-contain" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none" class="h-10 w-10 shrink-0 items-center justify-center rounded-md bg-brand-700 font-semibold text-white"><?= strtoupper(substr($schoolName, 0, 1)) ?></span>
            <?php else: ?>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-brand-700 font-semibold text-white"><?= strtoupper(substr($schoolName, 0, 1)) ?></span>
            <?php endif; ?>
            <span class="min-w-0 leading-tight">
                <span class="block truncate font-semibold text-slate-900"><?= esc($schoolName) ?></span>
                <span class="block truncate text-xs text-slate-500">Sistem Informasi Akademik</span>
            </span>
        </a>

        <!-- menu layar lebar -->
        <nav class="hidden h-full items-stretch lg:flex" aria-label="Menu utama">
            <?php foreach ($menu as [$url, $label]): $active = $cur === $url; ?>
                <a href="<?= site_url($url) ?>" class="flex items-center border-b-2 px-3 text-sm font-medium transition-colors <?= $active ? 'border-brand-600 text-brand-800' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
            <a href="<?= esc($urlPkl, 'attr') ?>" class="flex items-center border-b-2 border-transparent px-3 text-sm font-medium text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-900">Pengajuan PKL</a>
            <?php if ($formOpen): ?>
                <a href="<?= site_url('isi') ?>" class="flex items-center border-b-2 border-transparent px-3 text-sm font-medium text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-900">Form Kesediaan</a>
            <?php endif; ?>
            <span class="flex items-center pl-3">
                <a href="<?= site_url('admin/login') ?>" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-800">Masuk</a>
            </span>
        </nav>

        <!-- tombol menu HP -->
        <button type="button" @click="open = !open" class="flex h-10 w-10 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 lg:hidden" :aria-expanded="open" aria-label="Buka menu">
            <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- menu HP -->
    <div x-show="open" x-cloak class="border-t border-slate-200 bg-white lg:hidden">
        <nav class="mx-auto max-w-6xl px-4 py-2 sm:px-6" aria-label="Menu utama (HP)">
            <?php foreach ($menu as [$url, $label]): $active = $cur === $url; ?>
                <a href="<?= site_url($url) ?>" class="block border-l-2 px-3 py-2.5 text-sm font-medium <?= $active ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-transparent text-slate-700 hover:bg-slate-50' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <a href="<?= esc($urlPkl, 'attr') ?>" class="block border-l-2 border-transparent px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Pengajuan PKL</a>
            <?php if ($formOpen): ?>
                <a href="<?= site_url('isi') ?>" class="block border-l-2 border-transparent px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Form Kesediaan Guru</a>
            <?php endif; ?>
            <a href="<?= site_url('admin/login') ?>" class="mt-2 mb-1 block rounded-md bg-brand-700 px-3 py-2.5 text-center text-sm font-medium text-white">Masuk (staf &amp; guru)</a>
        </nav>
    </div>
</header>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 sm:py-8">
    <?= $this->renderSection('content') ?>
</main>

<footer class="mt-8 bg-brand-900 text-sm text-white/75">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:grid-cols-3 sm:px-6">
        <div>
            <p class="font-semibold text-white"><?= esc($schoolName) ?></p>
            <?php if (! empty($setting['address'])): ?><p class="mt-2 max-w-xs leading-relaxed"><?= esc($setting['address']) ?></p><?php endif; ?>
        </div>
        <div>
            <p class="font-semibold text-white">Kontak</p>
            <ul class="mt-2 space-y-1">
                <?php if (! empty($setting['phone'])): ?><li>Telp. <?= esc($setting['phone']) ?></li><?php endif; ?>
                <?php if (! empty($setting['email'])): ?><li><a href="mailto:<?= esc($setting['email'], 'attr') ?>" class="hover:text-emerald-200"><?= esc($setting['email']) ?></a></li><?php endif; ?>
                <?php if (! empty($setting['website'])): ?><li><?= esc($setting['website']) ?></li><?php endif; ?>
                <?php if (empty($setting['phone']) && empty($setting['email']) && empty($setting['website'])): ?><li>&mdash;</li><?php endif; ?>
            </ul>
        </div>
        <div>
            <p class="font-semibold text-white">Tautan</p>
            <ul class="mt-2 space-y-1">
                <li><a href="<?= site_url('jadwal-kelas') ?>" class="hover:text-emerald-200">Jadwal kelas</a></li>
                <li><a href="<?= site_url('jadwal-guru') ?>" class="hover:text-emerald-200">Jadwal guru</a></li>
                <li><a href="<?= esc($urlPkl, 'attr') ?>" class="hover:text-emerald-200">Pengajuan PKL</a></li>
                <li><a href="<?= site_url('admin/login') ?>" class="hover:text-emerald-200">Masuk staf &amp; guru</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-white/60 sm:px-6">
            &copy; <?= date('Y') ?> <?= esc($schoolName) ?><?php if (! empty($setting['academic_year'])): ?> &middot; Tahun Pelajaran <?= esc($setting['academic_year']) ?><?php endif; ?>
            <?= view('partials/kredit', ['kreditKelas' => 'mt-1 text-white/50']) ?>
        </div>
    </div>
</footer>
</body>
</html>
