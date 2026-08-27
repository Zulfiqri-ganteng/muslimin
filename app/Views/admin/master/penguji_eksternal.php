<?php
/**
 * Halaman Master Penguji Eksternal UKK — pola tampilan mengikuti Master Teknisi.
 *
 * @var string                        $q     Kata kunci pencarian
 * @var int                           $per   Baris per halaman
 * @var array                         $rows  Baris penguji eksternal halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager Paginasi
 * @var int                           $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'penguji_eksternal',
    'helpTitle' => 'Master Penguji Eksternal',
    'helpBody'  => '<p>Penguji UKK dari <b>luar sekolah</b> (DUDI/industri/asosiasi profesi) — bukan guru, jadi disimpan di tabel tersendiri.</p>
        <p class="mt-1">Penguji internal (guru sekolah) diambil langsung dari <b>Master Guru</b> saat penugasan di <b>Jadwal UKK</b> — tidak perlu didaftarkan di sini.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/penguji-eksternal') ?>"
     data-entity="penguji_eksternal"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'instansi' => '',
         'jabatan' => '', 'no_hp' => '', 'email' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/penguji-eksternal'),
        'searchPlaceholder' => 'Cari kode, nama, atau instansi...',
        'q'                 => $q,
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/penguji-eksternal/export'),
        'exportTitle'       => 'Keluarkan semua penguji eksternal ke file Excel',
        'bulkUrl'           => site_url('admin/master/penguji-eksternal/bulk-delete'),
        'bulkLabel'         => 'penguji eksternal',
    ]) ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Penguji Eksternal <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-24">Kode</th>
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Instansi / Jabatan</th>
                        <th class="px-4 py-3 font-semibold w-36">Kontak</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' ? 'Tidak ada penguji eksternal yang cocok dengan pencarian ini.' : 'Belum ada data penguji eksternal. Tambah manual atau import Excel.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-bold text-brand-700"><?= esc($r['kode']) ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= esc($r['nama']) ?></td>
                            <td class="px-4 py-3">
                                <div class="text-slate-700"><?= $r['instansi'] !== null && $r['instansi'] !== '' ? esc($r['instansi']) : '<span class="text-slate-300">—</span>' ?></div>
                                <?php if (! empty($r['jabatan'])): ?>
                                    <div class="text-xs text-slate-400"><?= esc($r['jabatan']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= $r['no_hp'] !== null && $r['no_hp'] !== '' ? esc($r['no_hp']) : '<span class="text-slate-300">—</span>' ?>
                                <?php if (! empty($r['email'])): ?>
                                    <div class="text-xs text-slate-400"><?= esc($r['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/penguji-eksternal/delete/' . $r['id']),
                                    'confirm'   => 'Hapus data penguji eksternal ini?',
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
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Penguji Eksternal' : 'Edit Penguji Eksternal'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode *</label>
                        <input type="text" name="kode" x-model="form.kode" maxlength="30" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">No HP</label>
                        <input type="text" name="no_hp" x-model="form.no_hp" maxlength="25"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Instansi</label>
                        <input type="text" name="instansi" x-model="form.instansi" maxlength="150"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jabatan</label>
                        <input type="text" name="jabatan" x-model="form.jabatan" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                        <input type="email" name="email" x-model="form.email" maxlength="100"
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
        'importTitle'  => 'Import Penguji Eksternal',
        'importAction' => site_url('admin/master/penguji-eksternal/import-preview'),
        'templateUrl'  => site_url('admin/master/penguji-eksternal/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sudah ada akan diperbarui.',
    ]) ?>
</div>

<?= $this->endSection() ?>
