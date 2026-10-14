<?php
/**
 * Kerangka panel admin (rombak tampilan F3 — docs/RENCANA-UPGRADE-TAMPILAN.md §3–§5a), pola Flowbite Admin Dashboard:
 * bilah atas putih selebar layar (tombol menu, logo + nama sekolah, menu akun), menu samping di bawahnya (3 gaya tema, cari menu,
 * grup lipat, bisa diciutkan jadi ikon, laci di HP), kepala halaman seragam (remah roti otomatis dari katalog menu + judul), pesan,
 * isi, kaki. Tema (<html data-warna data-sidebar>) & status menu (ciut, grup terbuka — cookie) ditulis server: tanpa kedipan.
 * SIAP CSP: tanpa skrip/gaya sebaris; atribut Alpine hanya isian sederhana (adminLayout / sbMenu / sbGrup di app.js, kit di ui.js),
 * jadi jalan di Alpine biasa maupun versi CSP (dipilih per grup: Config\Tampilan::$alpineCsp).
 *
 * Dipakai halaman: $title (judul); section 'content'; opsional section 'aksi' (tombol di kanan judul), 'kepala' (mengganti kepala
 * halaman bawaan, mis. ui_kepala() dengan tab), 'scripts' (berkas JS halaman). Halaman yang isinya sudah punya <h1> sendiri
 * (belum dirombak) hanya mendapat remah roti, supaya judul tidak ganda.
 */
$cur   = uri_string();
$admin = session('admin') ?? [];

// Menu samping dari katalog Libraries\MenuAdmin (SATU sumber dengan penjaga rute & halaman Sistem → Hak Akses).
// Item: [url, label, ikon (nama Flowbite Icons), persis?, segera?]. Urutan grup & item mengikuti aturan urutan sidebar.
// Menu tiap peran disaring HakAkses::boleh — aturan yang sama dengan penjaga rute, jadi menu & akses tak mungkin berselisih.
$peranKini = (string) ($admin['role'] ?? '');
$groups    = array_values(array_filter(array_map(static function (array $g) use ($peranKini): array {
    $g['items'] = array_values(array_filter(
        $g['items'],
        static fn (array $it): bool => \App\Libraries\HakAkses::boleh($peranKini, $it[0])
    ));

    return $g;
}, \App\Libraries\MenuAdmin::sidebar()), static fn (array $g): bool => $g['items'] !== []));
$labelPeranKini = \App\Libraries\HakAkses::label($peranKini);
$beranda        = \App\Libraries\HakAkses::beranda($peranKini);
// Menu yang diberi Admin level "Lihat saja": pita penjelas + tombol kirim (POST) di isi halaman dinonaktifkan (app.js). Penjaga
// rute tetap menolak POST di halaman itu; ini hanya supaya pengguna tidak mengisi formulir yang pasti ditolak.
$lihatSaja       = \App\Libraries\HakAkses::levelAlamat($peranKini, $cur) === \App\Libraries\MenuAdmin::LIHAT;
$bolehFormPublik = \App\Libraries\HakAkses::boleh($peranKini, 'admin/submissions');

$setting    = (new \App\Models\SettingModel())->get();
$schoolName = trim((string) ($setting['school_name'] ?? '')) ?: 'Panel Admin';
$logo       = (string) ($setting['logo'] ?? '');
$schoolLogo = $logo !== '' && is_file(FCPATH . 'uploads/' . $logo) ? base_url('uploads/' . rawurlencode($logo)) : null;
$tema       = \App\Libraries\Tampilan::tema($setting);
$ciut       = \App\Libraries\Tampilan::sidebarCiut();
$grupBuka   = \App\Libraries\Tampilan::grupTerbuka();
$namaAkun   = (string) ($admin['full_name'] ?? 'Admin');
$awalAkun   = mb_strtoupper(mb_substr($namaAkun, 0, 1));

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

// Remah roti otomatis: Beranda (di ui_remah) › Grup › Menu › judul halaman (bila beda dengan nama menunya). Halaman beranda: tanpa remah.
$judulHalaman = (string) ($title ?? 'Panel Admin');
$namaGrup     = static fn (string $t): string => strtr(mb_convert_case(mb_strtolower($t), MB_CASE_TITLE), ['(Ukk)' => '(UKK)', 'Pkl ' => 'PKL ']);
$remah        = [];
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        if (! ($it[4] ?? false) && $itemAktif($it)) {
            if ($g['title'] !== null) {
                $remah[] = [$namaGrup((string) $g['title'])];
            }
            if (mb_strtolower((string) $it[1]) === mb_strtolower($judulHalaman)) {
                $remah[] = [(string) $it[1]];
            } else {
                $remah[] = [(string) $it[1], site_url($it[0])];
                $remah[] = [$judulHalaman];
            }
        }
    }
}
if ($remah === [] && $cur !== $beranda) {
    $remah[] = [$judulHalaman];
}
if ($cur === $beranda) {
    $remah = [];
}

// Isi halaman dirender lebih dulu: halaman lama yang sudah punya <h1> sendiri cukup diberi remah roti (tanpa judul ganda).
$isiHalaman   = $this->renderSection('content');
$kepalaKhusus = trim($this->renderSection('kepala'));
$aksiHalaman  = trim($this->renderSection('aksi'));
$punyaJudul   = str_contains($isiHalaman, '<h1');

// Pencarian menu: item tampil bila teks cari kosong atau cocok dengan labelnya (sbMenu.sesuai di app.js).
$atrCari = static fn (string $label): string => ' x-show="sesuai(' . esc(json_encode(mb_strtolower($label)), 'attr') . ')"';
$renderItem = static function (array $it) use ($itemAktif, $atrCari): void {
    [$url, $label, $ikon] = $it;
    if ($it[4] ?? false) {
        // Menu rancangan (fitur belum jadi): redup, tak bisa diklik, bertanda "Segera".
        echo '<div class="sb-item sb-item-segera" title="' . esc($label, 'attr') . ' — segera hadir" aria-disabled="true"' . $atrCari($label) . '>'
            . ikon((string) $ikon, 'sb-ikon') . '<span class="sb-label">' . esc($label) . '</span><span class="sb-segera">Segera</span></div>';

        return;
    }
    echo '<a href="' . site_url($url) . '" class="sb-item" title="' . esc($label, 'attr') . '"' . ($itemAktif($it) ? ' aria-current="page"' : '')
        . $atrCari($label) . '>' . ikon((string) $ikon, 'sb-ikon') . '<span class="sb-label">' . esc($label) . '</span></a>';
};
$semuaLabel = [];
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        $semuaLabel[] = mb_strtolower((string) $it[1]);
    }
}
$alpineJs = \App\Libraries\Tampilan::alpineCsp($cur) ? 'assets/js/vendor/alpine-csp.min.js' : 'assets/js/vendor/alpine.min.js';
$js       = static fn (string $berkas): string => '<script defer src="' . base_url($berkas) . '?v=' . @filemtime(FCPATH . $berkas) . '" ' . csp_script_nonce() . '></script>';
?>
<!DOCTYPE html>
<html lang="id" data-warna="<?= $tema['warna'] ?>" data-sidebar="<?= $tema['sidebar'] ?>"<?= $ciut ? ' data-ciut' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($judulHalaman) ?> &mdash; Panel Admin</title>
    <meta name="robots" content="noindex">
    <?php // Token CSRF untuk admin/app.js (tombol hapus via POST + header fetch). ?>
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <?= view('partials/favicon') ?>
    <?php // admin.css = Tailwind v4 khusus admin (npm run build:admin); font Inter disimpan sendiri; penanda nonce = persiapan CSP (§5a). ?>
    <link rel="preload" href="<?= base_url('assets/fonts/inter/inter-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/admin.css') ?>" <?= csp_style_nonce() ?>>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="adminLayout" @keydown.escape.window="tutupLaci()">
<a href="#isi" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[80] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-brand-700 focus:shadow-sm">Lewati ke isi halaman</a>

<!-- Layar "Memproses…" saat formulir dikirim (semua formulir, kecuali data-noload). -->
<div x-show="loading" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-[70] flex items-center justify-center bg-white/70" role="status" aria-live="polite">
    <div class="flex flex-col items-center gap-3">
        <span class="pemutar h-10 w-10 border-4 text-brand-600" aria-hidden="true"></span>
        <p class="text-sm font-medium text-brand-700" x-text="pesan">Memproses…</p>
    </div>
</div>

<!-- ===================== BILAH ATAS ===================== -->
<header class="navbar">
    <div class="flex h-full items-center justify-between gap-3 px-3 lg:px-4">
        <div class="flex min-w-0 items-center gap-2">
            <button type="button" class="tombol-navbar lg:hidden" @click="alihLaci()" aria-controls="menu-samping" :aria-expanded="sidebar" aria-label="Buka atau tutup menu"><?= ikon('bars', 'h-6 w-6') ?></button>
            <button type="button" class="tombol-navbar hidden lg:inline-flex" @click="alihCiut()" aria-controls="menu-samping" :aria-pressed="collapsed"
                    aria-label="Ciutkan menu samping" :title="collapsed ? 'Lebarkan menu samping' : 'Ciutkan menu samping'"><?= ikon('bars', 'h-6 w-6') ?></button>
            <a href="<?= site_url($beranda) ?>" class="flex min-w-0 items-center gap-2.5 rounded-lg focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-300">
                <?php if ($schoolLogo !== null): ?>
                    <img src="<?= esc($schoolLogo, 'attr') ?>" alt="" class="h-9 w-9 shrink-0 rounded-lg object-contain">
                <?php else: ?>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-700 font-semibold text-white" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($schoolName, 0, 1))) ?></span>
                <?php endif; ?>
                <span class="hidden min-w-0 leading-tight sm:block">
                    <span class="block truncate text-sm font-semibold text-gray-900" title="<?= esc($schoolName, 'attr') ?>"><?= esc($schoolName) ?></span>
                    <span class="block text-xs text-gray-500">Sistem Informasi Akademik Sekolah</span>
                </span>
            </a>
        </div>

        <!-- Menu akun -->
        <div class="relative" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">
            <button type="button" x-ref="pemicu" @click="alih()" :aria-expanded="buka" aria-haspopup="true" aria-label="Menu akun <?= esc($namaAkun, 'attr') ?>"
                    class="flex items-center gap-2 rounded-lg p-1 text-left transition hover:bg-gray-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-gray-300">
                <span class="hidden text-right leading-tight sm:block">
                    <span class="block max-w-48 truncate text-sm font-medium text-gray-900"><?= esc($namaAkun) ?></span>
                    <span class="block text-xs text-gray-500"><?= esc($labelPeranKini) ?></span>
                </span>
                <?php if (! empty($admin['photo'])): ?>
                    <img src="<?= base_url('uploads/' . esc($admin['photo'], 'url')) ?>" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover">
                <?php else: ?>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700" aria-hidden="true"><?= esc($awalAkun) ?></span>
                <?php endif; ?>
                <?= ikon('chevron-down', 'hidden h-4 w-4 text-gray-500 sm:block') ?>
            </button>
            <div class="dropdown-menu right-0 w-60" x-show="buka" x-cloak x-transition.origin.top.right.duration.150ms>
                <div class="dropdown-kepala">
                    <p class="truncate text-sm font-semibold"><?= esc($namaAkun) ?></p>
                    <p class="truncate text-xs text-gray-500"><?= esc($labelPeranKini) ?> &middot; @<?= esc((string) ($admin['username'] ?? '')) ?></p>
                </div>
                <ul class="py-1">
                    <li><a href="<?= site_url('admin/profile') ?>" class="dropdown-item"><?= ikon('user', 'h-4 w-4') ?>Profil Saya</a></li>
                    <?php if ($bolehFormPublik): // tautan form kesediaan guru — tak relevan bagi Operator/Hubin ?>
                        <li><a href="<?= site_url('isi') ?>" target="_blank" rel="noopener" class="dropdown-item"><?= ikon('arrow-up-right-from-square', 'h-4 w-4') ?>Lihat Form Publik</a></li>
                    <?php endif; ?>
                </ul>
                <ul class="py-1">
                    <li><a href="<?= site_url('admin/logout') ?>" class="dropdown-item dropdown-item-bahaya"><?= ikon('arrow-right-to-bracket', 'h-4 w-4') ?>Keluar</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>

<!-- ===================== MENU SAMPING ===================== -->
<aside id="menu-samping" class="sb" :data-terbuka="sidebar" aria-label="Menu samping">
    <nav class="sb-nav" aria-label="Menu utama" x-data="sbMenu" data-label="<?= esc(json_encode($semuaLabel), 'attr') ?>">
        <label class="sb-cari-wadah">
            <span class="sr-only">Cari menu</span>
            <?= ikon('search', 'sb-cari-ikon') ?>
            <input type="search" x-model="cari" class="sb-cari" placeholder="Cari menu…" autocomplete="off">
        </label>
        <?php foreach ($groups as $g): ?>
            <?php if ($g['title'] === null): ?>
                <?php foreach ($g['items'] as $it) { $renderItem($it); } ?>
            <?php else:
                // Grup lipat: grup berisi halaman sekarang selalu terbuka; grup lain mengikuti pilihan pengguna (cookie sb_grup, dibaca
                // server → tidak berkedip). Menu menciut (desktop) menampilkan semua ikon, judul grup diganti garis tipis.
                $grupAktif = false;
                foreach ($g['items'] as $it) {
                    $grupAktif = $grupAktif || (! ($it[4] ?? false) && $itemAktif($it));
                }
                $kunciGrup  = 'sb_g_' . substr(md5((string) $g['title']), 0, 8);
                $bukaAwal   = $grupAktif || in_array($kunciGrup, $grupBuka, true);
                $labelGrup  = array_map(static fn (array $it): string => mb_strtolower((string) $it[1]), $g['items']);
            ?>
                <div class="sb-grup" x-data="sbGrup" data-kunci="<?= $kunciGrup ?>" data-buka="<?= $bukaAwal ? '1' : '0' ?>"
                     data-label="<?= esc(json_encode($labelGrup), 'attr') ?>" x-show="cari === '' || grupCocok()">
                    <button type="button" class="sb-grup-judul"<?= $grupAktif ? ' data-aktif' : '' ?> @click="alih()" :aria-expanded="buka || cari !== ''">
                        <span><?= esc($g['title']) ?></span>
                        <span class="transition-transform<?= $bukaAwal ? ' rotate-180' : '' ?>" :class="{ 'rotate-180': buka || cari !== '' }"><?= ikon('chevron-down', 'h-3.5 w-3.5') ?></span>
                    </button>
                    <div class="sb-pemisah" aria-hidden="true"></div>
                    <div class="sb-grup-isi" x-show="buka || cari !== '' || sempit()" x-collapse.duration.200ms<?= $bukaAwal || $ciut ? '' : ' x-cloak' ?>>
                        <?php foreach ($g['items'] as $it) { $renderItem($it); } ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <p class="sb-kosong" x-show="cari !== '' && ! adaHasil()" x-cloak>Menu tidak ditemukan.</p>
    </nav>
</aside>
<div class="sb-latar" :data-terbuka="sidebar" @click="tutupLaci()" aria-hidden="true"></div>

<!-- ===================== ISI ===================== -->
<div class="kerangka-isi">
    <main id="isi" tabindex="-1" class="flex-1 px-4 py-6 focus:outline-hidden lg:px-6"<?= $lihatSaja ? ' data-lihat-saja="1"' : '' ?>>
        <?php $adaPesan = $lihatSaja || session('success') || session('error') || session('errors'); ?>
        <?php if ($adaPesan): ?>
            <div class="mb-5 space-y-3">
                <?php if ($lihatSaja): ?>
                    <?= ui_peringatan('info', 'Akun ' . $labelPeranKini . ' bisa membuka dan mengunduh dari halaman ini, tetapi tidak bisa mengubah datanya. Admin bisa mengubahnya di Sistem → Hak Akses.', ['judul' => 'Mode lihat saja']) ?>
                <?php endif; ?>
                <?php if (session('success')): ?><?= ui_peringatan('sukses', (string) session('success'), ['tutup' => true]) ?><?php endif; ?>
                <?php if (session('error')): ?><?= ui_peringatan('galat', (string) session('error'), ['tutup' => true]) ?><?php endif; ?>
                <?php if (session('errors')): ?><?= ui_peringatan('galat', array_map('strval', array_values((array) session('errors'))), ['tutup' => true]) ?><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($kepalaKhusus !== ''): ?>
            <?= $kepalaKhusus ?>
        <?php elseif ($punyaJudul): ?>
            <?php if ($remah !== []): ?><div class="mb-4"><?= ui_remah($remah) ?></div><?php endif; ?>
        <?php else: ?>
            <?= ui_kepala(['judul' => $judulHalaman, 'remah' => $remah, 'aksi' => $aksiHalaman]) ?>
        <?php endif; ?>

        <?= $isiHalaman ?>
    </main>

    <footer class="kaki-halaman">
        <p>&copy; <?= date('Y') ?> &middot; Sistem Informasi Akademik Sekolah (BINUS)</p>
        <?= view('partials/kredit', ['kreditKelas' => 'mt-1']) ?>
    </footer>
</div>

<?= ui_toast() ?>
<?= ui_konfirmasi() ?>

<?php // Urutan defer: kit (ui.js) → skrip halaman → app.js → plugin collapse → Alpine (komponen terdaftar sebelum Alpine berjalan).
      // Masa transisi rombak tampilan: grup yang sudah selesai (Config\Tampilan::$alpineCsp) memakai Alpine versi CSP (tanpa eval). ?>
<?= $js('assets/js/admin/ui.js') ?>
<?= $this->renderSection('scripts') ?>
<?= $js('assets/js/admin/app.js') ?>
<?= $js('assets/js/vendor/alpine-collapse.min.js') ?>
<?= $js($alpineJs) ?>
</body>
</html>
