<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>

<?php
/**
 * Beranda publik (dirombak 2026-10-12): susunan portal sekolah — keadaan hari ini, cek jadwal, layanan, pengumuman,
 * data sekolah. Sengaja tanpa hiasan (gradasi, cahaya, kilau, angka berhitung): isi yang diutamakan.
 * Data siswa hanya ANGKA AGREGAT — identitas siswa tidak pernah dikirim ke halaman publik.
 *
 * @var array $setting @var array $stats @var array $siswaStat @var array $kelasOpts @var list<array> $pengumuman
 * @var array $now (hariId, hariNama, label) @var bool $jadwalOn
 */
$sekolah  = (string) ($setting['school_name'] ?? 'Sekolah');
$urlPkl   = config('Pkl')->tautan();
$angka    = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$tglIndo  = static fn (string $ymd): string => \App\Libraries\IsianBantu::tanggalIndo($ymd);
$svg      = static fn (string $d, string $kelas = 'h-5 w-5'): string => '<svg class="' . $kelas . '" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';

// Layanan: [alamat, judul, keterangan, ikon]
$layanan = [];
if ($jadwalOn) {
    $layanan[] = [site_url('jadwal-kelas'), 'Jadwal kelas', 'Jadwal pelajaran mingguan tiap kelas', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'];
    $layanan[] = [site_url('jadwal-guru'), 'Jadwal guru', 'Jadwal mengajar tiap guru', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'];
}
if ((int) ($setting['absensi_publik'] ?? 1) === 1) {
    $layanan[] = [site_url('absensi'), 'Absensi guru', 'Kehadiran mengajar hari ini', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'];
}
$layanan[] = [$urlPkl, 'Pengajuan PKL', 'Siswa mengajukan tempat PKL tanpa login', 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'];
if (! empty($setting['form_open'])) {
    $layanan[] = [site_url('isi'), 'Form kesediaan mengajar', 'Untuk guru, selama formulir dibuka', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'];
}
if ((int) ($setting['dokumen_publik'] ?? 0) === 1) {
    $layanan[] = [site_url('dokumen-publik'), 'Dokumen sekolah', 'Dokumen resmi yang dibagikan sekolah', 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'];
}
$layanan[] = [site_url('admin/login'), 'Masuk staf & guru', 'Panel admin, operator, dan Waka Hubin', 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1'];

// Data sekolah
$siswaStat  = $siswaStat ?? [];
$perTingkat = (array) ($siswaStat['per_tingkat'] ?? []);
$perJurusan = array_slice((array) ($siswaStat['per_jurusan'] ?? []), 0, 6);
$maksT      = $perTingkat !== [] ? max(1, (int) max($perTingkat)) : 1;
$maksJ      = $perJurusan !== [] ? max(1, (int) max(array_column($perJurusan, 'jumlah'))) : 1;
$lk         = (int) ($siswaStat['jenis_kelamin']['L'] ?? 0);
$pr         = (int) ($siswaStat['jenis_kelamin']['P'] ?? 0);
$batang     = static function (string $label, int $nilai, int $maks): string {
    $lebar = $maks > 0 ? max(2, round($nilai / $maks * 100)) : 0;

    return '<div class="grid grid-cols-[5.5rem_1fr_3rem] items-center gap-3 text-sm">'
        . '<span class="truncate text-slate-600" title="' . esc($label, 'attr') . '">' . esc($label) . '</span>'
        . '<span class="h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-brand-500" style="width:' . $lebar . '%"></span></span>'
        . '<span class="text-right font-medium tabular-nums text-slate-800">' . number_format($nilai, 0, ',', '.') . '</span></div>';
};
?>

<!-- ===== Pembuka: tanggal, judul, keadaan jam pelajaran ===== -->
<section class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <p class="text-sm text-slate-500"><?= esc($now['hariNama'] ?? '') ?>, <?= esc($tglIndo(date('Y-m-d'))) ?></p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Informasi akademik <?= esc($sekolah) ?></h1>
        <p class="mt-2 max-w-2xl text-slate-600">Jadwal pelajaran, kehadiran guru, dan pengumuman sekolah. Siswa juga dapat mengajukan tempat PKL dari halaman ini.</p>
    </div>
    <div class="shrink-0 rounded-md border border-slate-200 bg-white px-4 py-3 sm:min-w-[14rem]">
        <p class="text-xs text-slate-500">Saat ini</p>
        <?php if (! empty($now['label'])): ?>
            <p class="mt-0.5 flex items-center gap-2 text-sm font-medium text-slate-900"><span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span><?= esc($now['label']) ?></p>
        <?php elseif (! empty($now['hariId'])): ?>
            <p class="mt-0.5 text-sm font-medium text-slate-900">Di luar jam pelajaran</p>
        <?php else: ?>
            <p class="mt-0.5 text-sm font-medium text-slate-900">Tidak ada kegiatan belajar hari ini</p>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Isi. Layar lebar: kolom kiri (jadwal, pengumuman) dan kanan (layanan, data sekolah) mengalir sendiri-sendiri,
     jadi tak ada ruang kosong. HP: pembungkus "contents" + order → jadwal, layanan, pengumuman, data sekolah. ===== -->
<div class="mt-6 flex flex-col gap-6 lg:grid lg:grid-cols-3 lg:items-start">
<div class="contents lg:col-span-2 lg:flex lg:flex-col lg:gap-6">
    <section class="order-1 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulJadwal">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 id="judulJadwal" class="font-semibold text-slate-900">Jadwal pelajaran</h2>
            <p class="mt-0.5 text-sm text-slate-500">Pilih kelas untuk melihat jadwal mingguannya.</p>
        </div>
        <div class="px-5 py-5">
            <?php if ($jadwalOn): ?>
                <form action="<?= site_url('jadwal-kelas') ?>" method="get" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label for="pilihKelasHome" class="lbl">Kelas</label>
                        <select id="pilihKelasHome" name="kelas_id" class="inp bg-white" required>
                            <option value="">Pilih kelas</option>
                            <?php foreach ($kelasOpts as $id => $label): ?>
                                <option value="<?= (int) $id ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="rounded-md bg-brand-700 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-brand-800">Tampilkan jadwal</button>
                </form>
                <p class="mt-4 text-sm text-slate-600">Mencari jadwal mengajar? <a href="<?= site_url('jadwal-guru') ?>" class="font-medium text-brand-700 hover:underline">Lihat jadwal per guru</a></p>
            <?php else: ?>
                <p class="text-sm text-slate-600">Jadwal pelajaran belum dibuka untuk umum. Silakan cek kembali nanti.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="order-3 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulPengumuman">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 id="judulPengumuman" class="font-semibold text-slate-900">Pengumuman</h2>
        </div>
        <?php if (empty($pengumuman)): ?>
            <p class="px-5 py-6 text-sm text-slate-500">Belum ada pengumuman.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($pengumuman as $p): $ymd = date('Y-m-d', strtotime((string) $p['created_at'])); ?>
                    <li class="px-5 py-4 sm:flex sm:gap-6">
                        <time datetime="<?= esc($ymd, 'attr') ?>" class="block shrink-0 text-sm text-slate-500 sm:w-36"><?= esc($tglIndo($ymd)) ?></time>
                        <div class="mt-1 min-w-0 sm:mt-0">
                            <h3 class="font-medium text-slate-900"><?= esc($p['judul']) ?></h3>
                            <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600"><?= esc($p['isi']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<div class="contents lg:flex lg:flex-col lg:gap-6">
    <section class="order-2 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulLayanan">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 id="judulLayanan" class="font-semibold text-slate-900">Layanan</h2>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($layanan as [$url, $judul, $ket, $ikon]): ?>
                <li>
                    <a href="<?= esc($url, 'attr') ?>" class="group flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50">
                        <span class="text-brand-600"><?= $svg($ikon) ?></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-slate-900 group-hover:text-brand-800"><?= esc($judul) ?></span>
                            <span class="block truncate text-xs text-slate-500"><?= esc($ket) ?></span>
                        </span>
                        <?= $svg('M9 5l7 7-7 7', 'h-4 w-4 shrink-0 text-slate-300 group-hover:text-slate-500') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="order-4 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulData">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 id="judulData" class="font-semibold text-slate-900">Data sekolah</h2>
            <p class="mt-0.5 text-sm text-slate-500">Tahun pelajaran <?= esc($setting['academic_year'] ?? '-') ?></p>
        </div>
        <dl class="divide-y divide-slate-100 px-5">
            <?php foreach ([['Siswa aktif', $stats['siswa'] ?? 0], ['Guru', $stats['guru'] ?? 0], ['Kelas', $stats['kelas'] ?? 0], ['Mata pelajaran', $stats['mapel'] ?? 0]] as [$l, $v]): ?>
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <dt class="text-slate-600"><?= $l ?></dt>
                    <dd class="font-semibold tabular-nums text-slate-900"><?= $angka($v) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php if ($perTingkat !== [] || $perJurusan !== []): ?>
            <div class="space-y-5 border-t border-slate-200 px-5 py-4">
                <?php if ($perTingkat !== []): ?>
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">Siswa per tingkat</p>
                        <div class="space-y-2">
                            <?php foreach (['X', 'XI', 'XII'] as $t): if (isset($perTingkat[$t])): ?>
                                <?= $batang('Kelas ' . $t, (int) $perTingkat[$t], $maksT) ?>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($perJurusan !== []): ?>
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500">Siswa per jurusan</p>
                        <div class="space-y-2">
                            <?php foreach ($perJurusan as $j): ?>
                                <?= $batang((string) ($j['kode'] ?: $j['nama']), (int) $j['jumlah'], $maksJ) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($lk + $pr > 0): ?>
                    <p class="text-xs text-slate-500">Laki-laki <?= $angka($lk) ?> &middot; Perempuan <?= $angka($pr) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>

<?= $this->endSection() ?>
