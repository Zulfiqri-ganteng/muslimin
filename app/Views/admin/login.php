<?php
/**
 * Halaman masuk panel admin — pola halaman "Sign in" Flowbite (rombak F2), diperhalus 2026-10-27 (permintaan user: lebih profesional +
 * animasi warna memutari kartu, tanpa kesan buatan AI): bilah atas (tautan situs sekolah · tombol ganti tema), satu kartu di tengah
 * dengan dua garis cahaya berseberangan yang berputar pelan (admin.css "HALAMAN MASUK"). Kartu dua kolom (≥ md): panel identitas
 * sekolah berwarna merek pekat (logo, nama, alamat, tahun ajaran) | formulir berisian tegas; di HP panel jadi pita di atas formulir. Tanpa kaki kartu (user: teks lupa sandi & arahan siswa tidak perlu). Latar: partikel melayang yang saling tersambung garis
 * tipis (kanvas .masuk-partikel + login-partikel.js, warna = dua warna cahaya kartu). Tanpa gradasi latar, glow, emoji, atau slogan.
 * Logika masuk TIDAK diubah (Admin\Auth::attemptLogin: isian "login" & "password" + token CSRF; pesan lewat flash error/success).
 * Formulir masuk tetap <form> PERTAMA di halaman (tools/uji-peramban.mjs isiLogin memakai document.querySelector('form')).
 * SIAP CSP: Alpine versi CSP + ui.js (komponen isianSandi) + login.js (formMasuk, temaMasuk) + login-partikel.js; tanpa skrip/gaya sebaris.
 * Tema: <html data-mode data-warna data-sidebar> dari Pengaturan Sekolah → Tampilan atau pilihan peramban (Libraries\Tampilan).
 *
 * @var array<string, mixed> $setting baris pengaturan sekolah (school_name, logo)
 */
$namaSekolah = trim((string) ($setting['school_name'] ?? ''));
$namaSekolah = $namaSekolah !== '' ? $namaSekolah : 'Panel Admin';
$logo        = (string) ($setting['logo'] ?? '');
// Berkas logo yang hilang (mis. data laptop belum lengkap) → kotak huruf awal, bukan gambar rusak.
$adaLogo     = $logo !== '' && is_file(FCPATH . 'uploads/' . $logo);
// Panel identitas (desktop): alamat & tahun ajaran dari Pengaturan Sekolah; kosong = barisnya tidak tampil.
$alamat      = trim((string) ($setting['address'] ?? ''));
$tahunAjaran = trim((string) ($setting['academic_year'] ?? ''));
// Gagal masuk (isian akun dikembalikan) → kursor langsung di kotak kata sandi.
$fokusSandi  = (string) old('login') !== '';
$tema        = \App\Libraries\Tampilan::tema($setting ?? null);
?>
<!DOCTYPE html>
<html lang="id" data-mode="<?= $tema['mode'] ?>" data-warna="<?= $tema['warna'] ?>" data-sidebar="<?= $tema['sidebar'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk &mdash; Panel Admin</title>
    <meta name="robots" content="noindex">
    <?= view('partials/favicon') ?>
    <link rel="preload" href="<?= base_url('assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/admin.css') ?>" <?= csp_style_nonce() ?>>
</head>
<body class="masuk-latar">
<canvas class="masuk-partikel" aria-hidden="true"></canvas>
<header class="masuk-atas">
    <a href="<?= site_url('/') ?>" class="btn btn-teks btn-sm -ms-3"><?= ikon('globe', 'h-4 w-4') ?>Situs sekolah</a>
    <?php // Ganti tema per peramban — sama dengan tombol di menu samping panel admin. ?>
    <button type="button" class="tombol-navbar rounded-lg" x-data="temaMasuk" @click="ganti()"
            title="Ganti tema: Terang → Gelap → Warna Sekolah" aria-label="Ganti tema tampilan">
        <span class="ikon-tema ikon-tema-terang"><?= ikon('sun', 'h-5 w-5') ?></span>
        <span class="ikon-tema ikon-tema-gelap"><?= ikon('moon', 'h-5 w-5') ?></span>
        <span class="ikon-tema ikon-tema-sekolah"><?= ikon('palette', 'h-5 w-5') ?></span>
    </button>
</header>

<main class="flex flex-1 flex-col items-center justify-center px-4 py-6">
    <div class="masuk-kartu" x-data="formMasuk" :data-kirim="kirim"<?= session('error') ? ' data-galat' : '' ?>>
        <div class="masuk-panel">
            <span class="masuk-panel-logo">
                <?php if ($adaLogo): ?>
                    <img src="<?= base_url('uploads/' . rawurlencode($logo)) ?>" alt="" class="h-full w-full object-contain">
                <?php else: ?>
                    <span class="text-xl font-semibold text-brand-700" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($namaSekolah, 0, 1))) ?></span>
                <?php endif; ?>
            </span>
            <div class="masuk-panel-nama">
                <p class="text-lg leading-tight font-semibold break-words md:text-2xl"><?= esc($namaSekolah) ?></p>
                <p class="mt-1 text-sm text-white/75">Sistem Informasi Akademik Sekolah</p>
                <?php if ($alamat !== '' || $tahunAjaran !== ''): ?>
                    <ul class="masuk-panel-info">
                        <?php if ($alamat !== ''): ?><li><?= ikon('map-pin') ?><span class="line-clamp-2"><?= esc($alamat) ?></span></li><?php endif; ?>
                        <?php if ($tahunAjaran !== ''): ?><li><?= ikon('calendar-month') ?><span>Tahun ajaran <?= esc($tahunAjaran) ?></span></li><?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="masuk-isi">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Masuk ke panel admin</h1>
                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Khusus guru dan staf sekolah.</p>
            </div>

            <?php if (session('error')): ?><?= ui_peringatan('galat', (string) session('error'), ['hilang' => 10000]) ?><?php endif; ?>
            <?php if (session('success')): ?><?= ui_peringatan('sukses', (string) session('success'), ['hilang' => 6000]) ?><?php endif; ?>

            <form action="<?= site_url('admin/login') ?>" method="post" class="space-y-5" @submit="kirimForm()" @input="hapusGalat()">
                <?= csrf_field() ?>
                <div>
                    <label for="login" class="lbl">Username atau email</label>
                    <div class="isian-ikon">
                        <?= ikon('user') ?>
                        <input type="text" id="login" name="login" value="<?= old('login') ?>" class="inp" required
                               autocomplete="username" autocapitalize="none" spellcheck="false"<?= $fokusSandi ? '' : ' autofocus' ?>>
                    </div>
                </div>
                <div x-data="isianSandi">
                    <label for="password" class="lbl">Kata sandi</label>
                    <div class="isian-sandi isian-ikon">
                        <?= ikon('lock') ?>
                        <input type="password" :type="lihat ? 'text' : 'password'" id="password" name="password" class="inp" required
                               autocomplete="current-password"<?= $fokusSandi ? ' autofocus' : '' ?>
                               @keydown="cekCapsLock($event)" @keyup="cekCapsLock($event)" @blur="capsLock = false">
                        <button type="button" class="tombol-sandi" x-cloak @mousedown.prevent @click="alih()"
                                aria-controls="password" aria-label="Tampilkan kata sandi" :aria-pressed="lihat"
                                :title="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                            <span x-show="!lihat"><?= ikon('eye', 'h-5 w-5') ?></span>
                            <span x-show="lihat"><?= ikon('eye-slash', 'h-5 w-5') ?></span>
                        </button>
                    </div>
                    <p class="hint flex items-center gap-1.5 font-medium text-yellow-700" x-show="capsLock" x-cloak role="status">
                        <?= ikon('exclamation-circle', 'h-4 w-4 shrink-0') ?>Caps Lock sedang menyala.
                    </p>
                </div>
                <button type="submit" class="btn btn-utama btn-lg w-full" :disabled="kirim">
                    <span class="pemutar" x-show="kirim" x-cloak aria-hidden="true"></span>
                    <span x-show="!kirim" aria-hidden="true"><?= ikon('arrow-right-to-bracket', 'h-5 w-5') ?></span>
                    <span x-text="kirim ? 'Memproses…' : 'Masuk'">Masuk</span>
                </button>
            </form>
        </div>
    </div>
</main>

<footer class="px-4 pb-6 text-center text-xs text-gray-500 dark:text-gray-400">
    <p>&copy; <?= date('Y') ?> <?= esc($namaSekolah) ?> &middot; Sistem Informasi Akademik Sekolah</p>
    <?= view('partials/kredit', ['kreditKelas' => 'mt-1']) ?>
</footer>

<?php // Urutan defer: komponen kit & halaman terdaftar sebelum Alpine versi CSP berjalan (penanda nonce = persiapan CSP). ?>
<script defer src="<?= base_url('assets/js/admin/ui.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ui.js') ?>" <?= csp_script_nonce() ?>></script>
<script defer src="<?= base_url('assets/js/admin/login.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/login.js') ?>" <?= csp_script_nonce() ?>></script>
<script defer src="<?= base_url('assets/js/admin/login-partikel.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/login-partikel.js') ?>" <?= csp_script_nonce() ?>></script>
<script defer src="<?= base_url('assets/js/vendor/alpine-csp.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine-csp.min.js') ?>" <?= csp_script_nonce() ?>></script>
</body>
</html>
