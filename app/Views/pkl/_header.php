<?php
/**
 * Kepala halaman PKL (dipakai form, selesai, tutup) — kop formulir resmi (partials/kop_form).
 *
 * @var array $setting
 */
?>
<?= view('partials/kop_form', ['setting' => $setting, 'judul' => 'Pengajuan PKL / Prakerin', 'keterangan' => 'Diisi sendiri oleh siswa lewat HP, tanpa login.']) ?>
