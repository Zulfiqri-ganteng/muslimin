<?= $this->extend('layouts/biodata') ?>
<?= $this->section('content') ?>
<div class="max-w-lg mx-auto px-4 py-6 sm:py-10">
    <?= view('biodata/_header', ['setting' => $setting]) ?>
    <div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-200 p-7 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
            <svg class="w-9 h-9 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h2 class="mt-5 text-xl font-extrabold text-slate-800">Pengisian Sedang Ditutup</h2>
        <p class="mt-2 text-slate-500 leading-relaxed">
            Formulir biodata siswa belum dibuka atau masa pengisian sudah berakhir.
            Tunggu informasi dari wali kelas, atau hubungi operator sekolah.
        </p>
    </div>
</div>
<?= $this->endSection() ?>
