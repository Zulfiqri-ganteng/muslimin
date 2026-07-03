<?php
/**
 * Halaman Master Jam Pelajaran — pola tampilan mengikuti Master Guru
 * (sisi kiri toolbar berupa tab shift, bukan kolom pencarian).
 *
 * @var string $shift Shift aktif (pagi/siang)
 * @var array  $rows  Slot jam shift aktif
 */
ob_start(); ?>
<div class="inline-flex rounded-lg border border-slate-300 bg-white p-1 text-sm font-semibold">
    <?php foreach (['pagi' => 'Pagi', 'siang' => 'Siang'] as $key => $label): ?>
        <a href="<?= site_url('admin/master/jam?shift=' . $key) ?>"
           class="px-4 py-1.5 rounded-md transition <?= $shift === $key ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>
<?php $leftHtml = ob_get_clean(); ?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jam',
    'helpTitle' => 'Master Jam Pelajaran',
    'helpBody'  => '<p>Slot waktu KBM per <b>shift</b> (Pagi &amp; Siang dikelola terpisah lewat tab di kiri atas). Durasi dihitung otomatis dari selisih waktu mulai–selesai.</p>
        <p class="mt-1">Centang <b>ISTIRAHAT</b> untuk baris jeda — baris ini muncul di jadwal tapi <b>tidak dihitung</b> sebagai jam mengajar.</p>
        <p class="mt-1">• <b>Import</b> — unggah Excel (shift + jam ke yang sama diperbarui). • <b>Export</b> — unduh seluruh jam (kedua shift). • <b>Hapus Terpilih / Hapus Semua</b> berlaku untuk shift yang sedang tampil.</p>',
]) ?>

<div x-data="jamPage"
     data-base="<?= site_url('admin/master/jam') ?>"
     data-entity="jam pelajaran (shift <?= esc($shift, 'attr') ?>)"
     data-defaults="<?= esc(json_encode(['shift' => $shift, 'jam_ke' => 1, 'waktu_mulai' => '', 'waktu_selesai' => '', 'is_istirahat' => false]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'     => site_url('admin/master/jam'),
        'leftHtml'    => $leftHtml,
        'exportUrl'   => site_url('admin/master/jam/export'),
        'exportTitle' => 'Keluarkan seluruh jam pelajaran (kedua shift) ke file Excel',
        'bulkUrl'     => site_url('admin/master/jam/bulk-delete'),
        'bulkHidden'  => ['shift' => $shift],
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Jam — Shift <?= ucfirst($shift) ?> <span class="text-slate-400 font-normal">(<?= count($rows) ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" @change="toggleAll($event)" title="Pilih semua di halaman ini" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold w-24">Jam Ke</th>
                        <th class="px-6 py-3 font-semibold">Waktu</th>
                        <th class="px-6 py-3 font-semibold w-28">Durasi</th>
                        <th class="px-6 py-3 font-semibold w-32">Jenis</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">Belum ada jam untuk shift ini. Tambah manual atau import Excel.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php if ($r['is_istirahat']): ?>
                            <!-- Baris ISTIRAHAT: tampil sebagai pemisah di tengah -->
                            <tr class="bg-amber-50">
                                <td class="pl-6 pr-2 py-2.5"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>" @change="refresh()"></td>
                                <td colspan="5" class="px-6 py-2.5">
                                    <div class="flex items-center justify-center gap-3">
                                        <span class="hidden sm:block flex-1 border-t border-dashed border-amber-300"></span>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 px-3 py-1 text-xs font-bold tracking-wide whitespace-nowrap">
                                            ISTIRAHAT · <?= esc(substr($r['waktu_mulai'], 0, 5)) ?> – <?= esc(substr($r['waktu_selesai'], 0, 5)) ?>
                                            <span class="font-normal text-amber-600">(<?= esc($r['durasi']) ?> menit)</span>
                                        </span>
                                        <button type="button" @click="openEditEl($el)" data-row="<?= esc(json_encode($r), 'attr') ?>" title="Edit"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-amber-600 hover:bg-amber-100 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <a href="<?= site_url('admin/master/jam/delete/' . $r['id']) ?>" data-confirm="Hapus jam istirahat ini?" title="Hapus"
                                           class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </a>
                                        <span class="hidden sm:block flex-1 border-t border-dashed border-amber-300"></span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr class="hover:bg-slate-50">
                                <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>" @change="refresh()"></td>
                                <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['jam_ke']) ?></td>
                                <td class="px-6 py-3"><?= esc(substr($r['waktu_mulai'], 0, 5)) ?> – <?= esc(substr($r['waktu_selesai'], 0, 5)) ?></td>
                                <td class="px-6 py-3 text-slate-500"><?= esc($r['durasi']) ?> menit</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-xs font-semibold">KBM</span>
                                </td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">
                                    <?= view('admin/master/partials/row_actions', [
                                        'row'       => $r,
                                        'deleteUrl' => site_url('admin/master/jam/delete/' . $r['id']),
                                        'confirm'   => 'Hapus jam ini? Jadwal pada slot tersebut ikut terhapus.',
                                    ]) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Jam Pelajaran' : 'Edit Jam Pelajaran'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Shift</label>
                        <select name="shift" x-model="form.shift" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="pagi">Pagi</option>
                            <option value="siang">Siang</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam Ke</label>
                        <input type="number" name="jam_ke" x-model="form.jam_ke" min="1" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Mulai</label>
                            <input type="time" name="waktu_mulai" x-model="form.waktu_mulai" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Selesai</label>
                            <input type="time" name="waktu_selesai" x-model="form.waktu_selesai" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="is_istirahat" value="1" x-model="form.is_istirahat" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Tandai sebagai jam ISTIRAHAT
                    </label>
                    <p class="text-xs text-slate-400">Durasi dihitung otomatis dari selisih waktu.</p>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <?= view('admin/master/partials/modal_import', [
        'importTitle'  => 'Import Jam Pelajaran',
        'importAction' => site_url('admin/master/jam/import-preview'),
        'templateUrl'  => site_url('admin/master/jam/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kombinasi shift + jam ke yang sudah ada akan diperbarui.',
    ]) ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/jam.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/jam.js') ?>"></script>
<?= $this->endSection() ?>
