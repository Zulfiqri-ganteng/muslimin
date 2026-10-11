<?php
/**
 * Dashboard Admin (dirombak 2026-10-12): sedikit kotak, lebih banyak grafik beranimasi.
 * Animasi: .rise (muncul berurutan), .bar-grow / .col-grow (batang tumbuh), .donut-seg (donat tergambar),
 * [data-hitung] (angka berhitung naik, admin/dashboard.js). Semua angka tetap tertulis di HTML —
 * tanpa JS / dengan "kurangi gerak" tampilannya sama, hanya tanpa gerak.
 * Warna: satu warna brand untuk besaran; donat kepegawaian = 4 warna kategori yang lolos uji buta-warna;
 * warna status (merah/kuning) selalu disertai ikon + teks.
 *
 * @var array  $kur      Kurikulum::dashboardData()
 * @var array  $stats    statistik kesediaan
 * @var array  $setting
 * @var list<array> $recent
 * @var array  $absensi  Absensi::ringkasHarian()
 * @var array  $ujian    UjianReport::dashboard()
 * @var array  $siswa    aktif, tanpa_kelas, jurusan[], tingkat[]
 * @var int    $jpTugas  total JP penugasan
 * @var list<array> $tindakan
 * @var string $nama
 */
$angka = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$jam   = (int) date('G');
$salam = $jam < 4 ? 'Selamat malam' : ($jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam')));
$hariIni = ucfirst(mb_strtolower((string) $absensi['hari_nama'])); // data hari tersimpan HURUF BESAR ("SENIN")
$namaDepan = trim(explode(',', $nama)[0]);

// Absensi hari ini
$abs      = $absensi['ringkas'];
$absTotal = array_sum($abs);
$persenHadir = $absTotal > 0 ? (int) round(($abs['hadir'] + $abs['telat']) / $absTotal * 100) : 0;

// Beban per guru: skala bersama = nilai terbesar di antara JP dan batasnya.
$beban = $kur['chart'] ?? [];
$skala = 1;
foreach ($beban as $b) {
    $skala = max($skala, (int) $b['total_jp'], (int) $b['max_beban']);
}

// Penjadwalan
$jpJadwal  = (int) $kur['jpTerjadwal'];
$persenJp  = $jpTugas > 0 ? min(100, (int) round($jpJadwal / $jpTugas * 100)) : 0;
$guruPas   = max(0, (int) $kur['guru'] - (int) $kur['kurang'] - (int) $kur['lebih']);
$persenPas = (int) $kur['guru'] > 0 ? (int) round($guruPas / (int) $kur['guru'] * 100) : 0;

// Siswa
$maxJurusan = max(1, ...array_map(static fn ($r) => (int) $r['n'], $siswa['jurusan'] ?: [['n' => 1]]));
$maxTingkat = max(1, ...array_map(static fn ($r) => (int) $r['n'], $siswa['tingkat'] ?: [['n' => 1]]));

// Kesediaan: donat status kepegawaian (urutan warna tetap = urutan status, bukan peringkat).
$warnaPegawai = ['PNS' => '#2a78d6', 'PPPK' => '#eb6834', 'GTY' => '#1baf7a', 'GTT' => '#eda100'];
$totalPegawai = array_sum($stats['byStatus'] ?: []);
$keliling     = 2 * M_PI * 52; // r = 52

// Ujian: status → tampilan langkah
$ujLangkah = [
    'draft'    => ['Draft', 'bg-white text-slate-400 ring-slate-200', 'text-slate-400'],
    'berjalan' => ['Berjalan', 'bg-emerald-500 text-white ring-emerald-200 ring-pulse', 'text-emerald-700'],
    'selesai'  => ['Selesai', 'bg-brand-600 text-white ring-brand-200', 'text-brand-700'],
];
$ujAktif = 0;
foreach ($ujian['kartu'] as $i => $u) {
    if ($u['status'] !== 'draft') {
        $ujAktif = $i + 1;
    }
}
$ikonTindakan = [
    'kritis' => ['text-red-600 bg-red-50', 'M12 9v3.75m0 3.75h.008M10.34 3.94 1.82 18a1.875 1.875 0 0 0 1.6 2.82h17.16a1.875 1.875 0 0 0 1.6-2.82L13.66 3.94a1.875 1.875 0 0 0-3.32 0Z'],
    'awas'   => ['text-amber-600 bg-amber-50', 'M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z'],
    'info'   => ['text-brand-600 bg-brand-50', 'M11.25 11.25l.04-.02a.75.75 0 0 1 1.06.85l-.7 2.84a.75.75 0 0 0 1.06.85l.04-.02M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z'],
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dashboard_v3',
    'helpTitle' => 'Selamat datang di Sistem Akademik Sekolah',
    'helpBody'  => '<p>Satu pusat kendali: <b>Penjadwalan KBM</b>, <b>Absensi</b>, <b>Data Siswa</b>, <b>Ujian</b>, dan <b>Kesediaan Guru Mengajar</b>.</p>
        <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><b>Perlu tindakan</b> — daftar pekerjaan yang menunggu (bentrok jadwal, absensi belum dicatat, ajuan PKL &amp; surat menunggu ACC, susulan ujian, siswa tanpa kelas). Klik untuk langsung membukanya. Bila kosong, semuanya beres.</li>
        <li><b>Beban mengajar</b> — batang = JP guru, garis tegak = batas maksimalnya. Arahkan kursor ke batang untuk rinciannya.</li>
        <li>Angka penjadwalan diperbarui paling lama tiap 30 menit; angka siswa tiap 5 menit.</li>
        </ul>
        <p class="mt-1"><b>Alur penjadwalan:</b> Master Data (Guru → Mapel → Jurusan → Kelas) ▸ Penugasan (JP) ▸ Hari &amp; Jam ▸ Ketersediaan ▸ Jadwal KBM ▸ Laporan.</p>',
]) ?>

<!-- ================= SAMBUTAN + ANGKA UTAMA ================= -->
<section class="rise rise-1 relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 via-brand-700 to-brand-800 p-5 text-white shadow-lg sm:p-7">
    <div class="hero-grid pointer-events-none absolute inset-0"></div>
    <div class="orb orb-float -right-16 -top-20 h-64 w-64 bg-brand-400/40"></div>
    <div class="orb orb-float -bottom-24 left-1/3 h-56 w-56 bg-emerald-300/20" style="animation-delay:-4s"></div>

    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-white/70"><?= esc($hariIni) ?>,<?= esc(\App\Libraries\IsianBantu::tanggalIndo($absensi['tanggal'])) ?> &middot; TP <?= esc($setting['academic_year']) ?></p>
            <h1 class="mt-1 text-2xl font-extrabold leading-tight sm:text-3xl"><?= esc($salam) ?>, <?= esc($namaDepan) ?> 👋</h1>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <?php if (! $absensi['hari_aktif']): ?>
                    <span class="rounded-full bg-white/10 px-3 py-1.5 text-sm text-white/85">Hari ini bukan hari KBM aktif — tidak ada sesi untuk diabsen.</span>
                <?php elseif (! $absensi['recorded']): ?>
                    <span class="inline-flex items-center gap-2 rounded-full bg-amber-400/20 px-3 py-1.5 text-sm font-semibold text-amber-100 ring-1 ring-amber-300/40">
                        <span class="h-2 w-2 rounded-full bg-amber-300"></span>Absensi <?= (int) $absensi['total_guru'] ?> guru hari ini belum dicatat
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-baseline gap-1.5 rounded-full bg-white/10 px-3 py-1 text-sm">
                        <b class="text-lg font-extrabold" data-hitung="<?= $persenHadir ?>" data-akhiran="%"><?= $persenHadir ?>%</b> guru hadir
                    </span>
                    <?php foreach (['hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'] as $k => $lbl): ?>
                        <?php if ((int) $abs[$k] > 0): ?>
                            <span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold text-white/90"><?= $lbl ?> <?= (int) $abs[$k] ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($absensi['hari_aktif']): ?>
                    <a href="<?= site_url('admin/absensi') ?>?tanggal=<?= esc($absensi['tanggal'], 'url') ?>"
                       class="btn-shine inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-1.5 text-sm font-bold text-brand-700 transition hover:bg-brand-50">
                        <?= $absensi['recorded'] ? 'Lihat / ubah absensi' : 'Catat sekarang' ?>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M6 12h12"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <dl class="grid shrink-0 grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4 sm:gap-x-0 sm:divide-x sm:divide-white/15">
            <?php foreach ([
                ['Guru pengajar', $kur['guru'], 'admin/master/guru'],
                ['Kelas', $kur['kelas'], 'admin/master/kelas'],
                ['Siswa aktif', $siswa['aktif'], 'admin/master/siswa'],
                ['Mata pelajaran', $kur['mapel'], 'admin/master/mapel'],
            ] as [$lbl, $n, $url]): ?>
                <a href="<?= site_url($url) ?>" class="group block sm:px-6 sm:first:pl-0 sm:last:pr-0">
                    <dt class="text-xs font-medium text-white/65 group-hover:text-white"><?= $lbl ?></dt>
                    <dd class="mt-0.5 text-3xl font-extrabold tracking-tight" data-hitung="<?= (int) $n ?>"><?= $angka($n) ?></dd>
                </a>
            <?php endforeach; ?>
        </dl>
    </div>
</section>

<!-- ================= BEBAN MENGAJAR + PERLU TINDAKAN ================= -->
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <section class="rise rise-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="font-bold text-slate-800">Beban mengajar per guru</h2>
                <p class="text-xs text-slate-500">12 terbanyak &middot; batang = JP penugasan, <span class="inline-block h-3 w-0.5 translate-y-0.5 rounded bg-slate-800"></span> = batas maksimal</p>
            </div>
            <a href="<?= site_url('admin/kurikulum/rekap') ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Rekap lengkap →</a>
        </div>
        <?php if ($beban === []): ?>
            <p class="py-10 text-center text-sm text-slate-400">Belum ada data beban. Atur Penugasan terlebih dahulu.</p>
        <?php else: ?>
            <ul class="space-y-1.5">
                <?php foreach ($beban as $i => $b):
                    $jp  = (int) $b['total_jp'];
                    $max = (int) $b['max_beban'];
                    $selisih = $jp - $max;
                    $tip = $b['kode_guru'] . ' · ' . $b['nama'] . ' — ' . $jp . ' JP dari batas ' . $max . ' JP'
                        . ($selisih > 0 ? ' (lebih ' . $selisih . ')' : ($selisih < 0 ? ' (kurang ' . -$selisih . ')' : ' (pas)'))
                        . ' · ' . (int) ($b['jml_tugas'] ?? 0) . ' penugasan'; ?>
                    <li tabindex="0" data-tip="<?= esc($tip, 'attr') ?>"
                        class="group grid grid-cols-[minmax(0,9rem)_1fr_5.5rem] items-center gap-3 rounded-lg px-1.5 py-1 outline-none transition hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-brand-300 sm:grid-cols-[minmax(0,13rem)_1fr_6rem]">
                        <span class="truncate text-sm text-slate-600"><b class="font-semibold text-slate-800"><?= esc($b['kode_guru']) ?></b> &middot; <?= esc($b['nama']) ?></span>
                        <span class="relative h-3.5 rounded-full bg-slate-100">
                            <span class="bar-grow absolute inset-y-0 left-0 rounded-r-[4px] rounded-l-full bg-brand-600 transition group-hover:bg-brand-500"
                                  style="width: <?= round($jp / $skala * 100, 2) ?>%; animation-delay: <?= 0.25 + $i * 0.05 ?>s"></span>
                            <?php if ($max > 0): ?>
                                <span class="absolute -inset-y-1 w-0.5 rounded bg-slate-800" style="left: calc(<?= round($max / $skala * 100, 2) ?>% - 1px)"></span>
                            <?php endif; ?>
                        </span>
                        <span class="flex items-center justify-end gap-1 text-right text-sm tabular-nums">
                            <?php if ($selisih > 0): ?>
                                <svg class="h-3.5 w-3.5 text-red-600" viewBox="0 0 20 20" fill="currentColor" aria-label="lebih dari batas"><path d="M10 3l7 12H3z"/></svg>
                            <?php elseif ($selisih < 0): ?>
                                <svg class="h-3.5 w-3.5 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-label="kurang dari batas"><path d="M10 17L3 5h14z"/></svg>
                            <?php endif; ?>
                            <b class="font-bold text-slate-800"><?= $jp ?></b><span class="text-slate-400">/<?= $max ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1"><svg class="h-3 w-3 text-red-600" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3l7 12H3z"/></svg> lebih dari batas</span>
                <span class="inline-flex items-center gap-1"><svg class="h-3 w-3 text-amber-500" viewBox="0 0 20 20" fill="currentColor"><path d="M10 17L3 5h14z"/></svg> kurang dari batas</span>
            </p>
        <?php endif; ?>
    </section>

    <section class="rise rise-3 flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">Perlu tindakan</h2>
            <?php if ($tindakan !== []): ?>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600"><?= count($tindakan) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($tindakan === []): ?>
            <div class="flex flex-1 flex-col items-center justify-center py-8 text-center">
                <span class="ring-pulse flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <p class="mt-3 font-semibold text-slate-700">Semua beres</p>
                <p class="text-sm text-slate-400">Tidak ada pekerjaan yang menunggu.</p>
            </div>
        <?php else: ?>
            <ul class="-mx-2 space-y-1">
                <?php foreach ($tindakan as $i => $t): [$warnaIkon, $ikon] = $ikonTindakan[$t['tingkat']]; ?>
                    <li class="reveal is-in" style="animation-delay: <?= 0.3 + $i * 0.06 ?>s">
                        <a href="<?= esc($t['url'], 'attr') ?>" class="group flex items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-slate-50">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg <?= $warnaIkon ?>">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $ikon ?>"/></svg>
                            </span>
                            <span class="min-w-0 flex-1 text-sm text-slate-600"><b class="text-base font-extrabold text-slate-800"><?= $angka($t['n']) ?></b> <?= esc($t['teks']) ?></span>
                            <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- ================= PENJADWALAN + SISWA ================= -->
<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
    <section class="rise rise-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-800">Penjadwalan KBM</h2>
        <p class="text-xs text-slate-500">Seberapa lengkap jadwal disusun</p>

        <div class="mt-4">
            <div class="flex items-baseline justify-between text-sm">
                <span class="text-slate-600">JP sudah terjadwal</span>
                <span><b class="text-xl font-extrabold text-slate-800" data-hitung="<?= $persenJp ?>" data-akhiran="%"><?= $persenJp ?>%</b></span>
            </div>
            <div class="mt-1.5 h-2.5 rounded-full bg-brand-100">
                <div class="bar-grow h-full rounded-full bg-brand-600" style="width: <?= $persenJp ?>%"></div>
            </div>
            <p class="mt-1 text-xs text-slate-500 tabular-nums"><?= $angka($jpJadwal) ?> dari <?= $angka($jpTugas) ?> JP penugasan</p>
        </div>

        <div class="mt-4">
            <div class="flex items-baseline justify-between text-sm">
                <span class="text-slate-600">Guru dengan beban pas</span>
                <span><b class="text-xl font-extrabold text-slate-800" data-hitung="<?= $persenPas ?>" data-akhiran="%"><?= $persenPas ?>%</b></span>
            </div>
            <div class="mt-1.5 h-2.5 rounded-full bg-brand-100">
                <div class="bar-grow h-full rounded-full bg-brand-600" style="width: <?= $persenPas ?>%; animation-delay: .4s"></div>
            </div>
            <p class="mt-1 text-xs text-slate-500 tabular-nums"><?= $angka($guruPas) ?> dari <?= $angka($kur['guru']) ?> guru pengajar</p>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 text-center">
            <a href="<?= site_url('admin/kurikulum/bentrok') ?>" class="rounded-xl px-1 py-2 transition hover:bg-slate-50">
                <p class="text-xl font-extrabold <?= $kur['bentrok'] > 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= $angka($kur['bentrok']) ?></p>
                <p class="text-[11px] text-slate-500"><?= $kur['bentrok'] > 0 ? '⚠ bentrok' : '✓ bentrok' ?></p>
            </a>
            <a href="<?= site_url('admin/kurikulum/rekap') ?>" class="rounded-xl px-1 py-2 transition hover:bg-slate-50">
                <p class="text-xl font-extrabold text-slate-800"><?= $angka($kur['kurang']) ?></p>
                <p class="text-[11px] text-slate-500">▼ kurang jam</p>
            </a>
            <a href="<?= site_url('admin/kurikulum/rekap') ?>" class="rounded-xl px-1 py-2 transition hover:bg-slate-50">
                <p class="text-xl font-extrabold text-slate-800"><?= $angka($kur['lebih']) ?></p>
                <p class="text-[11px] text-slate-500">▲ lebih jam</p>
            </a>
        </div>
    </section>

    <section class="rise rise-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="font-bold text-slate-800">Siswa aktif per jurusan</h2>
                <p class="text-xs text-slate-500"><span data-hitung="<?= (int) $siswa['aktif'] ?>"><?= $angka($siswa['aktif']) ?></span> siswa aktif</p>
            </div>
            <a href="<?= site_url('admin/master/siswa') ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Data →</a>
        </div>
        <?php if ($siswa['jurusan'] === []): ?>
            <p class="py-8 text-center text-sm text-slate-400">Belum ada siswa yang punya kelas.</p>
        <?php else: ?>
            <ul class="mt-4 space-y-3">
                <?php foreach ($siswa['jurusan'] as $i => $j):
                    $pct = $siswa['aktif'] > 0 ? round($j['n'] / $siswa['aktif'] * 100) : 0; ?>
                    <li tabindex="0" data-tip="<?= esc($j['nama'] . ' — ' . $angka($j['n']) . ' siswa (' . $pct . '%)', 'attr') ?>" class="group rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-brand-300">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="truncate font-semibold text-slate-700" title="<?= esc($j['nama'], 'attr') ?>"><?= esc($j['label']) ?></span>
                            <span class="shrink-0 tabular-nums text-slate-500"><b class="text-slate-800"><?= $angka($j['n']) ?></b> &middot; <?= $pct ?>%</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100">
                            <div class="bar-grow h-full rounded-full bg-brand-600 transition group-hover:bg-brand-500" style="width: <?= round($j['n'] / $maxJurusan * 100, 2) ?>%; animation-delay: <?= 0.35 + $i * 0.08 ?>s"></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rise rise-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2 xl:col-span-1">
        <h2 class="font-bold text-slate-800">Siswa per tingkat</h2>
        <p class="text-xs text-slate-500">Jumlah siswa aktif di tiap tingkat</p>
        <?php if ($siswa['tingkat'] === []): ?>
            <p class="py-8 text-center text-sm text-slate-400">Belum ada data tingkat.</p>
        <?php else: ?>
            <div class="mt-4 flex h-40 items-end justify-around gap-4 border-b border-slate-200 px-2">
                <?php foreach ($siswa['tingkat'] as $i => $t):
                    $tinggi = round($t['n'] / $maxTingkat * 100, 2); ?>
                    <div tabindex="0" data-tip="<?= esc('Kelas ' . $t['label'] . ' — ' . $angka($t['n']) . ' siswa', 'attr') ?>"
                         class="group flex h-full w-14 flex-col items-center justify-end outline-none focus-visible:ring-2 focus-visible:ring-brand-300">
                        <span class="mb-1 text-sm font-bold tabular-nums text-slate-800"><?= $angka($t['n']) ?></span>
                        <span class="col-grow block w-6 rounded-t-[4px] bg-brand-600 transition group-hover:bg-brand-500" style="height: <?= $tinggi ?>%; animation-delay: <?= 0.4 + $i * 0.1 ?>s"></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-around gap-4 px-2 pt-1.5">
                <?php foreach ($siswa['tingkat'] as $t): ?>
                    <span class="w-14 text-center text-xs font-semibold text-slate-500"><?= esc($t['label']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($siswa['tanpa_kelas'] > 0): ?>
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">⚠ <?= $angka($siswa['tanpa_kelas']) ?> siswa aktif belum punya kelas (tidak terhitung di grafik).</p>
        <?php endif; ?>
    </section>
</div>

<!-- ================= UJIAN: GARIS WAKTU 4 GELOMBANG ================= -->
<section class="rise rise-5 mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h2 class="font-bold text-slate-800">Ujian &mdash; TP <?= esc($ujian['tahun']) ?></h2>
            <p class="text-xs text-slate-500">ASTS 1 → ASAS → ASTS 2 → ASAT</p>
        </div>
    </div>
    <ol class="relative grid grid-cols-1 gap-4 sm:grid-cols-4 sm:gap-2">
        <?php // Garis penghubung (layar lebar): abu-abu penuh + biru sampai gelombang aktif terakhir. ?>
        <li aria-hidden="true" class="pointer-events-none absolute left-[12.5%] right-[12.5%] top-5 hidden h-1 rounded-full bg-slate-100 sm:block">
            <span class="bar-grow block h-full rounded-full bg-brand-500" style="width: <?= $ujAktif > 1 ? round(($ujAktif - 1) / 3 * 100, 2) : 0 ?>%; animation-delay: .5s"></span>
        </li>
        <?php foreach ($ujian['kartu'] as $i => $u): [$lblStatus, $bulat, $warnaTeks] = $ujLangkah[$u['status']] ?? $ujLangkah['draft']; ?>
            <li class="relative">
                <a href="<?= site_url('admin/ujian/' . $u['slug']) ?>" class="group flex items-center gap-3 rounded-xl p-2 transition hover:bg-slate-50 sm:flex-col sm:text-center">
                    <span class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-extrabold ring-4 <?= $bulat ?>"><?= $i + 1 ?></span>
                    <span class="min-w-0">
                        <span class="block font-bold text-slate-800 group-hover:text-brand-700"><?= esc($u['label']) ?></span>
                        <span class="block text-xs font-semibold <?= $warnaTeks ?>"><?= $lblStatus ?></span>
                        <span class="mt-0.5 block text-xs text-slate-500 tabular-nums"><?= (int) $u['jadwal'] ?> sesi &middot; <?= (int) $u['takHadir'] ?> tidak hadir</span>
                        <?php if ($u['belum'] > 0): ?>
                            <span class="mt-0.5 block text-xs font-semibold text-amber-700">⚠ <?= (int) $u['belum'] ?> susulan belum dijadwalkan</span>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<!-- ================= KESEDIAAN GURU ================= -->
<div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
    <section class="rise rise-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="font-bold text-slate-800">Kesediaan guru mengajar</h2>
                <p class="text-xs text-slate-500">Menurut status kepegawaian</p>
            </div>
            <span class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold <?= $setting['form_open'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                <?= $setting['form_open'] ? '● Form dibuka' : '○ Form ditutup' ?>
            </span>
        </div>
        <?php if ($totalPegawai === 0): ?>
            <p class="py-10 text-center text-sm text-slate-400">Belum ada data kesediaan masuk.</p>
        <?php else: ?>
            <div class="mt-3 flex items-center gap-5">
                <div class="relative h-36 w-36 shrink-0">
                    <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#f1f5f9" stroke-width="16"/>
                        <?php $mulai = 0; $k = 0;
                        foreach ($stats['byStatus'] as $st => $n): if ((int) $n <= 0) { continue; }
                            $panjang = $n / $totalPegawai * $keliling;
                            $celah   = $totalPegawai > $n ? 2 : 0; // 2px celah warna permukaan antar-irisan ?>
                            <circle cx="60" cy="60" r="52" fill="none" stroke="<?= $warnaPegawai[$st] ?? '#94a3b8' ?>" stroke-width="16"
                                    class="donut-seg cursor-pointer outline-none" tabindex="0"
                                    data-tip="<?= esc($st . ' — ' . $n . ' guru (' . round($n / $totalPegawai * 100) . '%)', 'attr') ?>"
                                    stroke-dasharray="<?= round(max(0.5, $panjang - $celah), 2) ?> <?= round($keliling, 2) ?>"
                                    stroke-dashoffset="<?= round(-$mulai, 2) ?>" style="animation-delay: <?= 0.45 + $k * 0.12 ?>s"/>
                        <?php $mulai += $panjang; $k++; endforeach; ?>
                    </svg>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-extrabold text-slate-800" data-hitung="<?= (int) $stats['total'] ?>"><?= $angka($stats['total']) ?></span>
                        <span class="text-[11px] text-slate-500">guru</span>
                    </div>
                </div>
                <ul class="min-w-0 flex-1 space-y-1.5 text-sm">
                    <?php foreach ($stats['byStatus'] as $st => $n): ?>
                        <li class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: <?= $warnaPegawai[$st] ?? '#94a3b8' ?>"></span>
                            <span class="flex-1 text-slate-600"><?= esc($st) ?></span>
                            <b class="tabular-nums text-slate-800"><?= (int) $n ?></b>
                            <span class="w-9 text-right text-xs tabular-nums text-slate-400"><?= $totalPegawai > 0 ? round($n / $totalPegawai * 100) : 0 ?>%</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">Masuk hari ini: <b class="text-slate-800"><?= (int) $stats['today'] ?></b> &middot; <a href="<?= site_url('admin/submissions') ?>" class="font-semibold text-brand-600 hover:text-brand-800">Lihat semua →</a></p>
        <?php endif; ?>
    </section>

    <section class="rise rise-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-bold text-slate-800">Kesediaan terbaru</h2>
            <a href="<?= site_url('admin/submissions') ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Semua →</a>
        </div>
        <?php if (empty($recent)): ?>
            <div class="p-10 text-center text-sm text-slate-400">Belum ada data kesediaan masuk.</div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recent as $r): ?>
                    <a href="<?= site_url('admin/submissions/view/' . $r['id']) ?>" class="flex items-center gap-4 px-5 py-3 transition hover:bg-slate-50">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700"><?= esc(strtoupper(mb_substr($r['nama_lengkap'], 0, 1))) ?></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-700"><?= esc($r['nama_lengkap']) ?></p>
                            <p class="truncate text-xs text-slate-400">NIP/NUPTK: <?= esc($r['nip_nuptk']) ?> &middot; <?= esc($r['guru_mapel'] ?: '-') ?></p>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500"><?= esc($r['status_kepegawaian']) ?></span>
                        <span class="hidden shrink-0 text-xs text-slate-400 sm:block"><?= date('d/m/y H:i', strtotime($r['created_at'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php // Satu tooltip bersama untuk semua [data-tip] (isi diisi lewat textContent di dashboard.js). ?>
<div id="vizTip" role="tooltip" class="pointer-events-none fixed z-50 hidden max-w-xs rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-lg"></div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/dashboard.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/dashboard.js') ?>"></script>
<?= $this->endSection() ?>
