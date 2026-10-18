<?php

/**
 * Gerbang kata sandi tautan berbagi (rombak publik P5: kotak pesan di tengah + isian kit).
 *
 * @var string      $token
 * @var string|null $galat
 */
?>
<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<section class="isi-halaman">
    <div class="wadah py-6 sm:py-10">
        <div class="kotak-pesan">
            <span class="kosong-ikon"><?= ikon('lock') ?></span>
            <h1 class="kosong-judul text-heading-md">Dokumen terkunci</h1>
            <p class="kosong-teks mx-auto">Masukkan kata sandi yang diberikan pengirim untuk membuka dokumen ini.</p>

            <?php if ($galat !== null): ?>
                <div class="peringatan peringatan-galat mt-5 text-left" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($galat) ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?= site_url('d/' . $token . '/buka') ?>" class="mt-6 space-y-3 text-left">
                <?= csrf_field() ?>
                <label class="lbl" for="sandi-berbagi">Kata sandi</label>
                <input type="password" id="sandi-berbagi" name="sandi" required autofocus autocomplete="off" class="inp">
                <button type="submit" class="tombol tombol-utama tombol-blok h-11"><?= ikon('lock') ?>Buka dokumen</button>
            </form>

            <p class="mt-5 text-sm text-teks-2">Percobaan yang terlalu sering akan dibatasi sementara demi keamanan dokumen.</p>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
