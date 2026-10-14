<?php
/**
 * Dialog Import Excel halaman data (kit F5; tampilan dialog kit, keadaan = importOpen milik komponen "masterList"): keterangan,
 * pilih berkas, tautan unduh template, tombol Batal / Unggah & Pratinjau. Sama fungsinya dengan modal impor lama (kini admin/ujian/partials/modal_import.php, dirombak di F7).
 * Panggil: ui_crud_impor(['judul' => 'Import Jurusan', 'aksi' => …, 'templateUrl' => …, 'catatan' => '<b>…</b>', 'tambahanHtml' => …]).
 *
 * @var array{judul: string, aksi: string, templateUrl: string, catatan?: string, tambahanHtml?: string|null} $ui
 */
?>
<div class="dialog" :data-buka="importOpen" @keydown.escape.stop="tutupImpor()" role="dialog" aria-modal="true" aria-labelledby="dialog-impor-judul">
    <div class="dialog-latar" @click="tutupImpor()" aria-hidden="true"></div>
    <div class="dialog-panel dialog-panel-sm" tabindex="-1">
        <div class="dialog-kepala">
            <h3 id="dialog-impor-judul" class="dialog-judul"><?= esc((string) $ui['judul']) ?></h3>
            <button type="button" class="tombol-tutup" @click="tutupImpor()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
        </div>
        <form method="post" action="<?= esc((string) $ui['aksi'], 'attr') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="dialog-isi">
                <?php if (! empty($ui['catatan'])): ?><p class="text-sm text-gray-500 dark:text-gray-400"><?= $ui['catatan'] ?></p><?php endif; ?>
                <div>
                    <label class="lbl" for="berkas-impor">Berkas Excel (.xlsx / .xls)</label>
                    <input type="file" id="berkas-impor" name="file" accept=".xlsx,.xls" required class="inp-berkas">
                </div>
                <a href="<?= esc((string) $ui['templateUrl'], 'attr') ?>" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:underline dark:text-brand-400"><?= ikon('download', 'h-4 w-4') ?>Unduh template Excel</a>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutupImpor()">Batal</button>
                <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Unggah &amp; Pratinjau</button>
            </div>
        </form>
        <?php // Tambahan halaman (boleh berisi formulir sendiri) — di LUAR formulir unggah, seperti modal_import lama. ?>
        <?php if (! empty($ui['tambahanHtml'])): ?>
            <div class="border-t border-gray-200 p-4 md:p-5 dark:border-gray-700"><?= $ui['tambahanHtml'] ?></div>
        <?php endif; ?>
    </div>
</div>
