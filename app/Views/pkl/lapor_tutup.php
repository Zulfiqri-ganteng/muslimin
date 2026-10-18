<?php
/**
 * Form lapor diterima PKL sedang ditutup (saklar di Pengaturan PKL) atau belum aktif di server (migrasi belum dijalankan).
 * (P6b rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama.)
 *
 * @var array $setting
 */
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('pkl/_kop_lapor', ['setting' => $setting]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-amber-700"><?= ikon('clock', 'h-8 w-8') ?></span>
        <h2 class="mt-4 text-heading-lg text-teks">Form lapor sedang ditutup</h2>
        <p class="mt-2 leading-relaxed text-teks-2">Saat ini sekolah tidak menerima laporan diterima PKL lewat form ini. Bila kamu sudah diterima perusahaan, sampaikan langsung ke operator sekolah atau Waka Hubin.</p>
    </div>
</div>
<?= $this->endSection() ?>
