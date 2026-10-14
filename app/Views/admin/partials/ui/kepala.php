<?php
/**
 * Kepala halaman (kit F1): remah roti → judul (+ keterangan) dengan tombol aksi di kanan → tab. Panggil: ui_kepala([...]).
 * 'tingkat' => 2 = judul <h2> (kepala contoh/di dalam halaman yang sudah punya <h1>); bawaan <h1>.
 *
 * @var array{judul: string, ket?: string, remah?: list<array{0: string, 1?: string|null}>, aksi?: string, tab?: list<array<string, mixed>>, tingkat?: int} $ui
 */
$remah = $ui['remah'] ?? [];
$aksi  = (string) ($ui['aksi'] ?? '');
$tab   = $ui['tab'] ?? [];
$ket   = (string) ($ui['ket'] ?? '');
$h     = (int) ($ui['tingkat'] ?? 1) === 2 ? 'h2' : 'h1';
?>
<div class="kepala-halaman">
    <?php if ($remah !== []): ?><?= ui_remah($remah) ?><?php endif; ?>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <<?= $h ?> class="judul-halaman"><?= esc($ui['judul'] ?? '') ?></<?= $h ?>>
            <?php if ($ket !== ''): ?><p class="ket-halaman"><?= esc($ket) ?></p><?php endif; ?>
        </div>
        <?php if ($aksi !== ''): ?><div class="flex flex-wrap items-center gap-2 sm:shrink-0"><?= $aksi ?></div><?php endif; ?>
    </div>
    <?php if ($tab !== []): ?><div class="mt-4"><?= ui_tab($tab) ?></div><?php endif; ?>
</div>
