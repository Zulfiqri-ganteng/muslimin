<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<?php /* Form kesediaan guru ditutup (P7 rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama). */ ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Format Kesediaan Guru Mengajar', 'tahunLabel' => 'Tahun Ajaran'], ['saveData' => false]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-amber-700"><?= ikon('clock', 'h-8 w-8') ?></span>
        <h2 class="mt-5 text-heading-lg text-teks">Pengisian ditutup</h2>
        <p class="mt-2 leading-relaxed text-teks-2">
            Mohon maaf, periode pengisian Format Kesediaan Guru Mengajar untuk saat ini sedang <b>ditutup</b>.
            Silakan hubungi admin/operator sekolah untuk informasi lebih lanjut.
        </p>
    </div>
</div>
<?= $this->endSection() ?>
