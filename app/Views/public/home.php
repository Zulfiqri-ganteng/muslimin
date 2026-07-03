<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<?php $formOpen = ! empty($setting['form_open']); ?>

<!-- HERO -->
<section class="rounded-3xl bg-gradient-to-br from-brand-700 to-brand-900 text-white p-8 sm:p-12 shadow-lg">
    <div class="max-w-3xl">
        <p class="text-brand-200 font-semibold mb-2">Selamat datang di</p>
        <h1 class="text-3xl sm:text-5xl font-extrabold leading-tight"><?= esc($setting['school_name'] ?? 'Sekolah') ?></h1>
        <?php if (! empty($setting['academic_year'])): ?>
            <p class="mt-3 text-brand-100">Sistem Informasi Akademik &middot; Tahun Ajaran <?= esc($setting['academic_year']) ?></p>
        <?php endif; ?>

        <?php if (! empty($now['label'])): ?>
            <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm text-white">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Sekarang: <?= esc($now['hariNama']) ?>, <?= esc($now['label']) ?>
            </p>
        <?php elseif (! empty($now['hariNama'])): ?>
            <p class="mt-3 text-sm text-brand-200">Hari ini: <?= esc($now['hariNama']) ?></p>
        <?php endif; ?>

        <?php if ($jadwalOn): ?>
            <!-- Pencarian jadwal kelas cepat -->
            <form action="<?= site_url('jadwal-kelas') ?>" method="get" class="mt-7 flex flex-col sm:flex-row gap-2 max-w-xl">
                <select name="kelas_id" class="flex-1 rounded-xl border-0 px-4 py-3 text-slate-700 text-sm focus:ring-2 focus:ring-gold-400 outline-none">
                    <option value="">— Pilih kelas untuk lihat jadwal —</option>
                    <?php foreach ($kelasOpts as $id => $label): ?>
                        <option value="<?= $id ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="rounded-xl bg-gold-400 hover:bg-gold-500 text-brand-900 font-bold px-6 py-3 text-sm transition">Lihat Jadwal</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if (! empty($pengumuman)): ?>
    <!-- PENGUMUMAN -->
    <section class="mt-6">
        <h2 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-gold-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
            Pengumuman
        </h2>
        <div class="space-y-3">
            <?php foreach ($pengumuman as $p): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-bold text-slate-800"><?= esc($p['judul']) ?></h3>
                        <span class="text-xs text-slate-400 shrink-0"><?= esc(date('d M Y', strtotime($p['created_at']))) ?></span>
                    </div>
                    <p class="text-sm text-slate-600 mt-1.5 whitespace-pre-line"><?= esc($p['isi']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- STATISTIK -->
<section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
    <?php
    $cards = [
        ['Guru', $stats['guru'], 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z'],
        ['Kelas', $stats['kelas'], 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5'],
        ['Mata Pelajaran', $stats['mapel'], 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253'],
    ];
    foreach ($cards as [$label, $val, $icon]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>" />
                </svg>
            </span>
            <div>
                <p class="text-2xl font-extrabold text-slate-800"><?= (int) $val ?></p>
                <p class="text-sm text-slate-500"><?= $label ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<!-- AKSI CEPAT + INFO -->
<section class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-6">
    <a href="<?= site_url('jadwal-kelas') ?>" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition group">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-white mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg></span>
        <h3 class="font-bold text-slate-800 group-hover:text-brand-700">Jadwal per Kelas</h3>
        <p class="text-sm text-slate-500 mt-1">Lihat jadwal pelajaran mingguan tiap kelas.</p>
    </a>
    <a href="<?= site_url('jadwal-guru') ?>" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition group">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600 text-white mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z" />
            </svg></span>
        <h3 class="font-bold text-slate-800 group-hover:text-emerald-700">Jadwal per Guru</h3>
        <p class="text-sm text-slate-500 mt-1">Lihat jadwal mengajar tiap guru.</p>
    </a>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h3 class="font-bold text-slate-800 mb-2">Info Sekolah</h3>
        <ul class="text-sm text-slate-500 space-y-1.5">
            <?php if (! empty($setting['address'])): ?><li>📍 <?= esc($setting['address']) ?></li><?php endif; ?>
            <?php if (! empty($setting['phone'])): ?><li>📞 <?= esc($setting['phone']) ?></li><?php endif; ?>
            <?php if (! empty($setting['email'])): ?><li>✉️ <?= esc($setting['email']) ?></li><?php endif; ?>
            <?php if (! empty($setting['website'])): ?><li>🌐 <?= esc($setting['website']) ?></li><?php endif; ?>
            <?php if (empty($setting['address']) && empty($setting['phone']) && empty($setting['email'])): ?>
                <li class="text-slate-400">Lengkapi info sekolah di panel admin.</li>
            <?php endif; ?>
        </ul>
        <?php if ($formOpen): ?>
            <a href="<?= site_url('isi') ?>" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800">Isi Form Kesediaan Guru &rarr;</a>
        <?php endif; ?>
    </div>
</section>

<?= $this->endSection() ?>