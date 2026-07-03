<?php
/**
 * Halaman Penugasan Mengajar (Pengampu) — pola tampilan mengikuti Master Guru.
 *
 * @var array $kelasOpts Opsi kelas (id => label)
 * @var int   $kelasId   Kelas terpilih
 * @var array $rows      Penugasan kelas terpilih
 * @var int   $totalJp   Total JP kelas terpilih
 * @var array $mapelOpts Opsi mapel (id => label)
 * @var array $mapelJp   JP standar per mapel (id => jp)
 * @var array $guruOpts  Opsi guru (id => label)
 * @var array $kompMap   Peta kompetensi (mapel_id => [guru_id])
 */
$guruJs = [];
foreach ($guruOpts as $id => $label) {
    $guruJs[] = ['id' => (int) $id, 'label' => $label];
}

// Sisi kiri toolbar: pemilih kelas (menggantikan kolom pencarian).
ob_start(); ?>
<form method="get" class="flex flex-1 items-center gap-2">
    <label class="text-sm font-semibold text-slate-600 shrink-0">Kelas:</label>
    <select name="kelas_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none min-w-[200px]">
        <option value="" <?= $kelasId === 0 ? 'selected' : '' ?>><?= empty($kelasOpts) ? '— belum ada kelas —' : '— pilih kelas —' ?></option>
        <?php foreach ($kelasOpts as $id => $label): ?>
            <option value="<?= $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<?php $leftHtml = ob_get_clean(); ?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pengampu',
    'helpTitle' => 'Penugasan Mengajar (Pengampu) — langkah kunci sebelum penjadwalan',
    'helpBody'  => '<p>Di sinilah ditetapkan: <b>untuk satu kelas, mapel apa diajar oleh siapa dan berapa JP/minggu.</b> Pilih kelas dulu di atas, lalu tambah penugasannya.</p>
        <p class="mt-1">Pilihan guru otomatis <b>disaring sesuai kompetensi</b> (dari menu Mata Pelajaran), dan JP terisi otomatis dari standar mapel (bisa diubah). <b>Total JP Kelas</b> dihitung otomatis. Data inilah yang nanti mengisi sel-sel jadwal &amp; menjadi acuan kuota JP.</p>',
]) ?>

<div x-data="pengampuPage"
     data-base="<?= site_url('admin/master/pengampu') ?>"
     data-all-guru="<?= esc(json_encode($guruJs), 'attr') ?>"
     data-komp-map="<?= esc(json_encode($kompMap ?: new \stdClass()), 'attr') ?>"
     data-mapel-jp="<?= esc(json_encode($mapelJp ?: new \stdClass()), 'attr') ?>">

    <?= view('admin/master/partials/toolbar', [
        'baseUrl'     => site_url('admin/master/pengampu'),
        'leftHtml'    => $leftHtml,
        'showAdd'     => $kelasId > 0,
        'showImport'  => false,
        'exportUrl'   => site_url('admin/master/pengampu/export'),
        'exportTitle' => 'Keluarkan seluruh penugasan (semua kelas) ke file Excel',
        'bulkUrl'     => site_url('admin/master/pengampu/bulk-delete'),
        'bulkHidden'  => ['kelas_id' => $kelasId],
        'bulkLabel'   => 'penugasan pada kelas ini',
        'bulkWarn'    => 'Jadwal yang memakai penugasan tersebut ikut terhapus.',
    ]) ?>

    <!-- Tabel penugasan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Penugasan <span class="text-slate-400 font-normal">(<?= count($rows) ?>)</span></h2>
            <p class="text-sm text-slate-500">Total JP Kelas: <span class="font-extrabold text-brand-700 text-lg"><?= $totalJp ?></span> <span class="text-slate-400">JP/minggu</span></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" title="Pilih semua di halaman ini" class="js-check-all rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold w-20">Kode</th>
                        <th class="px-6 py-3 font-semibold">Mata Pelajaran</th>
                        <th class="px-6 py-3 font-semibold">Guru Pengampu</th>
                        <th class="px-6 py-3 font-semibold w-24">JP</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400"><?= $kelasId ? 'Belum ada penugasan untuk kelas ini.' : 'Pilih kelas terlebih dahulu (atau tambahkan kelas).' ?></td></tr>
                    <?php else: foreach ($rows as $r): $beda = (int) $r['jp'] !== (int) $r['jp_default']; ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>"></td>
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['kode_mapel']) ?></td>
                            <td class="px-6 py-3 font-medium"><?= esc($r['nama_mapel']) ?></td>
                            <td class="px-6 py-3 text-slate-600"><span class="text-slate-400"><?= esc($r['kode_guru']) ?></span> · <?= esc($r['guru_nama']) ?></td>
                            <td class="px-6 py-3">
                                <span class="font-semibold"><?= esc($r['jp']) ?> JP</span>
                                <?php if ($beda): ?><span class="ml-1 text-xs text-amber-600" title="Standar mapel <?= esc($r['jp_default'], 'attr') ?> JP">(std <?= esc($r['jp_default']) ?>)</span><?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button type="button" @click="openEditEl($el)" data-row="<?= esc(json_encode($r), 'attr') ?>" title="Edit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= site_url('admin/master/pengampu/delete/' . $r['id']) ?>" data-confirm="Hapus penugasan ini? Jadwal yang memakainya ikut terhapus." title="Hapus"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Tambah Penugasan</h3>
            <form method="post" action="<?= site_url('admin/master/pengampu') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mata Pelajaran *</label>
                        <select name="mapel_id" x-model.number="form.mapel_id" @change="onMapelChange()" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— pilih mapel —</option>
                            <?php foreach ($mapelOpts as $id => $label): ?><option value="<?= $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Guru Pengampu *</label>
                        <select name="guru_id" x-model.number="form.guru_id" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">— pilih guru —</option>
                            <template x-for="g in filteredGuru" :key="g.id">
                                <option :value="g.id" x-text="g.label"></option>
                            </template>
                        </select>
                        <p class="text-xs text-slate-400 mt-1" x-show="form.mapel_id && filteredGuru.length < allGuru.length">Hanya menampilkan guru berkompetensi mapel ini.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jumlah JP / Minggu *</label>
                        <input type="number" name="jp" x-model.number="form.jp" min="1" max="50" required
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

    <!-- Modal Edit -->
    <div x-show="editOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="editOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-1">Edit Penugasan</h3>
            <p class="text-sm text-slate-500 mb-4" x-text="editMapel"></p>
            <form method="post" :action="editUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Guru Pengampu *</label>
                        <select name="guru_id" x-model.number="eform.guru_id" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <template x-for="g in editGuru" :key="g.id">
                                <option :value="g.id" x-text="g.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jumlah JP / Minggu *</label>
                        <input type="number" name="jp" x-model.number="eform.jp" min="1" max="50" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="editOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/pengampu.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/pengampu.js') ?>"></script>
<?= $this->endSection() ?>
