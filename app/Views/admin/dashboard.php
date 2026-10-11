<?php
/**
 * Dashboard Admin — versi profesional (2026-10-12). Gaya tenang seperti aplikasi kerja: kartu putih bergaris 1px, lilac
 * hemat, tanpa gradasi/cahaya/emoji/angka berhitung (memori feedback-tampilan-bukan-ai). Grafik: batang satu warna +
 * garis batas, donat 4 warna kategori yang lolos uji buta-warna; nilai selalu tertulis, tooltip di admin/dashboard.js.
 * Susunan: kolom kiri (tindakan, beban, modul, ujian) & kanan (hari ini, siswa, kesediaan, aktivitas) mengalir sendiri-
 * sendiri; di HP diurutkan lewat `order` (pembungkus `contents`).
 *
 * @var array $kur @var array $stats @var array $setting @var array $absensi @var array $ujian @var array $siswa
 * @var int $jpTugas @var array $modul @var list<array> $tindakan @var list<array> $aktivitas @var string $nama
 */
use App\Libraries\IsianBantu;

$angka   = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$jam     = (int) date('G');
$salam   = $jam < 4 ? 'Selamat malam' : ($jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam')));
$hariIni = ucfirst(mb_strtolower((string) $absensi['hari_nama'])); // data hari tersimpan HURUF BESAR
$bulanPendek = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$waktuPendek = static function (?string $dt) use ($bulanPendek): string {
    $t = $dt ? strtotime($dt) : false;

    return $t ? (int) date('j', $t) . ' ' . $bulanPendek[(int) date('n', $t) - 1] . ', ' . date('H.i', $t) : '';
};
$svg = static fn (string $d, string $kelas = 'h-5 w-5'): string => '<svg class="' . $kelas . '" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';

// Absensi hari ini
$abs         = $absensi['ringkas'];
$absTotal    = array_sum($abs);
$persenHadir = $absTotal > 0 ? (int) round(($abs['hadir'] + $abs['telat']) / $absTotal * 100) : 0;

// Beban per guru: skala bersama = nilai terbesar di antara JP dan batasnya.
$beban = $kur['chart'] ?? [];
$skala = 1;
foreach ($beban as $b) {
    $skala = max($skala, (int) $b['total_jp'], (int) $b['max_beban']);
}

// Penjadwalan
$jpJadwal = (int) $kur['jpTerjadwal'];
$persenJp = $jpTugas > 0 ? min(100, (int) round($jpJadwal / $jpTugas * 100)) : 0;

// Siswa
$maxJurusan = max(1, ...array_map(static fn ($r) => (int) $r['n'], $siswa['jurusan'] ?: [['n' => 1]]));
$maxTingkat = max(1, ...array_map(static fn ($r) => (int) $r['n'], $siswa['tingkat'] ?: [['n' => 1]]));

// Kesediaan: donat status kepegawaian (warna tetap per status, bukan peringkat; lolos uji buta-warna).
$warnaPegawai = ['PNS' => '#2a78d6', 'PPPK' => '#eb6834', 'GTY' => '#1baf7a', 'GTT' => '#eda100'];
$totalPegawai = array_sum($stats['byStatus'] ?: []);
$keliling     = 2 * M_PI * 52; // r = 52

// Ujian: status periode → lencana
$ujStatus = [
    'draft'    => ['Draft', 'bg-slate-100 text-slate-600'],
    'berjalan' => ['Berjalan', 'bg-emerald-50 text-emerald-700'],
    'selesai'  => ['Selesai', 'bg-brand-50 text-brand-700'],
];

// Perlu tindakan: ikon + warna per tingkat (selalu disertai teks)
$ikonTindakan = [
    'kritis' => ['text-red-600', 'M12 9v3.75m0 3.75h.008M10.34 3.94 1.82 18a1.875 1.875 0 0 0 1.6 2.82h17.16a1.875 1.875 0 0 0 1.6-2.82L13.66 3.94a1.875 1.875 0 0 0-3.32 0Z'],
    'awas'   => ['text-amber-600', 'M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z'],
    'info'   => ['text-brand-600', 'M11.25 11.25l.04-.02a.75.75 0 0 1 1.06.85l-.7 2.84a.75.75 0 0 0 1.06.85l.04-.02M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z'],
];

// Ringkasan modul: [judul, keterangan (baris 1), keterangan (baris 2) | null, alamat]
$baris = [];
if ($m = $modul['pkl']) {
    $s = $m['status'];
    $baris[] = ['PKL / Prakerin', $angka($s['menunggu'] ?? 0) . ' menunggu ACC · ' . $angka($s['perbaikan'] ?? 0) . ' perbaikan · ' . $angka($s['disetujui'] ?? 0) . ' disetujui · ' . $angka($s['ditolak'] ?? 0) . ' ditolak',
        'Siswa sudah PKL ' . $angka($m['siswa']['sudah_pkl'] ?? 0) . ' dari ' . $angka($m['siswa']['total'] ?? 0) . ' (' . (int) ($m['siswa']['persen_pkl'] ?? 0) . '%)', 'admin/pkl'];
}
if ($m = $modul['surat']) {
    $baris[] = ['Surat sekolah', $angka($m['menunggu'] ?? 0) . ' menunggu ACC · ' . $angka($m['belum_unduh'] ?? 0) . ' belum diunduh · ' . $angka($m['dikembalikan'] ?? 0) . ' dikembalikan',
        $angka($m['semua'] ?? 0) . ' surat tercatat', 'admin/surat'];
}
if ($m = $modul['ukk']) {
    $p = $m['peserta'];
    $baris[] = ['Uji kompetensi (UKK)', $angka(array_sum($p)) . ' peserta · ' . $angka($p['lulus'] ?? 0) . ' lulus · ' . $angka($p['tidak_lulus'] ?? 0) . ' tidak lulus',
        $angka($m['jadwal']) . ' jadwal uji', 'admin/peserta-ukk'];
}
if ($m = $modul['lab']) {
    $baris[] = ['Laboratorium', $angka(array_sum($m['pinjam'])) . ' sedang dipinjam' . (($m['pinjam']['terlambat'] ?? 0) > 0 ? ' (' . $angka($m['pinjam']['terlambat']) . ' terlambat)' : '') . ' · ' . $angka(array_sum($m['kerusakan'])) . ' kerusakan terbuka',
        null, 'admin/peminjaman'];
}
if ($m = $modul['dokumen']) {
    $mb = static fn (int $b): string => number_format($b / 1048576, $b < 10 * 1048576 ? 1 : 0, ',', '.') . ' MB';
    $baris[] = ['Arsip dokumen', $angka($m['jumlah']) . ' dokumen · ' . $mb($m['byte']) . ' dari ' . $mb($m['kuota']) . ' terpakai', null, 'admin/dokumen'];
}
if (($m = $modul['biodata']) !== null) {
    $baris[] = ['Isian biodata siswa', $angka($m['menunggu'] ?? 0) . ' menunggu · ' . $angka($m['perbaikan'] ?? 0) . ' perbaikan · ' . $angka($m['disetujui'] ?? 0) . ' disetujui', null, 'admin/biodata'];
}
$k = $modul['kesediaan'];
$baris[] = ['Kesediaan guru mengajar', $angka($k['total']) . ' isian · ' . $angka($k['hari_ini']) . ' masuk hari ini · formulir ' . ($k['dibuka'] ? 'dibuka' : 'ditutup'), null, 'admin/submissions'];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dashboard_v4',
    'helpTitle' => 'Dashboard',
    'helpBody'  => '<p>Ringkasan seluruh sekolah dalam satu halaman.</p>
        <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><b>Perlu tindakan</b>: pekerjaan yang menunggu (bentrok jadwal, absensi belum dicatat, ajuan PKL &amp; surat menunggu ACC, biodata menunggu, susulan ujian, lab, siswa tanpa kelas). Klik untuk membukanya.</li>
        <li><b>Ringkasan modul</b>: keadaan PKL, Surat Sekolah, UKK, Laboratorium, Arsip Dokumen, Biodata, dan Kesediaan.</li>
        <li><b>Beban mengajar</b>: batang = JP guru, garis tegak = batas maksimalnya. Arahkan kursor ke batang untuk rinciannya.</li>
        <li>Angka penjadwalan diperbarui paling lama tiap 30 menit; angka siswa tiap 5 menit; selebihnya langsung.</li>
        </ul>',
]) ?>

<!-- ===== Kepala: tanggal, sapaan, tombol cepat ===== -->
<div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm text-slate-500"><?= esc($hariIni) ?>, <?= esc(IsianBantu::tanggalIndo($absensi['tanggal'])) ?> &middot; Tahun Pelajaran <?= esc($setting['academic_year'] ?? '-') ?></p>
        <p class="mt-0.5 text-lg font-semibold text-slate-900"><?= esc($salam) ?>, <?= esc(trim(explode(',', $nama)[0])) ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($absensi['hari_aktif']): ?>
            <a href="<?= site_url('admin/absensi') ?>?tanggal=<?= esc($absensi['tanggal'], 'url') ?>" class="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-800"><?= $absensi['recorded'] ? 'Lihat absensi hari ini' : 'Catat absensi hari ini' ?></a>
        <?php endif; ?>
        <a href="<?= site_url('admin/pengumuman') ?>" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50">Pengumuman</a>
    </div>
</div>

<!-- ===== Angka utama ===== -->
<div class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 lg:grid-cols-4">
    <a href="<?= site_url('admin/master/siswa') ?>" class="bg-white px-5 py-4 transition-colors hover:bg-slate-50">
        <p class="text-sm text-slate-500">Siswa aktif</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $angka($siswa['aktif']) ?></p>
        <p class="mt-0.5 text-xs text-slate-500">di <?= $angka($kur['kelas']) ?> kelas<?= $siswa['tanpa_kelas'] > 0 ? ' · ' . $angka($siswa['tanpa_kelas']) . ' tanpa kelas' : '' ?></p>
    </a>
    <a href="<?= site_url('admin/master/guru') ?>" class="bg-white px-5 py-4 transition-colors hover:bg-slate-50">
        <p class="text-sm text-slate-500">Guru pengajar</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $angka($kur['guru']) ?></p>
        <p class="mt-0.5 text-xs text-slate-500"><?= $angka($kur['mapel']) ?> mata pelajaran</p>
    </a>
    <a href="<?= site_url('admin/absensi') ?>" class="bg-white px-5 py-4 transition-colors hover:bg-slate-50">
        <p class="text-sm text-slate-500">Kehadiran guru hari ini</p>
        <?php if (! $absensi['hari_aktif']): ?>
            <p class="mt-1 text-2xl font-semibold text-slate-400">&ndash;</p>
            <p class="mt-0.5 text-xs text-slate-500">Bukan hari KBM</p>
        <?php elseif (! $absensi['recorded']): ?>
            <p class="mt-1 text-lg font-semibold text-amber-700">Belum dicatat</p>
            <p class="mt-0.5 text-xs text-slate-500"><?= $angka($absensi['total_guru']) ?> guru mengajar hari ini</p>
        <?php else: ?>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $persenHadir ?>%</p>
            <p class="mt-0.5 text-xs text-slate-500"><?= $angka($abs['hadir'] + $abs['telat']) ?> dari <?= $angka($absTotal) ?> guru hadir</p>
        <?php endif; ?>
    </a>
    <a href="<?= site_url('admin/jadwal') ?>" class="bg-white px-5 py-4 transition-colors hover:bg-slate-50">
        <p class="text-sm text-slate-500">Jadwal KBM tersusun</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $persenJp ?>%</p>
        <p class="mt-0.5 text-xs text-slate-500"><?= $angka($jpJadwal) ?> dari <?= $angka($jpTugas) ?> JP penugasan</p>
    </a>
</div>

<!-- ===== Isi: dua kolom mengalir sendiri-sendiri; HP diurutkan lewat order ===== -->
<div class="mt-6 flex flex-col gap-6 lg:grid lg:grid-cols-3 lg:items-start">
<div class="contents lg:col-span-2 lg:flex lg:flex-col lg:gap-6">

    <!-- Perlu tindakan -->
    <section class="order-1 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulTindakan">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
            <h2 id="judulTindakan" class="font-semibold text-slate-900">Perlu tindakan</h2>
            <span class="text-sm text-slate-500"><?= count($tindakan) ?> hal</span>
        </div>
        <?php if ($tindakan === []): ?>
            <p class="px-5 py-5 text-sm text-slate-600">Tidak ada pekerjaan yang menunggu.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($tindakan as $t): [$warna, $ikon] = $ikonTindakan[$t['tingkat']]; ?>
                    <li>
                        <a href="<?= esc($t['url'], 'attr') ?>" class="group flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-slate-50">
                            <span class="<?= $warna ?>"><?= $svg($ikon) ?></span>
                            <span class="min-w-0 flex-1 text-sm text-slate-700"><b class="font-semibold text-slate-900"><?= $angka($t['n']) ?></b> <?= esc($t['teks']) ?></span>
                            <span class="shrink-0 text-sm font-medium text-brand-700 group-hover:underline">Buka</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Beban mengajar -->
    <section class="order-3 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulBeban">
        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 px-5 py-3.5">
            <div>
                <h2 id="judulBeban" class="font-semibold text-slate-900">Beban mengajar guru</h2>
                <p class="text-xs text-slate-500">12 terbanyak &middot; batang = JP penugasan, garis tegak = batas maksimal</p>
            </div>
            <a href="<?= site_url('admin/kurikulum/rekap') ?>" class="text-sm font-medium text-brand-700 hover:underline">Rekap lengkap</a>
        </div>
        <?php if ($beban === []): ?>
            <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada data beban. Atur Penugasan terlebih dahulu.</p>
        <?php else: ?>
            <ul class="space-y-1 px-4 py-3">
                <?php foreach ($beban as $i => $b):
                    $jp  = (int) $b['total_jp'];
                    $max = (int) $b['max_beban'];
                    $selisih = $jp - $max;
                    $tip = $b['kode_guru'] . ' · ' . $b['nama'] . ' — ' . $jp . ' JP dari batas ' . $max . ' JP'
                        . ($selisih > 0 ? ' (lebih ' . $selisih . ')' : ($selisih < 0 ? ' (kurang ' . -$selisih . ')' : ' (pas)'))
                        . ' · ' . (int) ($b['jml_tugas'] ?? 0) . ' penugasan'; ?>
                    <li tabindex="0" data-tip="<?= esc($tip, 'attr') ?>"
                        class="group grid grid-cols-[minmax(0,9rem)_1fr_5.5rem] items-center gap-3 rounded-md px-1.5 py-1 outline-none hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-brand-300 sm:grid-cols-[minmax(0,13rem)_1fr_6rem]">
                        <span class="truncate text-sm text-slate-600"><span class="font-medium text-slate-900"><?= esc($b['kode_guru']) ?></span> &middot; <?= esc($b['nama']) ?></span>
                        <span class="relative h-3 rounded-full bg-slate-100">
                            <span class="bar-grow absolute inset-y-0 left-0 rounded-r-[4px] rounded-l-full bg-brand-500" style="width: <?= round($jp / $skala * 100, 2) ?>%; animation-delay: <?= 0.1 + $i * 0.03 ?>s"></span>
                            <?php if ($max > 0): ?>
                                <span class="absolute -inset-y-1 w-0.5 rounded bg-slate-700" style="left: calc(<?= round($max / $skala * 100, 2) ?>% - 1px)"></span>
                            <?php endif; ?>
                        </span>
                        <span class="flex items-center justify-end gap-1 text-right text-sm tabular-nums">
                            <?php if ($selisih > 0): ?>
                                <svg class="h-3.5 w-3.5 text-red-600" viewBox="0 0 20 20" fill="currentColor" aria-label="lebih dari batas"><path d="M10 3l7 12H3z"/></svg>
                            <?php elseif ($selisih < 0): ?>
                                <svg class="h-3.5 w-3.5 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-label="kurang dari batas"><path d="M10 17L3 5h14z"/></svg>
                            <?php endif; ?>
                            <span class="font-semibold text-slate-900"><?= $jp ?></span><span class="text-slate-400">/<?= $max ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="flex flex-wrap gap-x-5 gap-y-1 border-t border-slate-100 px-5 py-2.5 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1"><svg class="h-3 w-3 text-red-600" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3l7 12H3z"/></svg> lebih dari batas (<?= $angka($kur['lebih']) ?> guru)</span>
                <span class="inline-flex items-center gap-1"><svg class="h-3 w-3 text-amber-500" viewBox="0 0 20 20" fill="currentColor"><path d="M10 17L3 5h14z"/></svg> kurang dari batas (<?= $angka($kur['kurang']) ?> guru)</span>
                <span>bentrok jadwal: <?= $angka($kur['bentrok']) ?></span>
            </div>
        <?php endif; ?>
    </section>

    <!-- Ringkasan modul -->
    <section class="order-4 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulModul">
        <div class="border-b border-slate-200 px-5 py-3.5">
            <h2 id="judulModul" class="font-semibold text-slate-900">Ringkasan modul</h2>
        </div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($baris as [$judul, $ket, $ket2, $url]): ?>
                <li class="flex items-start gap-4 px-5 py-3">
                    <div class="min-w-0 flex-1 sm:flex sm:gap-4">
                        <p class="shrink-0 text-sm font-medium text-slate-900 sm:w-44"><?= esc($judul) ?></p>
                        <div class="min-w-0 text-sm text-slate-600">
                            <p><?= esc($ket) ?></p>
                            <?php if ($ket2 !== null): ?><p class="text-xs text-slate-500"><?= esc($ket2) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <a href="<?= site_url($url) ?>" class="shrink-0 text-sm font-medium text-brand-700 hover:underline">Buka</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- Ujian -->
    <section class="order-6 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulUjian">
        <div class="border-b border-slate-200 px-5 py-3.5">
            <h2 id="judulUjian" class="font-semibold text-slate-900">Ujian &middot; TP <?= esc($ujian['tahun']) ?></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs text-slate-500">
                        <th class="px-5 py-2 font-medium">Gelombang</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 text-right font-medium">Sesi</th>
                        <th class="px-3 py-2 text-right font-medium">Tidak hadir</th>
                        <th class="px-5 py-2 text-right font-medium">Susulan belum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($ujian['kartu'] as $u): [$lblStatus, $lencana] = $ujStatus[$u['status']] ?? $ujStatus['draft']; ?>
                        <tr>
                            <td class="px-5 py-2.5"><a href="<?= site_url('admin/ujian/' . $u['slug']) ?>" class="font-medium text-slate-900 hover:text-brand-700 hover:underline"><?= esc($u['label']) ?></a></td>
                            <td class="px-3 py-2.5"><span class="rounded px-2 py-0.5 text-xs font-medium <?= $lencana ?>"><?= $lblStatus ?></span></td>
                            <td class="px-3 py-2.5 text-right tabular-nums"><?= (int) $u['jadwal'] ?></td>
                            <td class="px-3 py-2.5 text-right tabular-nums"><?= (int) $u['takHadir'] ?></td>
                            <td class="px-5 py-2.5 text-right tabular-nums <?= $u['belum'] > 0 ? 'font-semibold text-amber-700' : '' ?>"><?= (int) $u['belum'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="contents lg:flex lg:flex-col lg:gap-6">

    <!-- Hari ini -->
    <section class="order-2 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulHariIni">
        <div class="border-b border-slate-200 px-5 py-3.5">
            <h2 id="judulHariIni" class="font-semibold text-slate-900">Absensi guru hari ini</h2>
            <p class="text-xs text-slate-500"><?= esc($hariIni) ?>, <?= esc(IsianBantu::tanggalIndo($absensi['tanggal'])) ?></p>
        </div>
        <div class="px-5 py-4 text-sm">
            <?php if (! $absensi['hari_aktif']): ?>
                <p class="text-slate-600">Hari ini bukan hari KBM aktif.</p>
            <?php elseif (! $absensi['recorded']): ?>
                <p class="text-slate-600"><?= $angka($absensi['total_guru']) ?> guru mengajar hari ini. Kehadiran <b class="font-semibold text-amber-700">belum dicatat</b>.</p>
            <?php else: ?>
                <div class="grid grid-cols-5 gap-2 text-center">
                    <?php foreach (['hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'] as $kk => $lbl): ?>
                        <div class="rounded-md bg-slate-50 py-2">
                            <p class="text-base font-semibold tabular-nums text-slate-900"><?= (int) $abs[$kk] ?></p>
                            <p class="text-[11px] text-slate-500"><?= $lbl ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($absensi['hari_aktif']): ?>
                <a href="<?= site_url('admin/absensi') ?>?tanggal=<?= esc($absensi['tanggal'], 'url') ?>" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline"><?= $absensi['recorded'] ? 'Lihat atau ubah absensi' : 'Catat sekarang' ?></a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Siswa -->
    <section class="order-5 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulSiswa">
        <div class="flex items-start justify-between gap-2 border-b border-slate-200 px-5 py-3.5">
            <div>
                <h2 id="judulSiswa" class="font-semibold text-slate-900">Siswa aktif</h2>
                <p class="text-xs text-slate-500"><?= $angka($siswa['aktif']) ?> siswa</p>
            </div>
            <a href="<?= site_url('admin/master/siswa') ?>" class="text-sm font-medium text-brand-700 hover:underline">Data siswa</a>
        </div>
        <div class="space-y-5 px-5 py-4">
            <?php if ($siswa['jurusan'] !== []): ?>
                <div>
                    <p class="mb-2 text-xs font-medium text-slate-500">Per jurusan</p>
                    <ul class="space-y-2">
                        <?php foreach ($siswa['jurusan'] as $i => $j): $pct = $siswa['aktif'] > 0 ? round($j['n'] / $siswa['aktif'] * 100) : 0; ?>
                            <li tabindex="0" data-tip="<?= esc($j['nama'] . ' — ' . $angka($j['n']) . ' siswa (' . $pct . '%)', 'attr') ?>" class="grid grid-cols-[4.5rem_1fr_3.5rem] items-center gap-3 rounded text-sm outline-none focus-visible:ring-2 focus-visible:ring-brand-300">
                                <span class="truncate text-slate-600" title="<?= esc($j['nama'], 'attr') ?>"><?= esc($j['label']) ?></span>
                                <span class="h-1.5 rounded-full bg-slate-100"><span class="bar-grow block h-full rounded-full bg-brand-500" style="width: <?= round($j['n'] / $maxJurusan * 100, 2) ?>%; animation-delay: <?= 0.15 + $i * 0.05 ?>s"></span></span>
                                <span class="text-right font-medium tabular-nums text-slate-900"><?= $angka($j['n']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($siswa['tingkat'] !== []): ?>
                <div>
                    <p class="mb-2 text-xs font-medium text-slate-500">Per tingkat</p>
                    <ul class="space-y-2">
                        <?php foreach ($siswa['tingkat'] as $i => $t): ?>
                            <li tabindex="0" data-tip="<?= esc('Kelas ' . $t['label'] . ' — ' . $angka($t['n']) . ' siswa', 'attr') ?>" class="grid grid-cols-[4.5rem_1fr_3.5rem] items-center gap-3 rounded text-sm outline-none focus-visible:ring-2 focus-visible:ring-brand-300">
                                <span class="text-slate-600">Kelas <?= esc($t['label']) ?></span>
                                <span class="h-1.5 rounded-full bg-slate-100"><span class="bar-grow block h-full rounded-full bg-brand-500" style="width: <?= round($t['n'] / $maxTingkat * 100, 2) ?>%; animation-delay: <?= 0.2 + $i * 0.05 ?>s"></span></span>
                                <span class="text-right font-medium tabular-nums text-slate-900"><?= $angka($t['n']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($siswa['tanpa_kelas'] > 0): ?>
                <p class="text-xs text-amber-700"><?= $angka($siswa['tanpa_kelas']) ?> siswa aktif belum punya kelas (tidak terhitung di atas).</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Kesediaan guru -->
    <section class="order-7 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulKesediaan">
        <div class="flex items-start justify-between gap-2 border-b border-slate-200 px-5 py-3.5">
            <div>
                <h2 id="judulKesediaan" class="font-semibold text-slate-900">Kesediaan guru mengajar</h2>
                <p class="text-xs text-slate-500">Formulir <?= ! empty($setting['form_open']) ? 'dibuka' : 'ditutup' ?> &middot; <?= (int) $stats['today'] ?> masuk hari ini</p>
            </div>
            <a href="<?= site_url('admin/submissions') ?>" class="text-sm font-medium text-brand-700 hover:underline">Data</a>
        </div>
        <?php if ($totalPegawai === 0): ?>
            <p class="px-5 py-6 text-sm text-slate-500">Belum ada data kesediaan masuk.</p>
        <?php else: ?>
            <div class="flex items-center gap-5 px-5 py-4">
                <div class="relative h-28 w-28 shrink-0">
                    <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90" role="img" aria-label="Komposisi status kepegawaian">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#f1f5f9" stroke-width="16"/>
                        <?php $mulai = 0; $kIdx = 0;
                        foreach ($stats['byStatus'] as $st => $n): if ((int) $n <= 0) { continue; }
                            $panjang = $n / $totalPegawai * $keliling;
                            $celah   = $totalPegawai > $n ? 2 : 0; // celah 2px warna permukaan antar-irisan ?>
                            <circle cx="60" cy="60" r="52" fill="none" stroke="<?= $warnaPegawai[$st] ?? '#94a3b8' ?>" stroke-width="16"
                                    class="donut-seg outline-none" tabindex="0"
                                    data-tip="<?= esc($st . ' — ' . $n . ' guru (' . round($n / $totalPegawai * 100) . '%)', 'attr') ?>"
                                    stroke-dasharray="<?= round(max(0.5, $panjang - $celah), 2) ?> <?= round($keliling, 2) ?>"
                                    stroke-dashoffset="<?= round(-$mulai, 2) ?>" style="animation-delay: <?= 0.2 + $kIdx * 0.1 ?>s"/>
                        <?php $mulai += $panjang; $kIdx++; endforeach; ?>
                    </svg>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xl font-semibold text-slate-900"><?= $angka($stats['total']) ?></span>
                        <span class="text-[11px] text-slate-500">guru</span>
                    </div>
                </div>
                <ul class="min-w-0 flex-1 space-y-1.5 text-sm">
                    <?php foreach ($stats['byStatus'] as $st => $n): ?>
                        <li class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: <?= $warnaPegawai[$st] ?? '#94a3b8' ?>"></span>
                            <span class="flex-1 text-slate-600"><?= esc($st) ?></span>
                            <span class="font-medium tabular-nums text-slate-900"><?= (int) $n ?></span>
                            <span class="w-9 text-right text-xs tabular-nums text-slate-400"><?= $totalPegawai > 0 ? round($n / $totalPegawai * 100) : 0 ?>%</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <!-- Aktivitas terbaru -->
    <section class="order-8 rounded-lg border border-slate-200 bg-white" aria-labelledby="judulAktivitas">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
            <h2 id="judulAktivitas" class="font-semibold text-slate-900">Aktivitas terbaru</h2>
            <a href="<?= site_url('admin/audit') ?>" class="text-sm font-medium text-brand-700 hover:underline">Audit log</a>
        </div>
        <?php if ($aktivitas === []): ?>
            <p class="px-5 py-5 text-sm text-slate-500">Belum ada aktivitas tercatat.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($aktivitas as $a): ?>
                    <li class="px-5 py-2.5 text-sm">
                        <p class="text-slate-700"><span class="font-medium text-slate-900"><?= esc($a['nama'] ?: (! empty($a['admin_id']) ? 'Akun terhapus' : 'Sistem')) ?></span> &middot; <?= esc(mb_strimwidth((string) $a['deskripsi'], 0, 90, '…')) ?></p>
                        <p class="text-xs text-slate-500"><?= esc($waktuPendek($a['waktu'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
</div>

<?php // Satu tooltip bersama untuk semua [data-tip] (isi diisi lewat textContent di dashboard.js). ?>
<div id="vizTip" role="tooltip" class="pointer-events-none fixed z-50 hidden max-w-xs rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-lg"></div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/dashboard.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/dashboard.js') ?>"></script>
<?= $this->endSection() ?>
