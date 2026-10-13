<?php
/**
 * Form lapor diterima PKL sedang ditutup (saklar di Pengaturan PKL) atau belum aktif di server (migrasi belum dijalankan).
 *
 * @var array $setting
 */
?>
<?= $this->extend('layouts/pkl') ?>
<?= $this->section('content') ?>
<div class="mx-auto max-w-lg px-4 py-6 sm:py-10">
    <?= view('partials/kop_form', ['setting' => $setting, 'judul' => 'Lapor Diterima PKL', 'keterangan' => 'Diisi siswa lewat HP, tanpa login, setelah diterima perusahaan.']) ?>
    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-7 text-center shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
            <svg class="h-8 w-8 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h2 class="mt-4 text-xl font-bold text-slate-800">Form lapor sedang ditutup</h2>
        <p class="mt-2 leading-relaxed text-slate-500">Saat ini sekolah tidak menerima laporan diterima PKL lewat form ini. Bila kamu sudah diterima perusahaan, sampaikan langsung ke operator sekolah atau Waka Hubin.</p>
    </div>
</div>
<?= $this->endSection() ?>
