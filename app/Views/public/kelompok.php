<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Halaman layanan per pengguna (/siswa, /guru, /orang-tua) — pola halaman ruang rumah.pendidikan.go.id: pembuka (ikon + label, H1,
 * satu kalimat) lalu "Layanan yang tersedia" berkelompok di latar abu.
 *
 * @var array{label: string, ikon: string, ket: string} $g @var array<string, list<array>> $bagian
 */
?>
<?= view('public/partials/pembuka', [
    'remah' => [[$g['label'], null]],
    'ikon'  => $g['ikon'],
    'ilu'   => $g['ilu'],
    'label' => 'Layanan untuk ' . mb_strtolower($g['label']),
    'judul' => $g['label'],
    'ket'   => $g['ket'],
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah">
        <h2 class="judul-sub mb-6 sm:text-display-sm">Layanan yang tersedia</h2>
        <div class="space-y-8">
            <?php foreach ($bagian as $judul => $daftar): ?>
                <div>
                    <h3 class="judul-kelompok"><?= esc($judul) ?></h3>
                    <div class="grid-layanan">
                        <?php foreach ($daftar as $l): ?>
                            <?= view('public/partials/kartu_layanan', ['l' => $l, 'tingkat' => 'h4'], ['saveData' => false]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
