<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<div class="max-w-lg mx-auto px-4 py-16">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
            <svg class="w-11 h-11 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="mt-6 text-2xl font-extrabold text-slate-800">Kesediaan Terkirim!</h1>
        <p class="mt-3 text-slate-500 leading-relaxed">
            Terima kasih <b class="text-slate-700"><?= esc(session('nama') ?? 'Bapak/Ibu Guru') ?></b>.
            Data kesediaan mengajar Anda telah kami terima dan akan diproses oleh admin sekolah.
        </p>
        <div class="mt-6 rounded-xl bg-slate-50 border border-slate-200 px-5 py-4 text-sm text-slate-500">
            Jika ada kesalahan data, silakan hubungi admin/operator sekolah.
        </div>
        <a href="<?= site_url('/') ?>" class="mt-6 inline-block text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Kembali ke halaman utama</a>
    </div>
</div>
<?= $this->endSection() ?>
