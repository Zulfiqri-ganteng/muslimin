<?php
/**
 * Galeri foto satu record SIMLAB (generik). Rombak tampilan F8: Kembali di kepala halaman (a[data-kembali], dulu onclick
 * history.back), kartu kit, area unggah dengan komponen kit "pilihBerkas" (dulu objek sebaris + fungsi panah), galeri dengan
 * tombol Hapus merah (dialog konfirmasi kit), ui_kosong → siap CSP.
 *
 * @var string $entitas
 * @var int    $id
 * @var string $label
 * @var array  $rows  Baris lab_gambar
 */
// Tujuan cadangan tombol Kembali (bila halaman dibuka langsung, bukan dari daftarnya).
$asal = [
    'aset'       => 'admin/master/aset',
    'kerusakan'  => 'admin/kerusakan',
    'perbaikan'  => 'admin/perbaikan',
    'lab'        => 'admin/master/lab',
    'sparepart'  => 'admin/master/sparepart',
    'peminjaman' => 'admin/peminjaman',
][$entitas] ?? 'admin';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url($asal) ?>" data-kembali class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<section class="kartu max-w-4xl overflow-hidden" aria-labelledby="judul-foto">
    <div class="kartu-kepala">
        <div>
            <h2 id="judul-foto" class="kartu-judul">Foto <?= esc($label) ?></h2>
            <p class="kartu-ket">Semua gambar otomatis dikonversi ke <b>WEBP</b> (maks. 5 MB/foto). Bisa unggah beberapa sekaligus.</p>
        </div>
    </div>

    <!-- Unggah -->
    <form method="post" action="<?= site_url("admin/lab-gambar/{$entitas}/{$id}") ?>" enctype="multipart/form-data" class="kartu-isi border-b border-gray-200 dark:border-gray-700">
        <?= csrf_field() ?>
        <label x-data="pilihBerkas" data-kosong="Belum ada berkas dipilih"
               class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center transition-colors focus-within:ring-4 focus-within:ring-brand-300 hover:border-brand-400 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700/50 dark:hover:border-brand-500 dark:hover:bg-gray-700">
            <?= ikon('upload', 'h-8 w-8 text-gray-500 dark:text-gray-400') ?>
            <span class="text-sm font-semibold text-gray-900 dark:text-white">Pilih gambar (JPG, PNG, WEBP, GIF, HEIC…)</span>
            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="namaBerkasTerpilih()">Belum ada berkas dipilih</span>
            <input type="file" name="gambar[]" accept="image/*" multiple class="sr-only" @change="catatBerkas($event)">
        </label>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="btn btn-utama"><?= ikon('upload', 'h-4 w-4') ?>Unggah</button>
        </div>
    </form>

    <!-- Galeri -->
    <div class="kartu-isi">
        <?php if (empty($rows)): ?>
            <?= ui_kosong(['ikon' => 'image', 'judul' => 'Belum ada foto', 'teks' => 'Unggah foto lewat kotak di atas.']) ?>
        <?php else: ?>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <?php foreach ($rows as $g): ?>
                    <div class="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700">
                        <a href="<?= esc(labimage_url($g['file'])) ?>" target="_blank" rel="noopener">
                            <img src="<?= esc(labimage_url($g['file'])) ?>" alt="Foto <?= esc($label, 'attr') ?>" loading="lazy" class="h-full w-full object-cover">
                        </a>
                        <a href="<?= site_url('admin/lab-gambar/hapus/' . $g['id']) ?>" data-method="post" title="Hapus foto" aria-label="Hapus foto"
                           data-confirm="Hapus foto ini?" data-confirm-judul="Hapus foto?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya"
                           class="btn btn-bahaya btn-sm absolute end-1.5 top-1.5 px-2 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"><?= ikon('trash-bin', 'h-4 w-4') ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?= $this->endSection() ?>
