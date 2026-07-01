<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<?php
    $statusOpts = ['hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Tidak Hadir'];
    $badge = [
        'hadir' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'telat' => 'bg-amber-50 text-amber-700 border-amber-200',
        'izin'  => 'bg-sky-50 text-sky-700 border-sky-200',
        'sakit' => 'bg-violet-50 text-violet-700 border-violet-200',
        'alpa'  => 'bg-red-50 text-red-700 border-red-200',
    ];
?>

<h1 class="text-2xl font-extrabold text-slate-800 mb-1">Absensi Guru</h1>
<p class="text-slate-500 mb-5">Kehadiran guru per tanggal. Tanpa tanda khusus berarti <b>Hadir</b>.</p>

<!-- Pemilih tanggal + ringkasan -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="<?= esc($tanggal) ?>" onchange="this.form.submit()"
                       class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            </div>
            <div class="pb-1">
                <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600"><?= $hariNama !== '' ? esc($hariNama) : '—' ?></span>
            </div>
            <noscript><button class="rounded-xl bg-brand-700 text-white font-bold px-5 py-2.5 text-sm">Lihat</button></noscript>
        </form>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($statusOpts as $k => $lbl): ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold <?= $badge[$k] ?>"><?= esc($lbl) ?>: <?= (int) ($ringkas[$k] ?? 0) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (! $hariAktif): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Tanggal ini bukan hari sekolah aktif.</div>
<?php elseif (! $recorded): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
        <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <p class="text-slate-500 font-semibold">Absensi tanggal ini belum tersedia.</p>
        <p class="text-sm text-slate-400 mt-1">Data kehadiran akan tampil setelah diinput oleh pihak sekolah.</p>
    </div>
<?php elseif ($total === 0): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">Belum ada jadwal pada hari <?= esc($hariNama) ?>.</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($grup as $g): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50">
                <h3 class="font-bold text-slate-800"><?= esc($g['nama']) ?></h3>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($g['sesi'] as $s): ?>
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                        <div class="sm:w-56 shrink-0">
                            <p class="text-sm font-bold text-slate-700">Jam <?= esc($s['jam_ke']) ?>
                                <span class="text-slate-400 font-normal text-xs">(<?= esc(substr($s['waktu_mulai'], 0, 5)) ?>–<?= esc(substr($s['waktu_selesai'], 0, 5)) ?>)</span>
                            </p>
                            <p class="text-sm text-slate-600"><?= esc($s['nama_kelas']) ?> &middot; <span class="font-semibold"><?= esc($s['nama_mapel']) ?></span></p>
                        </div>
                        <div class="flex items-center flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-bold <?= $badge[$s['status']] ?? $badge['hadir'] ?>"><?= esc($statusOpts[$s['status']] ?? $s['status']) ?></span>
                            <?php if ($s['jam_masuk'] !== ''): ?>
                                <span class="text-xs text-slate-500">masuk <?= esc($s['jam_masuk']) ?></span>
                            <?php endif; ?>
                            <?php if ($s['keterangan'] !== ''): ?>
                                <span class="text-xs text-slate-400 italic">“<?= esc($s['keterangan']) ?>”</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
