<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_v2',
    'helpTitle' => 'Jadwal KBM — susun manual, generate, atau import Excel',
    'helpBody'  => '<p>Ada <b>3 cara</b> mengisi jadwal:</p>
        <p class="mt-1">1) <b>Manual (tarik & lepas):</b> pilih kelas, lalu seret kartu mapel dari panel kiri ke sel hari/jam. Seret antar sel untuk <b>memindah</b>; lepas di sel terisi untuk <b>menukar</b>. Klik tanda × untuk menghapus.</p>
        <p class="mt-1">2) <b>Generate Otomatis:</b> sistem mengisi sel secara <b>rapi &amp; berurutan</b> — JP mapel yang sama ditaruh bersebelahan, mengisi hari demi hari (bukan acak). Cocok untuk draf awal, lalu rapikan manual.</p>
        <p class="mt-1">3) <b>Import Excel:</b> bila jadwal asli sudah ada di Excel, klik <b>Import Excel</b> → unduh template (kolom: Kelas, Hari, Jam ke, Mapel, Guru) → unggah → <b>periksa/edit pratinjau</b> → Simpan. Bisa satu file untuk banyak kelas sekaligus.</p>
        <p class="mt-2">Sistem otomatis menolak/melewati bila: guru bentrok jam sama (R1), sel sudah terisi (R2), guru tak tersedia (R3), atau kuota JP penuh (R4). Angka <b>sisa</b> pada kartu = JP yang belum terpasang.</p>',
]) ?>

<?php $kelas = $kelas ?? null; ?>
<div x-data="jadwalGrid()" x-init="init()">
    <!-- Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            <!-- Kiri: pemilih kelas + info -->
            <form method="get" class="flex items-center gap-2 flex-1 min-w-0">
                <label class="text-sm font-semibold text-slate-600 shrink-0">Kelas</label>
                <select name="kelas_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none flex-1 min-w-[180px] max-w-xs">
                    <?php if (empty($kelasOpts)): ?>
                        <option value="">— belum ada kelas —</option>
                    <?php else: foreach ($kelasOpts as $id => $label): ?>
                        <option value="<?= $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; endif; ?>
                </select>
                <?php if ($kelas): ?>
                    <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 px-3 py-1.5 text-xs font-semibold shrink-0">
                        <?= esc($kelas['tingkat']) ?>
                        <span class="text-slate-300">·</span>
                        <span class="<?= $shift === 'pagi' ? 'text-amber-600' : 'text-indigo-600' ?>"><?= ucfirst($shift) ?></span>
                    </span>
                <?php endif; ?>
            </form>

            <!-- Kanan: aksi -->
            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                <?php if ($kelas): ?>
                    <button type="button" @click="genOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Generate
                    </button>
                <?php endif; ?>
                <button type="button" @click="importOpen=true" class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold px-3.5 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import
                </button>

                <?php if ($kelas): ?>
                    <span class="hidden sm:block w-px h-6 bg-slate-200 mx-0.5"></span>

                    <!-- Ekspor (dropdown Excel/PDF) -->
                    <div x-data="{ openExp: false }" class="relative" @click.outside="openExp=false">
                        <button type="button" @click="openExp=!openExp" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 text-sm font-semibold px-3.5 py-2.5 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/></svg>
                            Ekspor
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="openExp" x-cloak x-transition.opacity.duration.150ms
                             class="absolute right-0 lg:right-0 mt-1.5 w-44 rounded-xl border border-slate-200 bg-white shadow-lg py-1.5 z-30">
                            <a href="<?= site_url('admin/cetak/jadwal-kelas/' . $kelasId . '/excel') ?>" class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                                Unduh Excel
                            </a>
                            <a href="<?= site_url('admin/cetak/jadwal-kelas/' . $kelasId . '/pdf') ?>" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7h-3V4a2 2 0 00-2-2H10a2 2 0 00-2 2v3H5a2 2 0 00-2 2v6a2 2 0 002 2h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
                                Cetak PDF
                            </a>
                        </div>
                    </div>

                    <!-- Hapus massal (ghost) -->
                    <button type="button" @click="toggleBulk()"
                            class="inline-flex items-center gap-1.5 rounded-lg text-sm font-semibold px-3.5 py-2.5 transition border"
                            :class="selectMode ? 'bg-slate-700 text-white border-slate-700' : 'bg-white text-slate-500 border-slate-300 hover:bg-red-50 hover:text-red-600 hover:border-red-200'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span x-text="selectMode ? 'Selesai' : 'Hapus Massal'"></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (! $kelasId): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Pilih kelas dulu (atau tambahkan kelas di menu Kelas).</div>
    <?php elseif (empty($jam) || empty($hari)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada hari aktif atau jam pelajaran untuk shift <?= esc($shift) ?>. Atur di menu Hari & Jam Pelajaran.</div>
    <?php else: ?>
        <!-- ============ BAR HAPUS MASSAL ============ -->
        <div x-show="selectMode" x-cloak x-transition
             class="mb-4 flex flex-col sm:flex-row sm:items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 shadow-sm px-4 py-3">
            <p class="text-sm text-slate-600 flex-1">
                Mode hapus massal aktif. <b>Klik sel</b> yang ingin dihapus, lalu tekan <b>Hapus Terpilih</b>.
                <span class="inline-flex items-center rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-xs font-semibold ml-1"><span x-text="selectedCount()"></span> dipilih</span>
            </p>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="selectAll()" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-3 py-2 hover:bg-white transition">Pilih Semua</button>
                <button type="button" @click="clearSelection()" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-3 py-2 hover:bg-white transition">Batal Pilih</button>
                <button type="button" @click="bulkDelete()" :disabled="selectedCount()===0"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold px-3.5 py-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Terpilih (<span x-text="selectedCount()"></span>)
                </button>
                <button type="button" @click="clearAll()" class="rounded-lg border border-red-300 text-red-600 text-sm font-semibold px-3 py-2 hover:bg-red-50 transition">Kosongkan Semua</button>
            </div>
        </div>

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
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-bold text-brand-700 text-[13px] leading-tight line-clamp-2" :title="p.nama_mapel" x-text="p.nama_mapel"></span>
                                    <span class="text-[11px] font-bold rounded-full px-2 py-0.5 shrink-0"
                                          :class="p.sisa === 0 ? 'bg-emerald-100 text-emerald-700' : (p.sisa < 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')"
                                          x-text="'sisa ' + p.sisa + '/' + p.jp"></span>
                                </div>
                                <p class="text-[11px] text-slate-400 truncate mt-0.5" :title="p.guru_nama" x-text="p.guru_nama"></p>
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
                                            <div x-show="cells['<?= $k ?>']" x-cloak
                                                 :draggable="!selectMode"
                                                 @dragstart="startCell($event, '<?= $k ?>')"
                                                 @click="selectMode && toggleSelect('<?= $k ?>')"
                                                 class="group relative h-full rounded-lg p-1.5 border transition"
                                                 :class="selectMode
                                                     ? (selected['<?= $k ?>'] ? 'bg-red-50 border-red-500 ring-2 ring-red-400 cursor-pointer' : 'bg-brand-50 border-brand-200 cursor-pointer hover:border-red-300')
                                                     : 'bg-brand-50 border-brand-200 cursor-grab hover:shadow'">
                                                <button x-show="!selectMode" @click.stop="removeCell('<?= $k ?>')" title="Hapus"
                                                        class="absolute top-0.5 right-0.5 hidden group-hover:flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white text-xs">×</button>
                                                <span x-show="selectMode" class="absolute top-0.5 right-0.5 h-4 w-4 rounded flex items-center justify-center text-[10px] font-bold border"
                                                      :class="selected['<?= $k ?>'] ? 'bg-red-500 border-red-500 text-white' : 'bg-white border-slate-300 text-transparent'">✓</span>
                                                <p class="font-bold text-brand-700 text-[13px] leading-tight line-clamp-2"
                                                   :title="cells['<?= $k ?>']?.nama_mapel"
                                                   x-text="cells['<?= $k ?>']?.nama_mapel || cells['<?= $k ?>']?.kode_mapel"></p>
                                                <p class="text-[11px] text-slate-500 leading-tight truncate"
                                                   :title="cells['<?= $k ?>']?.guru_nama"
                                                   x-text="cells['<?= $k ?>']?.guru_nama || cells['<?= $k ?>']?.kode_guru"></p>
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
            <p class="text-sm text-slate-500 mb-4">Sistem mengisi sel secara <b>rapi &amp; berurutan</b> (JP mapel sama bersebelahan, mengisi hari demi hari) berdasarkan penugasan kelas ini, sambil menghormati ketersediaan guru (R3) dan menghindari bentrok guru (R1). Penugasan yang tak muat akan dilaporkan.</p>
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

    <!-- Modal Import Excel -->
    <div x-show="importOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="importOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
            <h3 class="font-bold text-lg text-slate-800 mb-2">Import Jadwal dari Excel</h3>
            <p class="text-sm text-slate-500 mb-4">Unggah file Excel berformat <b>grid</b> (Jam × Hari, sama seperti hasil cetak/Excel). Isi tiap sel dengan <b>nama/kode mapel</b> — guru diambil otomatis dari <b>Penugasan</b>. (Opsional: tulis <b>Mapel / Guru</b> untuk menentukan guru langsung.) Setelah diunggah Anda bisa <b>memeriksa & mengedit</b> sebelum disimpan.</p>
            <div class="rounded-xl bg-sky-50 border border-sky-100 p-3 mb-4 text-sm text-sky-800">
                Belum punya filenya? <a href="<?= site_url('admin/jadwal/template' . ($kelasId ? '?kelas_id=' . $kelasId : '')) ?>" class="font-semibold underline">Unduh template Excel<?= $kelas ? ' (' . esc($kelas['nama_kelas']) . ')' : '' ?></a> lalu isi grid-nya sesuai jadwal asli.
            </div>
            <form method="post" action="<?= site_url('admin/jadwal/import-preview') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= (int) $kelasId ?>">
                <input type="file" name="file" accept=".xlsx,.xls" required
                       class="block w-full text-sm text-slate-600 mb-5 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-700 file:text-white file:px-4 file:py-2 file:text-sm file:font-semibold hover:file:bg-brand-800 border border-slate-300 rounded-lg">
                <div class="flex justify-end gap-2">
                    <button type="button" @click="importOpen=false" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</button>
                    <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold px-5 py-2.5">Lanjut ke Pratinjau</button>
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
        importOpen: false,
        selectMode: false,
        selected: {},
        toast: { show: false, msg: '', type: 'ok' },

        init() {},

        // ---- hapus massal ----
        toggleBulk() {
            this.selectMode = !this.selectMode;
            if (!this.selectMode) this.selected = {};
        },
        toggleSelect(key) {
            if (!this.cells[key]) return;
            if (this.selected[key]) delete this.selected[key];
            else this.selected[key] = true;
        },
        selectedCount() { return Object.keys(this.selected).length; },
        selectAll() {
            const sel = {};
            Object.keys(this.cells).forEach(k => { if (this.cells[k]) sel[k] = true; });
            this.selected = sel;
        },
        clearSelection() { this.selected = {}; },
        applySisaAll(list) {
            (list || []).forEach(s => {
                const p = this.palet.find(x => x.id === s.pengampu_id);
                if (p) p.sisa = s.sisa;
            });
        },
        // bangun body dgn dukungan array (ids[]) untuk fetch
        body(o) {
            const p = new URLSearchParams();
            for (const [k, v] of Object.entries(o)) {
                if (Array.isArray(v)) v.forEach(x => p.append(k + '[]', x));
                else p.append(k, v);
            }
            return p;
        },
        async bulkDelete() {
            const keys = Object.keys(this.selected).filter(k => this.cells[k]);
            if (!keys.length) return this.notify('Belum ada sel dipilih.', 'err');
            const ids = keys.map(k => this.cells[k].id);
            const r = await this.post('<?= site_url('admin/jadwal/bulk-remove') ?>', this.body({ mode: 'selected', kelas_id: this.kelasId, ids }));
            if (!r.ok) return this.notify(r.msg, 'err');
            (r.removedKeys || keys).forEach(k => delete this.cells[k]);
            this.applySisaAll(r.sisaAll);
            this.selected = {};
            this.notify((r.count || keys.length) + ' sel dihapus.');
        },
        async clearAll() {
            if (!Object.keys(this.cells).length) return this.notify('Jadwal sudah kosong.', 'err');
            if (!confirm('Kosongkan SEMUA jadwal kelas ini? Tindakan ini tidak bisa dibatalkan.')) return;
            const r = await this.post('<?= site_url('admin/jadwal/bulk-remove') ?>', this.body({ mode: 'all', kelas_id: this.kelasId }));
            if (!r.ok) return this.notify(r.msg, 'err');
            this.cells = {};
            this.applySisaAll(r.sisaAll);
            this.selected = {};
            this.selectMode = false;
            this.notify((r.count || 0) + ' sel dihapus. Jadwal dikosongkan.');
        },

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
