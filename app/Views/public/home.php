<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<?php
$formOpen = ! empty($setting['form_open']);
$urlPkl   = config('Pkl')->tautan();
$logoHome = ! empty($setting['logo']) ? base_url('uploads/' . $setting['logo']) : null;
?>

<!-- ===================== HERO ===================== -->
<section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-900 text-white shadow-xl">
    <div class="hero-grid absolute inset-0 -z-10" aria-hidden="true"></div>
    <span class="orb orb-float -z-10 -top-24 -right-20 h-80 w-80 bg-brand-400/40" aria-hidden="true"></span>
    <span class="orb -z-10 -bottom-28 -left-12 h-72 w-72 bg-gold-400/20" aria-hidden="true"></span>

    <div class="relative grid gap-8 p-6 sm:p-10 lg:grid-cols-5 lg:items-center lg:p-14">
        <div class="lg:col-span-3">
            <p class="rise inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1 text-xs font-semibold text-brand-100 backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                Sistem Informasi Akademik<?php if (! empty($setting['academic_year'])): ?> &middot; T.P. <?= esc($setting['academic_year']) ?><?php endif; ?>
            </p>
            <h1 class="rise rise-1 mt-4 text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl"><?= esc($setting['school_name'] ?? 'Sekolah') ?></h1>
            <p class="rise rise-2 mt-3 max-w-xl text-brand-100 sm:text-lg">Jadwal pelajaran, absensi guru, dan pengajuan PKL dalam satu tempat &mdash; cepat dibuka lewat HP.</p>

            <?php if (! empty($now['label'])): ?>
                <p class="rise rise-3 mt-4 inline-flex items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-sm text-white">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
                    Sekarang: <?= esc($now['hariNama']) ?>, <?= esc($now['label']) ?>
                </p>
            <?php elseif (! empty($now['hariNama'])): ?>
                <p class="rise rise-3 mt-4 text-sm text-brand-200">Hari ini: <?= esc($now['hariNama']) ?></p>
            <?php endif; ?>

            <?php if ($jadwalOn): ?>
                <!-- Pencarian jadwal kelas cepat -->
                <form action="<?= site_url('jadwal-kelas') ?>" method="get" class="rise rise-3 mt-6 flex max-w-xl flex-col gap-2 sm:flex-row">
                    <label for="pilihKelasHome" class="sr-only">Pilih kelas</label>
                    <select id="pilihKelasHome" name="kelas_id" class="flex-1 rounded-xl border-0 px-4 py-3 text-sm text-slate-700 shadow-sm outline-none focus:ring-2 focus:ring-gold-400">
                        <option value="">— Pilih kelas untuk lihat jadwal —</option>
                        <?php foreach ($kelasOpts as $id => $label): ?>
                            <option value="<?= $id ?>"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-shine rounded-xl bg-gold-400 px-6 py-3 text-sm font-bold text-brand-900 shadow-sm transition hover:bg-gold-500 active:scale-95">Lihat Jadwal</button>
                </form>
            <?php endif; ?>

            <div class="rise rise-4 mt-5 flex flex-wrap gap-2.5">
                <a href="<?= esc($urlPkl, 'attr') ?>" class="btn-shine inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-brand-800 shadow-md transition hover:bg-brand-50 active:scale-95">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Ajukan PKL
                </a>
                <a href="<?= site_url('admin/login') ?>" class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur transition hover:bg-white/20 active:scale-95">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    Login Staf &amp; Guru
                </a>
            </div>
        </div>

        <!-- Lambang sekolah (hanya layar lebar) -->
        <div class="rise rise-3 hidden lg:col-span-2 lg:flex lg:justify-center" aria-hidden="true">
            <div class="orb-float flex h-56 w-56 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20 backdrop-blur">
                <span class="flex h-40 w-40 items-center justify-center rounded-full bg-white shadow-2xl">
                    <?php if ($logoHome): ?><img src="<?= esc($logoHome) ?>" alt="" class="h-28 w-28 object-contain" onerror="this.style.display='none';this.nextElementSibling.style.display='block'"><?php endif; ?>
                    <span<?= $logoHome ? ' style="display:none"' : '' ?> class="text-6xl font-extrabold text-brand-700"><?= esc(strtoupper(substr((string) ($setting['school_name'] ?? 'S'), 0, 1))) ?></span>
                </span>
            </div>
        </div>
    </div>
</section>

<?php if (! empty($pengumuman)): ?>
    <!-- PENGUMUMAN -->
    <section class="reveal mt-8">
        <h2 class="mb-3 flex items-center gap-2 font-bold text-slate-800">
            <svg class="h-5 w-5 text-gold-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
            Pengumuman
        </h2>
        <div class="space-y-3">
            <?php foreach ($pengumuman as $p): ?>
                <div class="lift rounded-2xl border border-slate-200 border-l-4 border-l-gold-400 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-bold text-slate-800"><?= esc($p['judul']) ?></h3>
                        <span class="shrink-0 text-xs text-slate-400"><?= esc(date('d M Y', strtotime($p['created_at']))) ?></span>
                    </div>
                    <p class="mt-1.5 whitespace-pre-line text-sm text-slate-600"><?= esc($p['isi']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ===================== STATISTIK ===================== -->
<section class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <?php
    $cards = [
        ['Siswa Aktif', $stats['siswa'] ?? 0, 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z', 'bg-brand-50 text-brand-700'],
        ['Guru', $stats['guru'], 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z', 'bg-emerald-50 text-emerald-700'],
        ['Kelas', $stats['kelas'], 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5', 'bg-amber-50 text-amber-700'],
        ['Mata Pelajaran', $stats['mapel'], 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253', 'bg-violet-50 text-violet-700'],
    ];
    foreach ($cards as $i => [$label, $val, $icon, $warna]): ?>
        <div class="reveal lift flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:gap-4 sm:p-5" style="animation-delay: <?= $i * 70 ?>ms">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl sm:h-12 sm:w-12 <?= $warna ?>">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>" />
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-2xl font-extrabold tabular-nums text-slate-800" data-count="<?= (int) $val ?>"><?= (int) $val ?></p>
                <p class="truncate text-xs text-slate-500 sm:text-sm"><?= $label ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<!-- ===================== LAYANAN ===================== -->
<?php
$layanan = [
    [site_url('jadwal-kelas'), 'Jadwal per Kelas', 'Lihat jadwal pelajaran mingguan tiap kelas.', 'bg-brand-700', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'group-hover:text-brand-700'],
    [site_url('jadwal-guru'), 'Jadwal per Guru', 'Lihat jadwal mengajar tiap guru.', 'bg-emerald-600', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0m6 0a4 4 0 10-2 0M7 8a4 4 0 108 0 4 4 0 00-8 0z', 'group-hover:text-emerald-700'],
];
if ((int) ($setting['absensi_publik'] ?? 1) === 1) {
    $layanan[] = [site_url('absensi'), 'Absensi Guru', 'Catat dan lihat kehadiran mengajar.', 'bg-violet-600', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'group-hover:text-violet-700'];
}
if ((int) ($setting['dokumen_publik'] ?? 0) === 1) {
    $layanan[] = [site_url('dokumen-publik'), 'Dokumen Sekolah', 'Baca dan unduh dokumen resmi sekolah.', 'bg-sky-600', 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z', 'group-hover:text-sky-700'];
}
if ($formOpen) {
    $layanan[] = [site_url('isi'), 'Form Kesediaan Guru', 'Isi kesediaan mengajar semester ini.', 'bg-rose-600', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'group-hover:text-rose-700'];
}
?>
<section class="mt-8">
    <h2 class="reveal mb-3 font-bold text-slate-800">Layanan</h2>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
        <!-- PKL disorot: layanan baru untuk siswa -->
        <a href="<?= esc($urlPkl, 'attr') ?>" class="reveal lift group relative flex items-start gap-4 overflow-hidden rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm sm:col-span-2 lg:col-span-1">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center gap-2">
                    <span class="font-bold text-slate-800 transition group-hover:text-amber-700">Ajukan PKL / Prakerin</span>
                    <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Siswa</span>
                </span>
                <span class="mt-1 block text-sm text-slate-500">Isi data tempat PKL-mu di sini. Tanpa login, cukup lewat HP.</span>
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-amber-700">Buka formulir <span class="transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
            </span>
        </a>

        <?php foreach ($layanan as $i => [$url, $judul, $ket, $warna, $ikon, $hover]): ?>
            <a href="<?= esc($url, 'attr') ?>" class="reveal lift group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="animation-delay: <?= ($i + 1) * 60 ?>ms">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-sm <?= $warna ?>">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $ikon ?>" /></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold text-slate-800 transition <?= $hover ?>"><?= esc($judul) ?></span>
                    <span class="mt-1 block text-sm text-slate-500"><?= esc($ket) ?></span>
                    <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-slate-400 transition group-hover:text-slate-700">Buka <span class="transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===================== GRAFIK JUMLAH SISWA =====================
     Sengaja hanya menampilkan ANGKA AGREGAT. Identitas siswa (nama, alamat,
     tanggal lahir) tidak pernah dikirim ke halaman publik.
     Bentuk: batang satu warna (data ini membandingkan besaran, bukan
     identitas) + angka hero untuk totalnya. Nilai selalu tertulis, jadi
     tidak ada informasi yang hanya bisa dibaca lewat warna. -->
<?php
    $siswaStat = $siswaStat ?? [];
    $sTotal    = (int) ($siswaStat['total'] ?? 0);
    $sTahun    = (int) ($siswaStat['tahun'] ?? date('Y'));

    /** Satu baris batang: label, panjang proporsional, nilai tertulis di ujung. */
    $barSiswa = static function (string $label, int $nilai, int $maks): string {
        $persen = $maks > 0 ? max(2, round($nilai / $maks * 100)) : 0;
        ob_start(); ?>
        <div class="flex items-center gap-3">
            <span class="w-24 shrink-0 text-xs font-medium text-slate-600 truncate" title="<?= esc($label, 'attr') ?>"><?= esc($label) ?></span>
            <span class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <span class="block h-full rounded-full bg-brand-500" style="width: <?= $persen ?>%"></span>
            </span>
            <span class="w-10 shrink-0 text-right text-xs font-bold text-slate-700 tabular-nums"><?= $nilai ?></span>
        </div>
        <?php return (string) ob_get_clean();
    };
?>
<?php if ($sTotal > 0): ?>
    <section class="reveal mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5 flex items-baseline justify-between gap-3">
            <h2 class="font-bold text-slate-800">Statistik Siswa</h2>
            <p class="text-xs text-slate-400">Siswa aktif &middot; Tahun <?= $sTahun ?></p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
            <!-- Angka utama + komposisi L/P -->
            <div>
                <p class="text-4xl font-extrabold leading-none tabular-nums text-slate-800 sm:text-5xl" data-count="<?= $sTotal ?>"><?= number_format($sTotal, 0, ',', '.') ?></p>
                <p class="mt-1.5 text-sm text-slate-500">Total siswa aktif</p>

                <?php
                    $lk = (int) ($siswaStat['jenis_kelamin']['L'] ?? 0);
                    $pr = (int) ($siswaStat['jenis_kelamin']['P'] ?? 0);
                    $jk = $lk + $pr;
                ?>
                <?php if ($jk > 0): ?>
                    <!-- Bagian dari keseluruhan: 2 segmen, dipisah jarak 2px (bukan garis tepi) -->
                    <div class="mt-5">
                        <div class="flex h-2.5 gap-0.5">
                            <span class="rounded-full bg-brand-500" style="width: <?= round($lk / $jk * 100, 1) ?>%"></span>
                            <span class="rounded-full bg-amber-700" style="width: <?= round($pr / $jk * 100, 1) ?>%"></span>
                        </div>
                        <div class="mt-2.5 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                            <span class="inline-flex items-center gap-1.5 text-slate-600">
                                <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                                Laki-laki <b class="tabular-nums text-slate-800"><?= $lk ?></b>
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-slate-600">
                                <span class="h-2 w-2 rounded-full bg-amber-700"></span>
                                Perempuan <b class="tabular-nums text-slate-800"><?= $pr ?></b>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Per tingkat -->
            <?php $perTingkat = $siswaStat['per_tingkat'] ?? []; ?>
            <?php if (! empty($perTingkat)): ?>
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Per Tingkat</p>
                    <div class="space-y-2.5">
                        <?php
                            $maksT = max($perTingkat);
                        foreach (['X', 'XI', 'XII'] as $t) {
                            if (isset($perTingkat[$t])) {
                                echo $barSiswa('Kelas ' . $t, (int) $perTingkat[$t], (int) $maksT);
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Per jurusan -->
            <?php $perJurusan = $siswaStat['per_jurusan'] ?? []; ?>
            <?php if (! empty($perJurusan)): ?>
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Per Jurusan</p>
                    <div class="space-y-2.5">
                        <?php
                            $maksJ = max(array_column($perJurusan, 'jumlah'));
                        foreach (array_slice($perJurusan, 0, 6) as $j) {
                            echo $barSiswa($j['kode'] ?: $j['nama'], (int) $j['jumlah'], (int) $maksJ);
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ===================== INFO SEKOLAH ===================== -->
<section class="reveal mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 class="mb-3 font-bold text-slate-800">Info Sekolah</h2>
    <ul class="grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
        <?php
        $kontak = [
            ['address', 'Alamat', 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z'],
            ['phone', 'Telepon', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
            ['email', 'Email', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['website', 'Website', 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9'],
        ];
        $adaKontak = false;
        foreach ($kontak as [$kunci, $nama, $ikon]):
            if (empty($setting[$kunci])) { continue; }
            $adaKontak = true; ?>
            <li class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $ikon ?>" /></svg></span>
                <span class="min-w-0"><span class="block text-xs font-semibold text-slate-400"><?= $nama ?></span><span class="break-words"><?= esc($setting[$kunci]) ?></span></span>
            </li>
        <?php endforeach; ?>
        <?php if (! $adaKontak): ?><li class="text-slate-400">Lengkapi info sekolah di panel admin.</li><?php endif; ?>
    </ul>
</section>

<?= $this->endSection() ?>
