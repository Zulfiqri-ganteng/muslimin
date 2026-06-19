<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center max-w-lg mx-auto">
    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-4">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
    </span>
    <h1 class="text-xl font-bold text-slate-800">Jadwal Belum Dipublikasikan</h1>
    <p class="text-slate-500 mt-2 text-sm">Sekolah belum menampilkan jadwal untuk umum. Silakan hubungi pihak sekolah.</p>
    <a href="<?= site_url('/') ?>" class="mt-5 inline-flex rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Kembali ke Beranda</a>
</div>

<?= $this->endSection() ?>
