<?php
/**
 * Kartu panduan ringkas untuk tiap halaman. Bisa ditutup & diingat (localStorage).
 * Pakai:  <?= view('admin/partials/help', ['helpKey'=>'guru', 'helpTitle'=>'...', 'helpBody'=>'<p>...</p>']) ?>
 *
 * @var string $helpKey   kunci unik halaman (untuk localStorage)
 * @var string $helpTitle judul panduan
 * @var string $helpBody  isi HTML panduan
 */
?>
<div x-data="{ show: localStorage.getItem('help_<?= esc($helpKey, 'js') ?>') !== '0' }" x-cloak class="mb-5">
    <div x-show="show" x-transition class="rounded-2xl border border-brand-200 bg-brand-50 p-4 sm:p-5">
        <div class="flex items-start gap-3">
            <span class="shrink-0 flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div class="flex-1 min-w-0 text-sm leading-relaxed text-slate-600">
                <p class="font-bold text-brand-800 mb-1"><?= esc($helpTitle) ?></p>
                <?= $helpBody ?>
            </div>
            <button type="button" @click="show=false; localStorage.setItem('help_<?= esc($helpKey, 'js') ?>','0')"
                    class="shrink-0 text-slate-400 hover:text-slate-600" title="Sembunyikan panduan">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    <button type="button" x-show="!show" @click="show=true; localStorage.removeItem('help_<?= esc($helpKey, 'js') ?>')"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Tampilkan panduan halaman
    </button>
</div>
