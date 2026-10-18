<?php
/**
 * Biodata terkirim (P6c rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama).
 *
 * @var array  $setting
 * @var array  $info    ['nama','kelas','no','revisi']
 * @var string $urlForm
 */
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('biodata/_header', ['setting' => $setting]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700"><?= ikon('check-circle', 'h-8 w-8') ?></span>
        <h2 class="mt-5 text-heading-lg text-teks"><?= ! empty($info['revisi']) ? 'Perbaikan terkirim' : 'Biodata terkirim' ?></h2>
        <p class="mt-2 leading-relaxed text-teks-2">
            Terima kasih, <b class="text-teks"><?= esc($info['nama'] ?? '') ?></b><?php if (! empty($info['kelas'])): ?> (<?= esc($info['kelas']) ?>)<?php endif; ?>.
            Biodatamu sudah kami terima dan akan diperiksa oleh sekolah.
        </p>
        <div class="mt-5 rounded-lg border border-brand-100 bg-brand-50 px-5 py-4">
            <p class="text-xs font-semibold tracking-wide text-teks-2 uppercase">Nomor bukti isian</p>
            <p class="mt-1 text-display-sm tracking-wider text-brand-800">#<?= str_pad((string) (int) ($info['no'] ?? 0), 5, '0', STR_PAD_LEFT) ?></p>
            <p class="mt-1 text-sm text-teks-2">Simpan / tangkap layar halaman ini sebagai bukti.</p>
        </div>
        <p class="mt-5 text-sm leading-relaxed text-teks-2">
            Kamu <b>tidak perlu mengisi lagi</b>. Jika ternyata ada data yang salah,
            hubungi wali kelas atau operator sekolah.
        </p>
        <a href="<?= esc($urlForm, 'attr') ?>" class="tombol tombol-teks mt-5"><?= ikon('angle-left', 'h-4 w-4') ?>Kembali ke halaman awal</a>
    </div>
</div>
<?= $this->endSection() ?>
