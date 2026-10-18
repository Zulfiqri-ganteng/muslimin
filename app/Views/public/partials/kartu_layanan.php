<?php
/**
 * Satu kartu layanan (acuan "Layanan yang Tersedia"): ikon, judul (tautan diregangkan → seluruh kartu bisa diklik), satu kalimat,
 * label "Dibuka"/"Sedang ditutup" untuk formulir yang bisa dibuka-tutup, ajakan di kaki kartu.
 *
 * @var array{judul: string, ket: string, ikon: string, url: string, luar: bool, status: ?string} $l satu butir LayananPublik
 * @var string|null $tingkat tag judul (bawaan h3)
 */
use App\Libraries\LayananPublik;

$tutup   = ($l['status'] ?? null) === LayananPublik::TUTUP;
$tag     = in_array($tingkat ?? 'h3', ['h2', 'h3', 'h4'], true) ? $tingkat ?? 'h3' : 'h3';
$luar    = ! empty($l['luar']) ? ' target="_blank" rel="noopener"' : '';
?>
<article class="kartu-layanan"<?= $tutup ? ' data-tutup' : '' ?>>
    <div class="kartu-layanan-kepala">
        <?php if (! empty($l['ilu'])): ?>
            <img src="<?= LayananPublik::ilustrasi($l['ilu']) ?>" alt="" class="kartu-layanan-gambar" width="48" height="48" loading="lazy">
        <?php else: ?>
            <span class="kartu-layanan-ikon"><?= ikon($l['ikon']) ?></span>
        <?php endif; ?>
        <?php if (($l['status'] ?? null) === LayananPublik::BUKA): ?>
            <span class="label label-buka">Dibuka</span>
        <?php elseif ($tutup): ?>
            <span class="label label-tutup">Sedang ditutup</span>
        <?php endif; ?>
    </div>
    <<?= $tag ?> class="kartu-layanan-judul"><a href="<?= esc($l['url'], 'attr') ?>" class="tautan-penuh"<?= $luar ?>><?= esc($l['judul']) ?></a></<?= $tag ?>>
    <p class="kartu-layanan-ket"><?= esc($l['ket']) ?></p>
    <p class="kartu-layanan-kaki" aria-hidden="true"><?= $tutup ? 'Lihat keterangan' : 'Buka layanan' ?><?= ikon('arrow-right') ?></p>
</article>
