<?php
/**
 * Halaman Master Sparepart — pola tampilan mengikuti Master Jurusan (masterList).
 *
 * @var string                        $q     Kata kunci pencarian
 * @var int                           $per   Baris per halaman
 * @var array                         $rows  Baris sparepart halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager Paginasi
 * @var int                           $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'sparepart',
    'helpTitle' => 'Master Sparepart',
    'helpBody'  => '<p>Stok suku cadang lab (RAM, SSD, PSU, kabel, dsb). Isi <b>Stok Minimum</b> untuk penanda stok menipis (baris ditandai merah bila stok ≤ minimum).</p>
        <p class="mt-1">Saat mencatat <b>penggantian komponen</b> pada Perbaikan (P4), stok sparepart akan berkurang otomatis.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/sparepart') ?>"
     data-entity="sparepart"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'kategori' => '', 'satuan' => 'unit',
         'stok' => 0, 'stok_minimum' => 0, 'harga' => '', 'lokasi' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/sparepart'),
        'searchPlaceholder' => 'Cari kode, nama, atau kategori...',
        'q'                 => $q,
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/sparepart/export'),
        'exportTitle'       => 'Keluarkan semua sparepart ke file Excel',
        'bulkUrl'           => site_url('admin/master/sparepart/bulk-delete'),
        'bulkLabel'         => 'sparepart',
    ]) ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Sparepart <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-24">Kode</th>
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold w-28">Kategori</th>
                        <th class="px-4 py-3 font-semibold w-24 text-center">Stok</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Harga</th>
                        <th class="px-4 py-3 font-semibold w-28">Lokasi</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' ? 'Tidak ada sparepart yang cocok.' : 'Belum ada data sparepart. Tambah manual atau import Excel.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r):
                        $menipis = (int) $r['stok'] <= (int) $r['stok_minimum']; ?>
                        <tr class="hover:bg-slate-50 <?= $menipis ? 'bg-red-50/40' : '' ?>">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-bold text-brand-700"><?= esc($r['kode']) ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= esc($r['nama']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['kategori'] !== null && $r['kategori'] !== '' ? esc($r['kategori']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold <?= $menipis ? 'text-red-600' : 'text-slate-700' ?>"><?= (int) $r['stok'] ?></span>
                                <span class="text-slate-400 text-xs"><?= esc($r['satuan']) ?></span>
                                <?php if ($menipis): ?><div class="text-[10px] font-semibold text-red-500">menipis (min <?= (int) $r['stok_minimum'] ?>)</div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-600"><?= $r['harga'] !== null ? 'Rp' . number_format((float) $r['harga'], 0, ',', '.') : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['lokasi'] !== null && $r['lokasi'] !== '' ? esc($r['lokasi']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/sparepart/delete/' . $r['id']),
                                    'confirm'   => 'Hapus sparepart ini?',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Sparepart' : 'Edit Sparepart'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode *</label>
                        <input type="text" name="kode" x-model="form.kode" maxlength="30" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kategori</label>
                        <input type="text" name="kategori" x-model="form.kategori" maxlength="50" list="daftar-kat-sp"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <datalist id="daftar-kat-sp">
                            <?php foreach (['RAM', 'SSD', 'HDD', 'PSU', 'Motherboard', 'Kabel', 'Keyboard', 'Mouse', 'Monitor', 'Kartu Jaringan'] as $k): ?>
                                <option value="<?= $k ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Stok</label>
                        <input type="number" name="stok" x-model="form.stok" min="0"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Stok Minimum</label>
                        <input type="number" name="stok_minimum" x-model="form.stok_minimum" min="0"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Satuan</label>
                        <input type="text" name="satuan" x-model="form.satuan" maxlength="20"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Harga (Rp)</label>
                        <input type="number" name="harga" x-model="form.harga" min="0" step="1"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Lokasi Simpan</label>
                        <input type="text" name="lokasi" x-model="form.lokasi" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" x-model="form.keterangan" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <?= view('admin/master/partials/modal_import', [
        'importTitle'  => 'Import Sparepart',
        'importAction' => site_url('admin/master/sparepart/import-preview'),
        'templateUrl'  => site_url('admin/master/sparepart/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Kode yang sudah ada akan diperbarui.',
    ]) ?>
</div>

<?= $this->endSection() ?>
