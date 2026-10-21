<?php
/**
 * Pembuka kotak dialog / modal (kit F1, komponen Alpine "dialog" di ui.js). Isi di antara ui_dialog_buka() dan ui_dialog_tutup():
 * <div class="dialog-isi">…</div> lalu <div class="dialog-kaki">tombol</div> (boleh dibungkus <form>). Tombol tutup di isi: @click="tutup()".
 * Dibuka lewat <button type="button" data-buka-dialog="nama">, $dispatch('buka-dialog', { nama: 'nama' }) atau Ui.bukaDialog('nama').
 * Esc, klik latar, atau tombol tutup menutupnya; fokus berputar di dalam panel dan kembali ke tombol pembuka saat ditutup.
 *
 * @var array{nama: string, judul: string, ukuran?: 'sm'|'lg'|'xl'|'penuh', awalBuka?: bool} $ui
 */
$nama    = (string) ($ui['nama'] ?? '');
$idJudul = 'dialog-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $nama) . '-judul';
$ukuran  = ['sm' => ' dialog-panel-sm', 'lg' => ' dialog-panel-lg', 'xl' => ' dialog-panel-xl', 'penuh' => ' dialog-panel-penuh'][$ui['ukuran'] ?? ''] ?? '';
?>
<div x-data="dialog" data-nama="<?= esc($nama, 'attr') ?>"<?= ! empty($ui['awalBuka']) ? ' data-awal-buka="1"' : '' ?>
     :data-buka="buka" @buka-dialog.window="dariAcara($event)" @tutup-dialog.window="dariAcara($event)" @keydown.escape.stop="tutup()"
     class="dialog" role="dialog" aria-modal="true" aria-labelledby="<?= esc($idJudul, 'attr') ?>">
    <div class="dialog-latar" @click="tutup()" aria-hidden="true"></div>
    <div class="dialog-panel<?= $ukuran ?>" x-ref="panel" tabindex="-1" @keydown.tab="jebakFokus($event)">
        <div class="dialog-kepala">
            <h3 id="<?= esc($idJudul, 'attr') ?>" class="dialog-judul"><?= esc((string) ($ui['judul'] ?? '')) ?></h3>
            <button type="button" class="tombol-tutup" @click="tutup()" data-tutup aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
        </div>
