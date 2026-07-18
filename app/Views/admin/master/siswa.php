<?php
/**
 * Halaman Master Siswa — pola tampilan mengikuti Master Guru/Jabatan.
 *
 * @var string                        $q           Kata kunci pencarian
 * @var int                           $kelasId     Filter kelas aktif
 * @var string                        $tingkat     Filter tingkat aktif
 * @var string                        $status      Filter status aktif
 * @var int                           $per         Baris per halaman
 * @var array                         $rows        Baris siswa halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var int                           $total       Total seluruh data (sesuai filter)
 * @var array<int,string>             $kelasOpts   Opsi kelas
 * @var array                         $tingkatList Daftar tingkat sah
 * @var array                         $statusList  Daftar status sah
 */
$warnaStatus = [
    'aktif'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'lulus'  => 'bg-blue-50 text-blue-700 border-blue-200',
    'pindah' => 'bg-amber-50 text-amber-700 border-amber-200',
    'keluar' => 'bg-slate-100 text-slate-500 border-slate-200',
];
$opsiStatus  = array_combine($statusList, array_map('ucfirst', $statusList));
$opsiTingkat = array_combine($tingkatList, array_map(static fn ($t) => 'Kelas ' . $t, $tingkatList));

// URL export ikut membawa filter yang sedang aktif.
$qsExport = array_filter([
    'kelas_id' => $kelasId ?: '',
    'tingkat'  => $tingkat,
    'status'   => $status,
], static fn ($v) => $v !== '');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'siswa',
    'helpTitle' => 'Master Siswa',
    'helpBody'  => '<p>Data siswa lengkap (identitas, kelas, orang tua/wali). Tingkat dan jurusan <b>mengikuti kelas</b> siswa, jadi cukup pilih kelasnya saja.</p>
        <p class="mt-1">• <b>Import</b> — cara tercepat memasukkan siswa sekelas sekaligus: unduh template, isi di Excel, unggah, periksa di pratinjau, simpan. NIS yang sudah ada akan <b>diperbarui</b>, bukan diduplikat.<br>
        • <b>Export</b> — mengikuti filter yang sedang aktif, jadi bisa mengunduh satu kelas saja.<br>
        • Kolom <b>Kelas</b> pada file impor diisi <b>nama kelas</b> persis seperti di Master Kelas (mis. <i>X TKJ 1</i>).</p>
        <p class="mt-1">• Status <b>Aktif</b> yang dihitung pada grafik jumlah siswa di halaman publik. Siswa lulus/pindah/keluar tetap tersimpan sebagai arsip.</p>',
]) ?>

<div x-data="siswaPage"
     data-base="<?= site_url('admin/master/siswa') ?>"
     data-entity="siswa"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'nis' => '', 'nisn' => '', 'nama' => '', 'jenis_kelamin' => '',
         'tempat_lahir' => '', 'tanggal_lahir' => '', 'agama' => '', 'alamat' => '',
         'no_hp' => '', 'nama_wali' => '', 'no_hp_wali' => '', 'kelas_id' => '',
         'tahun_masuk' => date('Y'), 'status' => 'aktif', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'           => site_url('admin/master/siswa'),
        'searchPlaceholder' => 'Cari nama, NIS, atau NISN...',
        'q'                 => $q,
        'per'               => $per,
        'filters'           => [
            ['name' => 'kelas_id', 'value' => (string) ($kelasId ?: ''), 'all' => 'Semua kelas',   'options' => $kelasOpts],
            ['name' => 'tingkat',  'value' => $tingkat,                  'all' => 'Semua tingkat', 'options' => $opsiTingkat],
            ['name' => 'status',   'value' => $status,                   'all' => 'Semua status',  'options' => $opsiStatus],
        ],
        'exportUrl'   => site_url('admin/master/siswa/export') . ($qsExport !== [] ? '?' . http_build_query($qsExport) : ''),
        'exportTitle' => 'Keluarkan data siswa (mengikuti filter aktif) ke file Excel',
        'bulkUrl'     => site_url('admin/master/siswa/bulk-delete'),
        'bulkLabel'   => 'siswa',
    ]) ?>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Siswa <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-4 py-3 font-semibold w-32">NIS</th>
                        <th class="px-4 py-3 font-semibold">Nama Siswa</th>
                        <th class="px-4 py-3 font-semibold w-14 text-center">JK</th>
                        <th class="px-4 py-3 font-semibold w-32">Kelas</th>
                        <th class="px-4 py-3 font-semibold w-24">Jurusan</th>
                        <th class="px-4 py-3 font-semibold w-24">Status</th>
                        <th class="px-4 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">
                            <?= $q !== '' || $kelasId || $tingkat !== '' || $status !== ''
                                ? 'Tidak ada siswa yang cocok dengan filter ini.'
                                : 'Belum ada data siswa. Tambah manual atau import Excel (lebih cepat untuk satu kelas sekaligus).' ?>
                        </td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-4 py-3 font-semibold text-brand-700"><?= esc($r['nis']) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800"><?= esc($r['nama']) ?></div>
                                <?php if (! empty($r['nisn'])): ?>
                                    <div class="text-xs text-slate-400">NISN <?= esc($r['nisn']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-600"><?= $r['jenis_kelamin'] !== null ? esc($r['jenis_kelamin']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= $r['nama_kelas'] !== null ? esc($r['nama_kelas']) : '<span class="text-slate-300">Belum ada kelas</span>' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['jurusan_kode'] !== null ? esc($r['jurusan_kode']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $warnaStatus[$r['status']] ?? 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= esc(ucfirst($r['status'])) ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?= view('admin/master/partials/row_actions', [
                                    'row'       => $r,
                                    'deleteUrl' => site_url('admin/master/siswa/delete/' . $r['id']),
                                    'confirm'   => 'Hapus data siswa ini?',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'kelas_id', 'tingkat', 'status', 'per'])->links('default', 'admin') ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Siswa' : 'Edit Siswa'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Identitas</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">NIS *</label>
                        <input type="text" name="nis" x-model="form.nis" maxlength="30" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">NISN</label>
                        <input type="text" name="nisn" x-model="form.nisn" maxlength="30"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" x-model="form.jenis_kelamin"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Pilih —</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Agama</label>
                        <input type="text" name="agama" x-model="form.agama" maxlength="25" list="daftar-agama"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        <datalist id="daftar-agama">
                            <?php foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $a): ?>
                                <option value="<?= $a ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" x-model="form.tempat_lahir" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" x-model="form.tanggal_lahir"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Alamat</label>
                        <input type="text" name="alamat" x-model="form.alamat" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mt-5 mb-2">Kontak &amp; Orang Tua/Wali</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">No HP Siswa</label>
                        <input type="text" name="no_hp" x-model="form.no_hp" maxlength="25"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">No HP Orang Tua/Wali</label>
                        <input type="text" name="no_hp_wali" x-model="form.no_hp_wali" maxlength="25"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Orang Tua/Wali</label>
                        <input type="text" name="nama_wali" x-model="form.nama_wali" maxlength="150"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>

                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mt-5 mb-2">Kelas &amp; Status</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kelas</label>
                        <select name="kelas_id" x-model="form.kelas_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— Belum ada kelas —</option>
                            <?php foreach ($kelasOpts as $id => $nama): ?>
                                <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Tingkat &amp; jurusan mengikuti kelas.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tahun Masuk</label>
                        <input type="number" name="tahun_masuk" x-model="form.tahun_masuk" min="1990" max="2100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
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
                    <div class="sm:col-span-3">
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
        'importTitle'  => 'Import Siswa',
        'importAction' => site_url('admin/master/siswa/import-preview'),
        'templateUrl'  => site_url('admin/master/siswa/template'),
        'importNote'   => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template — cocok untuk memasukkan satu kelas (mis. 50 siswa) sekaligus. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. <b>NIS</b> yang sudah ada akan diperbarui, bukan diduplikat. Kolom <b>Kelas</b> diisi nama kelas persis seperti di Master Kelas.',
    ]) ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/siswa.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/siswa.js') ?>"></script>
<?= $this->endSection() ?>
