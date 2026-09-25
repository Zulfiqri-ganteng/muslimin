<?php
/**
 * @var array  $setting
 * @var array  $info    ['nama','kelas','no','revisi']
 * @var string $urlForm
 */
?>
<?= $this->extend('layouts/biodata') ?>
<?= $this->section('content') ?>
<div class="max-w-lg mx-auto px-4 py-6 sm:py-10">
    <?= view('biodata/_header', ['setting' => $setting]) ?>
    <div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-200 p-7 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
            <svg class="w-9 h-9 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="mt-5 text-xl font-extrabold text-slate-800"><?= ! empty($info['revisi']) ? 'Perbaikan Terkirim!' : 'Biodata Terkirim!' ?></h2>
        <p class="mt-2 text-slate-500 leading-relaxed">
            Terima kasih, <b class="text-slate-700"><?= esc($info['nama'] ?? '') ?></b><?php if (! empty($info['kelas'])): ?> (<?= esc($info['kelas']) ?>)<?php endif; ?>.
            Biodatamu sudah kami terima dan akan diperiksa oleh sekolah.
        </p>
        <div class="mt-5 rounded-xl bg-slate-50 border border-slate-200 px-5 py-4">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Nomor bukti isian</p>
            <p class="mt-1 text-2xl font-extrabold text-brand-700 tracking-wider">#<?= str_pad((string) (int) ($info['no'] ?? 0), 5, '0', STR_PAD_LEFT) ?></p>
            <p class="mt-1 text-xs text-slate-400">Simpan / tangkap layar halaman ini sebagai bukti.</p>
        </div>
        <p class="mt-5 text-sm text-slate-500">
            Kamu <b>tidak perlu mengisi lagi</b>. Jika ternyata ada data yang salah,
            hubungi wali kelas atau operator sekolah.
        </p>
        <a href="<?= esc($urlForm, 'attr') ?>" class="mt-5 inline-block text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Kembali ke halaman awal</a>
    </div>
</div>
<?= $this->endSection() ?>
