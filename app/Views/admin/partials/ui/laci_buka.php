<?php
/**
 * Pembuka laci samping / drawer (kit F1, komponen Alpine "laci" = logika dialog). Isi di antara ui_laci_buka() dan ui_laci_tutup():
 * <div class="laci-isi">…</div> lalu (opsional) <div class="laci-kaki">tombol</div>. Dibuka sama seperti dialog (data-buka-dialog="nama").
 *
 * @var array{nama: string, judul: string, lebar?: 'lg'|'xl', awalBuka?: bool} $ui
 */
$nama    = (string) ($ui['nama'] ?? '');
$idJudul = 'laci-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $nama) . '-judul';
$lebar   = ['lg' => ' max-w-xl', 'xl' => ' max-w-3xl'][$ui['lebar'] ?? ''] ?? '';
?>
<div x-data="laci" data-nama="<?= esc($nama, 'attr') ?>"<?= ! empty($ui['awalBuka']) ? ' data-awal-buka="1"' : '' ?>
     :data-buka="buka" @buka-dialog.window="dariAcara($event)" @tutup-dialog.window="dariAcara($event)" @keydown.escape.stop="tutup()"
     class="laci" role="dialog" aria-modal="true" aria-labelledby="<?= esc($idJudul, 'attr') ?>">
    <div class="dialog-latar" @click="tutup()" aria-hidden="true"></div>
    <div class="laci-panel<?= $lebar ?>" x-ref="panel" tabindex="-1" @keydown.tab="jebakFokus($event)">
        <div class="laci-kepala">
            <h3 id="<?= esc($idJudul, 'attr') ?>" class="laci-judul"><?= esc((string) ($ui['judul'] ?? '')) ?></h3>
            <button type="button" class="tombol-tutup" @click="tutup()" data-tutup aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
        </div>
