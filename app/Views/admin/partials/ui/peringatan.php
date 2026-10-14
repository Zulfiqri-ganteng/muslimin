<?php
/**
 * Peringatan / alert (kit F1). Panggil: ui_peringatan('sukses', 'Data tersimpan.', ['judul' => …, 'tutup' => true]).
 * jenis: info | sukses | galat | awas | netral. 'tutup' = ada tombol tutup (Alpine, objek sederhana — aman versi CSP).
 * 'hilang' = milidetik → kotak memudar & hilang sendiri (ui.js, [data-hilang]); dipakai pesan sesudah aksi di luar layout admin
 * (halaman masuk). Di layout admin pesan sesudah aksi = toast.
 *
 * @var array{jenis: string, pesan: string|list<string>, judul?: string, html?: string, tutup?: bool, hilang?: int} $ui
 */
$ikonJenis = ['info' => 'info-circle', 'sukses' => 'check-circle', 'galat' => 'close-circle', 'awas' => 'exclamation-circle', 'netral' => 'info-circle'];
$jenis     = isset($ikonJenis[$ui['jenis'] ?? '']) ? (string) $ui['jenis'] : 'info';
$pesan     = $ui['pesan'] ?? '';
$judul     = (string) ($ui['judul'] ?? '');
$tutup     = ! empty($ui['tutup']);
$hilang    = (int) ($ui['hilang'] ?? 0);
?>
<div class="peringatan peringatan-<?= $jenis ?>" role="<?= $jenis === 'galat' ? 'alert' : 'status' ?>"<?= $hilang > 0 ? ' data-hilang="' . $hilang . '"' : '' ?><?= $tutup ? ' x-data="{ terbuka: true }" x-show="terbuka"' : '' ?>>
    <?= ikon($ikonJenis[$jenis], 'mt-0.5 h-5 w-5 shrink-0') ?>
    <div class="min-w-0 flex-1">
        <?php if ($judul !== ''): ?><p class="font-semibold"><?= esc($judul) ?></p><?php endif; ?>
        <?php if (isset($ui['html'])): ?>
            <div<?= $judul !== '' ? ' class="mt-1"' : '' ?>><?= $ui['html'] ?></div>
        <?php elseif (is_array($pesan)): ?>
            <ul class="<?= $judul !== '' ? 'mt-1 ' : '' ?>list-inside list-disc space-y-0.5"><?php foreach ($pesan as $p): ?><li><?= esc((string) $p) ?></li><?php endforeach; ?></ul>
        <?php elseif ($pesan !== ''): ?>
            <p<?= $judul !== '' ? ' class="mt-1"' : '' ?>><?= esc((string) $pesan) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($tutup): ?>
        <button type="button" class="tombol-tutup -my-1.5 -me-1.5 text-current opacity-70 hover:bg-black/5 hover:text-current hover:opacity-100" @click="terbuka = false" aria-label="Tutup pesan"><?= ikon('close', 'h-4 w-4') ?></button>
    <?php endif; ?>
</div>
