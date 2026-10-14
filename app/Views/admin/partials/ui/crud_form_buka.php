<?php
/**
 * Pembuka dialog formulir Tambah / Ubah halaman data (kit F5, pola modal "Add user / Edit user" template: kepala judul + tombol tutup,
 * isi kolom isian, kaki tombol Simpan). Keadaan = open / mode / actionUrl / form milik komponen "masterList". Judul dua versi
 * (Tambah …/Ubah …) ditulis server dan dipilih lewat x-show, jadi tanpa teks di dalam isian Alpine (siap CSP).
 * Isi di antara ui_crud_form_buka() dan ui_crud_form_tutup(): kolom isian (.lbl + .inp; dua kolom: <div class="grid gap-4 sm:grid-cols-2">).
 * Panggil: ui_crud_form_buka(['benda' => 'Jurusan', 'ukuran' => 'lg']).
 *
 * @var array{benda: string, ukuran?: 'sm'|'lg'|'xl', enctype?: string} $ui
 */
$benda  = (string) ($ui['benda'] ?? 'Data');
$ukuran = ['sm' => ' dialog-panel-sm', 'lg' => ' dialog-panel-lg', 'xl' => ' dialog-panel-xl'][$ui['ukuran'] ?? ''] ?? '';
?>
<div class="dialog" :data-buka="open" @keydown.escape.stop="tutupForm()" role="dialog" aria-modal="true" aria-labelledby="dialog-form-judul">
    <div class="dialog-latar" @click="tutupForm()" aria-hidden="true"></div>
    <div class="dialog-panel<?= $ukuran ?>" tabindex="-1">
        <div class="dialog-kepala">
            <h3 id="dialog-form-judul" class="dialog-judul">
                <span x-show="mode === 'add'">Tambah <?= esc($benda) ?></span><span x-show="mode !== 'add'" x-cloak>Ubah <?= esc($benda) ?></span>
            </h3>
            <button type="button" class="tombol-tutup" @click="tutupForm()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
        </div>
        <form method="post" :action="actionUrl"<?= ! empty($ui['enctype']) ? ' enctype="' . esc((string) $ui['enctype'], 'attr') . '"' : '' ?>>
            <?= csrf_field() ?>
            <div class="dialog-isi">
