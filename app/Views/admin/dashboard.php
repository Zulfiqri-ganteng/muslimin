<?php
/**
 * Dashboard Admin — rombak tampilan F4: pola halaman muka template Flowbite Admin Dashboard (resources/flowbite/content/_index.html)
 * + grafik ApexCharts. Susunan: kepala (sapaan, tanggal, tombol cepat) · 4 kartu angka bergrafik kecil (tiap kartu = tautan ke
 * halamannya) · tren kehadiran guru (area; 7/30/90 hari, dibanding rentang sebelumnya) + tab "Perlu tindakan / Aktivitas terbaru" ·
 * siswa per jurusan, absensi hari ini, kesediaan guru (donat) · beban mengajar (batang mendatar + garis batas) + ringkasan modul ·
 * tabel ujian per gelombang. Grafik = <div class="grafik" data-grafik="…" data-isi="JSON"> yang digambar admin/dashboard.js (tanpa
 * skrip/gaya sebaris; komponen Alpine kit saja → siap Alpine versi CSP). Semua angka juga tertulis sebagai teks.
 *
 * @var array $kur @var array $stats @var array $setting @var array $absensi @var array $ujian @var array $siswa
 * @var int $jpTugas @var array $modul @var list<array> $tindakan @var list<array> $aktivitas @var string $nama
 * @var list<array{t:string,n:int,hadir:int,telat:int,izin:int,sakit:int,alpa:int}> $tren
 */
use App\Libraries\IsianBantu;

$angka   = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$json    = static fn ($v): string => esc((string) json_encode($v, JSON_UNESCAPED_UNICODE), 'attr');
$jam     = (int) date('G');
$salam   = $jam < 4 ? 'Selamat malam' : ($jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam')));
$hariIni = ucfirst(mb_strtolower((string) $absensi['hari_nama'])); // data hari tersimpan HURUF BESAR
$tglIni  = IsianBantu::tanggalIndo($absensi['tanggal']);
$bulanPendek = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$waktuPendek = static function (?string $dt) use ($bulanPendek): string {
    $t = $dt ? strtotime($dt) : false;

    return $t ? (int) date('j', $t) . ' ' . $bulanPendek[(int) date('n', $t) - 1] . ' ' . date('Y', $t) . ', ' . date('H.i', $t) : '';
};
$urlAbsensi = site_url('admin/absensi') . '?tanggal=' . rawurlencode((string) $absensi['tanggal']);

// Kehadiran guru hari ini (status harian per guru).
$abs         = $absensi['ringkas'];
$absTotal    = array_sum($abs);
$persenHadir = $absTotal > 0 ? (int) round(($abs['hadir'] + $abs['telat']) / $absTotal * 100) : 0;
$statusAbsen = ['hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];

// Penjadwalan KBM.
$jpJadwal = (int) $kur['jpTerjadwal'];
$persenJp = $jpTugas > 0 ? min(100, (int) round($jpJadwal / $jpTugas * 100)) : 0;

// Guru vs batas jam mengajar (statistik penjadwalan).
$guruSesuai = max(0, (int) $kur['guru'] - (int) $kur['kurang'] - (int) $kur['lebih']);

// Tren kehadiran: nilai awal 30 hari (dashboard.js menghitung ulang saat rentang diganti — rumusnya sama: jumlah guru hadir +
// telat ÷ jumlah guru, semua hari tercatat dalam rentang; pembanding = rentang yang sama tepat sebelumnya).
$potongTren = static function (array $tren, int $hari, int $geser): array {
    $akhir = strtotime('today -' . $geser . ' days');
    $awal  = strtotime('-' . ($hari - 1) . ' days', $akhir);

    return array_values(array_filter($tren, static fn (array $d): bool => ($t = strtotime($d['t'])) >= $awal && $t <= $akhir));
};
$persenTren = static function (array $daftar): ?float {
    $h = 0;
    $n = 0;
    foreach ($daftar as $d) {
        $h += $d['hadir'] + $d['telat'];
        $n += $d['n'];
    }

    return $n > 0 ? $h / $n * 100 : null;
};
$pTren  = $persenTren($potongTren($tren, 30, 0));
$pLalu  = $persenTren($potongTren($tren, 30, 30));
$beda   = ($pTren !== null && $pLalu !== null) ? round($pTren - $pLalu, 1) : null;
$jmlHariTren = count($potongTren($tren, 30, 0));

// Beban mengajar: 12 terbanyak (nama dipendekkan untuk sumbu; nama lengkap di tooltip).
$beban = array_map(static fn (array $b): array => [
    'kode'  => (string) $b['kode_guru'],
    'nama'  => (string) $b['nama'],
    'label' => $b['kode_guru'] . ' · ' . mb_strimwidth(trim(explode(',', (string) $b['nama'])[0]), 0, 16, '…'),
    'jp'    => (int) $b['total_jp'],
    'max'   => (int) $b['max_beban'],
    'tugas' => (int) ($b['jml_tugas'] ?? 0),
], $kur['chart'] ?? []);

// Siswa per jurusan & kesediaan (donat; warna = urutan palet grafik).
$jurusan      = array_map(static fn (array $j): array => ['label' => (string) $j['label'], 'nama' => (string) $j['nama'], 'n' => (int) $j['n']], $siswa['jurusan']);
$tingkat      = array_map(static fn (array $t): array => ['label' => (string) $t['label'], 'n' => (int) $t['n']], $siswa['tingkat']);
$totalJurusan = array_sum(array_column($jurusan, 'n'));
$pegawai      = [];
foreach ($stats['byStatus'] as $st => $n) {
    $pegawai[] = ['label' => (string) $st, 'nama' => (string) $st, 'n' => (int) $n];
}
$totalPegawai = array_sum(array_column($pegawai, 'n'));
$warnaKe      = static fn (int $i): string => 'warna-grafik-' . (($i % 8) + 1);
$persen       = static fn (int $n, int $total): int => $total > 0 ? (int) round($n / $total * 100) : 0;

// Ujian: status periode → lencana kit.
$ujStatus = [
    'draft'    => ['Draft', 'lencana-abu'],
    'berjalan' => ['Berjalan', 'lencana-hijau'],
    'selesai'  => ['Selesai', 'lencana-merek'],
];

// Perlu tindakan: [ikon, warna ikon, keterangan tingkat] — warna selalu disertai teks.
$tingkatTindakan = [
    'kritis' => ['exclamation-circle', 'text-red-600 dark:text-red-500', 'Mendesak'],
    'awas'   => ['clock', 'text-yellow-600 dark:text-yellow-400', 'Perlu ditindaklanjuti'],
    'info'   => ['info-circle', 'text-brand-700 dark:text-brand-400', 'Informasi'],
];

// Ringkasan modul: [judul, ikon, keterangan, keterangan baris 2 | null, alamat]
$baris = [];
if ($m = $modul['pkl']) {
    $s = $m['status'];
    $baris[] = ['PKL / Prakerin', 'briefcase', $angka($s['menunggu'] ?? 0) . ' menunggu ACC · ' . $angka($s['perbaikan'] ?? 0) . ' perbaikan · ' . $angka($s['disetujui'] ?? 0) . ' disetujui · ' . $angka($s['ditolak'] ?? 0) . ' ditolak',
        'Siswa sudah PKL ' . $angka($m['siswa']['sudah_pkl'] ?? 0) . ' dari ' . $angka($m['siswa']['total'] ?? 0) . ' (' . (int) ($m['siswa']['persen_pkl'] ?? 0) . '%)', 'admin/pkl'];
}
if ($m = $modul['surat']) {
    $baris[] = ['Surat sekolah', 'envelope', $angka($m['menunggu'] ?? 0) . ' menunggu ACC · ' . $angka($m['belum_unduh'] ?? 0) . ' belum diunduh · ' . $angka($m['dikembalikan'] ?? 0) . ' dikembalikan',
        $angka($m['semua'] ?? 0) . ' surat tercatat', 'admin/surat'];
}
if ($m = $modul['ukk']) {
    $p = $m['peserta'];
    $baris[] = ['Uji kompetensi (UKK)', 'award', $angka(array_sum($p)) . ' peserta · ' . $angka($p['lulus'] ?? 0) . ' lulus · ' . $angka($p['tidak_lulus'] ?? 0) . ' tidak lulus',
        $angka($m['jadwal']) . ' jadwal uji', 'admin/peserta-ukk'];
}
if ($m = $modul['lab']) {
    $baris[] = ['Laboratorium', 'desktop-pc', $angka(array_sum($m['pinjam'])) . ' sedang dipinjam' . (($m['pinjam']['terlambat'] ?? 0) > 0 ? ' (' . $angka($m['pinjam']['terlambat']) . ' terlambat)' : '') . ' · ' . $angka(array_sum($m['kerusakan'])) . ' kerusakan terbuka',
        null, 'admin/peminjaman'];
}
if ($m = $modul['dokumen']) {
    $mb = static fn (int $b): string => number_format($b / 1048576, $b < 10 * 1048576 ? 1 : 0, ',', '.') . ' MB';
    $baris[] = ['Arsip dokumen', 'folder-open', $angka($m['jumlah']) . ' dokumen · ' . $mb($m['byte']) . ' dari ' . $mb($m['kuota']) . ' terpakai', null, 'admin/dokumen'];
}
if (($m = $modul['biodata']) !== null) {
    $baris[] = ['Isian biodata siswa', 'profile-card', $angka($m['menunggu'] ?? 0) . ' menunggu · ' . $angka($m['perbaikan'] ?? 0) . ' perbaikan · ' . $angka($m['disetujui'] ?? 0) . ' disetujui', null, 'admin/biodata'];
}
$k = $modul['kesediaan'];
$baris[] = ['Kesediaan guru mengajar', 'clipboard-list', $angka($k['total']) . ' isian · ' . $angka($k['hari_ini']) . ' masuk hari ini · formulir ' . ($k['dibuka'] ? 'dibuka' : 'ditutup'), null, 'admin/submissions'];

// Teks aria untuk grafik (pembaca layar membaca angkanya, bukan gambarnya).
$ariaDaftar = static fn (string $judul, array $daftar, string $satuan): string => $judul . ': ' . implode(', ', array_map(
    static fn (array $d): string => $d['label'] . ' ' . number_format($d['n'], 0, ',', '.') . ' ' . $satuan,
    $daftar
));
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('kepala') ?>
<?php ob_start(); ?>
    <?php if ($absensi['hari_aktif']): ?>
        <a href="<?= esc($urlAbsensi, 'attr') ?>" class="btn btn-utama"><?= ikon('clipboard-check', 'h-4 w-4') ?><?= $absensi['recorded'] ? 'Lihat absensi hari ini' : 'Catat absensi hari ini' ?></a>
    <?php endif; ?>
    <a href="<?= site_url('admin/pengumuman') ?>" class="btn btn-netral"><?= ikon('bullhorn', 'h-4 w-4') ?>Pengumuman</a>
<?php $aksiKepala = (string) ob_get_clean(); ?>
<?= ui_kepala([
    'judul' => $salam . ', ' . trim(explode(',', $nama)[0]),
    'ket'   => $hariIni . ', ' . $tglIni . ' · Tahun Pelajaran ' . ($setting['academic_year'] ?? '-'),
    'aksi'  => $aksiKepala,
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dashboard_v5',
    'helpTitle' => 'Dashboard',
    'helpBody'  => '<p>Ringkasan seluruh sekolah dalam satu halaman. Arahkan kursor ke grafik untuk melihat rinciannya.</p>
        <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><b>Kartu angka</b> di atas bisa diklik untuk membuka halamannya (Data Siswa, Data Guru, Absensi, Jadwal).</li>
        <li><b>Kehadiran guru</b>: persentase guru hadir (termasuk telat) pada hari-hari yang absensinya sudah dicatat. Pilih 7, 30, atau 90 hari di bawah grafik; panah hijau/merah = naik/turun dibanding rentang sebelumnya.</li>
        <li><b>Perlu tindakan</b>: pekerjaan yang menunggu (bentrok jadwal, absensi belum dicatat, ajuan PKL &amp; surat menunggu ACC, biodata, susulan ujian, lab, siswa tanpa kelas). Klik untuk membukanya. Tab <b>Aktivitas terbaru</b> = 8 kegiatan staf terakhir.</li>
        <li><b>Beban mengajar</b>: batang = JP penugasan guru, garis tegak = batas maksimalnya; batang merah = lebih dari batas.</li>
        <li>Angka penjadwalan diperbarui paling lama tiap 30 menit; angka siswa tiap 5 menit; selebihnya langsung.</li>
        </ul>',
]) ?>

<!-- ===== 1. Kartu angka utama (pola kartu statistik template) ===== -->
<div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
    <a href="<?= site_url('admin/master/siswa') ?>" class="kartu kartu-isi kartu-tautan flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 class="stat-label">Siswa aktif</h2>
            <p class="stat-angka mt-1"><?= $angka($siswa['aktif']) ?></p>
            <p class="stat-ket">di <?= $angka($kur['kelas']) ?> kelas<?php if ($siswa['tanpa_kelas'] > 0): ?> · <span class="font-medium text-yellow-600 dark:text-yellow-400"><?= $angka($siswa['tanpa_kelas']) ?> tanpa kelas</span><?php endif; ?></p>
        </div>
        <?php if ($tingkat !== []): ?>
            <div class="grafik grafik-kecil" data-grafik="tingkat" data-isi="<?= $json($tingkat) ?>" role="img" aria-label="<?= esc($ariaDaftar('Siswa aktif per tingkat', $tingkat, 'siswa'), 'attr') ?>"></div>
        <?php endif; ?>
    </a>

    <a href="<?= site_url('admin/master/guru') ?>" class="kartu kartu-isi kartu-tautan flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 class="stat-label">Guru pengajar</h2>
            <p class="stat-angka mt-1"><?= $angka($kur['guru']) ?></p>
            <p class="stat-ket"><?= $angka($kur['mapel']) ?> mata pelajaran</p>
        </div>
        <?php if ((int) $kur['guru'] > 0): ?>
            <div class="grafik grafik-kecil" data-grafik="batas" data-isi="<?= $json(['kurang' => (int) $kur['kurang'], 'sesuai' => $guruSesuai, 'lebih' => (int) $kur['lebih']]) ?>"
                 role="img" aria-label="<?= esc('Guru menurut batas jam: ' . $kur['kurang'] . ' kurang, ' . $guruSesuai . ' sesuai, ' . $kur['lebih'] . ' lebih', 'attr') ?>"></div>
        <?php endif; ?>
    </a>

    <a href="<?= esc($urlAbsensi, 'attr') ?>" class="kartu kartu-isi kartu-tautan flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 class="stat-label">Kehadiran guru hari ini</h2>
            <?php if (! $absensi['hari_aktif']): ?>
                <p class="stat-angka mt-1 text-gray-400 dark:text-gray-500">&ndash;</p>
                <p class="stat-ket">Bukan hari KBM</p>
            <?php elseif (! $absensi['recorded']): ?>
                <p class="mt-1 text-xl leading-none font-bold text-yellow-600 sm:text-2xl dark:text-yellow-400">Belum dicatat</p>
                <p class="stat-ket"><?= $angka($absensi['total_guru']) ?> guru mengajar hari ini</p>
            <?php else: ?>
                <p class="stat-angka mt-1"><?= $persenHadir ?>%</p>
                <p class="stat-ket"><?= $angka($abs['hadir'] + $abs['telat']) ?> dari <?= $angka($absTotal) ?> guru hadir</p>
            <?php endif; ?>
        </div>
        <?php if ($absensi['hari_aktif'] && $absensi['recorded']): ?>
            <div class="grafik w-28 shrink-0" data-grafik="radial" data-nilai="<?= $persenHadir ?>" role="img" aria-label="Kehadiran guru hari ini <?= $persenHadir ?> persen"></div>
        <?php endif; ?>
    </a>

    <a href="<?= site_url('admin/jadwal') ?>" class="kartu kartu-isi kartu-tautan flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 class="stat-label">Jadwal KBM tersusun</h2>
            <p class="stat-angka mt-1"><?= $persenJp ?>%</p>
            <p class="stat-ket"><?= $angka($jpJadwal) ?> dari <?= $angka($jpTugas) ?> JP penugasan</p>
        </div>
        <div class="grafik w-28 shrink-0" data-grafik="radial" data-nilai="<?= $persenJp ?>" role="img" aria-label="Jadwal KBM tersusun <?= $persenJp ?> persen"></div>
    </a>
</div>

<!-- ===== 2. Tren kehadiran (widget utama template) + tab Perlu tindakan / Aktivitas ===== -->
<div class="mt-4 grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
    <section class="kartu kartu-isi flex flex-col 2xl:col-span-2" data-tren aria-labelledby="judulTren">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="stat-angka" data-tren-angka aria-live="polite"><?= $pTren === null ? '–' : (int) round($pTren) . '%' ?></p>
                <h2 id="judulTren" class="stat-label mt-1">Kehadiran guru · <span data-tren-judul>30 hari terakhir</span></h2>
            </div>
            <p class="flex flex-col items-end text-right text-sm" data-tren-banding<?= $beda === null ? ' hidden' : '' ?>>
                <span class="<?= $beda === null || $beda == 0 ? 'tren-datar' : ($beda > 0 ? 'tren-naik' : 'tren-turun') ?> text-base" data-tren-beda>
                    <span data-tren-poin><?= $beda !== null ? number_format(abs($beda), 1, ',', '.') . ' poin' : '' ?></span>
                    <span data-tren-naik<?= $beda !== null && $beda > 0 ? '' : ' hidden' ?>><?= ikon('caret-up', 'h-5 w-5', 'solid') ?><span class="sr-only">naik</span></span>
                    <span data-tren-turun<?= $beda !== null && $beda < 0 ? '' : ' hidden' ?>><?= ikon('caret-down', 'h-5 w-5', 'solid') ?><span class="sr-only">turun</span></span>
                </span>
                <span class="text-gray-500 dark:text-gray-400">dibanding <span data-tren-pembanding>30 hari sebelumnya</span></span>
            </p>
        </div>
        <div class="grafik mb-4 min-h-60 sm:min-h-[380px]" data-grafik="tren" data-isi="<?= $json($tren) ?>" role="img"
             aria-label="Grafik kehadiran guru per hari tercatat. Rata-rata 30 hari terakhir <?= $pTren === null ? 'belum ada data' : (int) round($pTren) . ' persen' ?>."<?= $jmlHariTren === 0 ? ' hidden' : '' ?>></div>
        <div data-tren-kosong<?= $jmlHariTren === 0 ? '' : ' hidden' ?>>
            <?= ui_kosong(['ikon' => 'chart-line-up', 'judul' => 'Belum ada absensi tercatat', 'teks' => 'Grafik muncul setelah absensi guru dicatat di menu Absensi Guru.']) ?>
        </div>
        <div class="kaki-grafik mt-auto">
            <div class="relative" x-data="dropdown" @click.outside="tutup()" @keydown.escape="tutup(true)" @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)">
                <button type="button" class="tombol-rentang" x-ref="pemicu" @click="alih()" :aria-expanded="buka" aria-haspopup="true">
                    <span data-tren-label>30 hari terakhir</span><?= ikon('chevron-down', 'h-4 w-4') ?>
                </button>
                <div class="dropdown-menu bottom-full mb-2" x-show="buka" x-cloak x-transition.origin.bottom.left.duration.150ms>
                    <ul class="py-1">
                        <li><button type="button" class="dropdown-item" data-rentang="7" @click="tutup()">7 hari terakhir</button></li>
                        <li><button type="button" class="dropdown-item" data-rentang="30" @click="tutup()">30 hari terakhir</button></li>
                        <li><button type="button" class="dropdown-item" data-rentang="90" @click="tutup()">90 hari terakhir</button></li>
                    </ul>
                </div>
            </div>
            <a href="<?= site_url('admin/absensi/rekap') ?>" class="tautan-kartu">Rekap absensi<?= ikon('chevron-right', 'h-4 w-4') ?></a>
        </div>
    </section>

    <section class="kartu kartu-isi flex flex-col" x-data="tab" data-awal="tindakan" aria-labelledby="judulKerja">
        <h2 id="judulKerja" class="kartu-judul mb-4">Pekerjaan &amp; aktivitas</h2>
        <div class="tab-penuh-daftar" role="tablist" aria-label="Pekerjaan dan aktivitas" @keydown.right.prevent="pindah(1)" @keydown.left.prevent="pindah(-1)">
            <button type="button" role="tab" class="tab-penuh" data-tab="tindakan" :aria-selected="aktif === 'tindakan'" :tabindex="aktif === 'tindakan' ? '0' : '-1'" @click="pilih('tindakan')">
                Perlu tindakan<?php if ($tindakan !== []): ?> <span class="tab-jumlah"><?= count($tindakan) ?></span><?php endif; ?>
            </button>
            <button type="button" role="tab" class="tab-penuh" data-tab="aktivitas" :aria-selected="aktif === 'aktivitas'" :tabindex="aktif === 'aktivitas' ? '0' : '-1'" @click="pilih('aktivitas')">Aktivitas terbaru</button>
        </div>

        <div role="tabpanel" aria-label="Perlu tindakan" class="mt-2 flex-1" x-show="aktif === 'tindakan'">
            <?php if ($tindakan === []): ?>
                <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Semua beres', 'teks' => 'Tidak ada pekerjaan yang menunggu.']) ?>
            <?php else: ?>
                <ul class="daftar-kartu panel-gulir -mx-2 px-2">
                    <?php foreach ($tindakan as $t): [$ikonT, $warnaT, $ketT] = $tingkatTindakan[$t['tingkat']] ?? $tingkatTindakan['info']; ?>
                        <li>
                            <a href="<?= esc($t['url'], 'attr') ?>" class="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-1 transition-colors hover:bg-gray-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-300 dark:hover:bg-gray-700">
                                <span class="ubin-ikon <?= $warnaT ?>"><?= ikon($ikonT, 'h-5 w-5') ?></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white"><?= esc(ucfirst($t['teks'])) ?></span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400"><?= $ketT ?></span>
                                </span>
                                <span class="text-base font-semibold text-gray-900 tabular-nums dark:text-white"><?= $angka($t['n']) ?></span>
                                <span class="text-gray-400 group-hover:text-brand-700 dark:group-hover:text-brand-400"><?= ikon('chevron-right', 'h-4 w-4') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div role="tabpanel" aria-label="Aktivitas terbaru" class="mt-4 flex flex-1 flex-col" x-show="aktif === 'aktivitas'" x-cloak>
            <?php if ($aktivitas === []): ?>
                <?= ui_kosong(['ikon' => 'clock', 'judul' => 'Belum ada aktivitas', 'teks' => 'Kegiatan staf akan tercatat di sini.']) ?>
            <?php else: ?>
                <div class="panel-gulir ps-1.5"><ol class="relative border-s border-gray-200 dark:border-gray-700">
                    <?php foreach ($aktivitas as $a): ?>
                        <li class="ms-4 pb-4 last:pb-0">
                            <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full border border-white bg-gray-200 dark:border-gray-800 dark:bg-gray-700" aria-hidden="true"></span>
                            <time class="text-xs leading-none text-gray-400 dark:text-gray-500" datetime="<?= esc((string) $a['waktu'], 'attr') ?>"><?= esc($waktuPendek($a['waktu'])) ?></time>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($a['nama'] ?: (! empty($a['admin_id']) ? 'Akun terhapus' : 'Sistem')) ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?= esc(mb_strimwidth((string) $a['deskripsi'], 0, 110, '…')) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol></div>
            <?php endif; ?>
            <div class="kaki-grafik mt-auto">
                <span class="text-sm text-gray-500 dark:text-gray-400"><?= count($aktivitas) ?> kegiatan terakhir</span>
                <a href="<?= site_url('admin/audit') ?>" class="tautan-kartu">Audit log<?= ikon('chevron-right', 'h-4 w-4') ?></a>
            </div>
        </div>
    </section>
</div>

<!-- ===== 3. Kartu donat (pola "Traffic by device" template) ===== -->
<div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <section class="kartu kartu-isi" aria-labelledby="judulJurusan">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-gray-200 pb-4 dark:border-gray-700">
            <div>
                <h2 id="judulJurusan" class="stat-label">Siswa aktif per jurusan</h2>
                <p class="stat-angka mt-1"><?= $angka($siswa['aktif']) ?></p>
            </div>
            <a href="<?= site_url('admin/master/siswa') ?>" class="tautan-kartu">Data siswa<?= ikon('chevron-right', 'h-4 w-4') ?></a>
        </div>
        <?php if ($jurusan === []): ?>
            <?= ui_kosong(['ikon' => 'users', 'judul' => 'Belum ada siswa berkelas', 'teks' => 'Atur kelas siswa di menu Data Siswa.']) ?>
        <?php else: ?>
            <div class="grafik" data-grafik="donat" data-isi="<?= $json($jurusan) ?>" data-satuan="siswa" data-total="Siswa"
                 role="img" aria-label="<?= esc($ariaDaftar('Siswa aktif per jurusan', $jurusan, 'siswa'), 'attr') ?>"></div>
            <ul class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <?php foreach ($jurusan as $i => $j): ?>
                    <li class="flex items-center gap-2" title="<?= esc($j['nama'], 'attr') ?>">
                        <span class="titik-legenda <?= $warnaKe($i) ?>"></span>
                        <span class="min-w-0 flex-1 truncate text-gray-600 dark:text-gray-300"><?= esc($j['label']) ?></span>
                        <span class="font-semibold text-gray-900 tabular-nums dark:text-white"><?= $angka($j['n']) ?></span>
                        <span class="w-9 text-right text-xs text-gray-400 tabular-nums"><?= $persen($j['n'], $totalJurusan) ?>%</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($siswa['tanpa_kelas'] > 0): ?>
            <p class="mt-4 text-sm text-yellow-700 dark:text-yellow-400"><?= $angka($siswa['tanpa_kelas']) ?> siswa aktif belum punya kelas (tidak terhitung di grafik).</p>
        <?php endif; ?>
    </section>

    <section class="kartu kartu-isi" aria-labelledby="judulAbsen">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-gray-200 pb-4 dark:border-gray-700">
            <div class="min-w-0">
                <h2 id="judulAbsen" class="stat-label">Absensi guru hari ini</h2>
                <?php if (! $absensi['hari_aktif']): ?>
                    <p class="mt-1 text-xl leading-none font-bold text-gray-400 dark:text-gray-500">Bukan hari KBM</p>
                <?php elseif (! $absensi['recorded']): ?>
                    <p class="mt-1 text-xl leading-none font-bold text-yellow-600 sm:text-2xl dark:text-yellow-400">Belum dicatat</p>
                <?php else: ?>
                    <p class="stat-angka mt-1"><?= $persenHadir ?>% <span class="text-base font-normal text-gray-500 dark:text-gray-400">hadir</span></p>
                <?php endif; ?>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc($hariIni) ?>, <?= esc($tglIni) ?></p>
            </div>
            <?php if ($absensi['hari_aktif']): ?>
                <a href="<?= esc($urlAbsensi, 'attr') ?>" class="tautan-kartu"><?= $absensi['recorded'] ? 'Lihat' : 'Catat' ?><?= ikon('chevron-right', 'h-4 w-4') ?></a>
            <?php endif; ?>
        </div>
        <?php if (! $absensi['hari_aktif']): ?>
            <?= ui_kosong(['ikon' => 'calendar-month', 'judul' => 'Hari ini bukan hari KBM aktif', 'teks' => 'Tidak ada jadwal mengajar yang perlu dicatat.']) ?>
        <?php elseif (! $absensi['recorded']): ?>
            <?= ui_kosong(['ikon' => 'clipboard-check', 'judul' => $angka($absensi['total_guru']) . ' guru mengajar hari ini', 'teks' => 'Kehadiran hari ini belum dicatat.',
                'aksi' => '<a href="' . esc($urlAbsensi, 'attr') . '" class="btn btn-utama">' . ikon('clipboard-check', 'h-4 w-4') . 'Catat sekarang</a>']) ?>
        <?php else: ?>
            <?php $absData = []; foreach ($statusAbsen as $kk => $lbl) { $absData[] = ['kunci' => $kk, 'label' => $lbl, 'nama' => $lbl, 'n' => (int) $abs[$kk]]; } ?>
            <div class="grafik" data-grafik="donat" data-warna="status" data-isi="<?= $json($absData) ?>" data-satuan="guru" data-total="Guru"
                 role="img" aria-label="<?= esc($ariaDaftar('Absensi guru hari ini', $absData, 'guru'), 'attr') ?>"></div>
            <ul class="mt-4 grid grid-cols-5 gap-2 text-center">
                <?php foreach ($absData as $d): ?>
                    <li class="rounded-lg bg-gray-50 px-1 py-2 dark:bg-gray-700">
                        <p class="text-lg font-bold text-gray-900 tabular-nums dark:text-white"><?= $angka($d['n']) ?></p>
                        <p class="flex items-center justify-center gap-1 text-xs text-gray-500 dark:text-gray-400"><span class="titik-legenda warna-grafik-<?= $d['kunci'] ?>"></span><?= $d['label'] ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="kartu kartu-isi md:col-span-2 xl:col-span-1" aria-labelledby="judulKesediaan">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-gray-200 pb-4 dark:border-gray-700">
            <div>
                <h2 id="judulKesediaan" class="stat-label">Kesediaan guru mengajar</h2>
                <p class="stat-angka mt-1"><?= $angka($stats['total']) ?> <span class="text-base font-normal text-gray-500 dark:text-gray-400">isian</span></p>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="lencana <?= ! empty($setting['form_open']) ? 'lencana-hijau' : 'lencana-abu' ?>">Formulir <?= ! empty($setting['form_open']) ? 'dibuka' : 'ditutup' ?></span>
                    <?= $angka($stats['today']) ?> masuk hari ini
                </p>
            </div>
            <a href="<?= site_url('admin/submissions') ?>" class="tautan-kartu">Data<?= ikon('chevron-right', 'h-4 w-4') ?></a>
        </div>
        <?php if ($totalPegawai === 0): ?>
            <?= ui_kosong(['ikon' => 'clipboard-list', 'judul' => 'Belum ada isian kesediaan', 'teks' => 'Isian guru dari formulir kesediaan akan muncul di sini.']) ?>
        <?php else: ?>
            <div class="grafik" data-grafik="donat" data-isi="<?= $json($pegawai) ?>" data-satuan="guru" data-total="Guru"
                 role="img" aria-label="<?= esc($ariaDaftar('Kesediaan menurut status kepegawaian', $pegawai, 'guru'), 'attr') ?>"></div>
            <ul class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <?php foreach ($pegawai as $i => $p): ?>
                    <li class="flex items-center gap-2">
                        <span class="titik-legenda <?= $warnaKe($i) ?>"></span>
                        <span class="min-w-0 flex-1 text-gray-600 dark:text-gray-300"><?= esc($p['label']) ?></span>
                        <span class="font-semibold text-gray-900 tabular-nums dark:text-white"><?= $angka($p['n']) ?></span>
                        <span class="w-9 text-right text-xs text-gray-400 tabular-nums"><?= $persen($p['n'], $totalPegawai) ?>%</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- ===== 4. Beban mengajar + ringkasan modul ===== -->
<div class="mt-4 grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
    <section class="kartu kartu-isi 2xl:col-span-2" aria-labelledby="judulBeban">
        <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 id="judulBeban" class="kartu-judul">Beban mengajar guru</h2>
                <p class="kartu-ket">12 terbanyak · batang = JP penugasan, garis tegak = batas maksimal, merah = lebih dari batas</p>
            </div>
            <a href="<?= site_url('admin/kurikulum/rekap') ?>" class="tautan-kartu">Rekap lengkap<?= ikon('chevron-right', 'h-4 w-4') ?></a>
        </div>
        <?php if ($beban === []): ?>
            <?= ui_kosong(['ikon' => 'chalkboard-user', 'judul' => 'Belum ada data beban', 'teks' => 'Atur Penugasan guru terlebih dahulu.']) ?>
        <?php else: ?>
            <div class="grafik<?= count($beban) >= 12 ? ' min-h-[408px]' : '' ?>" data-grafik="beban" data-isi="<?= $json($beban) ?>" role="img"
                 aria-label="<?= esc('Beban mengajar 12 guru terbanyak: ' . implode(', ', array_map(static fn (array $b): string => $b['kode'] . ' ' . $b['jp'] . ' dari ' . $b['max'] . ' JP', $beban)), 'attr') ?>"></div>
            <div class="kaki-grafik flex-wrap justify-start gap-x-5 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="titik-legenda warna-grafik-1"></span>JP penugasan</span>
                <span class="inline-flex items-center gap-1.5"><span class="titik-legenda warna-grafik-alpa"></span>Lebih dari batas (<?= $angka($kur['lebih']) ?> guru)</span>
                <span>Kurang dari batas: <b class="font-semibold text-gray-900 dark:text-white"><?= $angka($kur['kurang']) ?></b> guru</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-0.5 rounded-sm warna-grafik-batas"></span>Batas maksimal</span>
                <span>Bentrok jadwal: <b class="font-semibold <?= (int) $kur['bentrok'] > 0 ? 'text-red-600 dark:text-red-500' : 'text-gray-900 dark:text-white' ?>"><?= $angka($kur['bentrok']) ?></b></span>
            </div>
        <?php endif; ?>
    </section>

    <section class="kartu kartu-isi" aria-labelledby="judulModul">
        <h2 id="judulModul" class="kartu-judul mb-2">Ringkasan modul</h2>
        <ul class="daftar-kartu">
            <?php foreach ($baris as [$judul, $ikonM, $ket, $ket2, $url]): ?>
                <li>
                    <a href="<?= site_url($url) ?>" class="group -mx-2 flex items-start gap-3 rounded-lg px-2 py-1 transition-colors hover:bg-gray-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-300 dark:hover:bg-gray-700">
                        <span class="ubin-ikon"><?= ikon($ikonM, 'h-5 w-5') ?></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-gray-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400"><?= esc($judul) ?></span>
                            <span class="block text-sm text-gray-500 dark:text-gray-400"><?= esc($ket) ?></span>
                            <?php if ($ket2 !== null): ?><span class="block text-xs text-gray-400 dark:text-gray-500"><?= esc($ket2) ?></span><?php endif; ?>
                        </span>
                        <span class="mt-2.5 text-gray-400 group-hover:text-brand-700 dark:group-hover:text-brand-400"><?= ikon('chevron-right', 'h-4 w-4') ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<!-- ===== 5. Ujian per gelombang (pola tabel "Transactions" template) ===== -->
<section class="kartu kartu-isi mt-4" aria-labelledby="judulUjian">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 id="judulUjian" class="kartu-judul">Ujian</h2>
            <p class="kartu-ket">Tahun pelajaran <?= esc($ujian['tahun']) ?> · keadaan tiap gelombang</p>
        </div>
        <a href="<?= site_url('admin/ujian') ?>" class="tautan-kartu">Semua ujian<?= ikon('chevron-right', 'h-4 w-4') ?></a>
    </div>
    <div class="tabel-bungkus overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="tabel">
            <thead>
                <tr>
                    <th scope="col">Gelombang</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-right">Sesi</th>
                    <th scope="col" class="text-right">Tidak hadir</th>
                    <th scope="col" class="text-right">Susulan belum dijadwalkan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ujian['kartu'] as $u): [$lblStatus, $lencana] = $ujStatus[$u['status']] ?? $ujStatus['draft']; ?>
                    <tr>
                        <th scope="row"><a href="<?= site_url('admin/ujian/' . $u['slug']) ?>" class="hover:text-brand-700 hover:underline dark:hover:text-brand-400"><?= esc($u['label']) ?></a></th>
                        <td><span class="lencana <?= $lencana ?>"><?= $lblStatus ?></span></td>
                        <td class="text-right tabular-nums"><?= $angka($u['jadwal']) ?></td>
                        <td class="text-right tabular-nums"><?= $angka($u['takHadir']) ?></td>
                        <td class="text-right tabular-nums">
                            <?php if ((int) $u['belum'] > 0): ?>
                                <span class="lencana lencana-kuning"><?= $angka($u['belum']) ?> belum</span>
                            <?php else: ?>
                                <span class="text-gray-400 dark:text-gray-500">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php // ApexCharts (MIT) lokal; CSS-nya dimuat sebagai berkas (link id="apexcharts-css") → ApexCharts tidak menyisipkan tag gaya sendiri (siap CSP). ?>
<link id="apexcharts-css" rel="stylesheet" href="<?= base_url('assets/css/vendor/apexcharts.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/vendor/apexcharts.css') ?>" <?= csp_style_nonce() ?>>
<script defer src="<?= base_url('assets/js/vendor/apexcharts.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/vendor/apexcharts.min.js') ?>" <?= csp_script_nonce() ?>></script>
<script defer src="<?= base_url('assets/js/admin/dashboard.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/dashboard.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
