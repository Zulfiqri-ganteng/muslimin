<?php
/**
 * Formulir biodata ditutup (P6c rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama).
 *
 * @var array $setting
 */
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('biodata/_header', ['setting' => $setting]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-amber-700"><?= ikon('clock', 'h-8 w-8') ?></span>
        <h2 class="mt-5 text-heading-lg text-teks">Pengisian sedang ditutup</h2>
        <p class="mt-2 leading-relaxed text-teks-2">
            Formulir biodata siswa belum dibuka atau masa pengisian sudah berakhir.
            Tunggu informasi dari wali kelas, atau hubungi operator sekolah.
        </p>
    </div>
</div>
<?= $this->endSection() ?>
