<?php
/**
 * Halaman Master Hari — pola tampilan mengikuti Master Guru.
 *
 * @var string                         $q      Kata kunci pencarian
 * @var string                         $status Filter status ('' | '1' | '0')
 * @var int                            $per    Baris per halaman
 * @var array                          $rows   Baris hari halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager  Paginasi
 * @var int                            $total  Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'hari',
    'helpTitle' => 'Master Hari',
    'helpBody'  => '<p>Hari yang berstatus <b>Aktif</b> akan muncul sebagai kolom pada grid Jadwal KBM nanti. Nonaktifkan hari yang tidak ada kegiatan belajar (mis. bila Sabtu libur). <b>Urutan</b> menentukan posisi tampil.</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, tampil pratinjau yang bisa diedit sebelum disimpan (nama hari sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — unduh seluruh hari ke Excel. • <b>Hapus Terpilih / Hapus Semua</b> tersedia.</p>',
]) ?>

<div x-data="hariPage"
     data-base="<?= site_url('admin/master/hari') ?>"
     data-entity="hari"
     data-defaults="<?= esc(json_encode(['nama' => '', 'urutan' => 1, 'aktif' => true]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/hari'),
        'searchPlaceholder' => 'Cari nama hari...',
        'q'                 => $q,
        'filters'           => [[
            'name'    => 'status',
            'value'   => $status,
            'all'     => 'Semua Status',
            'options' => ['1' => 'Aktif', '0' => 'Nonaktif'],
        ]],
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/hari/export'),
        'exportTitle'       => 'Keluarkan semua hari ke file Excel',
        'bulkUrl'           => site_url('admin/master/hari/bulk-delete'),
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Hari <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" @change="toggleAll($event)" title="Pilih semua di halaman ini" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold w-20">Urutan</th>
                        <th class="px-6 py-3 font-semibold">Nama Hari</th>
                        <th class="px-6 py-3 font-semibold w-28">Status</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada data hari. Tambah manual atau import Excel.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>" @change="refresh()"></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['urutan']) ?></td>
                            <td class="px-6 py-3 font-medium"><?= esc($r['nama']) ?></td>
                            <td class="px-6 py-3">
                                <?php if ($r['aktif']): ?>
                                    <span class="inline-flex items-center rounded-full bg-green-100 text-green-700 px-2.5 py-0.5 text-xs font-semibold">Aktif</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-500 px-2.5 py-0.5 text-xs font-semibold">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/hari/delete/' . $r['id']),
                                    'confirm'   => 'Hapus hari ini? Jadwal pada hari tersebut ikut terhapus.',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'status', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Hari' : 'Edit Hari'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Hari *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="15" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Urutan *</label>
                        <input type="number" name="urutan" x-model="form.urutan" min="1" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="aktif" value="1" x-model="form.aktif" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Aktif (tampil di grid jadwal)
                    </label>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <?= view('admin/master/partials/modal_import', [
        'importTitle'  => 'Import Hari',
        'importAction' => site_url('admin/master/hari/import-preview'),
        'templateUrl'  => site_url('admin/master/hari/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Nama hari yang sudah ada akan diperbarui.',
    ]) ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/hari.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/hari.js') ?>"></script>
<?= $this->endSection() ?>
