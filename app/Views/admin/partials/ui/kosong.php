<?php
/**
 * Keadaan kosong (kit F1) — pengganti baris "Belum ada data". Panggil: ui_kosong(['judul' => …, 'teks' => …, 'ikon' => …, 'aksi' => …]).
 *
 * @var array{judul?: string, teks?: string, ikon?: string, aksi?: string} $ui
 */
$teks = (string) ($ui['teks'] ?? '');
$aksi = (string) ($ui['aksi'] ?? '');
?>
<div class="kosong">
    <div class="kosong-ikon"><?= ikon((string) ($ui['ikon'] ?? 'inbox'), 'h-6 w-6') ?></div>
    <p class="kosong-judul"><?= esc((string) ($ui['judul'] ?? 'Belum ada data')) ?></p>
    <?php if ($teks !== ''): ?><p class="kosong-teks"><?= esc($teks) ?></p><?php endif; ?>
    <?php if ($aksi !== ''): ?><div class="mt-4 flex flex-wrap justify-center gap-2"><?= $aksi ?></div><?php endif; ?>
</div>
