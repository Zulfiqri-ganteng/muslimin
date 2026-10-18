<?php
/**
 * Kepala halaman Lapor Diterima PKL (dipakai lapor, lapor_selesai, lapor_tutup) — kop formulir gaya portal (partials/kop_formulir, P6b).
 *
 * @var array $setting
 */
?>
<?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Lapor Diterima PKL', 'keterangan' => 'Diisi siswa lewat HP, tanpa login, setelah diterima perusahaan.'], ['saveData' => false]) ?>
