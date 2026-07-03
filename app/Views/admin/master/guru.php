<?php
/**
 * Halaman Master Guru — acuan pola tampilan seluruh halaman master data.
 *
 * @var string                         $q          Kata kunci pencarian
 * @var string                         $status     Filter status kepegawaian
 * @var int                            $per        Baris per halaman
 * @var array                          $rows       Baris guru halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager      Paginasi
 * @var int                            $total      Total seluruh data
 * @var string[]                       $statusList Pilihan status kepegawaian
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'guru',
    'helpTitle' => 'Master Guru',
    'helpBody'  => '<p>Daftar semua guru pengajar. Tambah satu per satu, atau gunakan tombol di kanan atas:</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah file Excel, datanya akan <b>ditambahkan otomatis</b> ke daftar (kode guru yang sama akan diperbarui). Unduh template dulu agar kolomnya sesuai.<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh data guru yang ada saat ini menjadi file Excel.<br>
        • <b>Hapus Terpilih / Hapus Semua</b> — centang baris untuk menghapus sebagian, atau hapus seluruh data sekaligus.</p>
        <p class="mt-1"><b>Maks Beban</b> = batas jam mengajar (JP) per minggu untuk guru itu. Kompetensi guru diatur di menu <b>Mata Pelajaran ▸ Atur Guru</b>.</p>',
]) ?>

<div x-data="masterList"
     data-base="<?= site_url('admin/master/guru') ?>"
     data-entity="guru"
     data-defaults="<?= esc(json_encode(['kode_guru' => '', 'nip' => '', 'nama' => '', 'jenis_kelamin' => '', 'status_guru' => '', 'max_beban' => 24, 'keterangan' => '']), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/guru'),
        'searchPlaceholder' => 'Cari nama, kode, atau NIP...',
        'q'                 => $q,
        'filters'           => [[
            'name'    => 'status',
            'value'   => $status,
            'all'     => 'Semua Status',
            'options' => array_combine($statusList, $statusList),
        ]],
        'per'               => $per,
        'exportUrl'         => site_url('admin/master/guru/export'),
        'exportTitle'       => 'Keluarkan semua data guru ke file Excel',
        'bulkUrl'           => site_url('admin/master/guru/bulk-delete'),
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Guru <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" @change="toggleAll($event)" title="Pilih semua di halaman ini" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold w-16">Kode</th>
                        <th class="px-6 py-3 font-semibold">Nama Guru</th>
                        <th class="px-6 py-3 font-semibold">NIP</th>
                        <th class="px-6 py-3 font-semibold w-16">JK</th>
                        <th class="px-6 py-3 font-semibold w-20">Status</th>
                        <th class="px-6 py-3 font-semibold w-24">Maks JP</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">Belum ada data guru. Tambah manual, import Excel, atau impor dari data kesediaan.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>" @change="refresh()"></td>
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['kode_guru']) ?></td>
                            <td class="px-6 py-3 font-medium"><?= esc($r['nama']) ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['nip'] ?: '—') ?></td>
                            <td class="px-6 py-3"><?= esc($r['jenis_kelamin'] ?: '—') ?></td>
                            <td class="px-6 py-3"><?= $r['status_guru'] ? '<span class="inline-flex rounded-full bg-slate-100 text-slate-600 px-2 py-0.5 text-xs font-semibold">' . esc($r['status_guru']) . '</span>' : '—' ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['max_beban']) ?> JP</td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/guru/delete/' . $r['id']),
                                    'confirm'   => 'Hapus guru ini?',
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
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Guru' : 'Edit Guru'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode Guru *</label>
                        <input type="text" name="kode_guru" x-model="form.kode_guru" maxlength="20" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">NIP</label>
                        <input type="text" name="nip" x-model="form.nip" maxlength="60"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Guru *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" x-model="form.jenis_kelamin" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <select name="status_guru" x-model="form.status_guru" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <?php foreach ($statusList as $s): ?>
                                <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Maks Beban (JP/minggu)</label>
                        <input type="number" name="max_beban" x-model="form.max_beban" min="0" max="50"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" x-model="form.keterangan" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-3">Mata pelajaran yang diampu (kompetensi) diatur pada menu Mata Pelajaran / Pengampu.</p>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <?= view('admin/master/partials/modal_import', [
        'importTitle'  => 'Import Guru',
        'importAction' => site_url('admin/master/guru/import-preview'),
        'templateUrl'  => site_url('admin/master/guru/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah file Excel sesuai template. Setelah diunggah, data akan ditampilkan dalam <b>pratinjau yang bisa diedit langsung</b> sebelum benar-benar disimpan. Baris dengan <b>Kode Guru</b> yang sudah ada akan diperbarui (tidak dobel).',
        'importExtra'  => '<p class="text-sm text-slate-500 mb-2">Atau ambil otomatis dari data kesediaan yang sudah masuk:</p>
            <a href="' . site_url('admin/master/guru/import-kesediaan') . '" data-confirm="Impor semua guru dari Data Kesediaan? Yang sudah ada akan dilewati."
               class="inline-flex items-center gap-1.5 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold px-4 py-2.5">
                Impor dari Data Kesediaan
            </a>',
    ]) ?>
</div>

<?= $this->endSection() ?>
