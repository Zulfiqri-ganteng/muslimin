<?php
/**
 * Galeri foto satu record SIMLAB (generik).
 *
 * @var string $entitas
 * @var int    $id
 * @var string $label
 * @var array  $rows  Baris lab_gambar
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <a href="#" onclick="history.back();return false;" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Kembali
    </a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-4xl">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
        <h2 class="font-bold text-slate-800">Foto <?= esc($label) ?></h2>
        <p class="text-sm text-slate-500 mt-0.5">Semua gambar otomatis dikonversi ke <b>WEBP</b> (maks. 5 MB/foto). Bisa unggah beberapa sekaligus.</p>
    </div>

    <!-- Unggah -->
    <form method="post" action="<?= site_url("admin/lab-gambar/{$entitas}/{$id}") ?>" enctype="multipart/form-data" class="p-6 border-b border-slate-100"
          x-data="{ names: [] }">
        <?= csrf_field() ?>
        <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 hover:border-brand-400 cursor-pointer p-6 text-center transition">
            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <span class="text-sm font-semibold text-slate-600">Pilih gambar (JPG, PNG, WEBP, GIF, HEIC…)</span>
            <span class="text-xs text-slate-400" x-text="names.length ? names.join(', ') : 'Belum ada berkas dipilih'"></span>
            <input type="file" name="gambar[]" accept="image/*" multiple class="hidden"
                   @change="names = Array.from($event.target.files).map(f => f.name)">
        </label>
        <div class="flex justify-end mt-4">
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Unggah</button>
        </div>
    </form>

    <!-- Galeri -->
    <div class="p-6">
        <?php if (empty($rows)): ?>
            <p class="text-center text-slate-400 py-8">Belum ada foto.</p>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <?php foreach ($rows as $g): ?>
                    <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-slate-50 aspect-square">
                        <a href="<?= esc(labimage_url($g['file'])) ?>" target="_blank">
                            <img src="<?= esc(labimage_url($g['file'])) ?>" alt="foto" loading="lazy" class="w-full h-full object-cover">
                        </a>
                        <a href="<?= site_url('admin/lab-gambar/hapus/' . $g['id']) ?>" data-confirm="Hapus foto ini?"
                           class="absolute top-1.5 right-1.5 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/90 text-red-500 hover:bg-white shadow-sm opacity-0 group-hover:opacity-100 transition" title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
