<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<?php
    // ====== Identitas sistem (ubah di sini bila perlu) ======
    $systemName = 'SIKAGU';
    $systemTag  = 'Sistem Informasi Kesediaan Guru Mengajar';
    // ========================================================
    $tahun  = esc($setting['academic_year'] ?? '2026/2027');
    $isOpen = ! empty($setting['form_open']);
    $sekolah = esc($setting['school_name'] ?? 'Sekolah');
    $intro  = $setting['form_intro'] ?? 'Platform resmi pendataan kesediaan guru dalam melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan sekolah.';
?>

<!-- ===================== HERO ===================== -->
<section class="relative overflow-hidden bg-gradient-to-br from-brand-800 via-brand-800 to-brand-900">
    <!-- pola titik halus -->
    <div class="absolute inset-0 opacity-[0.5]" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.10) 1px, transparent 0); background-size: 22px 22px;"></div>
    <!-- glow -->
    <div class="pointer-events-none absolute -top-32 right-0 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-40 -left-20 h-96 w-96 rounded-full bg-gold-400/10 blur-3xl"></div>

    <!-- Navbar -->
    <nav class="relative max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <?php if (! empty($setting['logo'])): ?>
                <img src="<?= base_url('uploads/' . esc($setting['logo'])) ?>" alt="Logo" class="h-9 w-9 rounded-lg bg-white/10 object-contain p-0.5">
            <?php else: ?>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gold-400 text-brand-900 font-extrabold">S</span>
            <?php endif; ?>
            <span class="text-white font-bold tracking-tight"><?= esc($systemName) ?></span>
        </div>
        <a href="<?= site_url('admin/login') ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 px-3.5 py-1.5 text-sm font-semibold text-white/90 hover:bg-white/10 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Login Admin
        </a>
    </nav>

    <!-- Konten hero -->
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-10 pb-28 sm:pt-16 sm:pb-36 grid lg:grid-cols-2 gap-12 items-center">
        <!-- kiri: teks -->
        <div class="text-center lg:text-left">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 ring-1 ring-white/15 px-3.5 py-1.5 text-xs font-semibold text-brand-100">
                <span class="h-2 w-2 rounded-full bg-gold-400 animate-pulse"></span>
                <?= $sekolah ?> &middot; T.P. <?= $tahun ?>
            </span>

            <h1 class="mt-5 text-5xl sm:text-7xl font-extrabold tracking-tight text-white leading-[1.05]">
                <span class="bg-gradient-to-r from-gold-400 via-amber-300 to-gold-400 bg-clip-text text-transparent"><?= esc($systemName) ?></span>
            </h1>
            <p class="mt-3 text-lg sm:text-2xl font-semibold text-brand-100"><?= esc($systemTag) ?></p>
            <p class="mt-5 text-sm sm:text-base text-brand-100/80 max-w-xl mx-auto lg:mx-0 leading-relaxed"><?= esc($intro) ?></p>

            <div class="mt-8 flex flex-col sm:flex-row items-center lg:items-start gap-3 lg:justify-start justify-center">
                <?php if ($isOpen): ?>
                    <a href="<?= site_url('isi') ?>" class="group w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-gold-500 hover:bg-gold-400 text-brand-900 font-bold px-7 py-4 rounded-xl shadow-xl shadow-black/30 transition active:scale-95">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Mulai Isi Formulir
                        <svg class="w-4 h-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#cara" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 border border-white/25 text-white font-semibold px-6 py-4 rounded-xl hover:bg-white/10 transition">Lihat Cara Mengisi</a>
                <?php else: ?>
                    <span class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white/10 text-white/70 font-bold px-7 py-4 rounded-xl cursor-not-allowed">🔒 Pengisian Sedang Ditutup</span>
                <?php endif; ?>
            </div>

            <div class="mt-7 flex flex-wrap items-center justify-center lg:justify-start gap-x-5 gap-y-2 text-xs text-brand-100/70">
                <?php foreach (['Tanpa perlu login', 'Pengisian bertahap', 'Data tersimpan aman'] as $tr): ?>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gold-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?= $tr ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- kanan: mockup form -->
        <div class="relative hidden lg:block">
            <div class="absolute -inset-6 bg-gold-400/15 blur-3xl rounded-full"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 p-6 rotate-[1.5deg] hover:rotate-0 transition-transform duration-500">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-brand-700">Langkah 1 dari 6</p>
                    <p class="text-[11px] text-slate-400">Identitas Guru</p>
                </div>
                <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden"><div class="h-full w-1/6 bg-brand-600 rounded-full"></div></div>
                <div class="mt-5 space-y-4">
                    <div>
                        <div class="h-2 w-20 rounded bg-slate-200"></div>
                        <div class="mt-1.5 h-9 rounded-lg border border-slate-200 bg-slate-50"></div>
                    </div>
                    <div>
                        <div class="h-2 w-16 rounded bg-slate-200"></div>
                        <div class="mt-1.5 h-9 rounded-lg border border-slate-200 bg-slate-50"></div>
                    </div>
                    <div class="flex gap-2">
                        <span class="rounded-lg bg-brand-600 text-white text-xs font-semibold px-4 py-1.5">PNS</span>
                        <span class="rounded-lg border border-slate-200 text-slate-400 text-xs font-semibold px-4 py-1.5">PPPK</span>
                        <span class="rounded-lg border border-slate-200 text-slate-400 text-xs font-semibold px-4 py-1.5">GTY</span>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 text-white text-sm font-bold px-5 py-2.5">Lanjut
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </span>
                </div>
            </div>
            <!-- kartu kecil mengambang -->
            <div class="absolute -bottom-5 -left-5 bg-white rounded-xl shadow-xl ring-1 ring-black/5 px-4 py-3 flex items-center gap-3 -rotate-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-100 text-green-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <div class="leading-tight">
                    <p class="text-xs font-bold text-slate-700">Terkirim</p>
                    <p class="text-[11px] text-slate-400">Data tersimpan rapi</p>
                </div>
            </div>
        </div>
    </div>

    <!-- wave -->
    <div class="absolute bottom-0 inset-x-0 leading-none">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" class="w-full h-12 sm:h-20 fill-slate-100"><path d="M0,40 C360,90 1080,-10 1440,40 L1440,80 L0,80 Z"></path></svg>
    </div>
</section>

<!-- ===================== CARA MENGISI ===================== -->
<section id="cara" class="max-w-5xl mx-auto px-4 sm:px-6 py-12 sm:py-16 scroll-mt-6">
    <div class="text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800">Tiga langkah, selesai.</h2>
        <p class="mt-2 text-slate-500">Dirancang ringan dan mudah, baik dari HP maupun komputer.</p>
    </div>

    <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-5">
        <?php
        $langkah = [
            ['Buka Formulir', 'Klik "Mulai Isi Formulir". Tidak perlu membuat akun.', 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
            ['Lengkapi Data', 'Isi identitas, mata pelajaran & kesediaan secara bertahap (6 langkah).', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
            ['Kirim', 'Centang pernyataan lalu kirim. Admin akan memprosesnya.', 'M5 13l4 4L19 7'],
        ];
        foreach ($langkah as $i => $l): ?>
            <div class="relative bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition">
                <span class="absolute -top-3 -left-3 flex h-10 w-10 items-center justify-center rounded-xl bg-brand-700 text-white font-extrabold shadow-lg"><?= $i + 1 ?></span>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ml-6">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $l[2] ?>"/></svg>
                </div>
                <h3 class="mt-4 font-bold text-slate-800"><?= $l[0] ?></h3>
                <p class="mt-1 text-sm text-slate-500 leading-relaxed"><?= $l[1] ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($isOpen): ?>
        <div class="mt-12 relative overflow-hidden rounded-2xl bg-gradient-to-r from-brand-700 to-brand-900 px-6 py-8 sm:px-10 sm:py-10 text-center">
            <div class="absolute inset-0 opacity-[0.4]" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.10) 1px, transparent 0); background-size: 20px 20px;"></div>
            <div class="relative">
                <h3 class="text-xl sm:text-2xl font-extrabold text-white">Siap mengisi kesediaan Anda?</h3>
                <p class="mt-2 text-sm text-brand-100/80">Hanya butuh beberapa menit.</p>
                <a href="<?= site_url('isi') ?>" class="mt-6 inline-flex items-center gap-2 bg-gold-500 hover:bg-gold-400 text-brand-900 font-bold px-8 py-3.5 rounded-xl shadow-lg transition active:scale-95">
                    Isi Sekarang
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?= $this->endSection() ?>
