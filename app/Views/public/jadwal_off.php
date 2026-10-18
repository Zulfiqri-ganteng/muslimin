<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Halaman informasi yang sedang tidak dibuka untuk umum (saklar privasi di Pengaturan Sekolah): jadwal kelas/guru, kehadiran guru.
 * Publik::blokir() mengirim $halaman ('Jadwal pelajaran' / 'Kehadiran guru').
 *
 * @var string|null $halaman
 */
$halaman = $halaman ?? 'Jadwal pelajaran';
?>
<section class="isi-halaman">
    <div class="wadah py-6 sm:py-10">
        <div class="kotak-pesan">
            <span class="kosong-ikon"><?= ikon('eye-slash') ?></span>
            <h1 class="kosong-judul text-heading-md"><?= esc($halaman) ?> belum dibuka untuk umum</h1>
            <p class="kosong-teks mx-auto">Sekolah sedang tidak menampilkan halaman ini di situs. Silakan hubungi pihak sekolah untuk informasinya.</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="<?= site_url('/') ?>" class="tombol tombol-utama">Ke Beranda</a>
                <a href="<?= site_url('bantuan') ?>" class="tombol tombol-netral">Pusat Bantuan</a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
