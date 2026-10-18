<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<?php /* Kesediaan guru terkirim (P7 rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama). */ ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Format Kesediaan Guru Mengajar', 'tahunLabel' => 'Tahun Ajaran'], ['saveData' => false]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700"><?= ikon('check-circle', 'h-8 w-8') ?></span>
        <h2 class="mt-5 text-heading-lg text-teks"><?= session('revisi') ? 'Perbaikan tersimpan' : 'Kesediaan terkirim' ?></h2>
        <p class="mt-2 leading-relaxed text-teks-2">
            Terima kasih <b class="text-teks"><?= esc(session('nama') ?? 'Bapak/Ibu Guru') ?></b>.
            <?php if (session('revisi')): ?>
                Perbaikan data Anda telah kami simpan dan akan ditinjau ulang oleh admin sekolah.
            <?php else: ?>
                Data Anda telah kami terima dan akan diproses oleh admin sekolah.
            <?php endif; ?>
        </p>
        <p class="mt-5 rounded-lg border border-garis bg-permukaan px-5 py-4 text-sm text-teks-2">Jika ada kesalahan data, silakan hubungi admin/operator sekolah.</p>
        <a href="<?= site_url('/') ?>" class="tombol tombol-teks mt-5"><?= ikon('angle-left', 'h-4 w-4') ?>Kembali ke halaman utama</a>
    </div>
</div>
<?= $this->endSection() ?>
