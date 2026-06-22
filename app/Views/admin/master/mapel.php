<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
// Siapkan data untuk JS (aman: di-encode)
$guruJs = [];
foreach ($allGuru as $id => $label) {
    $guruJs[] = ['id' => (int) $id, 'label' => $label];
}
?>
<?= view('admin/partials/help', [
    'helpKey'   => 'mapel',
    'helpTitle' => 'Master Mata Pelajaran',
    'helpBody'  => '<p>Daftar mata pelajaran beserta <b>JP/minggu standar</b> (jumlah jam pelajaran yang dibutuhkan tiap minggu, mis. Pemrograman Dasar = 8 JP).</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, baris akan <b>ditambahkan otomatis</b> (kode mapel yang sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh mapel yang ada menjadi file Excel.<br>
        • <b>Hapus Terpilih / Hapus Semua</b> — centang baris atau hapus seluruh data sekaligus.</p>
        <p class="mt-1">Tombol <b>Atur Guru</b> menentukan guru mana saja yang <b>berkompetensi</b> mengajar mapel tersebut, dipakai saat membuat <b>Penugasan</b>.</p>',
]) ?>

<div x-data="mapelPage()">
    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <form method="get" class="flex flex-1 flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari nama atau kode mapel..."
                           class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                </div>
                <select name="kelompok" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="">Semua Kelompok</option>
                    <?php foreach ($kelompokList as $k): ?>
                        <option value="<?= esc($k) ?>" <?= $kelompok === $k ? 'selected' : '' ?>><?= esc($k) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">Cari</button>
                <?php if ($q || $kelompok): ?>
                    <a href="<?= site_url('admin/master/mapel') ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 transition text-center">Reset</a>
                <?php endif; ?>
            </form>
            <div class="flex flex-wrap gap-2">
                <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah
                </button>
                <button @click="importOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import
                </button>
                <a href="<?= site_url('admin/master/mapel/export') ?>" title="Keluarkan semua mata pelajaran ke file Excel" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </a>
                <button type="button" @click="submitBulk('selected')" x-show="bulkSelected>0" x-cloak class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Terpilih (<span x-text="bulkSelected"></span>)
                </button>
                <button type="button" @click="submitBulk('all')" class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Semua
                </button>
            </div>
        </div>
        <form method="post" action="<?= site_url('admin/master/mapel/bulk-delete') ?>" x-ref="bulkForm" class="hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="">
        </form>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
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
                                <button @click="openKompetensi(<?= (int) $r['id'] ?>, '<?= esc($r['nama_mapel'], 'js') ?>')"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                    <span class="inline-flex items-center justify-center rounded-full bg-brand-100 text-brand-700 w-5 h-5 text-[11px] font-bold"><?= $jml ?></span>
                                    Atur Guru
                                </button>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button @click='openEdit(<?= json_encode($r) ?>)' title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= site_url('admin/master/mapel/delete/' . $r['id']) ?>" onclick="return confirm('Hapus mapel ini?')" title="Hapus" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pager): ?>
            <div class="px-6 py-4 border-t border-slate-100"><?= $pager->only(['q', 'kelompok'])->links('default', 'admin') ?></div>
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

    <!-- Modal Kompetensi -->
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

    <!-- Modal Import -->
    <div x-show="importOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="importOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Import Mata Pelajaran</h3>
            <form method="post" action="<?= site_url('admin/master/mapel/import') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <p class="text-sm text-slate-500 mb-3"><b>Import = memasukkan data.</b> Unggah Excel sesuai template — semua baris akan <b>ditambahkan</b> ke daftar. Kode mapel yang sudah ada akan diperbarui (tidak dobel).</p>
                <input type="file" name="file" accept=".xlsx,.xls" required
                       class="w-full text-sm border border-slate-300 rounded-lg p-2 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700 file:font-semibold">
                <a href="<?= site_url('admin/master/mapel/template') ?>" class="inline-block mt-3 text-sm text-brand-600 hover:underline">⬇ Unduh template Excel</a>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="importOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5">Unggah & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function mapelPage() {
    return {
        open: false, importOpen: false, kompOpen: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/master/mapel') ?>',
        form: { kode_mapel:'', nama_mapel:'', kelompok:'', jp_default:2 },
        // ---- hapus massal ----
        bulkSelected: 0,
        refresh() { this.bulkSelected = document.querySelectorAll('.row-check:checked').length; },
        toggleAll(e) { document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked); this.refresh(); },
        submitBulk(mode) {
            const form = this.$refs.bulkForm;
            form.querySelectorAll('input[name="ids[]"]').forEach(n => n.remove());
            if (mode === 'all') {
                if (!confirm('HAPUS SEMUA mata pelajaran? Tindakan ini tidak dapat dibatalkan.')) return;
                form.querySelector('[name=mode]').value = 'all';
            } else {
                const checked = [...document.querySelectorAll('.row-check:checked')];
                if (checked.length === 0) { alert('Centang dulu data yang ingin dihapus.'); return; }
                if (!confirm('Hapus ' + checked.length + ' mapel terpilih?')) return;
                checked.forEach(c => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=c.value; form.appendChild(i); });
                form.querySelector('[name=mode]').value = 'selected';
            }
            form.submit();
        },
        // kompetensi
        allGuru: <?= json_encode($guruJs) ?>,
        kompMap: <?= json_encode($kompMap ?: new \stdClass()) ?>,
        selected: [], kompUrl: '', kompMapel: '',
        openAdd() { this.mode='add'; this.form={kode_mapel:'',nama_mapel:'',kelompok:'',jp_default:2}; this.actionUrl=this.base; this.open=true; },
        openEdit(r) { this.mode='edit'; this.form={kode_mapel:r.kode_mapel,nama_mapel:r.nama_mapel,kelompok:r.kelompok||'',jp_default:r.jp_default}; this.actionUrl=this.base+'/'+r.id; this.open=true; },
        openKompetensi(id, nama) {
            this.kompMapel = nama;
            this.kompUrl = this.base + '/kompetensi/' + id;
            this.selected = (this.kompMap[id] || []).map(Number);
            this.kompOpen = true;
        },
    }
}
</script>
<?= $this->endSection() ?>
