<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jam',
    'helpTitle' => 'Master Jam Pelajaran',
    'helpBody'  => '<p>Slot waktu KBM per <b>shift</b> (Pagi & Siang dikelola terpisah lewat tab di atas). Durasi dihitung otomatis dari selisih waktu mulai–selesai.</p>
        <p class="mt-1">Centang <b>ISTIRAHAT</b> untuk baris jeda — baris ini muncul di jadwal tapi <b>tidak dihitung</b> sebagai jam mengajar.</p>',
]) ?>

<div x-data="jamPage()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <!-- Tab shift -->
        <div class="inline-flex rounded-lg border border-slate-300 bg-white p-1 text-sm font-semibold">
            <?php foreach (['pagi' => 'Pagi', 'siang' => 'Siang'] as $key => $label): ?>
                <a href="<?= site_url('admin/master/jam?shift=' . $key) ?>"
                   class="px-4 py-1.5 rounded-md transition <?= $shift === $key ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
        <button @click="openAdd()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Jam (<?= ucfirst($shift) ?>)
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-6 py-3 font-semibold w-24">Jam Ke</th>
                    <th class="px-6 py-3 font-semibold">Waktu</th>
                    <th class="px-6 py-3 font-semibold w-28">Durasi</th>
                    <th class="px-6 py-3 font-semibold w-32">Jenis</th>
                    <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">Belum ada jam untuk shift ini.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50 <?= $r['is_istirahat'] ? 'bg-amber-50/50' : '' ?>">
                        <td class="px-6 py-3 font-bold text-brand-700"><?= $r['is_istirahat'] ? '—' : esc($r['jam_ke']) ?></td>
                        <td class="px-6 py-3"><?= esc(substr($r['waktu_mulai'], 0, 5)) ?> – <?= esc(substr($r['waktu_selesai'], 0, 5)) ?></td>
                        <td class="px-6 py-3 text-slate-500"><?= esc($r['durasi']) ?> menit</td>
                        <td class="px-6 py-3">
                            <?php if ($r['is_istirahat']): ?>
                                <span class="inline-flex rounded-full bg-amber-100 text-amber-700 px-2.5 py-0.5 text-xs font-semibold">Istirahat</span>
                            <?php else: ?>
                                <span class="inline-flex rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-xs font-semibold">KBM</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <button @click='openEdit(<?= json_encode($r) ?>)' class="text-brand-600 hover:text-brand-800 font-medium">Edit</button>
                            <a href="<?= site_url('admin/master/jam/delete/' . $r['id']) ?>" onclick="return confirm('Hapus jam ini?')" class="ml-3 text-red-500 hover:text-red-700 font-medium">Hapus</a>
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
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Jam Pelajaran' : 'Edit Jam Pelajaran'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Shift</label>
                        <select name="shift" x-model="form.shift" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="pagi">Pagi</option>
                            <option value="siang">Siang</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jam Ke</label>
                        <input type="number" name="jam_ke" x-model="form.jam_ke" min="1" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Mulai</label>
                            <input type="time" name="waktu_mulai" x-model="form.waktu_mulai" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Selesai</label>
                            <input type="time" name="waktu_selesai" x-model="form.waktu_selesai" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="is_istirahat" value="1" x-model="form.is_istirahat" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Tandai sebagai jam ISTIRAHAT
                    </label>
                    <p class="text-xs text-slate-400">Durasi dihitung otomatis dari selisih waktu.</p>
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
function jamPage() {
    return {
        open: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/master/jam') ?>',
        form: { shift: '<?= $shift ?>', jam_ke: 1, waktu_mulai: '', waktu_selesai: '', is_istirahat: false },
        openAdd() { this.mode='add'; this.form={shift:'<?= $shift ?>', jam_ke:1, waktu_mulai:'', waktu_selesai:'', is_istirahat:false}; this.actionUrl=this.base; this.open=true; },
        openEdit(r) {
            this.mode='edit';
            this.form={ shift:r.shift, jam_ke:r.jam_ke, waktu_mulai:(r.waktu_mulai||'').substring(0,5), waktu_selesai:(r.waktu_selesai||'').substring(0,5), is_istirahat: r.is_istirahat==1 };
            this.actionUrl=this.base+'/'+r.id; this.open=true;
        },
    }
}
</script>
<?= $this->endSection() ?>
