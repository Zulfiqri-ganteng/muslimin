<?php
/**
 * Halaman Master Laboratorium — pola tampilan mengikuti Master Jurusan (masterList).
 *
 * @var string                        $q           Kata kunci pencarian
 * @var string                        $jenis       Filter jenis aktif
 * @var int                           $per         Baris per halaman
 * @var array                         $rows        Baris lab halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var int                           $total       Total seluruh data
 * @var array<int,string>             $teknisiOpts Opsi teknisi (penanggung jawab)
 * @var array                         $jenisList   Daftar jenis sah
 */
$opsiJenis = [];
foreach ($jenisList as $j) {
    $opsiJenis[$j] = ucfirst($j);
}
$warnaJenis = [
    'komputer'   => 'bg-brand-50 text-brand-700 border-brand-200',
    'jaringan'   => 'bg-sky-50 text-sky-700 border-sky-200',
    'multimedia' => 'bg-violet-50 text-violet-700 border-violet-200',
    'lainnya'    => 'bg-slate-100 text-slate-500 border-slate-200',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'lab',
    'helpTitle' => 'Master Laboratorium',
    'helpBody'  => '<p>Data laboratorium sekolah (mis. Lab Komputer 1, Lab Jaringan). Tiap lab bisa punya <b>penanggung jawab</b> dari Master Teknisi.</p>
        <p class="mt-1">Lab menjadi <b>lokasi</b> bagi Aset/Inventaris dan acuan <b>Jadwal</b> serta <b>Jurnal</b> pemakaian lab. Tambahkan <b>Teknisi</b> lebih dulu bila ingin mengisi penanggung jawab.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/lab') ?>"
     data-entity="lab"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'jenis' => 'komputer',
         'ruang' => '', 'kapasitas' => '', 'teknisi_id' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/lab'),
        'searchPlaceholder' => 'Cari kode, nama, atau ruang lab...',
        'q'                 => $q,
        'per'               => $per,
        'filters'           => [
            ['name' => 'jenis', 'value' => $jenis, 'all' => 'Semua jenis', 'options' => $opsiJenis],
        ],
        'exportUrl'   => site_url('admin/master/lab/export'),
        'exportTitle' => 'Keluarkan semua laboratorium ke file Excel',
        'bulkUrl'     => site_url('admin/master/lab/bulk-delete'),
        'bulkLabel'   => 'laboratorium',
    ]) ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Laboratorium <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-24">Kode</th>
                        <th class="px-4 py-3 font-semibold">Nama Lab</th>
                        <th class="px-4 py-3 font-semibold w-28">Jenis</th>
                        <th class="px-4 py-3 font-semibold w-24">Ruang</th>
                        <th class="px-4 py-3 font-semibold w-20 text-center">Kapasitas</th>
                        <th class="px-4 py-3 font-semibold w-40">Penanggung Jawab</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' || $jenis !== '' ? 'Tidak ada lab yang cocok dengan filter ini.' : 'Belum ada data laboratorium. Tambah manual atau import Excel.' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-bold text-brand-700"><?= esc($r['kode']) ?></td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= esc($r['nama']) ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaJenis[$r['jenis']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst($r['jenis'])) ?></span>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['ruang'] !== null && $r['ruang'] !== '' ? esc($r['ruang']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-center text-slate-600"><?= $r['kapasitas'] !== null ? (int) $r['kapasitas'] : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['teknisi_nama'] !== null ? esc($r['teknisi_nama']) : '<span class="text-slate-300">Belum ditentukan</span>' ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="<?= site_url('admin/lab-gambar/lab/' . $r['id']) ?>" title="Foto" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition align-middle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </a>
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/lab/delete/' . $r['id']),
                                    'confirm'   => 'Hapus data laboratorium ini? Aset di dalamnya akan dilepas dari lab (tidak ikut terhapus).',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'jenis', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Laboratorium' : 'Edit Laboratorium'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode *</label>
                        <input type="text" name="kode" x-model="form.kode" maxlength="30" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jenis</label>
                        <select name="jenis" x-model="form.jenis"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($opsiJenis as $val => $label): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Lab *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Ruang</label>
                        <input type="text" name="ruang" x-model="form.ruang" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kapasitas</label>
                        <input type="number" name="kapasitas" x-model="form.kapasitas" min="0" max="1000"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Penanggung Jawab</label>
                        <select name="teknisi_id" x-model="form.teknisi_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Belum ditentukan —</option>
                            <?php foreach ($teknisiOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Diambil dari Master Teknisi.</p>
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
        'importTitle'  => 'Import Laboratorium',
        'importAction' => site_url('admin/master/lab/import-preview'),
        'templateUrl'  => site_url('admin/master/lab/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Kolom <b>Kode Teknisi</b> diisi kode dari Master Teknisi (boleh dikosongkan). Kode lab yang sudah ada akan diperbarui.',
    ]) ?>
</div>

<?= $this->endSection() ?>
