<?php
/**
 * Laporan Laboratorium (rekap gabungan).
 *
 * @var string $dari
 * @var string $sampai
 * @var int    $asetTotal
 * @var array  $asetKondisi
 * @var array  $asetStatus
 * @var array  $asetPerLab
 * @var int    $pmTotal
 * @var array  $pmStatus
 * @var int    $pmSedang
 * @var int    $pmTerlambat
 * @var int    $krTotal
 * @var array  $krStatus
 * @var int    $pbTotal
 * @var array  $pbJenis
 * @var float  $pbBiaya
 * @var int    $spTotal
 * @var array  $spMenipis
 * @var int    $spMenipisCount
 * @var int    $jrTotal
 * @var array  $jrPerLab
 */
$rp   = static fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
$qs   = 'dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai);
$base = site_url('admin/laporan-lab');
$tile = function (string $label, $value, string $color = 'text-slate-800') {
    return '<div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-2xl font-extrabold ' . $color . '">' . $value . '</div><div class="text-xs text-slate-500 mt-0.5">' . esc($label) . '</div></div>';
};
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'laporan_lab',
    'helpTitle' => 'Laporan Laboratorium',
    'helpBody'  => '<p>Rekap gabungan seluruh modul lab: aset & kondisinya, peminjaman, kerusakan/perbaikan, stok sparepart menipis, dan pemakaian lab. Angka aset & sparepart bersifat <b>kini</b> (snapshot); peminjaman/kerusakan/perbaikan/jurnal mengikuti <b>rentang tanggal</b>.</p>
        <p class="mt-1">Gunakan <b>Export PDF/Excel</b> untuk arsip atau laporan ke pimpinan.</p>',
]) ?>

<!-- Filter + export -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
        <form method="get" class="flex items-end gap-2 flex-wrap">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Dari</label>
                <input type="date" name="dari" value="<?= esc($dari) ?>" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Sampai</label>
                <input type="date" name="sampai" value="<?= esc($sampai) ?>" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Terapkan</button>
        </form>
        <div class="flex-1"></div>
        <div class="flex items-center gap-2">
            <a href="<?= $base ?>/pdf?<?= $qs ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 text-sm font-semibold px-3.5 py-2.5">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Cetak PDF
            </a>
            <a href="<?= $base ?>/excel?<?= $qs ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 text-sm font-semibold px-3.5 py-2.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/></svg>
                Unduh Excel
            </a>
        </div>
    </div>
</div>

<!-- Aset -->
<h2 class="font-bold text-slate-800 mb-3">Aset / Inventaris <span class="text-slate-400 font-normal text-sm">(kini)</span></h2>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <?= $tile('Total Aset', (int) $asetTotal, 'text-brand-700') ?>
    <?= $tile('Tersedia', (int) $asetStatus['tersedia'], 'text-emerald-600') ?>
    <?= $tile('Dipinjam', (int) $asetStatus['dipinjam'], 'text-sky-600') ?>
    <?= $tile('Perbaikan', (int) $asetStatus['perbaikan'], 'text-amber-600') ?>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">Kondisi Aset</h3>
        <div class="space-y-2">
            <?php foreach (['baik' => 'bg-emerald-500', 'rusak_ringan' => 'bg-amber-500', 'rusak_berat' => 'bg-red-500'] as $k => $warna):
                $v = (int) ($asetKondisi[$k] ?? 0);
                $frac = $asetTotal > 0 ? max(3, round($v / $asetTotal * 100)) : 0; ?>
                <div class="flex items-center gap-3">
                    <div class="w-24 text-xs text-slate-500"><?= ucfirst(str_replace('_', ' ', $k)) ?></div>
                    <div class="flex-1 h-3 rounded-full bg-slate-100 overflow-hidden"><div class="h-3 <?= $warna ?>" style="width: <?= $frac ?>%"></div></div>
                    <div class="w-8 text-right text-sm font-bold text-slate-700"><?= $v ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">Aset per Lab</h3>
        <?php if (empty($asetPerLab)): ?>
            <p class="text-sm text-slate-400">Belum ada aset.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($asetPerLab as $row): ?>
                        <tr><td class="py-1.5 text-slate-600"><?= $row['lab_nama'] !== null ? esc($row['lab_nama']) : '<span class="text-slate-400">(tanpa lab)</span>' ?></td><td class="py-1.5 text-right font-bold text-slate-700"><?= (int) $row['c'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Peminjaman -->
<h2 class="font-bold text-slate-800 mb-3">Peminjaman <span class="text-slate-400 font-normal text-sm">(<?= esc($dari) ?> s/d <?= esc($sampai) ?>)</span></h2>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
    <?= $tile('Total periode', (int) $pmTotal, 'text-brand-700') ?>
    <?= $tile('Dikembalikan', (int) $pmStatus['dikembalikan'], 'text-emerald-600') ?>
    <?= $tile('Hilang', (int) $pmStatus['hilang'], 'text-red-600') ?>
    <?= $tile('Sedang dipinjam', (int) $pmSedang, 'text-sky-600') ?>
    <?= $tile('Terlambat (kini)', (int) $pmTerlambat, 'text-red-600') ?>
    <?= $tile('Dipinjam (periode)', (int) $pmStatus['dipinjam']) ?>
</div>

<!-- Kerusakan & Perbaikan -->
<h2 class="font-bold text-slate-800 mb-3">Kerusakan &amp; Perbaikan <span class="text-slate-400 font-normal text-sm">(periode)</span></h2>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
    <?= $tile('Total kerusakan', (int) $krTotal, 'text-amber-600') ?>
    <?= $tile('Selesai', (int) $krStatus['selesai'], 'text-emerald-600') ?>
    <?= $tile('Total perbaikan', (int) $pbTotal, 'text-sky-600') ?>
    <?= $tile('Biaya perbaikan', $rp($pbBiaya), 'text-slate-800') ?>
</div>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-8">
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
        <?php foreach ($pbJenis as $k => $v): ?>
            <div class="flex items-center justify-between"><span class="text-slate-500"><?= esc(ucfirst($k)) ?></span><span class="font-bold text-slate-700"><?= (int) $v ?></span></div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Sparepart & Jurnal -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">Sparepart <span class="text-slate-400 font-normal">(kini)</span></h3>
        <div class="flex gap-3 mb-3">
            <?= $tile('Total jenis', (int) $spTotal) ?>
            <?= $tile('Stok menipis', (int) $spMenipisCount, $spMenipisCount > 0 ? 'text-red-600' : 'text-slate-800') ?>
        </div>
        <?php if (! empty($spMenipis)): ?>
            <table class="w-full text-sm">
                <thead><tr class="text-slate-400 text-xs text-left"><th class="py-1">Sparepart</th><th class="py-1 text-right">Stok</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($spMenipis as $s): ?>
                        <tr><td class="py-1.5 text-slate-600"><?= esc($s['nama']) ?></td><td class="py-1.5 text-right"><span class="font-bold text-red-600"><?= (int) $s['stok'] ?></span> <span class="text-slate-400 text-xs">/<?= (int) $s['stok_minimum'] ?> <?= esc($s['satuan']) ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-sm text-emerald-600">Semua stok aman.</p>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">Pemakaian Lab <span class="text-slate-400 font-normal">(jurnal, periode)</span></h3>
        <div class="mb-3"><?= $tile('Total sesi', (int) $jrTotal, 'text-brand-700') ?></div>
        <?php if (! empty($jrPerLab)): ?>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($jrPerLab as $row): ?>
                        <tr><td class="py-1.5 text-slate-600"><?= $row['lab_nama'] !== null ? esc($row['lab_nama']) : '<span class="text-slate-400">(tanpa lab)</span>' ?></td><td class="py-1.5 text-right font-bold text-slate-700"><?= (int) $row['c'] ?> sesi</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-sm text-slate-400">Belum ada catatan pemakaian pada periode ini.</p>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
