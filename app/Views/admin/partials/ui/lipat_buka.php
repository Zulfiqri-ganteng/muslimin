<?php
/**
 * Pembuka bagian yang bisa dilipat (U1 rapi tampilan): <details> tanpa JavaScript — judul + ringkasan satu baris di kepala, isi panjang
 * (riwayat, pembayaran, data lama) tersembunyi sampai dibuka. Isi di antara ui_lipat_buka() dan ui_lipat_tutup().
 *
 * @var array{judul: string, ringkas?: string, buka?: bool, id?: string, ikon?: string} $ui
 */
$id = (string) ($ui['id'] ?? '');
?>
<details class="lipat"<?= $id !== '' ? ' id="' . esc($id, 'attr') . '"' : '' ?><?= ! empty($ui['buka']) ? ' open' : '' ?>>
    <summary class="lipat-kepala">
        <?php if (! empty($ui['ikon'])): ?><?= ikon((string) $ui['ikon'], 'h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400') ?><?php endif; ?>
        <span class="lipat-judul"><?= esc((string) ($ui['judul'] ?? '')) ?></span>
        <?php if (trim((string) ($ui['ringkas'] ?? '')) !== ''): ?><span class="lipat-ringkas"><?= esc((string) $ui['ringkas']) ?></span><?php endif; ?>
        <span class="lipat-panah<?= trim((string) ($ui['ringkas'] ?? '')) === '' ? ' ms-auto' : '' ?>"><?= ikon('chevron-down', 'h-5 w-5') ?></span>
    </summary>
    <div class="lipat-isi">
