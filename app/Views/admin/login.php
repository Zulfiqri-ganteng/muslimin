<?php
/**
 * Halaman masuk panel admin (rombak tampilan F2) — pola halaman "Sign in" Flowbite: latar abu muda, logo + nama sekolah, kartu putih
 * di tengah, isian akun & kata sandi (tombol lihat sandi + peringatan Caps Lock), tombol penuh warna utama, tanpa gradasi.
 * Logika masuk TIDAK diubah (Admin\Auth::attemptLogin: isian "login" & "password" + token CSRF; pesan lewat flash error/success).
 * SIAP CSP: Alpine versi CSP + ui.js (komponen isianSandi) + login.js (formMasuk); tanpa skrip/gaya sebaris.
 * Tema: <html data-warna data-sidebar> dari Pengaturan Sekolah → Tampilan (sama dengan layout admin, Libraries\Tampilan).
 *
 * @var array<string, mixed> $setting baris pengaturan sekolah (school_name, logo)
 */
$namaSekolah = trim((string) ($setting['school_name'] ?? ''));
$namaSekolah = $namaSekolah !== '' ? $namaSekolah : 'Panel Admin';
$logo        = (string) ($setting['logo'] ?? '');
// Berkas logo yang hilang (mis. data laptop belum lengkap) → kotak huruf awal, bukan gambar rusak.
$adaLogo     = $logo !== '' && is_file(FCPATH . 'uploads/' . $logo);
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
    <link rel="preload" href="<?= base_url('assets/fonts/inter/inter-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/admin.css') ?>" <?= csp_style_nonce() ?>>
</head>
<body class="bg-gray-50 text-gray-800 antialiased dark:bg-gray-900 dark:text-gray-200 sekolah:bg-brand-50/60">
<main class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <a href="<?= site_url('/') ?>" class="mb-6 flex max-w-md items-center gap-3 rounded-lg focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-300" title="Ke beranda situs sekolah">
        <?php if ($adaLogo): ?>
            <img src="<?= base_url('uploads/' . rawurlencode($logo)) ?>" alt="" class="h-11 w-11 shrink-0 rounded-lg object-contain">
        <?php else: ?>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-700 text-lg font-semibold text-white" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($namaSekolah, 0, 1))) ?></span>
        <?php endif; ?>
        <span class="min-w-0 leading-tight">
            <span class="block text-lg font-semibold break-words text-gray-900 sm:text-xl dark:text-white"><?= esc($namaSekolah) ?></span>
            <span class="block text-sm text-gray-500 dark:text-gray-400">Sistem Informasi Akademik Sekolah</span>
        </span>
    </a>

    <div class="kartu w-full max-w-md shadow-sm">
        <div class="space-y-6 p-6 sm:p-8">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl dark:text-white">Masuk ke panel admin</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gunakan username atau email akun sekolah Anda.</p>
            </div>

            <?php if (session('error')): ?><?= ui_peringatan('galat', (string) session('error'), ['hilang' => 10000]) ?><?php endif; ?>
            <?php if (session('success')): ?><?= ui_peringatan('sukses', (string) session('success'), ['hilang' => 6000]) ?><?php endif; ?>

            <form action="<?= site_url('admin/login') ?>" method="post" class="space-y-5" x-data="formMasuk" @submit="kirimForm()">
                <?= csrf_field() ?>
                <div>
                    <label for="login" class="lbl">Username atau email</label>
                    <input type="text" id="login" name="login" value="<?= old('login') ?>" class="inp" required
                           autocomplete="username" autocapitalize="none" spellcheck="false"<?= $fokusSandi ? '' : ' autofocus' ?>>
                </div>
                <div x-data="isianSandi">
                    <label for="password" class="lbl">Kata sandi</label>
                    <div class="isian-sandi">
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
                    <span x-text="kirim ? 'Memproses…' : 'Masuk'">Masuk</span>
                </button>
            </form>

            <p class="text-sm text-gray-500 dark:text-gray-400">Lupa kata sandi? Hubungi Admin sekolah untuk dibuatkan sandi sementara.</p>
        </div>
    </div>

    <footer class="mt-8 text-center text-xs text-gray-500 dark:text-gray-400">
        <p>&copy; <?= date('Y') ?> &middot; Sistem Informasi Akademik Sekolah</p>
        <?= view('partials/kredit', ['kreditKelas' => 'mt-1']) ?>
    </footer>
</main>

<?php // Urutan defer: komponen kit & halaman terdaftar sebelum Alpine versi CSP berjalan (penanda nonce = persiapan CSP). ?>
<script defer src="<?= base_url('assets/js/admin/ui.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/ui.js') ?>" <?= csp_script_nonce() ?>></script>
<script defer src="<?= base_url('assets/js/admin/login.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/login.js') ?>" <?= csp_script_nonce() ?>></script>
<script defer src="<?= base_url('assets/js/vendor/alpine-csp.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/alpine-csp.min.js') ?>" <?= csp_script_nonce() ?>></script>
</body>
</html>
