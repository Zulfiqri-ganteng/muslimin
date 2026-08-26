<?php
/**
 * Halaman Master Fase (Kurikulum Merdeka) — pola tampilan mengikuti Master Jurusan.
 *
 * @var string                         $q     Kata kunci pencarian
 * @var int                            $per   Baris per halaman
 * @var array                          $rows  Baris fase halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager Paginasi
 * @var int                            $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'fase',
    'helpTitle' => 'Master Fase',
    'helpBody'  => '<p>Fase Kurikulum Merdeka (A-F) — dasar pengelompokan <b>Capaian Pembelajaran</b> per mata pelajaran. Untuk SMK umumnya dipakai <b>Fase E</b> (kelas X) dan <b>Fase F</b> (kelas XI-XII).</p>
        <p class="mt-1">Setiap kelas di menu <b>Master Data ▸ Kelas</b> sudah otomatis ditandai fasenya berdasar tingkat — bisa diubah manual di sana bila ada pengecualian.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/fase') ?>"
     data-entity="fase"
     data-defaults="<?= esc(json_encode(['kode' => '', 'nama' => '', 'urutan' => '', 'deskripsi' => '']), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/fase'),
        'searchPlaceholder' => 'Cari kode atau nama fase...',
        'q'                 => $q,
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/fase/export'),
        'exportTitle'       => 'Keluarkan semua fase ke file Excel',
        'bulkUrl'           => site_url('admin/master/fase/bulk-delete'),
        'bulkLabel'         => 'fase',
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Fase <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold w-20">Kode</th>
                        <th class="px-6 py-3 font-semibold">Nama Fase</th>
                        <th class="px-6 py-3 font-semibold">Deskripsi</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada data fase.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-6 py-3"><span class="inline-flex rounded bg-slate-100 text-slate-700 px-2 py-0.5 text-xs font-bold"><?= esc($r['kode']) ?></span></td>
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['nama']) ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['deskripsi'] ?: '—') ?></td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/fase/delete/' . $r['id']),
                                    'confirm'   => 'Hapus fase ini?',
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
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Fase' : 'Edit Fase'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Kode *</label>
                            <input type="text" name="kode" x-model="form.kode" maxlength="2" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Urutan</label>
                            <input type="number" name="urutan" x-model="form.urutan" min="0"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Fase *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="30" required placeholder="Fase E"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi</label>
                        <input type="text" name="deskripsi" x-model="form.deskripsi" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
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
        'importTitle'  => 'Import Fase',
        'importAction' => site_url('admin/master/fase/import-preview'),
        'templateUrl'  => site_url('admin/master/fase/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sama diperbarui.',
    ]) ?>
</div>

<?= $this->endSection() ?>
