<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dashboard',
    'helpTitle' => 'Selamat datang di Sistem Akademik Sekolah',
    'helpBody'  => '<p>Satu pusat kendali: <b>Penjadwalan KBM</b> (kurikulum) dan <b>Kesediaan Guru Mengajar</b>.</p>
        <p class="mt-1"><b>Alur:</b> Master Data (Guru → Mapel → Jurusan → Kelas) ▸ Penugasan (JP) ▸ Hari & Jam ▸ Ketersediaan ▸ Jadwal KBM (manual / generate otomatis) ▸ Laporan.</p>',
]) ?>

<!-- ================= HIGHLIGHT: ABSENSI GURU HARI INI ================= -->
<?php
$tglLabel = date('d/m/Y', strtotime($absensi['tanggal']));
$absChips = [
    ['Hadir', $absensi['ringkas']['hadir'], 'bg-emerald-500'],
    ['Telat', $absensi['ringkas']['telat'], 'bg-amber-400'],
    ['Izin',  $absensi['ringkas']['izin'],  'bg-sky-400'],
    ['Sakit', $absensi['ringkas']['sakit'], 'bg-violet-400'],
    ['Alpa',  $absensi['ringkas']['alpa'],  'bg-red-500'],
];
?>
<div class="rounded-2xl bg-brand-700 p-5 sm:p-6 mb-6 text-white">
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </span>
                <div>
                    <p class="font-extrabold text-[15px] leading-tight">Absensi Guru Hari Ini</p>
                    <p class="text-xs text-white/70"><?= esc($absensi['hari_nama']) ?>, <?= $tglLabel ?> &middot; <?= (int) $absensi['total_guru'] ?> guru mengajar</p>
                </div>
            </div>

            <?php if (! $absensi['hari_aktif']): ?>
                <p class="mt-3 text-sm text-white/80">Hari ini bukan hari KBM aktif — tidak ada sesi untuk diabsen.</p>
            <?php elseif (! $absensi['recorded']): ?>
                <p class="mt-3 text-sm text-white/80">Kehadiran guru <b class="text-white">belum dicatat</b>. Catat sekarang agar rekap bulan ini akurat.</p>
            <?php else: ?>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?php foreach ($absChips as [$label, $val, $dot]): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/12 px-3 py-1 text-xs font-semibold">
                            <span class="h-2 w-2 rounded-full <?= $dot ?>"></span><?= $label ?> <?= (int) $val ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($absensi['hari_aktif']): ?>
            <a href="<?= site_url('admin/absensi') ?>?tanggal=<?= $absensi['tanggal'] ?>"
               class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-brand-700 hover:bg-brand-50 transition">
                <?= $absensi['recorded'] ? 'Lihat / Ubah Absensi' : 'Catat Absensi Sekarang' ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M6 12h12"/></svg>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php
$kurCards = [
    ['Total Guru', $kur['guru'], 'bg-brand-600', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z'],
    ['Total Kelas', $kur['kelas'], 'bg-indigo-600', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5'],
    ['Total Mapel', $kur['mapel'], 'bg-teal-600', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253'],
    ['JP Terjadwal', $kur['jpTerjadwal'], 'bg-emerald-600', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
];
?>

<!-- ================= PENJADWALAN ================= -->
<div class="flex items-center gap-2 mb-3">
    <span class="h-5 w-1.5 rounded-full bg-brand-600"></span>
    <h2 class="font-bold text-slate-800">Penjadwalan KBM</h2>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php foreach ($kurCards as [$label, $value, $color, $icon]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500"><?= $label ?></p>
                <p class="mt-1 text-2xl font-extrabold text-slate-800"><?= (int) $value ?></p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl <?= $color ?> text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
    <a href="<?= site_url('admin/kurikulum/bentrok') ?>" class="rounded-2xl border p-5 transition hover:shadow <?= $kur['bentrok'] > 0 ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200' ?>">
        <p class="text-sm <?= $kur['bentrok'] > 0 ? 'text-red-600' : 'text-emerald-700' ?>">Total Bentrok / Masalah</p>
        <p class="text-3xl font-extrabold mt-1 <?= $kur['bentrok'] > 0 ? 'text-red-700' : 'text-emerald-700' ?>"><?= (int) $kur['bentrok'] ?></p>
        <p class="text-xs mt-1 <?= $kur['bentrok'] > 0 ? 'text-red-500' : 'text-emerald-600' ?>"><?= $kur['bentrok'] > 0 ? 'Klik untuk periksa →' : 'Tidak ada bentrok 🎉' ?></p>
    </a>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <p class="text-sm text-amber-600">Guru Kurang Jam</p>
        <p class="text-3xl font-extrabold text-amber-700 mt-1"><?= (int) $kur['kurang'] ?></p>
    </div>
    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
        <p class="text-sm text-orange-600">Guru Lebih Jam</p>
        <p class="text-3xl font-extrabold text-orange-700 mt-1"><?= (int) $kur['lebih'] ?></p>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mt-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-slate-800">Beban Mengajar per Guru <span class="text-slate-400 font-normal text-sm">(12 terbanyak)</span></h3>
        <a href="<?= site_url('admin/kurikulum/rekap') ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Rekap lengkap →</a>
    </div>
    <?php if (empty($kur['chart'])): ?>
        <p class="text-slate-400 text-sm py-8 text-center">Belum ada data beban. Atur Penugasan terlebih dahulu.</p>
    <?php else: ?>
        <div class="relative" style="height:320px"><canvas id="chartBeban"></canvas></div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
    <a href="<?= site_url('admin/absensi') ?>" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow transition flex items-center gap-4">
        <span class="shrink-0 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </span>
        <div class="min-w-0">
            <p class="font-bold text-slate-800 group-hover:text-brand-700">Absensi Guru Harian</p>
            <p class="text-sm text-slate-400">Catat kehadiran per sesi mengajar &rarr;</p>
        </div>
    </a>
    <a href="<?= site_url('admin/absensi/rekap') ?>" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow transition flex items-center gap-4">
        <span class="shrink-0 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </span>
        <div class="min-w-0">
            <p class="font-bold text-slate-800 group-hover:text-brand-700">Rekap Absensi</p>
            <p class="text-sm text-slate-400">Rekap kehadiran untuk gaji &rarr;</p>
        </div>
    </a>
</div>

<!-- ================= KESEDIAAN GURU ================= -->
<div class="flex items-center gap-2 mb-3 mt-8">
    <span class="h-5 w-1.5 rounded-full bg-gold-500"></span>
    <h2 class="font-bold text-slate-800">Kesediaan Guru Mengajar</h2>
</div>

<?php
$ksCards = [
    ['Total Kesediaan', $stats['total'], 'bg-brand-600', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ['Masuk Hari Ini',  $stats['today'], 'bg-green-600', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['Status Form', $setting['form_open'] ? 'Dibuka' : 'Ditutup', $setting['form_open'] ? 'bg-emerald-600' : 'bg-amber-500', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['Tahun Pelajaran', $setting['academic_year'], 'bg-brand-700', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
];
?>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php foreach ($ksCards as [$label, $value, $color, $icon]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500"><?= $label ?></p>
                <p class="mt-1 text-2xl font-extrabold text-slate-800"><?= esc($value) ?></p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl <?= $color ?> text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-4">
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h3 class="font-bold text-slate-800 mb-4">Rekap Status Kepegawaian</h3>
        <div class="space-y-3">
            <?php $colors = ['PNS'=>'bg-brand-600','PPPK'=>'bg-emerald-500','GTY'=>'bg-gold-500','GTT'=>'bg-slate-400'];
            $maxVal = max(1, max($stats['byStatus'] ?: [1])); ?>
            <?php foreach ($stats['byStatus'] as $st => $count): ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium text-slate-600"><?= $st ?></span>
                        <span class="font-semibold text-slate-800"><?= $count ?> guru</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full <?= $colors[$st] ?> rounded-full" style="width: <?= round($count / $maxVal * 100) ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <a href="<?= site_url('admin/submissions') ?>" class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800">Lihat semua data &rarr;</a>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">Kesediaan Terbaru</h3>
            <a href="<?= site_url('admin/submissions') ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Semua</a>
        </div>
        <?php if (empty($recent)): ?>
            <div class="p-10 text-center text-slate-400 text-sm">Belum ada data kesediaan masuk.</div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recent as $r): ?>
                    <a href="<?= site_url('admin/submissions/view/' . $r['id']) ?>" class="flex items-center gap-4 px-6 py-3.5 hover:bg-slate-50 transition">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-bold text-sm"><?= strtoupper(substr($r['nama_lengkap'], 0, 1)) ?></div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-700 text-sm truncate"><?= esc($r['nama_lengkap']) ?></p>
                            <p class="text-xs text-slate-400">NIP/NUPTK: <?= esc($r['nip_nuptk']) ?> &middot; <?= esc($r['guru_mapel'] ?: '-') ?></p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500"><?= esc($r['status_kepegawaian']) ?></span>
                        <span class="shrink-0 text-xs text-slate-400 hidden sm:block"><?= date('d/m/y H:i', strtotime($r['created_at'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (! empty($kur['chart'])): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const data = <?= json_encode($kur['chart']) ?>;
    const labels = data.map(d => d.kode_guru + ' · ' + (d.nama.length > 14 ? d.nama.slice(0, 14) + '…' : d.nama));
    const vals   = data.map(d => parseInt(d.total_jp));
    const colors = data.map(d => {
        const t = parseInt(d.total_jp), m = parseInt(d.max_beban);
        if (t > m) return '#ea580c';
        if (t < m) return '#f59e0b';
        return '#059669';
    });
    new Chart(document.getElementById('chartBeban'), {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Total JP', data: vals, backgroundColor: colors, borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
