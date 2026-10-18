<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<?php /* Tautan revisi kesediaan tidak berlaku (P7 rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama). */ ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Format Kesediaan Guru Mengajar', 'tahunLabel' => 'Tahun Ajaran'], ['saveData' => false]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-amber-700"><?= ikon('exclamation-circle', 'h-8 w-8') ?></span>
        <h2 class="mt-5 text-heading-lg text-teks">Tautan revisi tidak berlaku</h2>
        <p class="mt-2 leading-relaxed text-teks-2">
            Tautan revisi ini tidak ditemukan, sudah pernah digunakan, atau sudah tidak berlaku lagi.
        </p>
        <p class="mt-5 rounded-lg border border-garis bg-permukaan px-5 py-4 text-sm text-teks-2">Jika Anda perlu memperbaiki data, silakan hubungi admin/operator sekolah untuk meminta tautan revisi baru.</p>
        <a href="<?= site_url('/') ?>" class="tombol tombol-teks mt-5"><?= ikon('angle-left', 'h-4 w-4') ?>Kembali ke halaman utama</a>
    </div>
</div>
<?= $this->endSection() ?>
