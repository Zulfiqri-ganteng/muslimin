<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'hari',
    'helpTitle' => 'Master Hari',
    'helpBody'  => '<p>Hari yang berstatus <b>Aktif</b> akan muncul sebagai kolom pada grid Jadwal KBM nanti. Nonaktifkan hari yang tidak ada kegiatan belajar (mis. bila Sabtu libur). <b>Urutan</b> menentukan posisi tampil.</p>',
]) ?>

<div x-data="hariPage()">
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">Hari aktif akan tampil sebagai kolom pada grid jadwal.</p>
        <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Hari
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-6 py-3 font-semibold w-20">Urutan</th>
                    <th class="px-6 py-3 font-semibold">Nama Hari</th>
                    <th class="px-6 py-3 font-semibold w-28">Status</th>
                    <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400">Belum ada data hari.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50">
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
                            <button @click='openEdit(<?= json_encode($r) ?>)' class="text-brand-600 hover:text-brand-800 font-medium">Edit</button>
                            <a href="<?= site_url('admin/master/hari/delete/' . $r['id']) ?>" onclick="return confirm('Hapus hari ini?')" class="ml-3 text-red-500 hover:text-red-700 font-medium">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
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
        open: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/master/hari') ?>',
        form: { nama: '', urutan: 1, aktif: true },
        openAdd() { this.mode='add'; this.form={nama:'',urutan:1,aktif:true}; this.actionUrl=this.base; this.open=true; },
        openEdit(r) { this.mode='edit'; this.form={nama:r.nama, urutan:r.urutan, aktif: r.aktif==1}; this.actionUrl=this.base+'/'+r.id; this.open=true; },
    }
}
</script>
<?= $this->endSection() ?>
