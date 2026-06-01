<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<?php
    // ====== Identitas sistem (ubah di sini bila perlu) ======
    $systemName = 'SIKAGU';
    $systemTag  = 'Sistem Informasi Kesediaan Guru Mengajar';
    // ========================================================
    $tahun   = esc($setting['academic_year'] ?? '2026/2027');
    $isOpen  = ! empty($setting['form_open']);
    $intro   = $setting['form_intro'] ?? 'Platform resmi pendataan kesediaan guru dalam melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan sekolah.';
?>

<!-- ===================== HERO ===================== -->
<div class="relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900">
    <!-- ornamen -->
    <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-white/5"></div>
    <div class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-gold-400/10"></div>

    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 py-14 sm:py-20 text-center text-white">
        <?php if (! empty($setting['logo'])): ?>
            <img src="<?= base_url('uploads/' . esc($setting['logo'])) ?>" alt="Logo" class="h-16 sm:h-20 mx-auto mb-5 object-contain">
        <?php endif; ?>

        <p class="text-brand-200 text-xs sm:text-sm font-semibold tracking-[0.2em] uppercase"><?= esc($setting['school_name'] ?? 'Sekolah') ?></p>

        <h1 class="mt-3 text-4xl sm:text-6xl font-extrabold tracking-tight"><?= esc($systemName) ?></h1>
        <p class="mt-2 text-base sm:text-xl font-medium text-brand-100"><?= esc($systemTag) ?></p>

        <span class="inline-block mt-5 bg-gold-400 text-brand-900 text-xs sm:text-sm font-bold px-4 py-1.5 rounded-full">
            Tahun Pelajaran <?= $tahun ?>
        </span>

        <p class="mt-6 text-sm sm:text-base text-brand-100/90 max-w-2xl mx-auto leading-relaxed">
            <?= esc($intro) ?>
        </p>

        <!-- CTA -->
        <div class="mt-9 flex flex-col sm:flex-row items-center justify-center gap-3">
            <?php if ($isOpen): ?>
                <a href="<?= site_url('isi') ?>" class="group w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-gold-500 hover:bg-gold-400 text-brand-900 font-bold px-8 py-4 rounded-xl shadow-xl shadow-black/20 transition active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Mulai Isi Formulir Kesediaan
                    <svg class="w-4 h-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            <?php else: ?>
                <span class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white/10 text-white/70 font-bold px-8 py-4 rounded-xl cursor-not-allowed">
                    🔒 Pengisian Sedang Ditutup
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===================== MENU / FITUR ===================== -->
<div class="max-w-5xl mx-auto px-4 sm:px-6 -mt-8 sm:-mt-10 pb-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Menu utama: Isi Formulir -->
        <a href="<?= $isOpen ? site_url('isi') : '#' ?>" class="<?= $isOpen ? 'hover:-translate-y-1 hover:shadow-lg' : 'opacity-60 cursor-not-allowed' ?> group bg-white rounded-2xl shadow-sm border border-slate-200 p-6 transition">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h3 class="mt-4 font-bold text-slate-800 group-hover:text-brand-700">Isi Formulir Kesediaan</h3>
            <p class="mt-1 text-sm text-slate-500">Isi data kesediaan mengajar Anda secara bertahap & mudah.</p>
            <?php if ($isOpen): ?>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-600">Buka formulir
                    <svg class="w-4 h-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            <?php endif; ?>
        </a>

        <!-- Info: pengisian bertahap -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <h3 class="mt-4 font-bold text-slate-800">Pengisian Bertahap</h3>
            <p class="mt-1 text-sm text-slate-500">Formulir dipandu 6 langkah, ringan dibuka di HP maupun komputer.</p>
        </div>

        <!-- Info: resmi & aman -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold-400/20 text-gold-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="mt-4 font-bold text-slate-800">Resmi &amp; Tersimpan Aman</h3>
            <p class="mt-1 text-sm text-slate-500">Data tercatat rapi sebagai dasar pembagian tugas & penyusunan jadwal.</p>
        </div>
    </div>
</div>

<!-- ===================== LANGKAH SINGKAT ===================== -->
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <h2 class="text-center text-lg sm:text-xl font-bold text-slate-800">Cara Mengisi</h2>
    <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
        <?php
        $langkah = [
            ['1', 'Buka Formulir', 'Klik tombol "Mulai Isi Formulir" di atas.'],
            ['2', 'Lengkapi Data', 'Isi identitas, mata pelajaran, & kesediaan secara bertahap.'],
            ['3', 'Kirim', 'Centang pernyataan, lalu kirim. Selesai!'],
        ];
        foreach ($langkah as $l): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-extrabold"><?= $l[0] ?></div>
                <h3 class="mt-3 font-semibold text-slate-800"><?= $l[1] ?></h3>
                <p class="mt-1 text-sm text-slate-500"><?= $l[2] ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($isOpen): ?>
        <div class="mt-8 text-center">
            <a href="<?= site_url('isi') ?>" class="inline-flex items-center gap-2 bg-brand-700 hover:bg-brand-800 text-white font-bold px-8 py-3.5 rounded-xl shadow-lg transition active:scale-95">
                Isi Sekarang
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
