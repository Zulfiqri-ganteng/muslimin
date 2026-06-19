<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<h1 class="text-2xl font-extrabold text-slate-800 mb-1">Jadwal Pelajaran per Kelas</h1>
<p class="text-slate-500 mb-5">Pilih kelas untuk melihat jadwal mingguannya.</p>

<form method="get" class="flex flex-col sm:flex-row gap-2 max-w-xl mb-6">
    <select name="kelas_id" onchange="this.form.submit()" class="flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        <option value="">— Pilih kelas —</option>
        <?php foreach ($kelasOpts as $id => $label): ?>
            <option value="<?= $id ?>" <?= $kelasId === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="rounded-xl bg-brand-700 text-white font-bold px-6 py-3 text-sm">Lihat</button></noscript>
</form>

<?php if (! $kelas): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada kelas dipilih.</div>
<?php elseif (empty($jam) || empty($hari)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Jadwal untuk kelas ini belum tersedia.</div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800"><?= esc($kelas['nama_kelas']) ?></h2>
            <span class="text-xs rounded-full px-2.5 py-0.5 font-semibold <?= $kelas['shift'] === 'pagi' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' ?>"><?= ucfirst($kelas['shift']) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500">
                        <th class="px-3 py-3 text-left font-semibold w-32 border-b border-slate-100">Jam</th>
                        <?php foreach ($hari as $h): ?><th class="px-2 py-3 text-center font-semibold border-b border-l border-slate-100 min-w-[110px]"><?= esc($h['nama']) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jam as $j): ?>
                        <?php if ($j['is_istirahat']): ?>
                            <tr><td colspan="<?= count($hari) + 1 ?>" class="bg-amber-50 text-amber-700 text-center text-xs font-semibold py-1.5 border-b border-slate-100 tracking-wider">ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)</td></tr>
                        <?php else: ?>
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-3 py-2 border-b border-slate-100 whitespace-nowrap align-top">
                                    <span class="font-bold text-brand-700">Jam <?= esc($j['jam_ke']) ?></span><br>
                                    <span class="text-slate-400 text-xs"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                </td>
                                <?php foreach ($hari as $h): $c = $grid[$h['id'] . '-' . $j['id']] ?? null; ?>
                                    <td class="p-1.5 border-b border-l border-slate-100 align-top">
                                        <?php if ($c): ?>
                                            <div class="rounded-lg bg-brand-50 border border-brand-100 p-1.5">
                                                <p class="font-semibold text-brand-700 text-sm leading-tight"><?= esc($c['nama_mapel']) ?></p>
                                                <p class="text-[11px] text-slate-500"><?= esc($c['guru_nama']) ?></p>
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
<?php endif; ?>

<?= $this->endSection() ?>
