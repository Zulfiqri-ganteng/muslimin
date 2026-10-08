<?php
/**
 * Tombol aksi baris tabel (Edit + Hapus) — identik untuk semua halaman master.
 * Data baris dioper lewat atribut data-row (JSON, di-escape aman) sehingga
 * tidak ada JavaScript inline di dalam view.
 *
 * @var array  $row       Baris data (untuk form edit)
 * @var string $deleteUrl URL hapus satu baris
 * @var string $confirm   Teks konfirmasi hapus
 * @var string|null $detailUrl (opsional) bila diisi, tampil tombol "Detail" (ikon mata) di depan Edit
 */
?>
<div class="inline-flex items-center justify-end gap-1">
    <?php if (! empty($detailUrl)): ?>
    <a href="<?= esc($detailUrl, 'attr') ?>" title="Detail"
       class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-brand-700 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    </a>
    <?php endif; ?>
    <button type="button" @click="openEditEl($el)" data-row="<?= esc(json_encode($row), 'attr') ?>" title="Edit"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    </button>
    <a href="<?= $deleteUrl ?>" data-confirm="<?= esc($confirm, 'attr') ?>" title="Hapus"
       class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
    </a>
</div>
