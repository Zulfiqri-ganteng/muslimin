<?php
/**
 * Kepala halaman Pengajuan PKL (dipakai form, selesai, tutup) — kop formulir gaya portal (partials/kop_formulir, P6a).
 *
 * @var array $setting
 */
?>
<?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Pengajuan PKL / Prakerin', 'keterangan' => 'Diisi sendiri oleh siswa lewat HP, tanpa login.'], ['saveData' => false]) ?>
