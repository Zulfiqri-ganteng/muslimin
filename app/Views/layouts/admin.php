<!DOCTYPE html>
<?php // tema-lilac: warna brand akun admin = ungu lilac + aksen mint (variabel di resources/css/app.css). ?>
<html lang="id" class="tema-lilac">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Admin') ?> &mdash; Panel Admin</title>
    <meta name="robots" content="noindex">
    <?php // Token CSRF untuk admin/app.js (tombol hapus via POST + header fetch). ?>
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <?= view('partials/favicon') ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="adminLayout">

<!-- ===================== LOADING OVERLAY (global, semua form) ===================== -->
<div x-show="loading" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-[70] flex items-center justify-center bg-white/60 backdrop-blur-sm">
    <div class="flex flex-col items-center gap-3">
        <svg class="animate-spin h-10 w-10 text-brand-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <p class="text-sm font-semibold text-brand-700" x-text="pesan">Memproses…</p>
    </div>
</div>

<?php
    $cur = uri_string();
    // Menu samping dari katalog Libraries\MenuAdmin (SATU sumber dengan penjaga rute & halaman Sistem → Hak Akses).
    // Item: [url, label, ikon, persis?, segera?]. Urutan grup & item mengikuti aturan urutan sidebar (lihat MenuAdmin).
    // Menu tiap peran disaring HakAkses::boleh di bawah — aturan yang sama dengan penjaga rute.
    $groups = \App\Libraries\MenuAdmin::sidebar();
    $admin = session('admin') ?? [];

    // Menu per peran: hanya tampilkan yang boleh dibuka (sumber aturan SAMA dengan
    // penjaga rute — Config\Peran — jadi menu & akses tak mungkin berselisih).
    $peranKini = (string) ($admin['role'] ?? '');
    $groups    = array_values(array_filter(array_map(static function (array $g) use ($peranKini): array {
        $g['items'] = array_values(array_filter(
            $g['items'],
            static fn (array $it): bool => \App\Libraries\HakAkses::boleh($peranKini, $it[0])
        ));
        return $g;
    }, $groups), static fn (array $g): bool => $g['items'] !== []));
    $labelPeranKini  = \App\Libraries\HakAkses::label($peranKini);
    // Menu yang diberi Admin level "Lihat saja": pita penjelas + tombol kirim (POST) di isi halaman dinonaktifkan (app.js). Penjaga
    // rute tetap menolak POST di halaman itu; ini hanya supaya pengguna tidak mengisi formulir yang pasti ditolak.
    $lihatSaja = \App\Libraries\HakAkses::levelAlamat($peranKini, $cur) === \App\Libraries\MenuAdmin::LIHAT;
    $bolehFormPublik = \App\Libraries\HakAkses::boleh($peranKini, 'admin/submissions');

    $setting = (new \App\Models\SettingModel())->get();
    $schoolName = $setting['school_name'] ?? 'Panel Admin';
    $schoolLogo = ! empty($setting['logo']) ? base_url('uploads/' . $setting['logo']) : null;

    // Item menu menyala bila alamat sekarang sama persis, atau (kecuali $it[3] = persis) berada di bawahnya.
    // Dicocokkan sebagai prefiks SEGMEN agar 'admin/jadwal' tidak ikut menyala saat membuka 'admin/jadwal-guru'.
    // Bila beberapa item cocok (mis. 'admin/absensi' & 'admin/absensi/piket'), hanya yang alamatnya TERPANJANG yang menyala.
    $cocokItem = static function (array $it) use ($cur): bool {
        $persis = (bool) ($it[3] ?? false);

        return $cur === $it[0] || (! $persis && str_starts_with($cur, $it[0] . '/'));
    };
    $urlAktif = '';
    foreach ($groups as $g) {
        foreach ($g['items'] as $it) {
            if (! ($it[4] ?? false) && strlen($it[0]) > strlen($urlAktif) && $cocokItem($it)) {
                $urlAktif = $it[0];
            }
        }
    }
    $itemAktif = static fn (array $it): bool => $urlAktif !== '' && $it[0] === $urlAktif;
    // Atribut pencarian menu: item tampil bila teks cari kosong atau cocok dengan labelnya.
    $atrCari = static fn (string $label): string => ' x-show="cari === \'\' || ' . esc(json_encode(mb_strtolower($label)), 'attr') . '.includes(cari.toLowerCase())"';

    // helper render satu link menu
    $renderLink = static function (array $it) use ($itemAktif, $atrCari) {
        [$url, $label, $icon] = $it;
        $active = $itemAktif($it);
        // Menu aktif: latar terang + garis mint di kiri (aksen tema lilac).
        $cls = $active ? 'bg-white/15 text-white shadow-[inset_3px_0_0_theme(colors.emerald.300)]' : 'text-brand-100 hover:bg-white/10 hover:text-white';
        echo '<a href="' . site_url($url) . '" title="' . esc($label) . '" ' . ($active ? 'aria-current="page" ' : '') . ltrim($atrCari($label)) . ' '
            . ':class="collapsed && !sidebar ? \'lg:justify-center\' : \'\'" '
            . 'class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium transition ' . $cls . '">'
            . '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="' . $icon . '"/></svg>'
            . '<span x-show="!collapsed || sidebar">' . esc($label) . '</span>'
            . '</a>';
    };

    // Menu rancangan (fitur belum jadi; item bertanda $it[4] = true): redup, tak bisa diklik, bertanda "Segera".
    $renderSegera = static function (array $it) use ($atrCari) {
        [, $label, $icon] = $it;
        echo '<div title="' . esc($label) . ' — segera hadir" aria-disabled="true"' . $atrCari($label) . ' '
            . ':class="collapsed && !sidebar ? \'lg:justify-center\' : \'\'" '
            . 'class="flex cursor-not-allowed select-none items-center gap-3 rounded-lg px-3.5 py-2 text-sm font-medium text-brand-300/70">'
            . '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="' . $icon . '"/></svg>'
            . '<span class="min-w-0 flex-1 leading-snug" x-show="!collapsed || sidebar">' . esc($label) . '</span>'
            . '<span class="shrink-0 rounded-full bg-gold-400/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-gold-400" x-show="!collapsed || sidebar">Segera</span>'
            . '</div>';
    };
    $renderItem = static fn (array $g, array $it) => ($it[4] ?? false) ? $renderSegera($it) : $renderLink($it);
?>

<!-- Overlay mobile -->
<div x-show="sidebar" x-cloak @click="sidebar=false" class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

<!-- ===================== SIDEBAR ===================== -->
<aside class="fixed inset-y-0 left-0 z-40 flex flex-col w-64 bg-gradient-to-b from-brand-700 via-brand-800 to-brand-900 text-white transform transition-all duration-200 lg:translate-x-0"
       :class="{ 'translate-x-0': sidebar, '-translate-x-full': !sidebar, 'lg:w-20': collapsed, 'lg:w-64': !collapsed }">
    <!-- Brand -->
    <div class="h-16 flex items-center gap-2.5 px-5 border-b border-white/10 shrink-0"
         :class="collapsed && !sidebar ? 'lg:px-0 lg:justify-center' : ''">
        <?php if ($schoolLogo): ?>
            <img src="<?= esc($schoolLogo) ?>" alt="Logo" class="h-9 w-9 rounded-lg object-contain shrink-0">
        <?php else: ?>
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-300 text-brand-900 font-extrabold shrink-0"><?= strtoupper(substr($schoolName, 0, 1)) ?></div>
        <?php endif; ?>
        <div class="leading-tight overflow-hidden" x-show="!collapsed || sidebar">
            <p class="font-bold text-sm truncate" title="<?= esc($schoolName) ?>"><?= esc($schoolName) ?></p>
            <p class="text-[11px] text-brand-200">Sistem Akademik Sekolah</p>
        </div>
    </div>

    <!-- Menu -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1" x-data="{ cari: '' }" aria-label="Menu utama">
        <!-- Cari menu: ±60 menu, jadi ada kotak pencarian (hanya saat sidebar lebar) -->
        <div class="px-0.5 pb-2" x-show="!collapsed || sidebar">
            <label class="relative block">
                <span class="sr-only">Cari menu</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                <input type="search" x-model="cari" placeholder="Cari menu…" autocomplete="off"
                       class="w-full rounded-lg border-0 bg-white/10 py-2 pl-9 pr-3 text-sm text-white placeholder-brand-300 transition focus:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/30">
            </label>
        </div>
        <?php foreach ($groups as $g): ?>
            <?php if ($g['title'] === null): ?>
                <?php foreach ($g['items'] as $it) { $renderItem($g, $it); } ?>
            <?php else:
                // Akordeon: grup yang berisi halaman sekarang selalu terbuka; grup lain terlipat dan ingatan buka/tutupnya
                // disimpan per peramban (localStorage). Saat mencari, semua grup yang cocok terbuka.
                $grupAktif = false;
                foreach ($g['items'] as $it) {
                    $grupAktif = $grupAktif || (! ($it[4] ?? false) && $itemAktif($it));
                }
                $labelGrup = array_map(static fn (array $it): string => mb_strtolower($it[1]), $g['items']);
                $kunciGrup = 'sb_g_' . substr(md5((string) $g['title']), 0, 8);
            ?>
                <div x-data="{ open: <?= $grupAktif ? 'true' : 'false' ?>, kunci: '<?= $kunciGrup ?>',
                               init() { if (! <?= $grupAktif ? 'true' : 'false' ?>) { try { this.open = localStorage.getItem(this.kunci) === '1'; } catch (e) {} } },
                               ganti() { this.open = ! this.open; try { localStorage.setItem(this.kunci, this.open ? '1' : '0'); } catch (e) {} } }"
                     x-show="cari === '' || <?= esc(json_encode($labelGrup), 'attr') ?>.some(l => l.includes(cari.toLowerCase()))" class="pt-2">
                    <!-- Judul grup (klik untuk buka/tutup) - hanya saat lebar -->
                    <button type="button" @click="ganti()" x-show="!collapsed || sidebar" :aria-expanded="open || cari !== ''"
                            class="flex w-full items-center justify-between rounded-md px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider transition hover:text-white <?= $grupAktif ? 'text-white' : 'text-brand-300' ?>">
                        <span class="flex items-center gap-1.5"><?= esc($g['title']) ?><?php if ($grupAktif): ?><span class="h-1.5 w-1.5 rounded-full bg-emerald-300" aria-hidden="true"></span><?php endif; ?></span>
                        <svg class="h-3.5 w-3.5 transition-transform" :class="(open || cari !== '') ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <!-- Saat menciut: garis pemisah tipis pengganti judul -->
                    <div x-show="collapsed && !sidebar" class="mx-3 my-1 border-t border-white/10"></div>
                    <div class="space-y-1 mt-1" x-show="open || cari !== '' || (collapsed && !sidebar)" x-collapse.duration.200ms>
                        <?php foreach ($g['items'] as $it) { $renderItem($g, $it); } ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php $semuaLabel = []; foreach ($groups as $g) { foreach ($g['items'] as $it) { $semuaLabel[] = mb_strtolower($it[1]); } } ?>
        <p x-cloak x-show="cari !== '' && ! <?= esc(json_encode($semuaLabel), 'attr') ?>.some(l => l.includes(cari.toLowerCase()))" class="px-3.5 py-3 text-xs text-brand-300">Menu tidak ditemukan.</p>

        <?php if ($bolehFormPublik): // tautan form kesediaan guru — tak relevan bagi Operator/Hubin ?>
        <div class="pt-2" x-show="cari === ''">
            <a href="<?= site_url('isi') ?>" target="_blank" title="Lihat Form Publik"
               :class="collapsed && !sidebar ? 'lg:justify-center' : ''"
               class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-brand-100 hover:bg-white/10 transition">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span x-show="!collapsed || sidebar">Lihat Form Publik</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Footer: tombol keluar (flex, tidak lagi absolute → tak menumpuk) -->
    <div class="p-3 border-t border-white/10 shrink-0">
        <a href="<?= site_url('admin/logout') ?>" title="Keluar"
           :class="collapsed && !sidebar ? 'lg:justify-center' : ''"
           class="flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium text-red-200 hover:bg-red-500/20 transition">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span x-show="!collapsed || sidebar">Keluar</span>
        </a>
    </div>
</aside>

<!-- ===================== MAIN ===================== -->
<div class="min-h-screen flex flex-col transition-all duration-200" :class="collapsed ? 'lg:ml-20' : 'lg:ml-64'">
    <!-- Topbar -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-20">
        <div class="flex items-center gap-3">
            <!-- Mobile: buka drawer -->
            <button @click="sidebar=true" class="lg:hidden text-slate-500 hover:text-slate-700"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <!-- Desktop: ciutkan/lebarkan sidebar -->
            <button @click="collapsed=!collapsed" class="hidden lg:inline-flex text-slate-500 hover:text-slate-700" :title="collapsed ? 'Lebarkan menu' : 'Ciutkan menu'"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <h1 class="font-bold text-slate-800 text-lg"><?= esc($title ?? 'Dashboard') ?></h1>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block leading-tight">
                <p class="text-sm font-semibold text-slate-700"><?= esc($admin['full_name'] ?? 'Admin') ?></p>
                <p class="text-xs text-slate-400"><span class="font-semibold text-brand-600"><?= esc($labelPeranKini) ?></span> · @<?= esc($admin['username'] ?? '') ?></p>
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

    <main class="flex-1 p-4 sm:p-6"<?= $lihatSaja ? ' data-lihat-saja="1"' : '' ?>>
        <?php if ($lihatSaja): ?>
            <div class="mb-5 flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-5 py-3 text-sm text-sky-900">
                <span aria-hidden="true">👁</span>
                <span><b>Mode lihat saja.</b> Akun <?= esc($labelPeranKini) ?> bisa membuka dan mengunduh dari halaman ini, tetapi tidak bisa mengubah datanya. Admin bisa mengubahnya di Sistem → Hak Akses.</span>
            </div>
        <?php endif; ?>
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

    <footer class="px-4 sm:px-6 pb-5 text-center text-xs text-slate-400">
        <p>&copy; <?= date('Y') ?> &middot; Sistem Informasi Akademik Sekolah (BINUS)</p>
        <?= view('partials/kredit', ['kreditKelas' => '']) ?>
    </footer>
</div>

<?php // Skrip halaman dimuat lebih dulu (defer = urut dokumen), lalu util global, terakhir Alpine. ?>
<?= $this->renderSection('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/app.js') ?>"></script>
<script defer src="<?= base_url('assets/js/vendor/alpine-collapse.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine-collapse.min.js') ?>"></script>
<script defer src="<?= base_url('assets/js/vendor/alpine.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine.min.js') ?>"></script>
</body>
</html>
