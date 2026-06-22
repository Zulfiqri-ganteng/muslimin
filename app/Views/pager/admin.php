<?php
use CodeIgniter\Pager\PagerRenderer;

/** @var PagerRenderer $pager */
$pager->setSurroundCount(2);
?>
<?php if ($pager->getPageCount() > 1): ?>
<nav aria-label="Navigasi halaman" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <p class="text-xs text-slate-400">
        Halaman <b class="text-slate-600"><?= $pager->getCurrentPageNumber() ?></b> dari <?= $pager->getPageCount() ?>
    </p>
    <ul class="inline-flex flex-wrap items-center gap-1">
        <?php if ($pager->hasPrevious()): ?>
            <li>
                <a href="<?= $pager->getFirst() ?>" title="Halaman pertama"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-300 px-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">«</a>
            </li>
            <li>
                <a href="<?= $pager->getPrevious() ?>" title="Sebelumnya"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-300 px-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">‹</a>
            </li>
        <?php endif; ?>

        <?php foreach ($pager->links() as $link): ?>
            <li>
                <a href="<?= $link['uri'] ?>"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm font-semibold transition <?= $link['active'] ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
                    <?= $link['title'] ?>
                </a>
            </li>
        <?php endforeach; ?>

        <?php if ($pager->hasNext()): ?>
            <li>
                <a href="<?= $pager->getNext() ?>" title="Berikutnya"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-300 px-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">›</a>
            </li>
            <li>
                <a href="<?= $pager->getLast() ?>" title="Halaman terakhir"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-300 px-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">»</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
