<?php
/**
 * Paginasi HALAMAN PUBLIK (pengumuman, dokumen publik) — kit publik.css (.paginasi*). Sebelumnya/Berikutnya = halaman tepat
 * sebelum/sesudah (hasPreviousPage), bukan jendela tautan. Pakai: $pager->links('grup', 'publik').
 */
use CodeIgniter\Pager\PagerRenderer;

/** @var PagerRenderer $pager */
$pager->setSurroundCount(2);
$jumlah = $pager->getPageCount();
?>
<?php if ($jumlah > 1): ?>
<nav class="flex flex-col items-center gap-3" aria-label="Navigasi halaman">
    <ul class="paginasi">
        <li>
            <?php if ($pager->hasPreviousPage()): ?>
                <a href="<?= esc((string) $pager->getPreviousPage(), 'attr') ?>" class="paginasi-item" rel="prev"><?= ikon('chevron-left') ?><span class="sr-only">Sebelumnya</span></a>
            <?php else: ?>
                <span class="paginasi-item paginasi-mati" aria-disabled="true"><?= ikon('chevron-left') ?><span class="sr-only">Sebelumnya</span></span>
            <?php endif; ?>
        </li>
        <?php if ($pager->getFirstPageNumber() > 1): ?>
            <li><a href="<?= esc($pager->getFirst(), 'attr') ?>" class="paginasi-item">1</a></li>
            <?php if ($pager->getFirstPageNumber() > 2): ?><li><span class="paginasi-item paginasi-mati" aria-hidden="true">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($pager->links() as $link): ?>
            <li><a href="<?= esc((string) $link['uri'], 'attr') ?>" class="paginasi-item"<?= $link['active'] ? ' aria-current="page"' : '' ?>><?= $link['title'] ?></a></li>
        <?php endforeach; ?>
        <?php if ($pager->getLastPageNumber() < $jumlah): ?>
            <?php if ($pager->getLastPageNumber() < $jumlah - 1): ?><li><span class="paginasi-item paginasi-mati" aria-hidden="true">…</span></li><?php endif; ?>
            <li><a href="<?= esc($pager->getLast(), 'attr') ?>" class="paginasi-item"><?= $jumlah ?></a></li>
        <?php endif; ?>
        <li>
            <?php if ($pager->hasNextPage()): ?>
                <a href="<?= esc((string) $pager->getNextPage(), 'attr') ?>" class="paginasi-item" rel="next"><span class="sr-only">Berikutnya</span><?= ikon('chevron-right') ?></a>
            <?php else: ?>
                <span class="paginasi-item paginasi-mati" aria-disabled="true"><span class="sr-only">Berikutnya</span><?= ikon('chevron-right') ?></span>
            <?php endif; ?>
        </li>
    </ul>
    <p class="text-sm text-teks-2">Halaman <?= $pager->getCurrentPageNumber() ?> dari <?= $jumlah ?></p>
</nav>
<?php endif; ?>
