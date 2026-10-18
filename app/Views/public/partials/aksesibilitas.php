<?php
/**
 * Tombol & panel "Aksesibilitas" (acuan rumah.pendidikan.go.id punya tombol serupa di pojok kanan bawah): ukuran huruf, kontras
 * tinggi, garis bawah tautan. Pilihan disimpan di cookie oleh komponen aksesibilitas (publik/portal.js) dan ditulis server ke <html>
 * (public/layout.php) sehingga berlaku sejak halaman dimuat.
 */
?>
<div class="aks" x-data="aksesibilitas" @keydown.escape="tutup" @click.outside="tutup">
    <div id="panel-aks" class="aks-panel" :data-buka="buka" role="dialog" aria-labelledby="aks-judul">
        <div class="flex items-center justify-between gap-3">
            <p id="aks-judul" class="aks-judul">Aksesibilitas</p>
            <button type="button" class="tombol-ikon h-8 w-8" @click="tutup" aria-label="Tutup panel aksesibilitas"><?= ikon('close', 'h-5 w-5') ?></button>
        </div>
        <p class="aks-label">Ukuran huruf</p>
        <div class="aks-pilihan" role="group" aria-label="Ukuran huruf">
            <button type="button" data-nilai="normal" @click="pilihHuruf($event)" :aria-pressed="huruf === 'normal'">Biasa</button>
            <button type="button" data-nilai="besar" @click="pilihHuruf($event)" :aria-pressed="huruf === 'besar'">Besar</button>
            <button type="button" data-nilai="sangat" @click="pilihHuruf($event)" :aria-pressed="huruf === 'sangat'">Terbesar</button>
        </div>
        <p class="aks-label">Tampilan</p>
        <button type="button" class="aks-saklar" @click="alihKontras" :aria-pressed="kontras">Kontras tinggi <span class="aks-saklar-tanda" aria-hidden="true"></span></button>
        <button type="button" class="aks-saklar" @click="alihGaris" :aria-pressed="garis">Garis bawah pada tautan <span class="aks-saklar-tanda" aria-hidden="true"></span></button>
        <button type="button" class="tombol tombol-teks mt-4 text-sm" @click="aturUlang">Kembalikan ke awal</button>
    </div>
    <button type="button" class="aks-tombol" @click="alih" :aria-expanded="buka" aria-controls="panel-aks"><?= ikon('text-size') ?><span class="hidden sm:inline">Aksesibilitas</span><span class="sr-only sm:hidden">Aksesibilitas</span></button>
</div>
