<?php
/**
 * Kartu panduan ringkas untuk tiap halaman (gaya kit F3: sudut rounded-lg, ikon Flowbite, tombol tutup kit).
 * Default TERTUTUP — user menekan "Baca panduan halaman" untuk membuka,
 * pilihan tersimpan di localStorage (komponen `helpCard` di assets/js/admin/app.js).
 *
 * Pakai:  <?= view('admin/partials/help', ['helpKey'=>'guru', 'helpTitle'=>'...', 'helpBody'=>'<p>...</p>']) ?>
 *
 * @var string $helpKey   kunci unik halaman (untuk localStorage)
 * @var string $helpTitle judul panduan
 * @var string $helpBody  isi HTML panduan
 */
?>
<div x-data="helpCard" data-help-key="<?= esc($helpKey, 'attr') ?>" x-cloak class="mb-5">
    <button type="button" x-show="!show" @click="openHelp()"
            class="inline-flex items-center gap-1.5 rounded-lg text-xs font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-300">
        <?= ikon('info-circle', 'h-4 w-4') ?>
        Baca panduan halaman
    </button>
    <div x-show="show" x-transition class="rounded-lg border border-brand-200 bg-brand-50 p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white">
                <?= ikon('info-circle', 'h-5 w-5') ?>
            </span>
            <div class="min-w-0 flex-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                <p class="mb-1 font-bold text-brand-800 dark:text-white"><?= esc($helpTitle) ?></p>
                <?= $helpBody ?>
            </div>
            <button type="button" @click="closeHelp()" class="tombol-tutup -my-1 -me-1" title="Sembunyikan panduan" aria-label="Sembunyikan panduan">
                <?= ikon('close', 'h-4 w-4') ?>
            </button>
        </div>
    </div>
</div>
