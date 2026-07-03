<?php
/**
 * Halaman Ketersediaan Guru — grid toggle slot "tidak tersedia".
 *
 * @var array  $guruOpts    Opsi guru (id => label)
 * @var int    $guruId      Guru terpilih
 * @var string $shift       Shift aktif (pagi/siang)
 * @var array  $hari        Hari aktif terurut
 * @var array  $jam         Jam KBM shift aktif
 * @var array  $unavailable Set slot tidak tersedia (kunci "hariId-jamId")
 */
$jamIds = array_map(static fn ($j) => (int) $j['id'], $jam);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'ketersediaan',
    'helpTitle' => 'Ketersediaan Guru',
    'helpBody'  => '<p>Tandai jam/hari di mana guru <b>TIDAK dapat</b> mengajar (mis. sedang kuliah, tugas lain). Pilih guru &amp; shift, lalu klik sel untuk men-toggle. Sel hijau = tersedia, merah = tidak tersedia.</p>
        <p class="mt-1">Klik nama <b>hari</b> untuk menandai/menghapus satu kolom sekaligus. Data ini dipakai sistem agar tidak menempatkan guru pada jam yang tak tersedia (aturan bentrok R3). Jangan lupa <b>Simpan</b>.</p>',
]) ?>

<div x-data="ketPage"
     data-selected="<?= esc(json_encode(array_keys($unavailable)), 'attr') ?>"
     data-jam-ids="<?= esc(json_encode($jamIds), 'attr') ?>">

    <!-- Pemilih guru + shift -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="get" class="flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-2 flex-1">
                <label class="text-sm font-semibold text-slate-600 shrink-0">Guru:</label>
                <select name="guru_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none flex-1 min-w-[200px]">
                    <?php if (empty($guruOpts)): ?>
                        <option value="">— belum ada guru —</option>
                    <?php else: foreach ($guruOpts as $id => $label): ?>
                        <option value="<?= $id ?>" <?= $guruId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="inline-flex rounded-lg border border-slate-300 bg-white p-1 text-sm font-semibold">
                <?php foreach (['pagi' => 'Pagi', 'siang' => 'Siang'] as $key => $label): ?>
                    <button type="submit" name="shift" value="<?= $key ?>"
                            class="px-4 py-1.5 rounded-md transition <?= $shift === $key ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100' ?>"><?= $label ?></button>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <?php if (! $guruId): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Pilih guru terlebih dahulu (atau tambahkan guru di menu Guru).</div>
    <?php elseif (empty($jam) || empty($hari)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada data hari aktif atau jam pelajaran untuk shift <?= esc($shift) ?>.</div>
    <?php else: ?>
        <form method="post" action="<?= site_url('admin/master/ketersediaan') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="guru_id" value="<?= $guruId ?>">
            <input type="hidden" name="shift" value="<?= $shift ?>">

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-3.5 rounded bg-emerald-100 border border-emerald-300"></span> Tersedia</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-3.5 rounded bg-red-100 border border-red-300"></span> Tidak tersedia</span>
                        <span class="text-slate-400">(<span x-text="sel.length"></span> slot ditandai)</span>
                    </div>
                    <button type="button" @click="clearAll()" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Kosongkan semua</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500">
                                <th class="px-3 py-3 text-left font-semibold w-40 border-b border-slate-100">Jam</th>
                                <?php foreach ($hari as $h): ?>
                                    <th class="px-2 py-3 text-center font-semibold border-b border-l border-slate-100">
                                        <button type="button" @click="toggleDay(<?= (int) $h['id'] ?>)" class="hover:text-brand-700 transition" title="Toggle satu hari"><?= esc($h['nama']) ?></button>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jam as $j): ?>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-3 py-2 border-b border-slate-100 whitespace-nowrap">
                                        <span class="font-bold text-brand-700">Jam <?= esc($j['jam_ke']) ?></span>
                                        <span class="text-slate-400 text-xs ml-1"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                    </td>
                                    <?php foreach ($hari as $h): $key = $h['id'] . '-' . $j['id']; ?>
                                        <td class="p-1 border-b border-l border-slate-100 text-center">
                                            <button type="button" @click="toggle('<?= $key ?>')"
                                                    :class="has('<?= $key ?>') ? 'bg-red-100 text-red-600 ring-1 ring-red-300' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100'"
                                                    class="w-full h-9 rounded-md text-xs font-semibold transition flex items-center justify-center">
                                                <span x-show="!has('<?= $key ?>')">✓</span>
                                                <span x-show="has('<?= $key ?>')" x-cloak>✕</span>
                                            </button>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <!-- hidden input untuk tiap slot tidak-tersedia -->
                    <template x-for="key in sel" :key="key"><input type="hidden" name="unavailable[]" :value="key"></template>
                    <button class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Simpan Ketersediaan
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/ketersediaan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/ketersediaan.js') ?>"></script>
<?= $this->endSection() ?>
