<?php
/**
 * Halaman Master Aset / Inventaris — pola masterList + aksi "Detail Komputer".
 *
 * @var string                        $q            Kata kunci pencarian
 * @var int                           $labId        Filter lab aktif
 * @var string                        $kategori     Filter kategori aktif
 * @var string                        $kondisi      Filter kondisi aktif
 * @var string                        $status       Filter status aktif
 * @var int                           $per          Baris per halaman
 * @var array                         $rows         Baris aset halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager        Paginasi
 * @var int                           $total        Total seluruh data
 * @var array<int,string>             $labOpts      Opsi lab (lokasi)
 * @var array                         $kategoriList Daftar kategori sah
 * @var array                         $kondisiList  Daftar kondisi sah
 * @var array                         $statusList   Daftar status sah
 */
$mk = static fn (array $list) => array_combine($list, array_map(static fn ($v) => ucfirst(str_replace('_', ' ', $v)), $list));
$opsiKategori = $mk($kategoriList);
$opsiKondisi  = $mk($kondisiList);
$opsiStatus   = $mk($statusList);

$warnaKondisi = [
    'baik'         => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'rusak_ringan' => 'bg-amber-50 text-amber-700 border-amber-200',
    'rusak_berat'  => 'bg-red-50 text-red-700 border-red-200',
];
$warnaStatus = [
    'tersedia'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'dipinjam'  => 'bg-sky-50 text-sky-700 border-sky-200',
    'perbaikan' => 'bg-amber-50 text-amber-700 border-amber-200',
    'dihapus'   => 'bg-slate-100 text-slate-500 border-slate-200',
];

$qsExport = array_filter([
    'lab_id'   => $labId ?: '',
    'kategori' => $kategori,
    'kondisi'  => $kondisi,
    'status'   => $status,
], static fn ($v) => $v !== '');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'aset',
    'helpTitle' => 'Master Aset / Inventaris',
    'helpBody'  => '<p>Seluruh barang/inventaris lab beserta <b>nomor aset</b>, <b>kondisi</b>, dan lokasinya (lab). Kosongkan Nomor Aset saat menambah untuk <b>penomoran otomatis</b> (format <i>KODELAB-KAT-001</i>).</p>
        <p class="mt-1">• Aset kategori <b>komputer/laptop</b> punya tombol <b>Detail Komputer</b> (spesifikasi CPU/RAM/OS, IP/MAC).<br>
        • <b>Import/Export</b> Excel tersedia — kolom <b>Kode Lab</b> diisi kode dari Master Laboratorium.<br>
        • Baris merah pada Sparepart menandai stok menipis; di sini <b>Status</b> otomatis berubah saat aset dipinjam/diperbaiki (P3–P4).</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/aset') ?>"
     data-entity="aset"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'nomor_aset' => '', 'nama' => '', 'kategori' => 'komputer', 'lab_id' => '',
         'merk' => '', 'spesifikasi' => '', 'tahun_pengadaan' => '', 'sumber_dana' => '',
         'harga' => '', 'kondisi' => 'baik', 'status' => 'tersedia', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/aset'),
        'searchPlaceholder' => 'Cari nomor aset, nama, atau merk...',
        'q'                 => $q,
        'per'               => $per,
        'filters'           => [
            ['name' => 'lab_id',   'value' => (string) ($labId ?: ''), 'all' => 'Semua lab',      'options' => $labOpts],
            ['name' => 'kategori', 'value' => $kategori,               'all' => 'Semua kategori', 'options' => $opsiKategori],
            ['name' => 'kondisi',  'value' => $kondisi,                'all' => 'Semua kondisi',  'options' => $opsiKondisi],
            ['name' => 'status',   'value' => $status,                 'all' => 'Semua status',   'options' => $opsiStatus],
        ],
        'exportUrl'   => site_url('admin/master/aset/export') . ($qsExport !== [] ? '?' . http_build_query($qsExport) : ''),
        'exportTitle' => 'Keluarkan data aset (mengikuti filter aktif) ke file Excel',
        'bulkUrl'     => site_url('admin/master/aset/bulk-delete'),
        'bulkLabel'   => 'aset',
    ]) ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Aset <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-40">Nomor Aset</th>
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold w-28">Kategori</th>
                        <th class="px-4 py-3 font-semibold w-36">Lab</th>
                        <th class="px-4 py-3 font-semibold w-28">Kondisi</th>
                        <th class="px-4 py-3 font-semibold w-24">Status</th>
                        <th class="px-4 py-3 font-semibold w-36 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' || $labId || $kategori !== '' || $kondisi !== '' || $status !== ''
                                ? 'Tidak ada aset yang cocok dengan filter ini.'
                                : 'Belum ada data aset. Tambah manual atau import Excel.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r):
                        $isKomputer = in_array($r['kategori'], ['komputer', 'laptop'], true); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-mono font-semibold text-brand-700"><?= esc($r['nomor_aset']) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['nama']) ?></div>
                                <?php if (! empty($r['merk'])): ?><div class="text-xs text-slate-400"><?= esc($r['merk']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= esc(ucfirst($r['kategori'])) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['lab_nama'] !== null ? esc($r['lab_nama']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaKondisi[$r['kondisi']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst(str_replace('_', ' ', $r['kondisi']))) ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaStatus[$r['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst($r['status'])) ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <a href="<?= site_url('admin/lab-gambar/aset/' . $r['id']) ?>" title="Foto" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </a>
                                    <?php if ($isKomputer): ?>
                                        <a href="<?= site_url('admin/master/aset/komputer/' . $r['id']) ?>" title="Detail Komputer"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-sky-600 hover:bg-sky-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        </a>
                                    <?php endif; ?>
                                    <?= view('admin/master/partials/row_actions', [
                                        'row'       => $r,
                                        'deleteUrl' => site_url('admin/master/aset/delete/' . $r['id']),
                                        'confirm'   => 'Hapus aset ini? Detail komputernya (bila ada) ikut terhapus.',
                                    ]) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'lab_id', 'kategori', 'kondisi', 'status', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Aset' : 'Edit Aset'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nomor Aset</label>
                        <input type="text" name="nomor_aset" x-model="form.nomor_aset" maxlength="50"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 outline-none">
                        <p class="text-xs text-slate-400 mt-1">Kosongkan untuk penomoran otomatis.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kategori</label>
                        <select name="kategori" x-model="form.kategori"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($opsiKategori as $val => $label): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Aset *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Lokasi (Lab)</label>
                        <select name="lab_id" x-model="form.lab_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tanpa lab —</option>
                            <?php foreach ($labOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Merk</label>
                        <input type="text" name="merk" x-model="form.merk" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Spesifikasi ringkas</label>
                        <input type="text" name="spesifikasi" x-model="form.spesifikasi" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tahun Pengadaan</label>
                        <input type="number" name="tahun_pengadaan" x-model="form.tahun_pengadaan" min="1990" max="2100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Sumber Dana</label>
                        <input type="text" name="sumber_dana" x-model="form.sumber_dana" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Harga (Rp)</label>
                        <input type="number" name="harga" x-model="form.harga" min="0" step="1"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kondisi</label>
                        <select name="kondisi" x-model="form.kondisi"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($opsiKondisi as $val => $label): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <select name="status" x-model="form.status"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($opsiStatus as $val => $label): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
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
        'importTitle'  => 'Import Aset',
        'importAction' => site_url('admin/master/aset/import-preview'),
        'templateUrl'  => site_url('admin/master/aset/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. <b>Nomor Aset</b> boleh dikosongkan (otomatis). Kolom <b>Kode Lab</b> diisi kode dari Master Laboratorium. Nomor aset yang sudah ada akan diperbarui.',
    ]) ?>
</div>

<?= $this->endSection() ?>
