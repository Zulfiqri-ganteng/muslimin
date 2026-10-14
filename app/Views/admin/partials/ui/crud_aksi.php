<?php
/**
 * Tombol aksi per baris tabel data (kit F5, pola template content/crud/users.html: "Edit user" biru + "Delete user" merah, ber-ikon).
 * Ubah = <button data-ubah data-row="JSON"> — ditangkap komponen "masterList" (delegasi klik, tanpa isian Alpine → siap CSP) lalu
 * membuka dialog formulir terisi. Hapus = POST + dialog konfirmasi merah kit (data-confirm-jenis="bahaya"). Detail (opsional) = tautan.
 * Panggil: ui_crud_aksi(['row' => $r, 'hapusUrl' => …, 'konfirmasi' => 'Hapus jurusan ini?', 'detailUrl' => …|null]).
 * 'tambahanHtml' = tombol khusus halaman di depan (mis. form "Aktifkan" btn-sukses btn-sm); 'ubah' => false = tanpa tombol Ubah.
 *
 * @var array{row?: array<string, mixed>|null, hapusUrl?: string|null, konfirmasi?: string, detailUrl?: string|null, ubah?: bool, tambahanHtml?: string} $ui
 */
$row      = $ui['row'] ?? null;
$hapusUrl = $ui['hapusUrl'] ?? null;
$ubah     = ($ui['ubah'] ?? true) && $row !== null;
?>
<div class="inline-flex items-center justify-end gap-2">
    <?= $ui['tambahanHtml'] ?? '' ?>
    <?php if (! empty($ui['detailUrl'])): ?>
        <a href="<?= esc((string) $ui['detailUrl'], 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('eye', 'h-4 w-4') ?>Detail</a>
    <?php endif; ?>
    <?php if ($ubah): ?>
        <button type="button" class="btn btn-utama btn-sm" data-ubah data-row="<?= esc((string) json_encode($row), 'attr') ?>"><?= ikon('edit', 'h-4 w-4') ?>Ubah</button>
    <?php endif; ?>
    <?php if ($hapusUrl): ?>
        <a href="<?= esc((string) $hapusUrl, 'attr') ?>" class="btn btn-bahaya btn-sm" data-method="post"
           data-confirm="<?= esc((string) ($ui['konfirmasi'] ?? 'Hapus data ini?'), 'attr') ?>" data-confirm-judul="Hapus data?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus</a>
    <?php endif; ?>
</div>
