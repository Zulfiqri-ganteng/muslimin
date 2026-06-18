<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dashboard',
    'helpTitle' => 'Selamat datang di Sistem Akademik Sekolah',
    'helpBody'  => '<p>Pusat kendali kurikulum sekolah. Ringkasan data & statistik tampil di bawah.</p>
        <p class="mt-1"><b>Alur kerja yang disarankan:</b> 1) Isi <b>Master Data</b> (Guru → Mata Pelajaran → Jurusan → Kelas) ▸ 2) Atur <b>Penugasan</b> (siapa mengajar apa & berapa JP) ▸ 3) Atur <b>Hari & Jam</b> ▸ 4) Susun <b>Jadwal KBM</b> (tahap berikutnya).</p>',
]) ?>

<?php
    $cards = [
        ['Total Kesediaan', $stats['total'], 'bg-brand-600', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8z'],
        ['Masuk Hari Ini',  $stats['today'], 'bg-green-600', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['Status Sekolah', $setting['form_open'] ? 'Form Dibuka' : 'Form Ditutup', $setting['form_open'] ? 'bg-emerald-600' : 'bg-amber-500', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Tahun Pelajaran', $setting['academic_year'], 'bg-brand-700', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
    ];
?>

<!-- Kartu Statistik -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php foreach ($cards as [$label, $value, $color, $icon]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500"><?= $label ?></p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-800"><?= esc($value) ?></p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl <?= $color ?> text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-5">
    <!-- Rekap Status Kepegawaian -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h2 class="font-bold text-slate-800 mb-4">Rekap Status Kepegawaian</h2>
        <div class="space-y-3">
            <?php $colors = ['PNS'=>'bg-brand-600','PPPK'=>'bg-emerald-500','GTY'=>'bg-gold-500','GTT'=>'bg-slate-400'];
            $maxVal = max(1, max($stats['byStatus'])); ?>
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
        <a href="<?= site_url('admin/submissions') ?>" class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800">
            Lihat semua data &rarr;
        </a>
    </div>

    <!-- Kesediaan Terbaru -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Kesediaan Terbaru</h2>
            <a href="<?= site_url('admin/submissions') ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Semua</a>
        </div>
        <?php if (empty($recent)): ?>
            <div class="p-10 text-center text-slate-400 text-sm">Belum ada data kesediaan masuk.</div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recent as $r): ?>
                    <a href="<?= site_url('admin/submissions/view/' . $r['id']) ?>" class="flex items-center gap-4 px-6 py-3.5 hover:bg-slate-50 transition">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-bold text-sm">
                            <?= strtoupper(substr($r['nama_lengkap'], 0, 1)) ?>
                        </div>
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
