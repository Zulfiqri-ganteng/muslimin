<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<div class="max-w-lg mx-auto px-4 py-16">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100">
            <svg class="w-11 h-11 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <h1 class="mt-6 text-2xl font-extrabold text-slate-800">Tautan Revisi Tidak Berlaku</h1>
        <p class="mt-3 text-slate-500 leading-relaxed">
            Tautan revisi ini tidak ditemukan, sudah pernah digunakan, atau sudah tidak berlaku lagi.
        </p>
        <div class="mt-6 rounded-xl bg-slate-50 border border-slate-200 px-5 py-4 text-sm text-slate-500">
            Jika Anda perlu memperbaiki data, silakan hubungi admin/operator sekolah untuk meminta tautan revisi baru.
        </div>
        <a href="<?= site_url('/') ?>" class="mt-6 inline-block text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Kembali ke halaman utama</a>
    </div>
</div>
<?= $this->endSection() ?>
