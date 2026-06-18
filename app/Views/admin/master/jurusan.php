<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jurusan',
    'helpTitle' => 'Master Jurusan',
    'helpBody'  => '<p>Kompetensi keahlian sekolah (mis. TKJT, MPLB, AKL). Jurusan dipakai untuk mengelompokkan <b>Kelas</b> dan ditampilkan pada laporan/jadwal.</p>',
]) ?>

<div x-data="jurusanPage()">
    <!-- Header -->
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">Kelola jurusan/kompetensi keahlian sekolah.</p>
        <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Jurusan
        </button>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-6 py-3 font-semibold w-20">Kode</th>
                    <th class="px-6 py-3 font-semibold">Nama Jurusan</th>
                    <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="3" class="px-6 py-10 text-center text-slate-400">Belum ada data jurusan.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['kode']) ?></td>
                        <td class="px-6 py-3"><?= esc($r['nama']) ?></td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1">
                                <button @click='openEdit(<?= json_encode($r) ?>)' title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <a href="<?= site_url('admin/master/jurusan/delete/' . $r['id']) ?>" onclick="return confirm('Hapus jurusan ini?')" title="Hapus" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Jurusan' : 'Edit Jurusan'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode</label>
                        <input type="text" name="kode" x-model="form.kode" maxlength="20" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Jurusan</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="100" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                    </div>
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
function jurusanPage() {
    return {
        open: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/master/jurusan') ?>',
        form: { kode: '', nama: '' },
        openAdd() { this.mode='add'; this.form={kode:'',nama:''}; this.actionUrl=this.base; this.open=true; },
        openEdit(r) { this.mode='edit'; this.form={kode:r.kode, nama:r.nama}; this.actionUrl=this.base+'/'+r.id; this.open=true; },
    }
}
</script>
<?= $this->endSection() ?>
