<?php
/**
 * Menu "Lainnya" (U1 rapi tampilan, docs/RENCANA-RAPI-TAMPILAN-PKL-SURAT.md): aksi yang jarang dipakai / berbahaya disimpan di balik satu
 * tombol, supaya halaman hanya menampilkan SATU tombol utama. Memakai komponen kit "dropdown" (ui.js; Esc, klik di luar, panah atas/bawah).
 * Pakai helper ui_menu_lain($butir, $opsi).
 *
 * Butir: ['label' => teks, 'ikon' => nama Flowbite?, 'href' => tautan?, 'dialog' => nama dialog kit? (tombol data-buka-dialog),
 *         'kirim' => ['form' => id formulir, 'name' => …, 'value' => …]? (tombol submit milik formulir itu — formulir tetap sama),
 *         'bahaya' => bool (merah), 'atr' => atribut HTML tambahan apa adanya (mis. data-confirm, data-uji)]. Butir tanpa href/dialog/kirim = tombol biasa.
 *
 * @var array{butir: list<array<string, mixed>>, label?: string, kanan?: bool, kecil?: bool} $ui
 */
$butir = array_values(array_filter((array) ($ui['butir'] ?? []), static fn ($b): bool => is_array($b) && trim((string) ($b['label'] ?? '')) !== ''));
if ($butir === []) {
    return;
}
$label = (string) ($ui['label'] ?? 'Lainnya');
?>
<div class="relative inline-block" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">
    <button type="button" class="btn btn-netral<?= ($ui['kecil'] ?? true) ? ' btn-sm' : '' ?>" x-ref="pemicu" @click="alih()" :aria-expanded="buka" aria-haspopup="true">
        <?= ikon('dots-vertical', 'h-4 w-4') ?><?= esc($label) ?>
    </button>
    <div class="dropdown-menu w-60<?= ($ui['kanan'] ?? true) ? ' right-0' : '' ?>" x-show="buka" x-cloak x-transition.origin.top.right.duration.150ms role="menu">
        <?php foreach ($butir as $b):
            $kelas = 'dropdown-item' . (! empty($b['bahaya']) ? ' dropdown-item-bahaya' : '');
            $isi   = (! empty($b['ikon']) ? ikon((string) $b['ikon'], 'h-4 w-4 shrink-0') : '') . '<span>' . esc((string) $b['label']) . '</span>';
            $atr   = ' role="menuitem"' . (isset($b['atr']) ? ' ' . $b['atr'] : '');
        ?>
            <?php if (! empty($b['href'])): ?>
                <a href="<?= esc((string) $b['href'], 'attr') ?>" class="<?= $kelas ?>"<?= $atr ?>><?= $isi ?></a>
            <?php elseif (! empty($b['kirim'])): $k = (array) $b['kirim']; ?>
                <button type="submit" class="<?= $kelas ?>" form="<?= esc((string) $k['form'], 'attr') ?>"<?= isset($k['name']) ? ' name="' . esc((string) $k['name'], 'attr') . '" value="' . esc((string) ($k['value'] ?? ''), 'attr') . '"' : '' ?> @click="tutup()"<?= $atr ?>><?= $isi ?></button>
            <?php elseif (! empty($b['dialog'])): ?>
                <button type="button" class="<?= $kelas ?>" data-buka-dialog="<?= esc((string) $b['dialog'], 'attr') ?>" @click="tutup()"<?= $atr ?>><?= $isi ?></button>
            <?php else: ?>
                <button type="button" class="<?= $kelas ?>" @click="tutup()"<?= $atr ?>><?= $isi ?></button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
