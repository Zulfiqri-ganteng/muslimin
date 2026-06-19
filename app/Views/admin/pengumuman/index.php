<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pengumuman',
    'helpTitle' => 'Pengumuman',
    'helpBody'  => '<p>Pengumuman yang berstatus <b>Aktif</b> akan tampil di beranda situs publik sekolah. Cocok untuk info libur, kegiatan, atau pengumuman jadwal.</p>',
]) ?>

<div x-data="pengPage()">
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">Kelola pengumuman yang tampil di beranda publik.</p>
        <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Pengumuman
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-6 py-3 font-semibold">Judul</th>
                    <th class="px-6 py-3 font-semibold w-28">Status</th>
                    <th class="px-6 py-3 font-semibold w-36">Tanggal</th>
                    <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400">Belum ada pengumuman.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3">
                            <p class="font-medium text-slate-800"><?= esc($r['judul']) ?></p>
                            <p class="text-xs text-slate-400 truncate max-w-md"><?= esc(mb_substr($r['isi'], 0, 80)) ?></p>
                        </td>
                        <td class="px-6 py-3">
                            <?php if ($r['is_aktif']): ?>
                                <span class="inline-flex rounded-full bg-green-100 text-green-700 px-2.5 py-0.5 text-xs font-semibold">Aktif</span>
                            <?php else: ?>
                                <span class="inline-flex rounded-full bg-slate-100 text-slate-500 px-2.5 py-0.5 text-xs font-semibold">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3 text-slate-500"><?= esc(date('d-m-Y', strtotime($r['created_at']))) ?></td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1">
                                <button @click='openEdit(<?= json_encode($r) ?>)' title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <a href="<?= site_url('admin/pengumuman/delete/' . $r['id']) ?>" onclick="return confirm('Hapus pengumuman ini?')" title="Hapus" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
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
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Pengumuman' : 'Edit Pengumuman'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Judul *</label>
                        <input type="text" name="judul" x-model="form.judul" maxlength="200" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Isi *</label>
                        <textarea name="isi" x-model="form.isi" rows="4" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="is_aktif" value="1" x-model="form.is_aktif" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Tampilkan di beranda publik (aktif)
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
function pengPage() {
    return {
        open: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/pengumuman') ?>',
        form: { judul: '', isi: '', is_aktif: true },
        openAdd() { this.mode='add'; this.form={judul:'',isi:'',is_aktif:true}; this.actionUrl=this.base; this.open=true; },
        openEdit(r) { this.mode='edit'; this.form={judul:r.judul,isi:r.isi,is_aktif:r.is_aktif==1}; this.actionUrl=this.base+'/'+r.id; this.open=true; },
    }
}
</script>
<?= $this->endSection() ?>
