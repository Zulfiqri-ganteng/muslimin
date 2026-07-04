<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<h1 class="text-2xl font-extrabold text-slate-800 mb-1">Jadwal Mengajar per Guru</h1>
<p class="text-slate-500 mb-5">Pilih guru untuk melihat jadwal mengajarnya.</p>

<form method="get" class="flex flex-col sm:flex-row gap-2 max-w-xl mb-6">
    <select name="guru_id" onchange="this.form.submit()" class="flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        <option value="">— Pilih guru —</option>
        <?php foreach ($guruOpts as $id => $label): ?>
            <option value="<?= $id ?>" <?= $guruId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="rounded-xl bg-brand-700 text-white font-bold px-6 py-3 text-sm">Lihat</button></noscript>
</form>

<?php if (! $guru): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada guru dipilih.</div>
<?php elseif (empty($jam) || empty($hari)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Jadwal belum tersedia.</div>
<?php
    // Pisahkan jam per shift → tiap shift jadi kartu/tabel sendiri (ada jarak).
    $byShift = [];
    foreach ($jam as $j) {
        $byShift[$j['shift']][] = $j;
    }
    $shiftLabel = ['pagi' => 'Pagi', 'siang' => 'Siang'];
?>
<div class="space-y-5">
    <!-- Header guru -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 flex items-center justify-between gap-3">
        <h2 class="font-bold text-lg text-slate-800"><?= esc($guru['nama']) ?></h2>
        <a href="<?= site_url('jadwal-guru/' . $guruId . '/pdf') ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-3 py-2 transition shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7h-3V4a2 2 0 00-2-2H10a2 2 0 00-2 2v3H5a2 2 0 00-2 2v6a2 2 0 002 2h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
            Cetak PDF
        </a>
    </div>

    <!-- Satu kartu per shift -->
    <?php foreach ($byShift as $shift => $jamShift): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 bg-brand-50">
                <h3 class="font-bold text-brand-700 text-sm uppercase tracking-wider">Shift <?= esc($shiftLabel[$shift] ?? ucfirst($shift)) ?></h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500">
                            <th class="px-3 py-3 text-left font-semibold w-36 border-b border-slate-100">Jam</th>
                            <?php foreach ($hari as $h): $td = (int) $h['id'] === ($now['hariId'] ?? 0); ?><th class="px-2 py-3 text-center font-semibold border-b border-l border-slate-100 min-w-[110px] <?= $td ? 'bg-brand-100 text-brand-700' : '' ?>"><?= esc($h['nama']) ?><?= $td ? ' •' : '' ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jamShift as $j): ?>
                            <?php if (! empty($j['is_istirahat'])): ?>
                                <tr><td colspan="<?= count($hari) + 1 ?>" class="bg-amber-50 text-amber-700 text-center text-xs font-semibold py-1.5 border-b border-slate-100 tracking-wider">ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)</td></tr>
                            <?php else: ?>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-3 py-2 border-b border-slate-100 whitespace-nowrap align-top">
                                        <span class="font-bold text-brand-700">Jam ke <?= esc($j['jam_ke']) ?></span><br>
                                        <span class="text-slate-400 text-xs"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                    </td>
                                    <?php foreach ($hari as $h): $c = $grid[$h['id'] . '-' . $j['id']] ?? null; $td = (int) $h['id'] === ($now['hariId'] ?? 0); ?>
                                        <td class="p-1.5 border-b border-l border-slate-100 align-top <?= $td ? 'bg-brand-50/60' : '' ?>">
                                            <?php if ($c): ?>
                                                <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-1.5">
                                                    <p class="text-emerald-700 text-sm leading-tight"><?= esc($c['nama_kelas']) ?></p>
                                                    <p class="text-sm font-bold text-emerald-800 leading-tight"><?= esc($c['nama_mapel']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
