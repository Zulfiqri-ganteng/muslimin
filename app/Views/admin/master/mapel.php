<?php
/**
 * Halaman Master Mata Pelajaran — pola tampilan mengikuti Master Guru.
 *
 * @var string                         $q            Kata kunci pencarian
 * @var string                         $kelompok     Filter kelompok mapel
 * @var int                            $per          Baris per halaman
 * @var array                          $rows         Baris mapel halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager        Paginasi
 * @var int                            $total        Total seluruh data
 * @var array                          $allGuru      Opsi guru (id => "kode - nama")
 * @var array                          $kompMap      Peta kompetensi (mapel_id => [guru_id])
 * @var string[]                       $kelompokList Pilihan kelompok mapel
 */
$guruJs = [];
foreach ($allGuru as $id => $label) {
    $guruJs[] = ['id' => (int) $id, 'label' => $label];
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'mapel',
    'helpTitle' => 'Master Mata Pelajaran',
    'helpBody'  => '<p>Daftar mata pelajaran beserta <b>JP/minggu standar</b> (jumlah jam pelajaran yang dibutuhkan tiap minggu, mis. Pemrograman Dasar = 8 JP).</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, baris akan <b>ditambahkan otomatis</b> (kode mapel yang sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh mapel yang ada menjadi file Excel.<br>
        • <b>Hapus Terpilih / Hapus Semua</b> — centang baris atau hapus seluruh data sekaligus.</p>
        <p class="mt-1">Tombol <b>Atur Guru</b> menentukan guru mana saja yang <b>berkompetensi</b> mengajar mapel tersebut, dipakai saat membuat <b>Penugasan</b>.</p>',
]) ?>

<div x-data="mapelPage"
     data-base="<?= site_url('admin/master/mapel') ?>"
     data-entity="mata pelajaran"
     data-defaults="<?= esc(json_encode(['kode_mapel' => '', 'nama_mapel' => '', 'kelompok' => '', 'jp_default' => 2]), 'attr') ?>"
     data-all-guru="<?= esc(json_encode($guruJs), 'attr') ?>"
     data-komp-map="<?= esc(json_encode($kompMap ?: new \stdClass()), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/mapel'),
        'searchPlaceholder' => 'Cari nama atau kode mapel...',
        'q'                 => $q,
        'filters'           => [[
            'name'    => 'kelompok',
            'value'   => $kelompok,
            'all'     => 'Semua Kelompok',
            'options' => array_combine($kelompokList, $kelompokList),
        ]],
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/mapel/export'),
        'exportTitle'       => 'Keluarkan semua mata pelajaran ke file Excel',
        'bulkUrl'           => site_url('admin/master/mapel/bulk-delete'),
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Mata Pelajaran <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" @change="toggleAll($event)" title="Pilih semua di halaman ini" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold w-20">Kode</th>
                        <th class="px-6 py-3 font-semibold">Nama Mapel</th>
                        <th class="px-6 py-3 font-semibold w-40">Kelompok</th>
                        <th class="px-6 py-3 font-semibold w-20">JP</th>
                        <th class="px-6 py-3 font-semibold w-40">Guru Pengampu</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada mata pelajaran. Tambah manual atau import Excel.</td></tr>
                    <?php else: foreach ($rows as $r): $jml = count($kompMap[(int) $r['id']] ?? []); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>" @change="refresh()"></td>
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['kode_mapel']) ?></td>
                            <td class="px-6 py-3 font-medium"><?= esc($r['nama_mapel']) ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['kelompok'] ?: '—') ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['jp_default']) ?> JP</td>
                            <td class="px-6 py-3">
                                <button type="button" @click="openKompetensiEl($el)"
                                        data-id="<?= (int) $r['id'] ?>" data-nama="<?= esc($r['nama_mapel'], 'attr') ?>"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                    <span class="inline-flex items-center justify-center rounded-full bg-brand-100 text-brand-700 w-5 h-5 text-[11px] font-bold"><?= $jml ?></span>
                                    Atur Guru
                                </button>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/mapel/delete/' . $r['id']),
                                    'confirm'   => 'Hapus mapel ini?',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'kelompok', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Mata Pelajaran' : 'Edit Mata Pelajaran'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode Mapel *</label>
                        <input type="text" name="kode_mapel" x-model="form.kode_mapel" maxlength="20" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">JP / Minggu</label>
                        <input type="number" name="jp_default" x-model="form.jp_default" min="0" max="50"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Mapel *</label>
                        <input type="text" name="nama_mapel" x-model="form.nama_mapel" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kelompok</label>
                        <input list="kelompokOpt" name="kelompok" x-model="form.kelompok" maxlength="50"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <datalist id="kelompokOpt">
                            <?php foreach ($kelompokList as $k): ?><option value="<?= esc($k) ?>"></option><?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kompetensi (Atur Guru) -->
    <div x-show="kompOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="kompOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6 max-h-[85vh] flex flex-col">
            <h3 class="font-bold text-lg text-slate-800 mb-1">Guru Pengampu</h3>
            <p class="text-sm text-slate-500 mb-3" x-text="kompMapel"></p>
            <form method="post" :action="kompUrl" class="flex flex-col flex-1 min-h-0">
                <?= csrf_field() ?>
                <div class="flex-1 overflow-y-auto border border-slate-200 rounded-lg p-3 space-y-1.5">
                    <template x-if="allGuru.length === 0">
                        <p class="text-sm text-slate-400 text-center py-6">Belum ada data guru. Tambahkan guru terlebih dahulu.</p>
                    </template>
                    <template x-for="g in allGuru" :key="g.id">
                        <label class="flex items-center gap-2 text-sm text-slate-700 hover:bg-slate-50 rounded px-2 py-1.5 cursor-pointer">
                            <input type="checkbox" name="guru_ids[]" :value="g.id" x-model.number="selected"
                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span x-text="g.label"></span>
                        </label>
                    </template>
                </div>
                <div class="flex justify-between items-center gap-2 mt-4">
                    <span class="text-xs text-slate-400"><span x-text="selected.length"></span> guru dipilih</span>
                    <div class="flex gap-2">
                        <button type="button" @click="kompOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                        <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?= view('admin/master/partials/modal_import', [
        'importTitle'  => 'Import Mata Pelajaran',
        'importAction' => site_url('admin/master/mapel/import-preview'),
        'templateUrl'  => site_url('admin/master/mapel/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data akan tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode mapel yang sudah ada akan diperbarui (tidak dobel).',
    ]) ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/mapel.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/mapel.js') ?>"></script>
<?= $this->endSection() ?>
