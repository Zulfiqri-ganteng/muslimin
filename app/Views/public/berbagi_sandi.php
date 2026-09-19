<?php

/**
 * Gerbang kata sandi tautan berbagi.
 *
 * @var string      $token
 * @var string|null $galat
 */
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<div class="max-w-md mx-auto px-4 py-16">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <div class="w-14 h-14 rounded-2xl bg-brand-50 flex items-center justify-center mx-auto">
            <svg class="w-7 h-7 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <h1 class="mt-4 text-lg font-bold text-slate-800 text-center">Dokumen Terkunci</h1>
        <p class="mt-1.5 text-sm text-slate-500 text-center">Masukkan kata sandi yang diberikan pengirim untuk membuka dokumen ini.</p>

        <?php if ($galat !== null) { ?>
            <div class="mt-4 rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700 text-center">
                <?= esc($galat) ?>
            </div>
        <?php } ?>

        <form method="post" action="<?= site_url('d/' . $token . '/buka') ?>" class="mt-5">
            <?= csrf_field() ?>
            <input type="password" name="sandi" required autofocus autocomplete="off" placeholder="Kata sandi"
                   class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm text-center focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            <button class="w-full mt-3 px-4 py-3 rounded-xl bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800">
                Buka Dokumen
            </button>
        </form>

        <p class="mt-4 text-[11px] text-slate-400 text-center">
            Percobaan yang terlalu sering akan dibatasi sementara demi keamanan dokumen.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
