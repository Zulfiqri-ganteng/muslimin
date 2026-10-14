<?php
/**
 * Paginasi PANEL ADMIN — gaya Flowbite (kit rombak tampilan F1; kelas .paginasi* di resources/css/admin.css).
 * Halaman publik memakai pager/publik.php (Tailwind v3). Pakai: $pager->only([...])->links('grup', 'admin').
 *
 * Sebelumnya / Berikutnya = halaman tepat sebelum / sesudah (hasPreviousPage), bukan jendela tautan (hasPrevious).
 * Halaman pertama & terakhir selalu bisa diklik (dengan "…" bila jendelanya tidak menyentuh ujung).
 */
use CodeIgniter\Pager\PagerRenderer;

/** @var PagerRenderer $pager */
$pager->setSurroundCount(2);
$total  = $pager->getTotal();
$dari   = $pager->getPerPageStart();
$sampai = $pager->getPerPageEnd();
$angka  = static fn (int $n): string => number_format($n, 0, ',', '.');
$jumlah = $pager->getPageCount();
$awalJendela  = $pager->getFirstPageNumber();
$akhirJendela = $pager->getLastPageNumber();
?>
<?php if ($jumlah > 1): ?>
<nav class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" aria-label="Navigasi halaman">
    <p class="paginasi-info">
        <?php if ($total !== null && $dari !== null && $sampai !== null): ?>
            Menampilkan <b><?= $angka($dari) ?>–<?= $angka(min($sampai, $total)) ?></b> dari <b><?= $angka($total) ?></b>
        <?php else: ?>
            Halaman <b><?= $pager->getCurrentPageNumber() ?></b> dari <b><?= $jumlah ?></b>
        <?php endif; ?>
    </p>
    <ul class="paginasi">
        <li>
            <?php if ($pager->hasPreviousPage()): ?>
                <a href="<?= esc((string) $pager->getPreviousPage(), 'attr') ?>" class="paginasi-item" rel="prev"><?= ikon('chevron-left', 'h-4 w-4') ?><span class="sr-only">Sebelumnya</span></a>
            <?php else: ?>
                <span class="paginasi-item" aria-disabled="true"><?= ikon('chevron-left', 'h-4 w-4') ?><span class="sr-only">Sebelumnya</span></span>
            <?php endif; ?>
        </li>
        <?php if ($awalJendela > 1): ?>
            <li><a href="<?= esc($pager->getFirst(), 'attr') ?>" class="paginasi-item">1</a></li>
            <?php if ($awalJendela > 2): ?><li><span class="paginasi-item" aria-hidden="true">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($pager->links() as $link): ?>
            <li>
                <a href="<?= esc((string) $link['uri'], 'attr') ?>" class="paginasi-item"<?= $link['active'] ? ' aria-current="page"' : '' ?>><?= $link['title'] ?></a>
            </li>
        <?php endforeach; ?>
        <?php if ($akhirJendela < $jumlah): ?>
            <?php if ($akhirJendela < $jumlah - 1): ?><li><span class="paginasi-item" aria-hidden="true">…</span></li><?php endif; ?>
            <li><a href="<?= esc($pager->getLast(), 'attr') ?>" class="paginasi-item"><?= $jumlah ?></a></li>
        <?php endif; ?>
        <li>
            <?php if ($pager->hasNextPage()): ?>
                <a href="<?= esc((string) $pager->getNextPage(), 'attr') ?>" class="paginasi-item" rel="next"><span class="sr-only">Berikutnya</span><?= ikon('chevron-right', 'h-4 w-4') ?></a>
            <?php else: ?>
                <span class="paginasi-item" aria-disabled="true"><span class="sr-only">Berikutnya</span><?= ikon('chevron-right', 'h-4 w-4') ?></span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
<?php endif; ?>
