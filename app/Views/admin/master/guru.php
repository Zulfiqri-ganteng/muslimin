<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'guru',
    'helpTitle' => 'Master Guru',
    'helpBody'  => '<p>Daftar semua guru pengajar. Tambah satu per satu, <b>Import</b> dari Excel (unduh template dulu), atau <b>Impor dari Data Kesediaan</b> yang sudah masuk.</p>
        <p class="mt-1"><b>Maks Beban</b> = batas jam mengajar (JP) per minggu untuk guru itu — dipakai untuk menandai guru yang kelebihan/kekurangan jam. Mata pelajaran yang dikuasai guru (kompetensi) diatur di menu <b>Mata Pelajaran ▸ Atur Guru</b>.</p>',
]) ?>

<div x-data="guruPage()">
    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <form method="get" class="flex flex-1 flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Cari nama, kode, atau NIP..."
                           class="w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                </div>
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    <option value="">Semua Status</option>
                    <?php foreach (['PNS', 'PPPK', 'GTY', 'GTT'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">Cari</button>
                <?php if ($q || $status): ?>
                    <a href="<?= site_url('admin/master/guru') ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50 transition text-center">Reset</a>
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
                <a href="<?= site_url('admin/master/guru/export') ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </a>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Daftar Guru <span class="text-slate-400 font-normal">(<?= $total ?>)</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-6 py-3 font-semibold w-16">Kode</th>
                        <th class="px-6 py-3 font-semibold">Nama Guru</th>
                        <th class="px-6 py-3 font-semibold">NIP</th>
                        <th class="px-6 py-3 font-semibold w-16">JK</th>
                        <th class="px-6 py-3 font-semibold w-20">Status</th>
                        <th class="px-6 py-3 font-semibold w-24">Maks JP</th>
                        <th class="px-6 py-3 font-semibold w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">Belum ada data guru. Tambah manual, import Excel, atau impor dari data kesediaan.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-bold text-brand-700"><?= esc($r['kode_guru']) ?></td>
                            <td class="px-6 py-3 font-medium"><?= esc($r['nama']) ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['nip'] ?: '—') ?></td>
                            <td class="px-6 py-3"><?= esc($r['jenis_kelamin'] ?: '—') ?></td>
                            <td class="px-6 py-3"><?= $r['status_guru'] ? '<span class="inline-flex rounded-full bg-slate-100 text-slate-600 px-2 py-0.5 text-xs font-semibold">' . esc($r['status_guru']) . '</span>' : '—' ?></td>
                            <td class="px-6 py-3 text-slate-500"><?= esc($r['max_beban']) ?> JP</td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button @click='openEdit(<?= json_encode($r) ?>)' title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <a href="<?= site_url('admin/master/guru/delete/' . $r['id']) ?>" onclick="return confirm('Hapus guru ini?')" title="Hapus" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
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
            <div class="px-6 py-4 border-t border-slate-100">
                <?= $pager->only(['q', 'status'])->links() ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg text-slate-800 mb-4" x-text="mode==='add' ? 'Tambah Guru' : 'Edit Guru'"></h3>
            <form method="post" :action="actionUrl">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kode Guru *</label>
                        <input type="text" name="kode_guru" x-model="form.kode_guru" maxlength="20" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">NIP</label>
                        <input type="text" name="nip" x-model="form.nip" maxlength="60"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama Guru *</label>
                        <input type="text" name="nama" x-model="form.nama" maxlength="150" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" x-model="form.jenis_kelamin" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <select name="status_guru" x-model="form.status_guru" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                            <option value="">—</option>
                            <?php foreach (['PNS', 'PPPK', 'GTY', 'GTT'] as $s): ?>
                                <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Maks Beban (JP/minggu)</label>
                        <input type="number" name="max_beban" x-model="form.max_beban" min="0" max="50"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" x-model="form.keterangan" maxlength="255"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-3">Mata pelajaran yang diampu (kompetensi) diatur pada menu Mata Pelajaran / Pengampu.</p>
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
            <h3 class="font-bold text-lg text-slate-800 mb-4">Import Guru</h3>
            <form method="post" action="<?= site_url('admin/master/guru/import') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <p class="text-sm text-slate-500 mb-3">Unggah file Excel sesuai template. Data dengan <b>Kode Guru</b> sama akan diperbarui.</p>
                <input type="file" name="file" accept=".xlsx,.xls" required
                       class="w-full text-sm border border-slate-300 rounded-lg p-2 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700 file:font-semibold">
                <a href="<?= site_url('admin/master/guru/template') ?>" class="inline-block mt-3 text-sm text-brand-600 hover:underline">⬇ Unduh template Excel</a>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="importOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5">Unggah & Import</button>
                </div>
            </form>
            <hr class="my-4 border-slate-100">
            <p class="text-sm text-slate-500 mb-2">Atau ambil otomatis dari data kesediaan yang sudah masuk:</p>
            <a href="<?= site_url('admin/master/guru/import-kesediaan') ?>" onclick="return confirm('Impor semua guru dari Data Kesediaan? Yang sudah ada akan dilewati.')"
               class="inline-flex items-center gap-1.5 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold px-4 py-2.5">
                Impor dari Data Kesediaan
            </a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function guruPage() {
    return {
        open: false, importOpen: false, mode: 'add', actionUrl: '',
        base: '<?= site_url('admin/master/guru') ?>',
        form: { kode_guru:'', nip:'', nama:'', jenis_kelamin:'', status_guru:'', max_beban:24, keterangan:'' },
        openAdd() {
            this.mode='add';
            this.form={ kode_guru:'', nip:'', nama:'', jenis_kelamin:'', status_guru:'', max_beban:24, keterangan:'' };
            this.actionUrl=this.base; this.open=true;
        },
        openEdit(r) {
            this.mode='edit';
            this.form={ kode_guru:r.kode_guru, nip:r.nip||'', nama:r.nama, jenis_kelamin:r.jenis_kelamin||'', status_guru:r.status_guru||'', max_beban:r.max_beban, keterangan:r.keterangan||'' };
            this.actionUrl=this.base+'/'+r.id; this.open=true;
        },
    }
}
</script>
<?= $this->endSection() ?>
