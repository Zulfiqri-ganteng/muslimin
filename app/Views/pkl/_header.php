<?php
/**
 * Kepala halaman PKL (dipakai form, selesai, tutup).
 *
 * @var array $setting
 */
?>
<div class="rise relative isolate overflow-hidden rounded-2xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 shadow-xl">
    <div class="hero-grid absolute inset-0 -z-10" aria-hidden="true"></div>
    <span class="orb orb-float -z-10 -top-16 -right-10 h-48 w-48 bg-brand-400/40" aria-hidden="true"></span>
    <span class="orb -z-10 -bottom-20 -left-8 h-44 w-44 bg-gold-400/20" aria-hidden="true"></span>
    <div class="relative px-5 py-7 sm:px-10 sm:py-9 text-center text-white">
        <?php if (! empty($setting['logo'])): ?>
            <span class="pop-anim mx-auto mb-3 flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-full bg-white shadow-lg ring-4 ring-white/15">
                <!-- Berkas logo hilang (mis. belum diunggah ulang di hosting) → sembunyikan bulatannya, jangan tampil ikon rusak. -->
                <img src="<?= esc(base_url('uploads/' . $setting['logo']), 'attr') ?>" alt="Logo sekolah" class="h-11 w-11 sm:h-14 sm:w-14 object-contain" onerror="this.parentElement.remove()">
            </span>
        <?php endif; ?>
        <p class="text-brand-200 text-xs sm:text-sm font-semibold tracking-wide uppercase"><?= esc($setting['school_name'] ?? '') ?></p>
        <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold leading-tight tracking-tight">Pengajuan PKL / Prakerin</h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-brand-100">Isi sendiri lewat HP, tanpa login. Tidak sampai 5 menit.</p>
        <?php if (! empty($setting['academic_year'])): ?>
            <span class="inline-block mt-3 bg-gold-400 text-brand-900 text-xs sm:text-sm font-bold px-4 py-1.5 rounded-full">Tahun Pelajaran <?= esc($setting['academic_year']) ?></span>
        <?php endif; ?>
    </div>
</div>
