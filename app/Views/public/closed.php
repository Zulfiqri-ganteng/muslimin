<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<div class="max-w-lg mx-auto px-4 py-16">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100">
            <svg class="w-11 h-11 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h1 class="mt-6 text-2xl font-extrabold text-slate-800">Pengisian Ditutup</h1>
        <p class="mt-3 text-slate-500 leading-relaxed">
            Mohon maaf, periode pengisian Format Kesediaan Guru Mengajar untuk saat ini sedang <b>ditutup</b>.
            Silakan hubungi admin/operator sekolah untuk informasi lebih lanjut.
        </p>
    </div>
</div>
<?= $this->endSection() ?>
