<?php
/**
 * Wadah toast (kit F1, komponen Alpine "toastWadah" di ui.js) — SEKALI per halaman. Toast dimunculkan dengan $toast(pesan, jenis)
 * dari atribut atau Ui.toast(pesan, jenis) dari berkas JS; jenis: sukses | galat | awas | info. Panggil: ui_toast($awal).
 *
 * @var array{awal: list<array{pesan: string, jenis?: string}>} $ui
 */
$awal = $ui['awal'] ?? [];
?>
<div x-data="toastWadah" class="toast-wadah" aria-live="polite"<?= $awal !== [] ? ' data-awal="' . esc((string) json_encode(array_values($awal)), 'attr') . '"' : '' ?>>
    <template x-for="t in daftar" :key="t.id">
        <div class="toast" :class="'toast-' + t.jenis" x-show="t.tampil" x-transition.duration.200ms role="status">
            <div class="toast-ikon">
                <span x-show="t.jenis === 'sukses'"><?= ikon('check-circle', 'h-5 w-5') ?></span>
                <span x-show="t.jenis === 'galat'"><?= ikon('close-circle', 'h-5 w-5') ?></span>
                <span x-show="t.jenis === 'awas'"><?= ikon('exclamation-circle', 'h-5 w-5') ?></span>
                <span x-show="t.jenis === 'info'"><?= ikon('info-circle', 'h-5 w-5') ?></span>
            </div>
            <p class="toast-pesan" x-text="t.pesan"></p>
            <button type="button" class="tombol-tutup" @click="hapus(t.id)" aria-label="Tutup pemberitahuan"><?= ikon('close', 'h-4 w-4') ?></button>
        </div>
    </template>
</div>
