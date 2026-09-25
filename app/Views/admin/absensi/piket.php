<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'piket_v1',
    'helpTitle' => 'Jadwal guru piket',
    'helpBody'  => '<p>Atur siapa yang <b>piket</b> tiap hari, terpisah untuk <b>KBM pagi</b> dan <b>KBM siang</b> (guru yang piket pagi saja cukup diisi di kolom Pagi). Pada halaman <b>Absensi Guru</b>, guru piket hari itu otomatis diisikan ke panel <b>Kehadiran Kerja</b> dan muncul di bagian <b>"Guru piket yang hadir"</b> pada pesan WhatsApp.</p>',
]) ?>

<?php
    $cfg = [
        'hari' => array_map(static fn ($h) => ['id' => (int) $h['id'], 'nama' => $h['nama']], $hari),
        'grid' => (object) $grid,
        'guru' => $guru,
    ];
?>

<form method="post" action="<?= site_url('admin/absensi/piket') ?>" x-data='jadwalPiket(<?= htmlspecialchars(json_encode($cfg), ENT_QUOTES) ?>)' @submit="isi()">
    <?= csrf_field() ?>
    <input type="hidden" name="grid_json" x-ref="json">

    <?php if (empty($hari)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
            Belum ada hari aktif. Atur dulu di <b>Master Data ▸ Hari</b>.
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-semibold w-32">Hari</th>
                            <th class="px-4 py-3 font-semibold">Piket KBM Pagi</th>
                            <th class="px-4 py-3 font-semibold">Piket KBM Siang</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="h in hari" :key="h.id">
                            <tr class="align-top">
                                <td class="px-4 py-3 font-bold text-slate-700" x-text="h.nama"></td>
                                <template x-for="sh in ['pagi', 'siang']" :key="sh">
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1.5 mb-2">
                                            <template x-for="gid in daftar(h.id, sh)" :key="gid">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 border border-brand-200 text-brand-700 pl-2.5 pr-1 py-0.5 text-xs font-semibold">
                                                    <span x-text="nama(gid)"></span>
                                                    <button type="button" @click="hapus(h.id, sh, gid)" class="rounded-full hover:bg-brand-100 p-0.5" title="Hapus">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </span>
                                            </template>
                                            <span x-show="daftar(h.id, sh).length === 0" class="text-xs text-slate-300">— belum ada —</span>
                                        </div>
                                        <select @change="tambah(h.id, sh, $event.target.value); $event.target.selectedIndex = 0"
                                                class="w-full max-w-xs rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs focus:border-brand-500 outline-none">
                                            <option value="">+ tambah guru piket…</option>
                                            <template x-for="g in guru" :key="g.id">
                                                <option :value="g.id" x-text="g.nama" :disabled="daftar(h.id, sh).indexOf(g.id) !== -1"></option>
                                            </template>
                                        </select>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-bold px-6 py-2.5 text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Simpan Jadwal Piket
                </button>
            </div>
        </div>
    <?php endif; ?>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Grid jadwal piket: [hari_id][pagi|siang] => [guru_id…], dikirim sebagai JSON.
    function jadwalPiket(cfg) {
        const grid = {};
        cfg.hari.forEach(function (h) {
            const g = cfg.grid[h.id] || {};
            grid[h.id] = { pagi: (g.pagi || []).map(Number), siang: (g.siang || []).map(Number) };
        });
        return {
            hari: cfg.hari,
            guru: cfg.guru,
            grid: grid,
            daftar(hid, sh) { return this.grid[hid][sh]; },
            nama(id) { const g = this.guru.find(function (x) { return x.id === id; }); return g ? g.nama : ('Guru #' + id); },
            tambah(hid, sh, val) {
                const id = Number(val);
                if (id && this.grid[hid][sh].indexOf(id) === -1) this.grid[hid][sh].push(id);
            },
            hapus(hid, sh, id) { this.grid[hid][sh] = this.grid[hid][sh].filter(function (x) { return x !== id; }); },
            isi() { this.$refs.json.value = JSON.stringify(this.grid); },
        };
    }
</script>
<?= $this->endSection() ?>
