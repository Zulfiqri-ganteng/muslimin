<?php
/**
 * Kerangka panel admin (rombak tampilan F3 + revisi R1 — docs/RENCANA-UPGRADE-TAMPILAN.md §3–§5a), MENGIKUTI TEMPLATE Flowbite Admin
 * Dashboard (resources/flowbite: layouts/_default/dashboard.html, partials/navbar-dashboard.html, partials/sidebar.html,
 * partials/footer-dashboard.html):
 *   - bilah atas putih bergaris bawah: kiri tombol menu + nama sekolah (teks besar); kanan kotak cari (desktop), lonceng
 *     pemberitahuan (pekerjaan menunggu, Libraries\Pemberitahuan), logo sekolah = tombol menu akun (2026-10-18);
 *   - menu samping putih bergaris kanan: baris menu 16px ber-ikon solid, GRUP = satu baris ber-ikon + panah ▾ dengan isi menjorok
 *     tanpa ikon, menu aktif berlatar abu, daftar kedua (tautan luar) dipisah garis, deret ikon kecil di bawah (desktop);
 *   - isi berlatar abu muda, kaki = kartu putih bertautan + baris hak cipta.
 * Semua menu & fitur tetap: katalog Libraries\MenuAdmin (disaring hak tiap peran), cari menu (atas & di laci HP, satu nilai `cari`),
 * grup lipat (cookie sb_grup), ciutkan menu jadi deret ikon (desktop, cookie sb_ciut), laci di HP, kepala halaman + remah otomatis.
 * Warna lewat token tema (<html data-warna data-sidebar>, ditulis server — tanpa kedipan).
 * SIAP CSP: tanpa skrip/gaya sebaris; atribut Alpine hanya isian sederhana (adminLayout / sbMenu / sbGrup di app.js, kit di ui.js),
 * dan seluruh panel admin memakai Alpine versi CSP (@alpinejs/csp, tanpa eval) sejak F12.
 *
 * Dipakai halaman: $title (judul); section 'content'; opsional section 'aksi' (tombol di kanan judul), 'kepala' (mengganti kepala
 * halaman bawaan, mis. ui_kepala() dengan tab), 'scripts' (berkas JS halaman). Halaman yang isinya sudah punya <h1> sendiri
 * (belum dirombak) hanya mendapat remah roti, supaya judul tidak ganda.
 * MODE CRUD (F5, pola template content/crud/users.html): section 'tata' berisi "crud" → isi tanpa bantalan, kepala = bilah putih
 * selebar layar (remah, judul, panduan dari section 'bantuan', pesan) yang menyambung dengan bilah alat halaman (ui_crud_alat),
 * lalu tabel selebar layar (.tabel-crud) dan bilah halaman yang menempel di bawah (ui_crud_kaki).
 */
$cur   = uri_string();
$admin = session('admin') ?? [];

// (F5) Pindah menu terasa instan: Chrome memuat halaman tujuan lebih dulu saat kursor berhenti di menu (± 200 md, Speculation Rules
// lewat header — siap CSP, tanpa skrip sebaris). Aturan: public/assets/aturan-prefetch.json — hanya tautan menu samping, remah, kartu
// dashboard; tanpa unduhan, tautan POST (data-method), tab baru. Peramban lain mengabaikannya.
service('response')->setHeader('Speculation-Rules', '"' . base_url('assets/aturan-prefetch.json') . '"');

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
$bolehPengaturan = \App\Libraries\HakAkses::boleh($peranKini, 'admin/settings');
$adminUtama      = $peranKini === \App\Libraries\HakPeran::ADMIN;

$setting    = (new \App\Models\SettingModel())->get();
$schoolName = trim((string) ($setting['school_name'] ?? '')) ?: 'Panel Admin';
$logo       = (string) ($setting['logo'] ?? '');
$schoolLogo = $logo !== '' && is_file(FCPATH . 'uploads/' . $logo) ? base_url('uploads/' . rawurlencode($logo)) : null;
$tema       = \App\Libraries\Tampilan::tema($setting);
$ciut       = \App\Libraries\Tampilan::sidebarCiut();
$grupBuka   = \App\Libraries\Tampilan::grupTerbuka();
$namaAkun   = (string) ($admin['full_name'] ?? 'Admin');
// Ikon lonceng: pekerjaan yang masih menunggu, disaring hak peran ini (dirender server, lalu diperbarui berkala oleh app.js).
$lonceng    = \App\Libraries\Pemberitahuan::untuk($peranKini);
// Pengingat LAYAR PENUH pekerjaan PKL yang lewat batas keputusan (Libraries\PklPengingat, 2026-10-21): pemegang hak ACC ajuan
// (Waka Hubin) = ajuan belum di-ACC; pemroses kotak laporan (Operator) = laporan siswa belum diproses. Bukan untuk Admin.
$pengingat  = \App\Libraries\PklPengingat::untukLayout($peranKini, $cur);

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
$namaGrup     = static fn (string $t): string => \App\Libraries\MenuAdmin::namaGrup($t);
$remah        = [];
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        if (! ($it[4] ?? false) && $itemAktif($it)) {
            // Nama grup sama dengan nama menunya (mis. Guru › Guru) → cukup sekali.
            if ($g['title'] !== null && mb_strtolower($namaGrup((string) $g['title'])) !== mb_strtolower((string) $it[1])) {
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
$modeCrud     = trim($this->renderSection('tata')) === 'crud';
$bantuan      = trim($this->renderSection('bantuan'));
$punyaJudul   = str_contains($isiHalaman, '<h1');

// Ikon menu samping: versi SOLID seperti template (cadangan versi garis bila ikon itu tak punya versi solid).
$ikonSb = static fn (string $nama, string $kelas): string => is_file(APPPATH . 'ThirdParty/flowbite-icons/solid/' . $nama . '.svg')
    ? ikon($nama, $kelas, 'solid') : ikon($nama, $kelas);
// Pencarian menu: baris tampil bila teks cari kosong atau cocok dengan labelnya (sbMenu.sesuai di app.js).
$atrCari = static fn (string $label): string => ' x-show="sesuai(' . esc(json_encode(mb_strtolower($label)), 'attr') . ')"';
// Satu baris menu. $anak = isi grup (menjorok, tanpa ikon — pola template).
$renderItem = static function (array $it, bool $anak = false) use ($itemAktif, $atrCari, $ikonSb): void {
    [$url, $label, $ikon] = $it;
    $kelas  = $anak ? 'sb-item sb-anak' : 'sb-item';
    $ikonEl = $anak ? '' : $ikonSb((string) $ikon, 'sb-ikon');
    if ($it[4] ?? false) {
        // Menu rancangan (fitur belum jadi): redup, tak bisa diklik, bertanda "Segera".
        echo '<li' . $atrCari($label) . '><div class="' . $kelas . ' sb-item-segera" title="' . esc($label, 'attr') . ' — segera hadir" aria-disabled="true">'
            . $ikonEl . '<span class="sb-label">' . esc($label) . '</span><span class="sb-segera">Segera</span></div></li>';

        return;
    }
    echo '<li' . $atrCari($label) . '><a href="' . site_url($url) . '" class="' . $kelas . '" title="' . esc($label, 'attr') . '"'
        . ($itemAktif($it) ? ' aria-current="page"' : '') . '>' . $ikonEl . '<span class="sb-label">' . esc($label) . '</span></a></li>';
};
// Sub-menu lipat WEB (MenuAdmin::tampilWeb, mis. "Buat surat" berisi menu tiap jenis surat): <details> tanpa skrip — terbuka bila memuat
// halaman sekarang atau saat mencari menu; tampil saat mencari bila salah satu anggotanya cocok.
$renderSub = static function (array $s) use ($renderItem, $itemAktif): void {
    $aktif = false;
    foreach ($s['items'] as $it) {
        $aktif = $aktif || (! ($it[4] ?? false) && $itemAktif($it));
    }
    $cocok = implode(' || ', array_map(static fn (array $it): string => 'sesuai(' . json_encode(mb_strtolower((string) $it[1])) . ')', $s['items']));
    echo '<li x-show="' . esc($cocok, 'attr') . '"><details class="sb-sub"' . ($aktif ? ' open' : '') . ' :open="cari !== \'\' || ' . ($aktif ? 'true' : 'false') . '">'
        . '<summary class="sb-item sb-anak sb-sub-tombol" title="' . esc((string) $s['sub'], 'attr') . '"><span class="sb-label">' . esc((string) $s['sub']) . '</span>'
        . '<span class="sb-panah">' . ikon('chevron-down', 'h-4 w-4') . '</span></summary><ul class="sb-sub-daftar">';
    foreach ($s['items'] as $it) {
        $renderItem($it, true);
    }
    echo '</ul></details></li>';
};
// Susunan tampilan web (item tersembunyi + sub-menu lipat). $groups (datar) tetap dipakai untuk menu aktif, remah roti, dan pencarian.
$groupsWeb = \App\Libraries\MenuAdmin::tampilWeb($groups);
$datarkan  = static function (array $items): array {
    $out = [];
    foreach ($items as $it) {
        array_push($out, ...(isset($it['sub']) ? $it['items'] : [$it]));
    }

    return $out;
};
// Tautan luar di daftar kedua (pola template: dipisah garis, di bawah menu utama).
$tautanLuar = [[site_url('/'), 'Situs sekolah', 'globe']];
if ($bolehFormPublik) {
    $tautanLuar[] = [site_url('isi'), 'Form kesediaan guru', 'clipboard-list'];
}
$semuaLabel = [];
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        $semuaLabel[] = mb_strtolower((string) $it[1]);
    }
}
foreach ($tautanLuar as $t) {
    $semuaLabel[] = mb_strtolower($t[1]);
}
$js = static fn (string $berkas): string => '<script defer src="' . base_url($berkas) . '?v=' . @filemtime(FCPATH . $berkas) . '" ' . csp_script_nonce() . '></script>';
?>
<!DOCTYPE html>
<html lang="id" data-mode="<?= $tema['mode'] ?>" data-warna="<?= $tema['warna'] ?>" data-sidebar="<?= $tema['sidebar'] ?>"<?= $ciut ? ' data-ciut' : '' ?>>
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
    <link rel="preload" href="<?= base_url('assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/admin.css') ?>" <?= csp_style_nonce() ?>>
</head>
<body class="bg-gray-50 text-gray-800 antialiased dark:bg-gray-900 dark:text-gray-200 sekolah:bg-brand-50/60" x-data="adminLayout" @keydown.escape.window="tutupLaci()">
<a href="#isi" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[80] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-brand-700 focus:shadow-sm">Lewati ke isi halaman</a>

<!-- Layar "Memproses…" saat formulir dikirim (semua formulir, kecuali data-noload). -->
<div x-show="loading" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-[70] flex items-center justify-center bg-white/70 dark:bg-gray-900/70" role="status" aria-live="polite">
    <div class="flex flex-col items-center gap-3">
        <span class="pemutar h-10 w-10 border-4 text-brand-600" aria-hidden="true"></span>
        <p class="text-sm font-medium text-brand-700 dark:text-brand-300" x-text="pesan">Memproses…</p>
    </div>
</div>

<!-- ===================== BILAH ATAS (template: navbar-dashboard) ===================== -->
<nav class="navbar" aria-label="Bilah atas">
    <div class="navbar-dalam">
        <div class="flex min-w-0 items-center">
            <button type="button" class="tombol-navbar lg:hidden" @click="alihLaci()" aria-controls="menu-samping" :aria-expanded="sidebar" aria-label="Buka atau tutup menu">
                <span x-show="! sidebar"><?= ikon('bars', 'h-6 w-6') ?></span>
                <span x-show="sidebar" x-cloak><?= ikon('close', 'h-6 w-6') ?></span>
            </button>
            <button type="button" class="tombol-navbar hidden lg:inline-flex" @click="alihCiut()" aria-controls="menu-samping" :aria-pressed="collapsed"
                    aria-label="Ciutkan menu samping" :title="collapsed ? 'Lebarkan menu samping' : 'Ciutkan menu samping'"><?= ikon('bars', 'h-6 w-6') ?></button>
            <?php // (2026-10-18) Kiri hanya nama sekolah; logo pindah ke kanan sebagai tombol menu akun. ?>
            <a href="<?= site_url($beranda) ?>" class="navbar-merek" title="<?= esc($schoolName, 'attr') ?>">
                <span class="navbar-nama"><?= esc($schoolName) ?></span>
            </a>
        </div>

        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            <div class="hidden lg:block" role="search">
                <label for="cari-menu-atas" class="sr-only">Cari menu</label>
                <div class="relative lg:w-72 xl:w-96">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><?= ikon('search', 'h-5 w-5 text-gray-500', 'solid') ?></div>
                    <input type="search" id="cari-menu-atas" class="navbar-cari" placeholder="Cari menu…" autocomplete="off" x-model="cari" @keydown.enter.prevent="bukaHasilPertama()">
                </div>
            </div>
            <?php // (2026-10-26) Tombol kaca pembesar khusus HP dibuang atas permintaan user: di HP kotak cari menu ada di dalam laci menu samping (tombol menu). ?>

            <!-- Lonceng: pekerjaan yang masih menunggu (Libraries\Pemberitahuan), diperbarui berkala (komponen lonceng di app.js) -->
            <div class="relative" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">
                <div x-data="lonceng" data-url="<?= site_url('admin/pemberitahuan') ?>" data-awal="<?= esc((string) json_encode($lonceng), 'attr') ?>">
                    <button type="button" x-ref="pemicu" class="tombol-navbar relative" @click="alihLonceng()" :aria-expanded="buka" aria-haspopup="true"
                            :aria-label="labelLonceng()" aria-label="Pemberitahuan" title="Pemberitahuan">
                        <?= ikon('bell', 'h-6 w-6') ?>
                        <span class="lonceng-angka" x-show="total > 0" x-text="teksTotal()"<?= $lonceng['total'] > 0 ? '' : ' x-cloak' ?>><?= $lonceng['total'] > 99 ? '99+' : (int) $lonceng['total'] ?></span>
                    </button>
                    <div class="dropdown-menu lonceng-menu" x-show="buka" x-cloak x-transition.origin.top.right.duration.150ms>
                        <div class="dropdown-kepala flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold">Pemberitahuan</p>
                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="total > 0 ? total + ' menunggu' : ''"></span>
                        </div>
                        <ul class="lonceng-daftar" x-show="total > 0">
                            <template x-for="b in butir" :key="b.kunci">
                                <li><a :href="b.url" class="dropdown-item lonceng-butir"><span class="lonceng-n" x-text="b.n > 99 ? '99+' : b.n"></span><span class="min-w-0 flex-1" x-text="b.teks"></span></a></li>
                            </template>
                        </ul>
                        <div class="lonceng-kosong" x-show="total === 0">
                            <?= ikon('check-circle', 'h-8 w-8 text-gray-400') ?>
                            <p>Tidak ada pekerjaan yang menunggu.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu akun: logo sekolah (dulu huruf awal nama akun) + dropdown -->
            <div class="relative" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">
                <button type="button" x-ref="pemicu" @click="alih()" :aria-expanded="buka" aria-haspopup="true" aria-label="Menu akun <?= esc($namaAkun, 'attr') ?>"
                        class="navbar-akun" title="<?= esc($namaAkun . ' · ' . $labelPeranKini, 'attr') ?>">
                    <?php if ($schoolLogo !== null): ?>
                        <img src="<?= esc($schoolLogo, 'attr') ?>" alt="" class="h-full w-full object-contain">
                    <?php else: ?>
                        <span class="flex h-full w-full items-center justify-center bg-brand-700 text-sm font-semibold text-white" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($schoolName, 0, 1))) ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu right-0 w-64" x-show="buka" x-cloak x-transition.origin.top.right.duration.150ms>
                    <div class="dropdown-kepala">
                        <p class="truncate text-sm font-semibold text-gray-900"><?= esc($namaAkun) ?></p>
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400"><?= esc($labelPeranKini) ?> &middot; @<?= esc((string) ($admin['username'] ?? '')) ?></p>
                    </div>
                    <ul class="py-1">
                        <li><a href="<?= site_url('admin/profile') ?>" class="dropdown-item"><?= ikon('user', 'h-4 w-4') ?>Profil saya</a></li>
                        <?php if ($bolehPengaturan): ?>
                            <li><a href="<?= site_url('admin/settings') ?>" class="dropdown-item"><?= ikon('cog', 'h-4 w-4') ?>Pengaturan sekolah</a></li>
                        <?php endif; ?>
                        <?php if ($adminUtama): ?>
                            <li><a href="<?= site_url('admin/settings') ?>#tampilan" class="dropdown-item"><?= ikon('adjustments-horizontal', 'h-4 w-4') ?>Tema bawaan sekolah</a></li>
                        <?php endif; ?>
                    </ul>
                    <ul class="py-1">
                        <li><a href="<?= site_url('admin/logout') ?>" class="dropdown-item dropdown-item-bahaya"><?= ikon('arrow-right-to-bracket', 'h-4 w-4') ?>Keluar</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ===================== MENU SAMPING (template: sidebar) ===================== -->
<aside id="menu-samping" class="sb" :data-terbuka="sidebar" aria-label="Menu samping">
    <div class="sb-panel">
        <nav class="sb-gulir" aria-label="Menu utama" x-data="sbMenu" data-label="<?= esc(json_encode($semuaLabel), 'attr') ?>">
            <div class="sb-isi">
                <ul class="sb-daftar">
                    <li class="lg:hidden">
                        <label class="sb-cari-wadah" for="cari-menu-samping">
                            <span class="sr-only">Cari menu</span>
                            <?= ikon('search', 'sb-cari-ikon', 'solid') ?>
                            <input type="search" id="cari-menu-samping" class="sb-cari" placeholder="Cari menu…" autocomplete="off" x-model="cari" @keydown.enter.prevent="bukaHasilPertama()">
                        </label>
                    </li>
                    <?php $bagianKini = null; foreach ($groupsWeb as $g): ?>
                        <?php
                        // Judul bagian (KURIKULUM, LAYANAN SEKOLAH) sebelum grup pertama bagiannya; bagian '' = garis pemisah saja.
                        // Disembunyikan saat mencari; saat menu menciut tampil sebagai garis.
                        $bagian = $g['bagian'] ?? null;
                        if ($bagian !== null && $bagian !== $bagianKini):
                            $bagianKini = $bagian;
                        ?>
                            <?php if ($bagian === ''): ?>
                                <li class="sb-bagian sb-bagian-garis" role="separator" x-show="cari === ''"></li>
                            <?php else: ?>
                                <li class="sb-bagian" x-show="cari === ''"><span class="sb-bagian-teks"><?= esc($bagian) ?></span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($g['title'] === null || (count($g['items']) === 1 && ! isset($g['items'][0]['sub']))): ?>
                            <?php foreach ($g['items'] as $it) { $renderItem($it); } ?>
                        <?php else:
                            // Grup lipat: grup berisi halaman sekarang selalu terbuka; grup lain mengikuti pilihan pengguna (cookie sb_grup, dibaca
                            // server → tidak berkedip). Menu menciut (desktop): hanya ikon grup; klik ikon = lebarkan menu + buka grupnya.
                            $grupAktif = false;
                            foreach ($datarkan($g['items']) as $it) {
                                $grupAktif = $grupAktif || (! ($it[4] ?? false) && $itemAktif($it));
                            }
                            $kunciGrup = 'sb_g_' . substr(md5((string) $g['title']), 0, 8);
                            $bukaAwal  = $grupAktif || in_array($kunciGrup, $grupBuka, true);
                            $labelGrup = array_map(static fn (array $it): string => mb_strtolower((string) $it[1]), $datarkan($g['items']));
                            $judulGrup = $namaGrup((string) $g['title']);
                        ?>
                            <li class="sb-grup" x-data="sbGrup" data-kunci="<?= $kunciGrup ?>" data-buka="<?= $bukaAwal ? '1' : '0' ?>"
                                data-label="<?= esc(json_encode($labelGrup), 'attr') ?>" x-show="cari === '' || grupCocok()">
                                <button type="button" class="sb-item sb-grup-tombol"<?= $grupAktif ? ' data-aktif' : '' ?> @click="alih()"
                                        :aria-expanded="buka || cari !== ''" aria-controls="<?= $kunciGrup ?>" title="<?= esc($judulGrup, 'attr') ?>">
                                    <?= $ikonSb((string) ($g['ikon'] ?? 'grid'), 'sb-ikon') ?>
                                    <span class="sb-label"><?= esc($judulGrup) ?></span>
                                    <span class="sb-panah<?= $bukaAwal ? ' rotate-180' : '' ?>" :class="{ 'rotate-180': buka || cari !== '' }"><?= ikon('chevron-down', 'h-5 w-5') ?></span>
                                </button>
                                <ul id="<?= $kunciGrup ?>" class="sb-anak-daftar" x-show="(buka || cari !== '') && ! sempit()" x-collapse.duration.200ms<?= $bukaAwal && ! $ciut ? '' : ' x-cloak' ?>>
                                    <?php foreach ($g['items'] as $it) { isset($it['sub']) ? $renderSub($it) : $renderItem($it, true); } ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li class="sb-kosong" x-show="cari !== '' && ! adaHasil()" x-cloak>Menu tidak ditemukan.</li>
                </ul>
                <ul class="sb-daftar sb-daftar-kedua">
                    <?php foreach ($tautanLuar as [$url, $label, $ikon]): ?>
                        <li<?= $atrCari($label) ?>><a href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener" class="sb-item" title="<?= esc($label, 'attr') ?> (tab baru)"><?= $ikonSb($ikon, 'sb-ikon') ?><span class="sb-label"><?= esc($label) ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
        <!-- Deret ikon bawah (template: sidebar-bottom-menu; desktop & laci HP) — kaki tetap bergaris atas; saat menciut tersusun ke bawah,
             sejajar dengan ikon menu, dan namanya melayang saat disorot (app.js pasangMenuMelayang). -->
        <div class="sb-bawah">
            <a href="<?= site_url('admin/profile') ?>" class="sb-bawah-tombol" title="Profil saya" aria-label="Profil saya"<?= $cur === 'admin/profile' ? ' aria-current="page"' : '' ?>><?= $ikonSb('user-circle', 'h-6 w-6') ?></a>
            <?php if ($bolehPengaturan): ?>
                <a href="<?= site_url('admin/settings') ?>" class="sb-bawah-tombol" title="Pengaturan sekolah" aria-label="Pengaturan sekolah"<?= $cur === 'admin/settings' ? ' aria-current="page"' : '' ?>><?= $ikonSb('cog', 'h-6 w-6') ?></a>
            <?php endif; ?>
            <?php // Ganti tema langsung (semua akun; pilihan per peramban — bawaan sekolah tetap di Pengaturan → Tampilan). ?>
            <button type="button" class="sb-bawah-tombol" @click="gantiTema()" title="Ganti tema: Terang → Gelap → Warna Sekolah" aria-label="Ganti tema tampilan">
                <span class="ikon-tema ikon-tema-terang"><?= ikon('sun', 'h-6 w-6') ?></span>
                <span class="ikon-tema ikon-tema-gelap"><?= ikon('moon', 'h-6 w-6') ?></span>
                <span class="ikon-tema ikon-tema-sekolah"><?= ikon('palette', 'h-6 w-6') ?></span>
            </button>
        </div>
    </div>
</aside>
<?php // SINKRON (tanpa defer): posisi gulir menu samping dikembalikan sebelum halaman digambar → pindah menu tanpa loncatan. ?>
<script src="<?= base_url('assets/js/admin/kerangka-awal.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/kerangka-awal.js') ?>" <?= csp_script_nonce() ?>></script>
<div class="sb-latar" :data-terbuka="sidebar" @click="tutupLaci()" aria-hidden="true"></div>

<!-- ===================== ISI (template: main-content) ===================== -->
<div class="kerangka-isi">
    <?php if (\App\Libraries\ModeMaintenance::aktif() && $cur !== 'admin/sistem/maintenance'): ?>
        <?php // Mode Maintenance aktif: hanya Admin yang sampai di sini — pengingat di semua halaman supaya tidak lupa dimatikan. ?>
        <div class="px-4 pt-4"><?= ui_peringatan('galat', '', ['judul' => 'Mode Maintenance AKTIF',
            'html' => 'Situs publik, layanan siswa, dan aplikasi Android sedang ditutup untuk selain Admin. <a href="' . site_url('admin/sistem/maintenance') . '" class="font-semibold underline">Kelola / matikan</a>']) ?></div>
    <?php endif; ?>
    <main id="isi" tabindex="-1" class="flex-1 <?= $modeCrud ? '' : 'px-4 pt-6 ' ?>focus:outline-hidden"<?= $lihatSaja ? ' data-lihat-saja="1"' : '' ?>>
        <?php // Pesan sukses = toast yang hilang sendiri (di bawah); kotak tetap hanya untuk galat & mode lihat saja. ?>
        <?php $adaPesan = $lihatSaja; ?>
        <?php if ($modeCrud): ?>
            <?php // Kepala CRUD: bilah putih selebar layar, menyambung dengan bilah alat (ui_crud_alat) di awal isi halaman. ?>
            <div class="kepala-crud">
                <?php if ($remah !== []): ?><div class="mb-5"><?= ui_remah($remah) ?></div><?php endif; ?>
                <h1 class="judul-halaman"><?= esc($judulHalaman) ?></h1>
                <?php if ($bantuan !== ''): ?><div class="mt-3"><?= $bantuan ?></div><?php endif; ?>
                <?php $adaPesan = false; ?>
                <?php if ($lihatSaja): ?>
                    <div class="mt-4 space-y-3">
                        <?php if ($lihatSaja): ?>
                            <?= ui_peringatan('info', 'Akun ' . $labelPeranKini . ' bisa membuka dan mengunduh dari halaman ini, tetapi tidak bisa mengubah datanya. Admin bisa mengubahnya di Sistem → Hak Akses.', ['judul' => 'Mode lihat saja']) ?>
                        <?php endif; ?>
                        
                        
                        
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($adaPesan): ?>
            <div class="mb-5 space-y-3">
                <?php if ($lihatSaja): ?>
                    <?= ui_peringatan('info', 'Akun ' . $labelPeranKini . ' bisa membuka dan mengunduh dari halaman ini, tetapi tidak bisa mengubah datanya. Admin bisa mengubahnya di Sistem → Hak Akses.', ['judul' => 'Mode lihat saja']) ?>
                <?php endif; ?>
                
                
                
            </div>
        <?php endif; ?>

        <?php if ($modeCrud): ?>
            <?php // kepala sudah dirender di atas ?>
        <?php elseif ($kepalaKhusus !== ''): ?>
            <?= $kepalaKhusus ?>
        <?php elseif ($punyaJudul): ?>
            <?php if ($remah !== []): ?><div class="mb-4"><?= ui_remah($remah) ?></div><?php endif; ?>
        <?php else: ?>
            <?= ui_kepala(['judul' => $judulHalaman, 'remah' => $remah, 'aksi' => $aksiHalaman]) ?>
        <?php endif; ?>

        <?= $isiHalaman ?>
    </main>

    <!-- Kaki (template: footer-dashboard) -->
    <footer class="kaki">
        <div class="kaki-kartu">
            <?php // Deret tautan Situs sekolah / Profil saya / Pengaturan sekolah dibuang (permintaan user 2026-10-26) — semuanya sudah ada di menu samping & menu profil. ?>
            <?= view('partials/kredit', ['kreditKelas' => 'text-sm text-gray-500 dark:text-gray-400']) ?>
        </div>
        <p class="kaki-hak">&copy; <?= date('Y') ?> &middot; Sistem Informasi Akademik Sekolah</p>
    </footer>
</div>

<?php
// SEMUA pesan sesudah aksi (sukses, galat, daftar galat isian) = toast (pola Flowbite) yang HILANG SENDIRI — aturan wajib user
// 2026-10-15. Lama tampil ikut panjang pesan: sukses 5–15 detik, galat 8–20 detik (beberapa galat = satu toast, satu baris tiap galat).
$toastAwal   = [];
$suksesFlash = trim((string) session('success'));
if ($suksesFlash !== '') {
    $toastAwal[] = ['pesan' => $suksesFlash, 'jenis' => 'sukses', 'lama' => max(5000, min(15000, mb_strlen($suksesFlash) * 70))];
}
$galatFlash = trim(implode("\n", array_filter(array_merge([(string) session('error')], array_map('strval', array_values((array) session('errors')))))));
if ($galatFlash !== '') {
    $toastAwal[] = ['pesan' => $galatFlash, 'jenis' => 'galat', 'lama' => max(8000, min(20000, mb_strlen($galatFlash) * 80))];
}
?>
<?= ui_toast($toastAwal) ?>
<?= ui_konfirmasi() ?>
<?php if ($pengingat !== null): ?>
<?= view('admin/pkl/_pengingat', ['pengingat' => $pengingat, 'kembali' => $cur], ['saveData' => false]) ?>
<?php endif; ?>

<?php // Urutan defer: kit (ui.js) → skrip halaman → app.js → plugin collapse → Alpine (komponen terdaftar sebelum Alpine berjalan).
      // Alpine versi CSP (tanpa eval) di seluruh panel admin; Alpine biasa (alpine.min.js) kini hanya dipakai halaman publik. ?>
<?= $js('assets/js/admin/ui.js') ?>
<?= $this->renderSection('scripts') ?>
<?= $js('assets/js/admin/app.js') ?>
<?= $js('assets/js/vendor/alpine-collapse.min.js') ?>
<?= $js('assets/js/vendor/alpine-csp.min.js') ?>
</body>
</html>
