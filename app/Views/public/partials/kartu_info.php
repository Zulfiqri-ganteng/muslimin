<?php
/**
 * Kartu informasi/pengumuman (acuan "Informasi Untuk Anda"): gambar (atau logo sekolah samar), label kategori, judul 2 baris,
 * ringkasan, tanggal "13 Juli 2026 | 15.59 WIB", "Baca selengkapnya". Seluruh kartu bisa diklik.
 *
 * @var array       $p    baris pengumuman
 * @var string|null $logo URL logo sekolah (pengganti gambar)
 */
use App\Libraries\LayananPublik;

$gambar = LayananPublik::gambar('pengumuman', $p['gambar'] ?? null);
?>
<article class="kartu-info">
    <div class="kartu-info-media">
        <?php if ($gambar): ?>
            <img src="<?= esc($gambar, 'attr') ?>" alt="" class="kartu-info-gambar" loading="lazy">
        <?php else: ?>
            <span class="kartu-info-kosong"><?= $logo ? '<img src="' . esc($logo, 'attr') . '" alt="">' : ikon('bullhorn') ?></span>
        <?php endif; ?>
    </div>
    <div class="kartu-info-isi">
        <p><span class="label label-merek"><?= esc((string) ($p['kategori'] ?? 'Pengumuman')) ?></span></p>
        <h3 class="kartu-info-judul"><a href="<?= site_url('pengumuman/' . (int) $p['id']) ?>" class="tautan-penuh"><?= esc((string) $p['judul']) ?></a></h3>
        <p class="kartu-info-ringkas"><?= esc(mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', (string) $p['isi'])), 0, 160, '…')) ?></p>
        <p class="kartu-info-tanggal"><?= esc(LayananPublik::tanggal((string) $p['created_at'])) ?></p>
        <p class="kartu-info-tautan" aria-hidden="true">Baca selengkapnya<?= ikon('arrow-right') ?></p>
    </div>
</article>
