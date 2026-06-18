<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dash_kurikulum',
    'helpTitle' => 'Dashboard Kurikulum',
    'helpBody'  => '<p>Ringkasan kondisi penjadwalan: jumlah data master, total JP terjadwal, bentrok, serta guru yang kekurangan / kelebihan jam (dibanding Maks Beban). Grafik menampilkan beban tiap guru.</p>',
]) ?>

<?php
$cards = [
    ['Total Guru', $guru, 'bg-brand-600', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z'],
    ['Total Kelas', $kelas, 'bg-indigo-600', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5'],
    ['Total Mapel', $mapel, 'bg-teal-600', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253'],
    ['JP Terjadwal', $jpTerjadwal, 'bg-emerald-600', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
];
?>

<!-- Kartu utama -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <?php foreach ($cards as [$label, $val, $bg, $icon]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500"><?= $label ?></p>
                <p class="text-3xl font-extrabold text-slate-800 mt-1"><?= (int) $val ?></p>
            </div>
            <span class="flex h-12 w-12 items-center justify-center rounded-xl <?= $bg ?> text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<!-- Kartu status -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    <a href="<?= site_url('admin/kurikulum/bentrok') ?>" class="rounded-2xl border p-5 transition hover:shadow <?= $bentrok > 0 ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200' ?>">
        <p class="text-sm <?= $bentrok > 0 ? 'text-red-600' : 'text-emerald-700' ?>">Total Bentrok / Masalah</p>
        <p class="text-3xl font-extrabold mt-1 <?= $bentrok > 0 ? 'text-red-700' : 'text-emerald-700' ?>"><?= (int) $bentrok ?></p>
        <p class="text-xs mt-1 <?= $bentrok > 0 ? 'text-red-500' : 'text-emerald-600' ?>"><?= $bentrok > 0 ? 'Klik untuk periksa →' : 'Tidak ada bentrok 🎉' ?></p>
    </a>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <p class="text-sm text-amber-600">Guru Kurang Jam</p>
        <p class="text-3xl font-extrabold text-amber-700 mt-1"><?= (int) $kurang ?></p>
        <p class="text-xs text-amber-500 mt-1">Beban &lt; Maks Beban</p>
    </div>
    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
        <p class="text-sm text-orange-600">Guru Lebih Jam</p>
        <p class="text-3xl font-extrabold text-orange-700 mt-1"><?= (int) $lebih ?></p>
        <p class="text-xs text-orange-500 mt-1">Beban &gt; Maks Beban</p>
    </div>
</div>

<!-- Grafik beban guru -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <h2 class="font-bold text-slate-800 mb-4">Beban Mengajar per Guru <span class="text-slate-400 font-normal text-sm">(12 terbanyak)</span></h2>
    <?php if (empty($chart)): ?>
        <p class="text-slate-400 text-sm py-8 text-center">Belum ada data beban. Atur Penugasan terlebih dahulu.</p>
    <?php else: ?>
        <div class="relative" style="height:340px"><canvas id="chartBeban"></canvas></div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (! empty($chart)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const data = <?= json_encode($chart) ?>;
    const labels = data.map(d => d.kode_guru + ' · ' + (d.nama.length > 14 ? d.nama.slice(0, 14) + '…' : d.nama));
    const vals   = data.map(d => parseInt(d.total_jp));
    const colors = data.map(d => {
        const t = parseInt(d.total_jp), m = parseInt(d.max_beban);
        if (t > m) return '#ea580c';     // lebih (oranye)
        if (t < m) return '#f59e0b';     // kurang (amber)
        return '#059669';                // pas (hijau)
    });
    new Chart(document.getElementById('chartBeban'), {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Total JP', data: vals, backgroundColor: colors, borderRadius: 6 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
