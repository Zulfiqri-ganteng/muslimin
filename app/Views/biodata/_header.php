<?php
/**
 * Kepala halaman biodata (dipakai form, selesai, tutup) — kop formulir gaya portal (partials/kop_formulir, P6c).
 *
 * @var array $setting
 */
?>
<?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Formulir Biodata Siswa'], ['saveData' => false]) ?>
