<?php
/**
 * Halaman Master Tahun Ajaran — pola tampilan mengikuti Master Jurusan.
 *
 * @var string                         $q     Kata kunci pencarian
 * @var int                            $per   Baris per halaman
 * @var array                          $rows  Baris tahun ajaran halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager Paginasi
 * @var int                            $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'tahun_ajaran',
    'helpTitle' => 'Master Tahun Ajaran',
    'helpBody'  => '<p>Daftar tahun ajaran + semester (mis. 2026/2027 Ganjil). Ini jadi acuan waktu untuk modul <b>Kurikulum &amp; Pembelajaran</b> (KKM, Struktur Kurikulum, Penilaian, dst).</p>
        <p class="mt-1">Tepat <b>satu</b> tahun ajaran yang aktif pada satu waktu — pakai tombol <b>Aktifkan</b> pada baris yang berlaku sekarang. Mengaktifkan satu otomatis menonaktifkan yang lain.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/tahun-ajaran') ?>"
     data-entity="tahun ajaran"
     data-defaults="<?= esc(json_encode(['tahun' => '', 'semester' => 'Ganjil']), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/tahun-ajaran'),
        'searchPlaceholder' => 'Cari tahun ajaran...',
        'q'                 => $q,
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/tahun-ajaran/export'),
        'exportTitle'       => 'Keluarkan semua tahun ajaran ke file Excel',
        'bulkUrl'           => site_url('admin/master/tahun-ajaran/bulk-delete'),
        'bulkLabel'         => 'tahun ajaran',
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Tahun Ajaran <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold">Tahun Ajaran</th>
                        <th class="px-6 py-3 font-semibold w-28">Semester</th>
                        <th class="px-6 py-3 font-semibold w-28">Status</th>
                        <th class="px-6 py-3 font-semibold w-40 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada data tahun ajaran. Tambah manual atau import Excel.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['tahun']) ?></td>
                            <td class="px-6 py-3"><?= esc($r['semester']) ?></td>
                            <td class="px-6 py-3">
                                <?php if ($r['is_aktif']): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold px-2.5 py-1">Aktif</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-500 text-xs font-semibold px-2.5 py-1">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <?php if (! $r['is_aktif']): ?>
                                    <form method="post" action="<?= site_url('admin/master/tahun-ajaran/' . $r['id'] . '/aktifkan') ?>" class="inline-block">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                data-confirm="Aktifkan tahun ajaran <?= esc($r['tahun'] . ' ' . $r['semester'], 'attr') ?>? Tahun ajaran lain otomatis dinonaktifkan."
                                                title="Aktifkan"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/tahun-ajaran/delete/' . $r['id']),
                                    'confirm'   => 'Hapus tahun ajaran ini?',
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
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Tahun Ajaran' : 'Edit Tahun Ajaran'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tahun Ajaran *</label>
                        <input type="text" name="tahun" x-model="form.tahun" maxlength="20" required placeholder="2026/2027"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Semester *</label>
                        <select name="semester" x-model="form.semester" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
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
        'importTitle'  => 'Import Tahun Ajaran',
        'importAction' => site_url('admin/master/tahun-ajaran/import-preview'),
        'templateUrl'  => site_url('admin/master/tahun-ajaran/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Tahun+semester yang sama akan diperbarui. Status aktif tidak diubah lewat import — gunakan tombol Aktifkan.',
    ]) ?>
</div>

<?= $this->endSection() ?>
