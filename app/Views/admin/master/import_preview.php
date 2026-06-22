<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * Pratinjau impor yang dapat diedit.
 * @var string $subtitle  Nama master (mis. "Master Guru")
 * @var array  $cols      Daftar kolom: ['key','label','type'(text|number|select),'options'?,'width'?]
 * @var array  $rows      Baris hasil baca Excel (assoc, boleh ada kunci '_status')
 * @var string $commitUrl URL tujuan simpan
 * @var string $backUrl   URL batal
 */
// Gabungkan nilai yang ADA di data ke daftar opsi select (agar nilai hasil impor
// selalu tampil & terpilih walau belum terdaftar di master). Datalist tak perlu ini.
foreach ($cols as &$c) {
    if (($c['type'] ?? '') === 'select') {
        $opts = $c['options'] ?? [];
        foreach ($rows as $r) {
            $v = trim((string) ($r[$c['key']] ?? ''));
            if ($v !== '' && ! in_array($v, $opts, true)) {
                $opts[] = $v;
            }
        }
        $c['options'] = $opts;
    }
}
unset($c);
$colsJs = $cols;
?>
<div x-data="importPreview()" class="space-y-5">
    <!-- Header -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-lg text-slate-800">Pratinjau Impor — <?= esc($subtitle) ?></h2>
                <p class="text-sm text-slate-500 mt-1">Periksa & <b>edit langsung</b> data di bawah sebelum disimpan. Anda bisa mengubah sel, menghapus baris, atau menambah baris baru. Klik <b>Simpan</b> bila sudah benar.</p>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 text-emerald-700 px-3 py-1.5 font-semibold">
                    <span x-text="rows.length"></span> baris
                </span>
            </div>
        </div>
    </div>

    <!-- Tabel editor -->
    <form method="post" action="<?= esc($commitUrl) ?>" @submit="prepare()">
        <?= csrf_field() ?>
        <?php foreach ($cols as $c): if (($c['type'] ?? '') === 'datalist'): ?>
            <datalist id="dl_<?= esc($c['key']) ?>">
                <?php foreach (($c['options'] ?? []) as $opt): ?><option value="<?= esc($opt) ?>"></option><?php endforeach; ?>
            </datalist>
        <?php endif; endforeach; ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-3 py-3 font-semibold w-12 text-center">#</th>
                            <th class="px-3 py-3 font-semibold w-28">Status</th>
                            <?php foreach ($cols as $c): ?>
                                <th class="px-3 py-3 font-semibold" <?= isset($c['width']) ? 'style="min-width:' . (int) $c['width'] . 'px"' : '' ?>>
                                    <?= esc($c['label']) ?><?= !empty($c['required']) ? ' <span class="text-red-500">*</span>' : '' ?>
                                </th>
                            <?php endforeach; ?>
                            <th class="px-3 py-3 font-semibold w-14 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, idx) in rows" :key="row._uid">
                            <tr class="hover:bg-slate-50 align-top">
                                <td class="px-3 py-2 text-center text-slate-400" x-text="idx + 1"></td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="row._status === 'perbarui' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
                                          x-text="row._status === 'perbarui' ? 'Perbarui' : 'Baru'"></span>
                                </td>
                                <?php foreach ($cols as $c): $key = $c['key']; $type = $c['type'] ?? 'text'; ?>
                                    <td class="px-3 py-2">
                                        <?php if ($type === 'select'): ?>
                                            <select x-model="row['<?= esc($key, 'js') ?>']"
                                                    class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 outline-none">
                                                <option value="">—</option>
                                                <?php foreach (($c['options'] ?? []) as $opt): ?>
                                                    <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($type === 'datalist'): ?>
                                            <input type="text" list="dl_<?= esc($key) ?>"
                                                   x-model="row['<?= esc($key, 'js') ?>']"
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 outline-none">
                                        <?php else: ?>
                                            <input type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                                   x-model="row['<?= esc($key, 'js') ?>']"
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 outline-none">
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" @click="removeRow(idx)" title="Hapus baris" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="rows.length === 0">
                            <td colspan="<?= count($cols) + 3 ?>" class="px-6 py-10 text-center text-slate-400">Tidak ada baris. Tambah baris atau batalkan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100">
                <button type="button" @click="addRow()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-3.5 py-2 hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah Baris
                </button>
            </div>
        </div>

        <!-- payload tersembunyi (diisi saat submit) -->
        <div x-ref="payload"></div>

        <div class="flex flex-wrap justify-end gap-2 mt-5">
            <a href="<?= esc($backUrl) ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-5 py-2.5 hover:bg-slate-50 transition">Batal</a>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-6 py-2.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Simpan <span x-text="rows.length"></span> Data
            </button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function importPreview() {
    return {
        cols: <?= json_encode(array_map(fn ($c) => $c['key'], $colsJs)) ?>,
        rows: (<?= json_encode(array_values($rows)) ?> || []).map((r, i) => ({ ...r, _uid: 'r' + i + '_' + Date.now(), _status: r._status || 'baru' })),
        addRow() {
            const blank = { _uid: 'n' + Date.now() + Math.random(), _status: 'baru' };
            this.cols.forEach(k => blank[k] = '');
            this.rows.push(blank);
        },
        removeRow(i) { this.rows.splice(i, 1); },
        // Bangun input tersembunyi rows[i][key] tepat sebelum submit (abaikan baris kosong).
        prepare() {
            const box = this.$refs.payload;
            box.innerHTML = '';
            let n = 0;
            this.rows.forEach(row => {
                // lewati baris yang semua kolomnya kosong
                const isEmpty = this.cols.every(k => !String(row[k] ?? '').trim());
                if (isEmpty) return;
                this.cols.forEach(k => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'rows[' + n + '][' + k + ']';
                    inp.value = row[k] ?? '';
                    box.appendChild(inp);
                });
                n++;
            });
        },
    };
}
</script>
<?= $this->endSection() ?>
