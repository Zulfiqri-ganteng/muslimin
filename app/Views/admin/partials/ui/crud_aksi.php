<?php
/**
 * Tombol aksi per baris tabel data (kit F5, pola template content/crud/users.html: "Edit user" biru + "Delete user" merah, ber-ikon).
 * Ubah = <button data-ubah data-row="JSON"> — ditangkap komponen "masterList" (delegasi klik, tanpa isian Alpine → siap CSP) lalu
 * membuka dialog formulir terisi. Hapus = POST + dialog konfirmasi merah kit (data-confirm-jenis="bahaya"). Detail (opsional) = tautan.
 * Panggil: ui_crud_aksi(['row' => $r, 'hapusUrl' => …, 'konfirmasi' => 'Hapus jurusan ini?', 'detailUrl' => …|null]).
 * 'tambahanHtml' = tombol khusus halaman di depan (mis. form "Aktifkan" btn-sukses btn-sm); 'ubah' => false = tanpa tombol Ubah.
 * 'hapusIkon' => true = tombol hapus hanya ikon tong sampah (label 'hapusLabel' tetap dibacakan & jadi tooltip); 'konfirmasiJudul'.
 * 'tambahanAkhir' => true = tambahanHtml di BELAKANG Ubah (sebelum hapus); 'hapusTempat' => true = tanpa hapusUrl tetap disisakan
 * ruang selebar ikon hapus (tak tampak) supaya tombol antarbaris lurus.
 *
 * @var array{row?: array<string, mixed>|null, hapusUrl?: string|null, konfirmasi?: string, konfirmasiJudul?: string, hapusLabel?: string, hapusIkon?: bool, detailUrl?: string|null, ubah?: bool, tambahanHtml?: string, tambahanAkhir?: bool, hapusTempat?: bool} $ui
 */
$row        = $ui['row'] ?? null;
$hapusUrl   = $ui['hapusUrl'] ?? null;
$ubah       = ($ui['ubah'] ?? true) && $row !== null;
$hapusLabel = (string) ($ui['hapusLabel'] ?? 'Hapus');
$hapusIkon  = ! empty($ui['hapusIkon']);
$akhir      = ! empty($ui['tambahanAkhir']);
?>
<div class="aksi-baris">
    <?= $akhir ? '' : ($ui['tambahanHtml'] ?? '') ?>
    <?php if (! empty($ui['detailUrl'])): ?>
        <a href="<?= esc((string) $ui['detailUrl'], 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('eye', 'h-4 w-4') ?>Detail</a>
    <?php endif; ?>
    <?php if ($ubah): ?>
        <button type="button" class="btn btn-utama btn-sm" data-ubah data-row="<?= esc((string) json_encode($row), 'attr') ?>"><?= ikon('edit', 'h-4 w-4') ?>Ubah</button>
    <?php endif; ?>
    <?= $akhir ? ($ui['tambahanHtml'] ?? '') : '' ?>
    <?php if ($hapusUrl): ?>
        <a href="<?= esc((string) $hapusUrl, 'attr') ?>" class="btn btn-bahaya btn-sm<?= $hapusIkon ? ' btn-ikon' : '' ?>" data-method="post"<?= $hapusIkon ? ' title="' . esc($hapusLabel, 'attr') . '" aria-label="' . esc($hapusLabel, 'attr') . '"' : '' ?>
           data-confirm="<?= esc((string) ($ui['konfirmasi'] ?? 'Hapus data ini?'), 'attr') ?>" data-confirm-judul="<?= esc((string) ($ui['konfirmasiJudul'] ?? 'Hapus data?'), 'attr') ?>" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?><?= $hapusIkon ? '' : esc($hapusLabel) ?></a>
    <?php elseif ($hapusIkon && ! empty($ui['hapusTempat'])): ?>
        <span class="btn btn-sm btn-ikon aksi-tempat" aria-hidden="true"><?= ikon('trash-bin', 'h-4 w-4') ?></span>
    <?php endif; ?>
</div>
