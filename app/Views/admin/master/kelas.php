<?php
/**
 * Halaman Master Kelas / Rombel — pola tampilan mengikuti Master Guru.
 *
 * @var string                         $q           Kata kunci pencarian
 * @var string                         $tingkat     Filter tingkat (X/XI/XII)
 * @var string                         $shift       Filter shift (pagi/siang)
 * @var int                            $per         Baris per halaman
 * @var array                          $rows        Baris kelas halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager       Paginasi
 * @var int                            $total       Total seluruh data
 * @var array                          $jurusanOpts Opsi jurusan (id => label)
 * @var array                          $guruOpts    Opsi guru (id => label)
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'kelas',
    'helpTitle' => 'Master Kelas / Rombel',
    'helpBody'  => '<p>Daftar rombongan belajar (mis. X TKJT 1). Tetapkan <b>tingkat</b> (X/XI/XII), <b>jurusan</b>, <b>wali kelas</b>, dan <b>shift</b>.</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, baris akan <b>ditambahkan otomatis</b> (nama kelas yang sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh kelas yang ada menjadi file Excel.<br>
        • <b>Hapus Terpilih / Hapus Semua</b> — centang baris atau hapus seluruh data sekaligus.</p>
        <p class="mt-1"><b>Shift</b> (Pagi/Siang) menentukan set jam pelajaran yang dipakai kelas tersebut saat penjadwalan.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/kelas') ?>"
     data-entity="kelas"
     data-defaults="<?= esc(json_encode(['nama_kelas' => '', 'tingkat' => 'X', 'shift' => 'pagi', 'jurusan_id' => '', 'wali_kelas_id' => '']), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/kelas'),
        'searchPlaceholder' => 'Cari nama kelas...',
        'q'                 => $q,
        'filters'           => [
            [
                'name'    => 'tingkat',
                'value'   => $tingkat,
                'all'     => 'Semua Tingkat',
                'options' => ['X' => 'X', 'XI' => 'XI', 'XII' => 'XII'],
            ],
            [
                'name'    => 'shift',
                'value'   => $shift,
                'all'     => 'Semua Shift',
                'options' => ['pagi' => 'Pagi', 'siang' => 'Siang'],
            ],
        ],
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/kelas/export'),
        'exportTitle'       => 'Keluarkan semua kelas ke file Excel',
        'bulkUrl'           => site_url('admin/master/kelas/bulk-delete'),
        'bulkLabel'         => 'kelas',
        'bulkWarn'          => 'Penugasan & jadwal kelas tersebut ikut terhapus.',
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Kelas <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold">Nama Kelas</th>
                        <th class="px-6 py-3 font-semibold w-20">Tingkat</th>
                        <th class="px-6 py-3 font-semibold w-24">Jurusan</th>
                        <th class="px-6 py-3 font-semibold">Wali Kelas</th>
                        <th class="px-6 py-3 font-semibold w-24">Shift</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada kelas. Tambah manual atau import Excel.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['nama_kelas']) ?></td>
                            <td class="px-6 py-3"><span class="inline-flex rounded bg-slate-100 text-slate-600 px-2 py-0.5 text-xs font-bold"><?= esc($r['tingkat']) ?></span></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['jurusan_kode'] ?: '—') ?></td>
                            <td class="px-6 py-3 text-slate-600"><?= esc($r['wali_nama'] ?: '—') ?></td>
                            <td class="px-6 py-3">
                                <?php if ($r['shift'] === 'pagi'): ?>
                                    <span class="inline-flex rounded-full bg-amber-100 text-amber-700 px-2.5 py-0.5 text-xs font-semibold">Pagi</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-indigo-100 text-indigo-700 px-2.5 py-0.5 text-xs font-semibold">Siang</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/kelas/delete/' . $r['id']),
                                    'confirm'   => 'Hapus kelas ini?',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'tingkat', 'shift', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Kelas' : 'Edit Kelas'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Kelas *</label>
                        <input type="text" name="nama_kelas" x-model="form.nama_kelas" maxlength="50" required placeholder="contoh: X TKJT 1"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tingkat *</label>
                        <select name="tingkat" x-model="form.tingkat" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="X">X</option><option value="XI">XI</option><option value="XII">XII</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Shift *</label>
                        <select name="shift" x-model="form.shift" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="pagi">Pagi</option><option value="siang">Siang</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jurusan</label>
                        <select name="jurusan_id" x-model="form.jurusan_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <?php foreach ($jurusanOpts as $id => $label): ?><option value="<?= $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Wali Kelas</label>
                        <select name="wali_kelas_id" x-model="form.wali_kelas_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <?php foreach ($guruOpts as $id => $label): ?><option value="<?= $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                        </select>
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
        'importTitle'  => 'Import Kelas',
        'importAction' => site_url('admin/master/kelas/import-preview'),
        'templateUrl'  => site_url('admin/master/kelas/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data akan tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Nama kelas yang sudah ada akan diperbarui. Jurusan dicocokkan dari kode, wali dari kode/nama guru.',
    ]) ?>
</div>

<?= $this->endSection() ?>
