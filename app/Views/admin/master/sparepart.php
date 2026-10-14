<?php
/**
 * Halaman Master Sparepart — pola halaman data template Flowbite (rombak tampilan F5). Baris stok menipis (stok ≤ minimum)
 * berlatar merah muda + keterangan merah; tombol "Foto" per baris (galeri sparepart).
 *
 * @var string                        $q     Kata kunci pencarian
 * @var int                           $per   Baris per halaman
 * @var array                         $rows  Baris sparepart halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager Paginasi
 * @var int                           $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'sparepart',
    'helpTitle' => 'Master Sparepart',
    'helpKelas' => '',
    'helpBody'  => '<p>Stok suku cadang lab (RAM, SSD, PSU, kabel, dsb). Isi <b>Stok minimum</b> untuk penanda stok menipis (baris ditandai merah bila stok ≤ minimum).</p>
        <p class="mt-1">Saat mencatat <b>penggantian komponen</b> pada Perbaikan, stok sparepart akan berkurang otomatis. Tombol <b>Foto</b> membuka galeri foto sparepart.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/sparepart') ?>" data-entity="sparepart"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'kategori' => '', 'satuan' => 'unit',
         'stok' => 0, 'stok_minimum' => 0, 'harga' => '', 'lokasi' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/sparepart'),
        'cari'        => 'Cari kode, nama, atau kategori…',
        'q'           => $q,
        'per'         => $per,
        'eksporUrl'   => site_url('admin/master/sparepart/export'),
        'eksporTitle' => 'Keluarkan semua sparepart ke file Excel',
        'massalUrl'   => site_url('admin/master/sparepart/bulk-delete'),
        'massalLabel' => 'sparepart',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua sparepart di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Kategori</th>
                    <th scope="col" class="text-center">Stok</th>
                    <th scope="col" class="text-right">Harga</th>
                    <th scope="col">Lokasi</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8">
                            <?= ui_kosong(['ikon' => 'tools', 'judul' => $q !== '' ? 'Sparepart tidak ditemukan' : 'Belum ada data sparepart',
                                'teks' => $q !== '' ? 'Tidak ada sparepart yang cocok dengan pencarian ini. Tekan Reset.' : 'Tambah sparepart satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $menipis = (int) $r['stok'] <= (int) $r['stok_minimum']; ?>
                    <tr<?= $menipis ? ' class="bg-red-50 dark:bg-red-500/10"' : '' ?>>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih sparepart <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td class="font-medium"><?= esc($r['nama']) ?></td>
                        <td class="sel-ket"><?= $r['kategori'] !== null && $r['kategori'] !== '' ? esc($r['kategori']) : '—' ?></td>
                        <td class="text-center whitespace-nowrap">
                            <span class="font-semibold <?= $menipis ? 'text-red-600 dark:text-red-400' : '' ?>"><?= (int) $r['stok'] ?></span>
                            <span class="sel-ket"><?= esc($r['satuan']) ?></span>
                            <?php if ($menipis): ?><div class="text-xs font-semibold text-red-600 dark:text-red-400">Menipis (min <?= (int) $r['stok_minimum'] ?>)</div><?php endif; ?>
                        </td>
                        <td class="sel-ket text-right whitespace-nowrap"><?= $r['harga'] !== null ? 'Rp' . number_format((float) $r['harga'], 0, ',', '.') : '—' ?></td>
                        <td class="sel-ket"><?= $r['lokasi'] !== null && $r['lokasi'] !== '' ? esc($r['lokasi']) : '—' ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'          => $r,
                                'hapusUrl'     => site_url('admin/master/sparepart/delete/' . $r['id']),
                                'konfirmasi'   => 'Hapus sparepart ' . $r['nama'] . '?',
                                'tambahanHtml' => '<a href="' . site_url('admin/lab-gambar/sparepart/' . $r['id']) . '" class="btn btn-netral btn-sm">' . ikon('image', 'h-4 w-4') . 'Foto</a>',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'satuan' => 'sparepart']) ?>

    <?= ui_crud_form_buka(['benda' => 'Sparepart', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="sp-kode">Kode <span class="text-red-600">*</span></label>
                <input type="text" id="sp-kode" name="kode" x-model="form.kode" maxlength="30" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="sp-kategori">Kategori</label>
                <input type="text" id="sp-kategori" name="kategori" x-model="form.kategori" maxlength="50" list="daftar-kat-sp" class="inp">
                <datalist id="daftar-kat-sp">
                    <?php foreach (['RAM', 'SSD', 'HDD', 'PSU', 'Motherboard', 'Kabel', 'Keyboard', 'Mouse', 'Monitor', 'Kartu Jaringan'] as $k): ?>
                        <option value="<?= esc($k, 'attr') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="sp-nama">Nama <span class="text-red-600">*</span></label>
                <input type="text" id="sp-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="sp-stok">Stok</label>
                <input type="number" id="sp-stok" name="stok" x-model="form.stok" min="0" class="inp">
            </div>
            <div>
                <label class="lbl" for="sp-min">Stok minimum</label>
                <input type="number" id="sp-min" name="stok_minimum" x-model="form.stok_minimum" min="0" class="inp">
            </div>
            <div>
                <label class="lbl" for="sp-satuan">Satuan</label>
                <input type="text" id="sp-satuan" name="satuan" x-model="form.satuan" maxlength="20" class="inp">
            </div>
            <div>
                <label class="lbl" for="sp-harga">Harga (Rp)</label>
                <input type="number" id="sp-harga" name="harga" x-model="form.harga" min="0" step="1" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="sp-lokasi">Lokasi simpan</label>
                <input type="text" id="sp-lokasi" name="lokasi" x-model="form.lokasi" maxlength="100" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="sp-ket">Keterangan</label>
                <input type="text" id="sp-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Sparepart',
        'aksi'        => site_url('admin/master/sparepart/import-preview'),
        'templateUrl' => site_url('admin/master/sparepart/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Kode yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
