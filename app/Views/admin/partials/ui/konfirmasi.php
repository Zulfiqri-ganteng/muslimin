<?php
/**
 * Dialog konfirmasi bersama (kit F1, komponen Alpine "konfirmasi" di ui.js) — SEKALI per halaman, pengganti confirm().
 * Dipakai otomatis oleh data-confirm="Pesan?" (opsional data-confirm-judul / -ya / -jenis="bahaya") dan Ui.konfirmasi({…}).
 * Panggil: ui_konfirmasi().
 */
?>
<div x-data="konfirmasi" id="ui-konfirmasi" :data-buka="buka" @keydown.escape.stop="tolak()"
     class="dialog z-[55]" role="alertdialog" aria-modal="true" aria-labelledby="ui-konfirmasi-judul" aria-describedby="ui-konfirmasi-pesan">
    <div class="dialog-latar" @click="tolak()" aria-hidden="true"></div>
    <div class="dialog-panel dialog-panel-sm" x-ref="panel" tabindex="-1" @keydown.tab="jebakFokus($event)">
        <div class="p-4 text-center md:p-6">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg" :class="bahaya ? 'bg-red-100 text-red-600' : 'bg-brand-100 text-brand-700'">
                <span x-show="bahaya"><?= ikon('exclamation-circle', 'h-6 w-6') ?></span>
                <span x-show="!bahaya"><?= ikon('info-circle', 'h-6 w-6') ?></span>
            </div>
            <h3 id="ui-konfirmasi-judul" class="mb-2 text-lg font-semibold text-gray-900" x-text="judul"></h3>
            <p id="ui-konfirmasi-pesan" class="text-sm whitespace-pre-line text-gray-500" x-text="pesan"></p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                <button type="button" class="btn btn-netral" x-ref="tombolBatal" @click="tolak()" x-text="batal"></button>
                <button type="button" class="btn" :class="bahaya ? 'btn-bahaya' : 'btn-utama'" x-ref="tombolYa" @click="setuju()" x-text="ya"></button>
            </div>
        </div>
    </div>
</div>
