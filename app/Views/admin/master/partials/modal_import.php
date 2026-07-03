<?php
/**
 * Modal Import Excel — identik untuk semua halaman master.
 *
 * @var string      $importTitle  Judul modal, mis. "Import Guru"
 * @var string      $importAction URL tujuan unggah (import-preview)
 * @var string      $templateUrl  URL unduh template Excel
 * @var string      $importNote   Keterangan (HTML) di dalam modal
 * @var string|null $importExtra  HTML tambahan di bawah form (opsional)
 */
$importExtra = $importExtra ?? null;
?>
<div x-show="importOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="importOpen=false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-bold text-lg text-slate-800 mb-4"><?= esc($importTitle) ?></h3>
        <form method="post" action="<?= $importAction ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <p class="text-sm text-slate-500 mb-3"><?= $importNote ?></p>
            <input type="file" name="file" accept=".xlsx,.xls" required
                   class="w-full text-sm border border-slate-300 rounded-lg p-2 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700 file:font-semibold">
            <a href="<?= $templateUrl ?>" class="inline-block mt-3 text-sm text-brand-600 hover:underline">⬇ Unduh template Excel</a>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" @click="importOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5">Unggah &amp; Pratinjau</button>
            </div>
        </form>
        <?php if ($importExtra): ?>
            <hr class="my-4 border-slate-100">
            <?= $importExtra ?>
        <?php endif; ?>
    </div>
</div>
