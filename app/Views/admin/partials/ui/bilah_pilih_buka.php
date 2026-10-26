<?php
/**
 * Pembuka bilah aksi untuk yang DICENTANG (U1 rapi tampilan): tombol massal (Unduh, ACC, Kabari WA …) baru muncul setelah ada yang
 * dicentang, menempel di bawah layar. Harus berada DI DALAM elemen x-data="daftarCentang" (ui.js; kotak centang ber-atribut data-centang
 * @change="hitungCentang()"). Tombol diletakkan di antara ui_bilah_pilih_buka() dan ui_bilah_pilih_tutup(); "Batal pilih" ikut otomatis.
 *
 * @var array{label?: string} $ui label = nama wilayah untuk pembaca layar
 */
?>
<div class="bilah-pilih" x-show="jumlah > 0" x-cloak role="region" aria-label="<?= esc((string) ($ui['label'] ?? 'Aksi untuk yang dipilih'), 'attr') ?>">
    <span class="bilah-pilih-teks" x-text="teksCentang()"></span>
