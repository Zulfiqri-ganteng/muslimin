<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
$guruJs = [];
foreach ($guruOpts as $id => $label) {
    $guruJs[] = ['id' => (int) $id, 'label' => $label];
}
?>
<?= view('admin/partials/help', [
    'helpKey'   => 'pengampu',
    'helpTitle' => 'Penugasan Mengajar (Pengampu) — langkah kunci sebelum penjadwalan',
    'helpBody'  => '<p>Di sinilah ditetapkan: <b>untuk satu kelas, mapel apa diajar oleh siapa dan berapa JP/minggu.</b> Pilih kelas dulu di atas, lalu tambah penugasannya.</p>
        <p class="mt-1">Pilihan guru otomatis <b>disaring sesuai kompetensi</b> (dari menu Mata Pelajaran), dan JP terisi otomatis dari standar mapel (bisa diubah). <b>Total JP Kelas</b> dihitung otomatis. Data inilah yang nanti mengisi sel-sel jadwal & menjadi acuan kuota JP.</p>',
]) ?>

<div x-data="pengampuPage()">
    <!-- Pemilih kelas + ringkasan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <form method="get" class="flex flex-1 items-center gap-2">
                <label class="text-sm font-semibold text-slate-600 shrink-0">Kelas:</label>
                <select name="kelas_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none min-w-[200px]">
                    <?php if (empty($kelasOpts)): ?>
                        <option value="">— belum ada kelas —</option>
                    <?php else: foreach ($kelasOpts as $id => $label): ?>
                        <option value="<?= $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </form>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-xs text-slate-400">Total JP Kelas</p>
                    <p class="text-2xl font-extrabold text-brand-700"><?= $totalJp ?> <span class="text-sm font-medium text-slate-400">JP/minggu</span></p>
                </div>
                <?php if ($kelasId): ?>
                    <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tambah Penugasan
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabel penugasan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold w-20">Kode</th>
                        <th class="px-6 py-3 font-semibold">Mata Pelajaran</th>
                        <th class="px-6 py-3 font-semibold">Guru Pengampu</th>
                        <th class="px-6 py-3 font-semibold w-24">JP</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400"><?= $kelasId ? 'Belum ada penugasan untuk kelas ini.' : 'Pilih kelas terlebih dahulu (atau tambahkan kelas).' ?></td></tr>
                    <?php else: foreach ($rows as $r): $beda = (int) $r['jp'] !== (int) $r['jp_default']; ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['kode_mapel']) ?></td>
                            <td class="px-6 py-3 font-medium"><?= esc($r['nama_mapel']) ?></td>
                            <td class="px-6 py-3 text-slate-600"><span class="text-slate-400"><?= esc($r['kode_guru']) ?></span> · <?= esc($r['guru_nama']) ?></td>
                            <td class="px-6 py-3">
                                <span class="font-semibold"><?= esc($r['jp']) ?> JP</span>
                                <?php if ($beda): ?><span class="ml-1 text-xs text-amber-600" title="Standar mapel <?= esc($r['jp_default']) ?> JP">(std <?= esc($r['jp_default']) ?>)</span><?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button @click='openEdit(<?= json_encode($r) ?>)' title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= site_url('admin/master/pengampu/delete/' . $r['id']) ?>" onclick="return confirm('Hapus penugasan ini?')" title="Hapus" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
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
<script>
function pengampuPage() {
    return {
        open: false, editOpen: false,
        base: '<?= site_url('admin/master/pengampu') ?>',
        allGuru: <?= json_encode($guruJs) ?>,
        kompMap: <?= json_encode($kompMap ?: new \stdClass()) ?>,
        mapelJp: <?= json_encode($mapelJp ?: new \stdClass()) ?>,
        form: { mapel_id:'', guru_id:'', jp:2 },
        eform: { guru_id:'', jp:2 }, editUrl:'', editMapel:'', editGuru: [],
        get filteredGuru() {
            const ids = this.kompMap[this.form.mapel_id];
            if (!this.form.mapel_id || !ids || ids.length === 0) return this.allGuru;
            return this.allGuru.filter(g => ids.includes(g.id));
        },
        onMapelChange() {
            this.form.jp = this.mapelJp[this.form.mapel_id] || 2;
            this.form.guru_id = '';
        },
        openAdd() { this.form = { mapel_id:'', guru_id:'', jp:2 }; this.open = true; },
        openEdit(r) {
            this.editUrl = this.base + '/' + r.id;
            this.editMapel = r.kode_mapel + ' — ' + r.nama_mapel;
            this.editGuru = this.allGuru; // edit boleh pilih guru manapun
            this.eform = { guru_id: Number(r.guru_id), jp: Number(r.jp) };
            this.editOpen = true;
        },
    }
}
</script>
<?= $this->endSection() ?>
