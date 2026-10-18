<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Isi satu pengumuman: kategori, judul, tanggal, gambar (bila ada), isi lengkap; pengumuman lain di bawahnya.
 *
 * @var array $p @var list<array> $lain @var string|null $logo
 */
use App\Libraries\LayananPublik;

$gambar = LayananPublik::gambar('pengumuman', $p['gambar'] ?? null);
?>
<article>
    <div class="pembuka">
        <div class="wadah max-w-3xl">
            <nav aria-label="Remah roti">
                <ol class="remah">
                    <li><a href="<?= site_url('/') ?>">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><?= ikon('chevron-right') ?><a href="<?= site_url('pengumuman') ?>">Pengumuman</a></li>
                    <li class="flex items-center gap-1.5"><?= ikon('chevron-right') ?><span aria-current="page"><?= esc((string) $p['judul']) ?></span></li>
                </ol>
            </nav>
            <p class="mt-6"><span class="label label-merek"><?= esc((string) ($p['kategori'] ?? 'Pengumuman')) ?></span></p>
            <h1 class="judul-halaman"><?= esc((string) $p['judul']) ?></h1>
            <p class="mt-2 text-sm text-teks-2"><?= esc(LayananPublik::tanggal((string) $p['created_at'])) ?></p>
        </div>
    </div>
    <div class="isi-halaman">
        <div class="wadah max-w-3xl">
            <div class="kartu overflow-hidden">
                <?php if ($gambar): ?><img src="<?= esc($gambar, 'attr') ?>" alt="<?= esc((string) $p['judul'], 'attr') ?>" class="w-full bg-permukaan object-cover"><?php endif; ?>
                <div class="kartu-isi sm:p-8"><p class="text-base leading-relaxed whitespace-pre-line text-teks"><?= esc((string) $p['isi']) ?></p></div>
            </div>
        </div>
    </div>
</article>

<?php if ($lain !== []): ?>
<section class="bagian pt-0 bg-permukaan">
    <div class="wadah">
        <h2 class="judul-sub mb-4">Pengumuman lainnya</h2>
        <div class="grid-info">
            <?php foreach ($lain as $x): ?>
                <?= view('public/partials/kartu_info', ['p' => $x, 'logo' => $logo], ['saveData' => false]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?= $this->endSection() ?>
