<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal',
    'helpTitle' => 'Jadwal KBM — susun dengan tarik & lepas',
    'helpBody'  => '<p>Pilih kelas, lalu <b>seret kartu mapel</b> dari panel kiri ke sel hari/jam. Seret antar sel untuk <b>memindah</b>; lepas di sel terisi untuk <b>menukar</b>. Klik tanda × untuk menghapus.</p>
        <p class="mt-1">Sistem otomatis menolak bila: guru bentrok jam sama (R1), sel sudah terisi (R2), guru tak tersedia (R3), atau kuota JP penuh (R4). Angka <b>sisa</b> pada kartu = JP yang belum terpasang.</p>',
]) ?>

<?php $kelas = $kelas ?? null; ?>
<div x-data="jadwalGrid()" x-init="init()">
    <!-- Pemilih kelas -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-2 flex-1">
                <label class="text-sm font-semibold text-slate-600 shrink-0">Kelas:</label>
                <select name="kelas_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none flex-1 min-w-[200px]">
                    <?php if (empty($kelasOpts)): ?>
                        <option value="">— belum ada kelas —</option>
                    <?php else: foreach ($kelasOpts as $id => $label): ?>
                        <option value="<?= $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <?php if ($kelas): ?>
                <div class="text-sm text-slate-500">
                    Tingkat <b><?= esc($kelas['tingkat']) ?></b> · Shift
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= $shift === 'pagi' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' ?>"><?= ucfirst($shift) ?></span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="genOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Generate Otomatis
                    </button>
                    <a href="<?= site_url('admin/cetak/jadwal-kelas/' . $kelasId . '/excel') ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Excel
                    </a>
                    <a href="<?= site_url('admin/cetak/jadwal-kelas/' . $kelasId . '/pdf') ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7h-3V4a2 2 0 00-2-2H10a2 2 0 00-2 2v3H5a2 2 0 00-2 2v6a2 2 0 002 2h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
                        PDF
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if (! $kelasId): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Pilih kelas dulu (atau tambahkan kelas di menu Kelas).</div>
    <?php elseif (empty($jam) || empty($hari)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada hari aktif atau jam pelajaran untuk shift <?= esc($shift) ?>. Atur di menu Hari & Jam Pelajaran.</div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-5">
            <!-- ============ PALET MAPEL ============ -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 h-max lg:sticky lg:top-20">
                <h3 class="font-bold text-slate-700 text-sm mb-1">Mapel Kelas Ini</h3>
                <p class="text-xs text-slate-400 mb-3">Seret ke grid. Angka = sisa JP.</p>
                <?php if (empty($palet)): ?>
                    <div class="text-sm text-slate-400 py-4">Belum ada penugasan. Atur dulu di menu <a href="<?= site_url('admin/master/pengampu?kelas_id=' . $kelasId) ?>" class="text-brand-600 underline">Penugasan</a>.</div>
                <?php else: ?>
                    <div class="space-y-2">
                        <template x-for="p in palet" :key="p.id">
                            <div :draggable="p.sisa > 0" @dragstart="startPalette($event, p)"
                                 :class="p.sisa > 0 ? 'cursor-grab hover:border-brand-400 hover:shadow' : 'opacity-40 cursor-not-allowed'"
                                 class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 transition select-none">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-brand-700 text-sm" x-text="p.kode_mapel"></span>
                                    <span class="text-[11px] font-bold rounded-full px-2 py-0.5"
                                          :class="p.sisa === 0 ? 'bg-emerald-100 text-emerald-700' : (p.sisa < 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')"
                                          x-text="'sisa ' + p.sisa + '/' + p.jp"></span>
                                </div>
                                <p class="text-xs text-slate-500 truncate" x-text="p.nama_mapel"></p>
                                <p class="text-[11px] text-slate-400 truncate"><span x-text="p.kode_guru"></span> · <span x-text="p.guru_nama"></span></p>
                            </div>
                        </template>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============ GRID ============ -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500">
                            <th class="px-3 py-3 text-left font-semibold w-36 border-b border-slate-100">Jam</th>
                            <?php foreach ($hari as $h): ?>
                                <th class="px-2 py-3 text-center font-semibold border-b border-l border-slate-100 min-w-[120px]"><?= esc($h['nama']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jam as $j): ?>
                            <?php if ($j['is_istirahat']): ?>
                                <tr>
                                    <td colspan="<?= count($hari) + 1 ?>" class="bg-amber-50 text-amber-700 text-center text-xs font-semibold py-1.5 border-b border-slate-100 tracking-wider">
                                        ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td class="px-3 py-2 border-b border-slate-100 whitespace-nowrap align-top">
                                        <span class="font-bold text-brand-700">Jam <?= esc($j['jam_ke']) ?></span><br>
                                        <span class="text-slate-400 text-xs"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                    </td>
                                    <?php foreach ($hari as $h): $k = $h['id'] . '-' . $j['id']; ?>
                                        <td class="p-1 border-b border-l border-slate-100 align-top h-16"
                                            @dragover.prevent @dragenter.prevent
                                            @drop="onDrop($event, <?= (int) $h['id'] ?>, <?= (int) $j['id'] ?>)">
                                            <!-- sel terisi -->
                                            <div x-show="cells['<?= $k ?>']" x-cloak draggable="true"
                                                 @dragstart="startCell($event, '<?= $k ?>')"
                                                 class="group relative h-full rounded-lg bg-brand-50 border border-brand-200 p-1.5 cursor-grab transition hover:shadow">
                                                <button @click.stop="removeCell('<?= $k ?>')" title="Hapus"
                                                        class="absolute top-0.5 right-0.5 hidden group-hover:flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white text-xs">×</button>
                                                <p class="font-bold text-brand-700 text-sm leading-tight" x-text="cells['<?= $k ?>']?.kode_mapel"></p>
                                                <p class="text-[11px] text-slate-500 leading-tight" x-text="cells['<?= $k ?>']?.kode_guru + ' · ' + (cells['<?= $k ?>']?.guru_nama || '')"></p>
                                            </div>
                                            <!-- sel kosong -->
                                            <div x-show="!cells['<?= $k ?>']" class="flex h-full min-h-[3rem] items-center justify-center text-slate-200">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Generate Otomatis -->
    <div x-show="genOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="genOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-2">Generate Jadwal Otomatis</h3>
            <p class="text-sm text-slate-500 mb-4">Sistem mengisi sel berdasarkan penugasan kelas ini, menghormati ketersediaan guru (R3) dan menghindari bentrok guru (R1). Penugasan yang tak muat akan dilaporkan.</p>
            <form method="post" action="<?= site_url('admin/jadwal/generate') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
                <label class="flex items-start gap-2 text-sm text-slate-600 mb-5">
                    <input type="checkbox" name="reset" value="1" class="mt-0.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                    <span><b>Kosongkan dulu</b> jadwal kelas ini sebelum generate (buat ulang dari nol). Jika tidak dicentang, hanya mengisi sel yang masih kosong.</span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="genOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold px-5 py-2.5">Generate Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="toast.show" x-cloak x-transition.opacity
         class="fixed bottom-5 right-5 z-[80] max-w-sm rounded-xl px-4 py-3 text-sm font-medium shadow-lg"
         :class="toast.type === 'ok' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'"
         x-text="toast.msg"></div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function jadwalGrid() {
    return {
        kelasId: <?= (int) $kelasId ?>,
        cells: <?= json_encode($grid ?: new \stdClass()) ?>,
        palet: <?= json_encode($palet ?: []) ?>,
        drag: null,
        genOpen: false,
        toast: { show: false, msg: '', type: 'ok' },

        init() {},

        notify(msg, type = 'ok') {
            this.toast = { show: true, msg, type };
            clearTimeout(this._t);
            this._t = setTimeout(() => this.toast.show = false, 3200);
        },

        async post(url, data) {
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams(data),
                });
                return await res.json();
            } catch (e) {
                return { ok: false, msg: 'Gagal terhubung ke server.' };
            }
        },

        updateSisa(s) {
            if (!s) return;
            const p = this.palet.find(x => x.id === s.pengampu_id);
            if (p) p.sisa = s.sisa;
        },

        // ---- drag sources ----
        startPalette(e, p) {
            if (p.sisa <= 0) { e.preventDefault(); return; }
            this.drag = { type: 'palette', pengampu_id: p.id };
            e.dataTransfer.effectAllowed = 'copy';
        },
        startCell(e, key) {
            const c = this.cells[key];
            if (!c) return;
            this.drag = { type: 'cell', id: c.id, fromKey: key };
            e.dataTransfer.effectAllowed = 'move';
        },

        // ---- drop target ----
        async onDrop(e, hariId, jamId) {
            const d = this.drag; this.drag = null;
            if (!d) return;
            if (d.type === 'palette') {
                await this.place(hariId, jamId, d.pengampu_id);
            } else {
                await this.move(d.id, hariId, jamId, d.fromKey);
            }
        },

        async place(hariId, jamId, pengampuId) {
            const r = await this.post('<?= site_url('admin/jadwal/place') ?>', {
                kelas_id: this.kelasId, hari_id: hariId, jam_id: jamId, pengampu_id: pengampuId,
            });
            if (!r.ok) return this.notify(r.msg, 'err');
            this.cells[r.cell.hari_id + '-' + r.cell.jam_id] = r.cell;
            this.updateSisa(r.sisa);
            this.notify('Jadwal ditambahkan.');
        },

        async removeCell(key) {
            const c = this.cells[key];
            if (!c) return;
            const r = await this.post('<?= site_url('admin/jadwal/remove') ?>', { id: c.id });
            if (!r.ok) return this.notify(r.msg, 'err');
            delete this.cells[key];
            this.updateSisa(r.sisa);
            this.notify('Sel dikosongkan.');
        },

        async move(id, hariId, jamId, fromKey) {
            if (fromKey === hariId + '-' + jamId) return;
            const r = await this.post('<?= site_url('admin/jadwal/move') ?>', {
                id, to_hari_id: hariId, to_jam_id: jamId,
            });
            if (!r.ok) return this.notify(r.msg, 'err');
            if (r.move) {
                delete this.cells[r.move.from.hari_id + '-' + r.move.from.jam_id];
                this.cells[r.move.cell.hari_id + '-' + r.move.cell.jam_id] = r.move.cell;
                this.notify('Jadwal dipindahkan.');
            } else if (r.swap) {
                r.swap.forEach(s => { this.cells[s.cell.hari_id + '-' + s.cell.jam_id] = s.cell; });
                this.notify('Jadwal ditukar.');
            }
        },
    };
}
</script>
<?= $this->endSection() ?>
