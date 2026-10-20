<?php
/**
 * Tombol TEMPLATE ALASAN di atas kotak "Alasan" (revisi klien 2026-10-20; Libraries\PklAlasan). Klik = kalimat template ditambahkan
 * ke kotak alasan (masih bisa diubah / ditambah; boleh klik lebih dari satu). Perilaku: assets/js/admin/pkl-alasan.js (delegasi
 * data-alasan-*, tanpa Alpine sebaris — siap CSP).
 *
 * @var list<array{jenis: string, judul: string, item: list<array{id: int, judul: string, isi: string}>}> $grup   PklAlasan::untukDialog()
 * @var string                                                                                            $target id textarea alasan
 * @var bool                                                                                              $kelola boleh mengelola template
 */
$grup   ??= [];
$kelola ??= false;
if ($grup === [] && ! $kelola) {
    return;
}
?>
<div class="space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700" data-alasan-cepat="<?= esc($target, 'attr') ?>">
    <?php if ($grup === []): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">Belum ada template alasan untuk jendela ini. <a href="<?= site_url('admin/pkl/alasan') ?>" class="font-semibold text-brand-700 hover:underline dark:text-brand-400">Tambah template</a></p>
    <?php else: ?>
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Alasan cepat — klik untuk menambahkan ke kotak alasan:</p>
        <?php foreach ($grup as $g): ?>
            <div>
                <?php if (count($grup) > 1): ?><p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($g['judul']) ?></p><?php endif; ?>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <?php foreach ($g['item'] as $it): ?>
                        <button type="button" class="btn btn-netral btn-sm" data-alasan-isi="<?= esc($it['isi'], 'attr') ?>" title="<?= esc($it['isi'], 'attr') ?>" aria-pressed="false"><?= esc($it['judul']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-xs">
            <button type="button" class="font-semibold text-gray-600 hover:underline dark:text-gray-300" data-alasan-kosong>Kosongkan kotak alasan</button>
            <?php if ($kelola): ?><a href="<?= site_url('admin/pkl/alasan') ?>" class="font-semibold text-brand-700 hover:underline dark:text-brand-400">Kelola template</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
