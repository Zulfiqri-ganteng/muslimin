<?php

/**
 * Halaman penolakan tautan berbagi — satu halaman untuk semua alasan,
 * dengan kalimat yang memberi tahu penerima apa yang harus dilakukan.
 *
 * @var string $judul
 * @var string $pesan
 */
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<div class="max-w-md mx-auto px-4 py-16 text-center">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto">
            <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m8.656-4.828l1.5-1.5a4 4 0 115.656 5.656l-3 3a4 4 0 01-5.656 0M4 4l16 16"/></svg>
        </div>
        <h1 class="mt-4 text-lg font-bold text-slate-800"><?= esc($judul) ?></h1>
        <p class="mt-2 text-sm text-slate-500 leading-relaxed"><?= esc($pesan) ?></p>
        <a href="<?= site_url('/') ?>" class="inline-block mt-5 px-4 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Ke Beranda
        </a>
    </div>
</div>

<?= $this->endSection() ?>
