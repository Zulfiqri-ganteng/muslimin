<?php
/**
 * Remah roti / breadcrumb (kit F1). "Beranda" = beranda peran yang sedang masuk. Panggil: ui_remah([['Grup'], ['Halaman']]).
 *
 * @var array{remah: list<array{0: string, 1?: string|null}>} $ui
 */
$remah   = array_values($ui['remah'] ?? []);
$beranda = site_url(\App\Libraries\HakAkses::beranda((string) ((session('admin') ?? [])['role'] ?? '')));
$akhir   = count($remah) - 1;
?>
<nav class="remah" aria-label="Remah roti">
    <ol>
        <li><a href="<?= esc($beranda, 'attr') ?>"><?= ikon('home', 'h-4 w-4', 'solid') ?>Beranda</a></li>
        <?php foreach ($remah as $i => $r): $url = $r[1] ?? null; ?>
            <li class="inline-flex items-center gap-1">
                <?= ikon('chevron-right', 'h-4 w-4 text-gray-400') ?>
                <?php if ($i === $akhir): ?>
                    <span aria-current="page"><?= esc((string) ($r[0] ?? '')) ?></span>
                <?php elseif ($url === null || $url === ''): ?>
                    <span class="text-gray-500"><?= esc((string) ($r[0] ?? '')) ?></span>
                <?php else: ?>
                    <a href="<?= esc((string) $url, 'attr') ?>"><?= esc((string) ($r[0] ?? '')) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
