<?php
/**
 * Tab berupa tautan (kit F1) — tiap tab membuka alamat sendiri / ?tab=…; tab aktif ditandai aria-current="page".
 * Panggil: ui_tab([['label' => 'Honor', 'url' => site_url('…'), 'aktif' => true, 'jumlah' => 3, 'ikon' => 'users'], …]).
 *
 * @var array{tab: list<array{label: string, url: string, aktif?: bool, jumlah?: int|string|null, ikon?: string}>, label?: string} $ui
 */
?>
<nav class="tab-daftar" aria-label="<?= esc((string) ($ui['label'] ?? 'Tab halaman'), 'attr') ?>">
    <?php foreach ($ui['tab'] ?? [] as $t): ?>
        <a href="<?= esc((string) $t['url'], 'attr') ?>" class="tab"<?= ! empty($t['aktif']) ? ' aria-current="page"' : '' ?>>
            <?php if (! empty($t['ikon'])): ?><?= ikon((string) $t['ikon'], 'h-4 w-4') ?><?php endif; ?>
            <?= esc((string) $t['label']) ?>
            <?php if (isset($t['jumlah']) && $t['jumlah'] !== ''): ?><span class="tab-jumlah"><?= esc((string) $t['jumlah']) ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
