<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Halaman layanan per pengguna (/siswa, /guru, /orang-tua) — RINGKAS (2026-10-22, acuan tab Layanan aplikasi Android): judul +
 * satu baris ringkas, lalu layanan per bagian sebagai kisi ikon (ilustrasi + nama, tanpa paragraf; keterangan = tooltip).
 *
 * @var array{label: string, ikon: string, ilu: string, ringkas: string} $g @var array<string, list<array>> $bagian
 */
?>
<?= view('public/partials/pembuka', [
    'remah' => [[$g['label'], null]],
    'judul' => 'Layanan untuk ' . mb_strtolower($g['label']),
    'ket'   => $g['ringkas'],
], ['saveData' => false]) ?>

<section class="seksi">
    <div class="wadah space-y-8">
        <?php foreach ($bagian as $judul => $daftar): ?>
            <div>
                <h2 class="judul-kelompok"><?= esc($judul) ?></h2>
                <?= view('public/partials/kisi_layanan', ['daftar' => $daftar], ['saveData' => false]) ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?= $this->endSection() ?>
