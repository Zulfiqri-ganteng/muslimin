<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'kelas',
    'helpTitle' => 'Master Kelas / Rombel',
    'helpBody'  => '<p>Daftar rombongan belajar (mis. X TKJT 1). Tetapkan <b>tingkat</b> (X/XI/XII), <b>jurusan</b>, <b>wali kelas</b>, dan <b>shift</b>.</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, baris akan <b>ditambahkan otomatis</b> (nama kelas yang sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh kelas yang ada menjadi file Excel.<br>
        • <b>Hapus Terpilih / Hapus Semua</b> — centang baris atau hapus seluruh data sekaligus.</p>
        <p class="mt-1"><b>Shift</b> (Pagi/Siang) menentukan set jam pelajaran yang dipakai kelas tersebut saat penjadwalan.</p>',
]) ?>

<div x-data="kelasPage()">
    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <form method="get" class="flex flex-1 flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari nama kelas..."
                           class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                </div>
                <select name="tingkat" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="">Semua Tingkat</option>
                    <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                        <option value="<?= $t ?>" <?= $tingkat === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="shift" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="">Semua Shift</option>
                    <option value="pagi" <?= $shift === 'pagi' ? 'selected' : '' ?>>Pagi</option>
                    <option value="siang" <?= $shift === 'siang' ? 'selected' : '' ?>>Siang</option>
                </select>
                <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">Cari</button>
                <?php if ($q || $tingkat || $shift): ?>
                    <a href="<?= site_url('admin/master/kelas') ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 transition text-center">Reset</a>
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
                <a href="<?= site_url('admin/master/kelas/export') ?>" title="Keluarkan semua kelas ke file Excel" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
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
        <form method="post" action="<?= site_url('admin/master/kelas/bulk-delete') ?>" x-ref="bulkForm" class="hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="">
        </form>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Daftar Kelas <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" @change="toggleAll($event)" title="Pilih semua di halaman ini" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                        <th class="px-6 py-3 font-semibold">Nama Kelas</th>
                        <th class="px-6 py-3 font-semibold w-20">Tingkat</th>
                        <th class="px-6 py-3 font-semibold w-24">Jurusan</th>
                        <th class="px-6 py-3 font-semibold">Wali Kelas</th>
                        <th class="px-6 py-3 font-semibold w-24">Shift</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada kelas. Tambah manual atau import Excel.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>" @change="refresh()"></td>
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['nama_kelas']) ?></td>
                            <td class="px-6 py-3"><span class="inline-flex rounded bg-slate-100 text-slate-600 px-2 py-0.5 text-xs font-bold"><?= esc($r['tingkat']) ?></span></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['jurusan_kode'] ?: '—') ?></td>
                            <td class="px-6 py-3 text-slate-600"><?= esc($r['wali_nama'] ?: '—') ?></td>
                            <td class="px-6 py-3">
                                <?php if ($r['shift'] === 'pagi'): ?>
                                    <span class="inline-flex rounded-full bg-amber-100 text-amber-700 px-2.5 py-0.5 text-xs font-semibold">Pagi</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-indigo-100 text-indigo-700 px-2.5 py-0.5 text-xs font-semibold">Siang</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button @click='openEdit(<?= json_encode($r) ?>)' title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= site_url('admin/master/kelas/delete/' . $r['id']) ?>" onclick="return confirm('Hapus kelas ini?')" title="Hapus" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
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
            <div class="px-6 py-4 border-t border-slate-100"><?= $pager->only(['q', 'tingkat', 'shift'])->links('default', 'admin') ?></div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Kelas' : 'Edit Kelas'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Kelas *</label>
                        <input type="text" name="nama_kelas" x-model="form.nama_kelas" maxlength="50" required placeholder="contoh: X TKJT 1"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tingkat *</label>
                        <select name="tingkat" x-model="form.tingkat" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="X">X</option><option value="XI">XI</option><option value="XII">XII</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Shift *</label>
                        <select name="shift" x-model="form.shift" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="pagi">Pagi</option><option value="siang">Siang</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jurusan</label>
                        <select name="jurusan_id" x-model="form.jurusan_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <?php foreach ($jurusanOpts as $id => $label): ?><option value="<?= $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Wali Kelas</label>
                        <select name="wali_kelas_id" x-model="form.wali_kelas_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <?php foreach ($guruOpts as $id => $label): ?><option value="<?= $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Import -->
    <div x-show="importOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="importOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Import Kelas</h3>
            <form method="post" action="<?= site_url('admin/master/kelas/import') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <p class="text-sm text-slate-500 mb-3"><b>Import = memasukkan data.</b> Unggah Excel sesuai template — semua baris akan <b>ditambahkan</b> ke daftar. Nama kelas yang sudah ada akan diperbarui. Jurusan dicocokkan dari kode, wali dari kode/nama guru.</p>
                <input type="file" name="file" accept=".xlsx,.xls" required
                       class="w-full text-sm border border-slate-300 rounded-lg p-2 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700 file:font-semibold">
                <a href="<?= site_url('admin/master/kelas/template') ?>" class="inline-block mt-3 text-sm text-brand-600 hover:underline">⬇ Unduh template Excel</a>
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
function kelasPage() {
    return {
        open: false, importOpen: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/master/kelas') ?>',
        form: { nama_kelas:'', tingkat:'X', shift:'pagi', jurusan_id:'', wali_kelas_id:'' },
        // ---- hapus massal ----
        bulkSelected: 0,
        refresh() { this.bulkSelected = document.querySelectorAll('.row-check:checked').length; },
        toggleAll(e) { document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked); this.refresh(); },
        submitBulk(mode) {
            const form = this.$refs.bulkForm;
            form.querySelectorAll('input[name="ids[]"]').forEach(n => n.remove());
            if (mode === 'all') {
                if (!confirm('HAPUS SEMUA kelas? Tindakan ini tidak dapat dibatalkan.')) return;
                form.querySelector('[name=mode]').value = 'all';
            } else {
                const checked = [...document.querySelectorAll('.row-check:checked')];
                if (checked.length === 0) { alert('Centang dulu data yang ingin dihapus.'); return; }
                if (!confirm('Hapus ' + checked.length + ' kelas terpilih?')) return;
                checked.forEach(c => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=c.value; form.appendChild(i); });
                form.querySelector('[name=mode]').value = 'selected';
            }
            form.submit();
        },
        openAdd() { this.mode='add'; this.form={nama_kelas:'',tingkat:'X',shift:'pagi',jurusan_id:'',wali_kelas_id:''}; this.actionUrl=this.base; this.open=true; },
        openEdit(r) { this.mode='edit'; this.form={nama_kelas:r.nama_kelas,tingkat:r.tingkat,shift:r.shift,jurusan_id:r.jurusan_id||'',wali_kelas_id:r.wali_kelas_id||''}; this.actionUrl=this.base+'/'+r.id; this.open=true; },
    }
}
</script>
<?= $this->endSection() ?>
