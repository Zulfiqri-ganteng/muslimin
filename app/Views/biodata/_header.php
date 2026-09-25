<?php
/**
 * Kepala halaman biodata (dipakai form, selesai, tutup).
 *
 * @var array $setting
 */
?>
<div class="bg-gradient-to-br from-brand-700 to-brand-900 rounded-2xl shadow-xl overflow-hidden">
    <div class="px-5 py-7 sm:px-10 sm:py-9 text-center text-white">
        <?php if (! empty($setting['logo'])): ?>
            <span class="mx-auto mb-3 flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-full bg-white shadow-lg ring-4 ring-white/15">
                <!-- Berkas logo hilang (mis. belum diunggah ulang di hosting) → sembunyikan bulatannya, jangan tampil ikon rusak. -->
                <img src="<?= esc(base_url('uploads/' . $setting['logo']), 'attr') ?>" alt="Logo sekolah" class="h-11 w-11 sm:h-14 sm:w-14 object-contain" onerror="this.parentElement.remove()">
            </span>
        <?php endif; ?>
        <p class="text-brand-200 text-xs sm:text-sm font-semibold tracking-wide uppercase"><?= esc($setting['school_name'] ?? '') ?></p>
        <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold leading-tight">Formulir Biodata Siswa</h1>
        <?php if (! empty($setting['academic_year'])): ?>
            <span class="inline-block mt-3 bg-gold-400 text-brand-900 text-xs sm:text-sm font-bold px-4 py-1.5 rounded-full">Tahun Pelajaran <?= esc($setting['academic_year']) ?></span>
        <?php endif; ?>
    </div>
</div>
