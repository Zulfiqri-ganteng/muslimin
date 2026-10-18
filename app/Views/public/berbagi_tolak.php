<?php

/**
 * Halaman penolakan tautan berbagi — satu halaman untuk semua alasan,
 * dengan kalimat yang memberi tahu penerima apa yang harus dilakukan.
 * (Rombak publik P5: kotak pesan di tengah.)
 *
 * @var string $judul
 * @var string $pesan
 */
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<section class="isi-halaman">
    <div class="wadah py-6 sm:py-10">
        <div class="kotak-pesan">
            <span class="kosong-ikon"><?= ikon('link') ?></span>
            <h1 class="kosong-judul text-heading-md"><?= esc($judul) ?></h1>
            <p class="kosong-teks mx-auto leading-relaxed"><?= esc($pesan) ?></p>
            <a href="<?= site_url('/') ?>" class="tombol tombol-netral mt-6">Ke Beranda</a>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
