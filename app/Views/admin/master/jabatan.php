<?php
/**
 * Halaman Master Jabatan — pola tampilan mengikuti Master Guru/Jurusan.
 *
 * @var string                        $q            Kata kunci pencarian
 * @var string                        $kategori     Filter kategori aktif
 * @var int                           $per          Baris per halaman
 * @var array                         $rows         Baris jabatan halaman ini
 * @var array<int,int>                $jumlahGuru   jabatan_id => jumlah penyandang
 * @var \CodeIgniter\Pager\Pager|null $pager        Paginasi
 * @var int                           $total        Total seluruh data
 * @var array                         $kategoriList Daftar kategori sah
 * @var array<int,string>             $indukOpts    Opsi induk jabatan
 * @var array<int,string>             $jurusanOpts  Opsi jurusan
 */
$labelKategori = [
    'struktural' => 'Struktural',
    'kurikulum'  => 'Kurikulum',
    'kesiswaan'  => 'Kesiswaan',
    'wali_kelas' => 'Wali Kelas',
    'mapel'      => 'Mata Pelajaran',
    'pembina'    => 'Pembina',
    'lainnya'    => 'Lainnya',
];
$opsiKategori = [];
foreach ($kategoriList as $k) {
    $opsiKategori[$k] = $labelKategori[$k] ?? $k;
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jabatan',
    'helpTitle' => 'Master Jabatan',
    'helpBody'  => '<p>Daftar jabatan yang disandang guru — <b>bebas dibuat sendiri</b> (Kurikulum, Guru MTK, Ketua Program, Pembina OSIS, dsb.). Satu guru boleh menyandang beberapa jabatan sekaligus; diatur di <b>Master Guru</b>.</p>
        <p class="mt-1">• <b>Induk Jabatan</b> membentuk hierarki (Kepala Sekolah → Wakil Kepala → Ketua Program).<br>
        • <b>Jurusan</b> diisi bila jabatan melekat pada satu jurusan, mis. Ketua Program TKJ.<br>
        • <b>Struktural</b> menandai jabatan yang <b>wajib hadir walau tidak punya jadwal mengajar</b> — penyandangnya otomatis muncul di panel <b>Kehadiran Kerja</b> halaman Absensi, termasuk hari Sabtu/Minggu.</p>
        <p class="mt-1">• <b>Import/Export</b> Excel tersedia. Menghapus jabatan tidak menghapus gurunya, hanya melepas jabatannya.</p>',
]) ?>

<div x-data="jabatanPage"
     data-base="<?= site_url('admin/master/jabatan') ?>"
     data-entity="jabatan"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'kategori' => 'lainnya',
         'parent_id' => '', 'jurusan_id' => '', 'level' => 5,
         'is_struktural' => false, 'keterangan' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/jabatan'),
        'searchPlaceholder' => 'Cari kode atau nama jabatan...',
        'q'                 => $q,
        'per'               => $per,
        'filters'           => [[
            'name'    => 'kategori',
            'value'   => $kategori,
            'all'     => 'Semua kategori',
            'options' => $opsiKategori,
        ]],
        'exportUrl'   => site_url('admin/master/jabatan/export'),
        'exportTitle' => 'Keluarkan semua jabatan ke file Excel',
        'bulkUrl'     => site_url('admin/master/jabatan/bulk-delete'),
        'bulkLabel'   => 'jabatan',
        'bulkWarn'    => 'Guru yang menyandangnya akan kehilangan jabatan tersebut.',
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Jabatan <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-28">Kode</th>
                        <th class="px-4 py-3 font-semibold">Nama Jabatan</th>
                        <th class="px-4 py-3 font-semibold w-32">Kategori</th>
                        <th class="px-4 py-3 font-semibold w-48">Induk</th>
                        <th class="px-4 py-3 font-semibold w-28">Jurusan</th>
                        <th class="px-4 py-3 font-semibold w-24 text-center">Guru</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">Belum ada data jabatan. Tambah manual atau import Excel.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-bold text-brand-700"><?= esc($r['kode']) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['nama']) ?></div>
                                <?php if (! empty($r['keterangan'])): ?>
                                    <div class="text-xs text-slate-400"><?= esc($r['keterangan']) ?></div>
                                <?php endif; ?>
                                <?php if (! empty($r['is_struktural'])): ?>
                                    <span class="inline-flex items-center gap-1 mt-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 text-[11px] font-semibold"
                                          title="Wajib hadir walau tidak ada jadwal mengajar — muncul otomatis di panel Kehadiran Kerja">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                        Struktural
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 text-slate-600 px-2.5 py-1 text-xs font-medium"><?= esc($labelKategori[$r['kategori']] ?? $r['kategori']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['induk_nama'] !== null ? esc($r['induk_nama']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['jurusan_kode'] !== null ? esc($r['jurusan_kode']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-center">
                                <?php $n = $jumlahGuru[(int) $r['id']] ?? 0; ?>
                                <span class="<?= $n > 0 ? 'text-slate-700 font-semibold' : 'text-slate-300' ?>"><?= $n ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/jabatan/delete/' . $r['id']),
                                    'confirm'   => 'Hapus jabatan ini? Guru yang menyandangnya akan kehilangan jabatan ini.',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'kategori', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Jabatan' : 'Edit Jabatan'"></h3>
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
                        <select name="kategori" x-model="form.kategori"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <?php foreach ($opsiKategori as $val => $label): ?>
                                <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Jabatan *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Induk Jabatan</label>
                        <select name="parent_id" x-model="form.parent_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tanpa induk —</option>
                            <?php foreach ($indukOpts as $id => $nama): ?>
                                <!-- jabatan tak boleh menjadi induk bagi dirinya sendiri -->
                                <option value="<?= (int) $id ?>" x-show="String(form.id) !== '<?= (int) $id ?>'"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Membentuk hierarki jabatan.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jurusan</label>
                        <select name="jurusan_id" x-model="form.jurusan_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Tidak terkait jurusan —</option>
                            <?php foreach ($jurusanOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Mis. Ketua Program TKJ.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Level</label>
                        <input type="number" name="level" x-model="form.level" min="1" max="99"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <p class="text-xs text-slate-400 mt-1">1 = paling tinggi, untuk urutan tampil.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 cursor-pointer">
                            <input type="hidden" name="is_struktural" value="0">
                            <input type="checkbox" name="is_struktural" value="1" x-model="form.is_struktural"
                                   class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-sm">
                                <span class="font-medium text-slate-700">Jabatan struktural</span>
                                <span class="block text-xs text-slate-500">Wajib hadir walau tidak punya jadwal mengajar. Penyandangnya otomatis muncul di panel <b>Kehadiran Kerja</b> pada halaman Absensi, termasuk Sabtu/Minggu.</span>
                            </span>
                        </label>
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
        'importTitle'  => 'Import Jabatan',
        'importAction' => site_url('admin/master/jabatan/import-preview'),
        'templateUrl'  => site_url('admin/master/jabatan/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sudah ada akan diperbarui. Kolom <b>Kode Induk</b> & <b>Kode Jurusan</b> diisi kode yang sudah terdaftar.',
    ]) ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/jabatan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/jabatan.js') ?>"></script>
<?= $this->endSection() ?>
