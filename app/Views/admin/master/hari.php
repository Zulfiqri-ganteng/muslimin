<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'hari',
    'helpTitle' => 'Master Hari',
    'helpBody'  => '<p>Hari yang berstatus <b>Aktif</b> akan muncul sebagai kolom pada grid Jadwal KBM nanti. Nonaktifkan hari yang tidak ada kegiatan belajar (mis. bila Sabtu libur). <b>Urutan</b> menentukan posisi tampil.</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, tampil pratinjau yang bisa diedit sebelum disimpan (nama hari sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — unduh seluruh hari ke Excel. • <b>Hapus Terpilih / Hapus Semua</b> tersedia.</p>',
]) ?>

<div x-data="hariPage()">
    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-sm text-slate-500">Hari aktif akan tampil sebagai kolom pada grid jadwal.</p>
            <div class="flex flex-wrap gap-2">
                <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah
                </button>
                <button @click="importOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Import
                </button>
                <a href="<?= site_url('admin/master/hari/export') ?>" title="Keluarkan semua hari ke file Excel" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
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
        <form method="post" action="<?= site_url('admin/master/hari/bulk-delete') ?>" x-ref="bulkForm" class="hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="">
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="pl-6 pr-2 py-3 w-10"><input type="checkbox" @change="toggleAll($event)" title="Pilih semua" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></th>
                    <th class="px-6 py-3 font-semibold w-20">Urutan</th>
                    <th class="px-6 py-3 font-semibold">Nama Hari</th>
                    <th class="px-6 py-3 font-semibold w-28">Status</th>
                    <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada data hari.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="pl-6 pr-2 py-3"><input type="checkbox" class="row-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" value="<?= (int) $r['id'] ?>" @change="refresh()"></td>
                        <td class="px-6 py-3 text-slate-500"><?= esc($r['urutan']) ?></td>
                        <td class="px-6 py-3 font-medium"><?= esc($r['nama']) ?></td>
                        <td class="px-6 py-3">
                            <?php if ($r['aktif']): ?>
                                <span class="inline-flex items-center rounded-full bg-green-100 text-green-700 px-2.5 py-0.5 text-xs font-semibold">Aktif</span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-500 px-2.5 py-0.5 text-xs font-semibold">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1">
                                <button @click='openEdit(<?= json_encode($r) ?>)' title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <a href="<?= site_url('admin/master/hari/delete/' . $r['id']) ?>" onclick="return confirm('Hapus hari ini?')" title="Hapus" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Import -->
    <div x-show="importOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="importOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4">Import Hari</h3>
            <form method="post" action="<?= site_url('admin/master/hari/import-preview') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <p class="text-sm text-slate-500 mb-3"><b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Nama hari yang sudah ada akan diperbarui.</p>
                <input type="file" name="file" accept=".xlsx,.xls" required
                       class="w-full text-sm border border-slate-300 rounded-lg p-2 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700 file:font-semibold">
                <a href="<?= site_url('admin/master/hari/template') ?>" class="inline-block mt-3 text-sm text-brand-600 hover:underline">⬇ Unduh template Excel</a>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="importOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5">Unggah & Pratinjau</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Hari' : 'Edit Hari'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Hari</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="15" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Urutan</label>
                        <input type="number" name="urutan" x-model="form.urutan" min="1" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="aktif" value="1" x-model="form.aktif" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Aktif (tampil di grid jadwal)
                    </label>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="open=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function hariPage() {
    return {
        open: false, importOpen: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/master/hari') ?>',
        form: { nama: '', urutan: 1, aktif: true },
        openAdd() { this.mode='add'; this.form={nama:'',urutan:1,aktif:true}; this.actionUrl=this.base; this.open=true; },
        openEdit(r) { this.mode='edit'; this.form={nama:r.nama, urutan:r.urutan, aktif: r.aktif==1}; this.actionUrl=this.base+'/'+r.id; this.open=true; },
        // ---- hapus massal ----
        bulkSelected: 0,
        refresh() { this.bulkSelected = document.querySelectorAll('.row-check:checked').length; },
        toggleAll(e) { document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked); this.refresh(); },
        submitBulk(mode) {
            const form = this.$refs.bulkForm;
            form.querySelectorAll('input[name="ids[]"]').forEach(n => n.remove());
            if (mode === 'all') {
                if (!confirm('HAPUS SEMUA hari? Tindakan ini tidak dapat dibatalkan.')) return;
                form.querySelector('[name=mode]').value = 'all';
            } else {
                const checked = [...document.querySelectorAll('.row-check:checked')];
                if (checked.length === 0) { alert('Centang dulu data yang ingin dihapus.'); return; }
                if (!confirm('Hapus ' + checked.length + ' hari terpilih?')) return;
                checked.forEach(c => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=c.value; form.appendChild(i); });
                form.querySelector('[name=mode]').value = 'selected';
            }
            form.submit();
        },
    }
}
</script>
<?= $this->endSection() ?>
