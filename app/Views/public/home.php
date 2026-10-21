<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Beranda publik — RINGKAS (2026-10-22, permintaan user: "kebanyakan teks", acuan beranda aplikasi Android): banner geser (judul +
 * satu tombol) · Jelajahi layanan (4 ubin) · baris jam pelajaran sekarang · Layanan unggulan (kisi ikon, tanpa paragraf) · Informasi
 * terbaru (3 baris). Tentang sekolah + angka + grafik sudah ada di halaman Profil; tanya-jawab ada di Pusat Bantuan.
 * Banner: foto sekolah yang dicentang "Tampilkan di banner" (menu Foto Sekolah) lebih dulu, lalu sambutan, formulir yang sedang
 * dibuka, dan pengumuman terbaru.
 *
 * @var array $setting @var list<array> $pengumuman @var list<array> $foto foto sekolah aktif (+ 'url')
 * @var array $now (hariId, hariNama, label) @var bool $jadwalOn
 */
use App\Libraries\LayananPublik;

$sekolah = (string) ($setting['school_name'] ?? 'Sekolah');
$tahun   = trim((string) ($setting['academic_year'] ?? ''));
$layanan = LayananPublik::semua($setting);
$logoB   = (string) ($setting['logo'] ?? '');
$logo    = $logoB !== '' && is_file(FCPATH . 'uploads/' . $logoB) ? base_url('uploads/' . rawurlencode($logoB)) : null;

// Sorotan banner: hanya label + judul + SATU tombol (tanpa paragraf). Foto sekolah dulu, lalu kotak sorotan.
$slaid = [];
foreach ($foto as $f) {
    if ((int) $f['tampil_banner'] === 1) {
        $slaid[] = ['foto' => $f];
    }
}
$nKotak = 0;
$gelap  = static function () use (&$nKotak): bool {
    return $nKotak++ % 2 === 0; // kotak sorotan bergantian gelap/terang, dimulai gelap
};
$slaid[] = [
    'gelap'  => $gelap(),
    'label'  => $tahun !== '' ? 'Tahun Pelajaran ' . $tahun : 'Selamat datang',
    'judul'  => 'Selamat datang di ' . $sekolah,
    'tombol' => ['Jelajahi layanan', '#layanan'],
    'logo'   => true,
    'ilu'    => 'sekolah',
];
$sorot = [
    'pkl'       => ['PKL / Prakerin', 'Pengajuan tempat PKL sedang dibuka', 'Ajukan sekarang'],
    'lapor_pkl' => ['PKL / Prakerin', 'Sudah diterima perusahaan? Laporkan di sini', 'Lapor diterima'],
    'biodata'   => ['Data siswa', 'Pengisian biodata siswa sedang dibuka', 'Isi biodata'],
    'kesediaan' => ['Untuk guru', 'Formulir kesediaan mengajar sedang dibuka', 'Isi formulir'],
];
foreach ($sorot as $k => [$label, $judul, $aksi]) {
    if (($layanan[$k]['status'] ?? null) === LayananPublik::BUKA) {
        $slaid[] = ['gelap' => $gelap(), 'label' => $label, 'judul' => $judul, 'tombol' => [$aksi, $layanan[$k]['url']], 'ilu' => $layanan[$k]['ilu']];
    }
}
if (! empty($pengumuman)) {
    $p0      = $pengumuman[0];
    $gambar0 = LayananPublik::gambar('pengumuman', $p0['gambar'] ?? null);
    $slaid[] = $gambar0
        ? ['foto' => ['url' => $gambar0, 'label' => 'Pengumuman terbaru', 'judul' => (string) $p0['judul'], 'tautan' => site_url('pengumuman/' . (int) $p0['id']), 'aksi' => 'Baca pengumuman']]
        : ['gelap' => $gelap(), 'label' => 'Pengumuman terbaru', 'judul' => (string) $p0['judul'], 'tombol' => ['Baca pengumuman', site_url('pengumuman/' . (int) $p0['id'])], 'ilu' => 'pengumuman'];
}
$n = count($slaid);
?>

<!-- ===== Banner geser ===== -->
<section class="wadah pt-4 sm:pt-6" aria-label="Sorotan">
    <h1 class="sr-only"><?= esc($sekolah) ?></h1>
    <div class="banner" x-data="bannerGeser" aria-roledescription="carousel">
        <div class="banner-jalur">
            <?php foreach ($slaid as $i => $s): ?>
                <div class="banner-slaid" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> dari <?= $n ?>">
                    <?php if (isset($s['foto'])): $f = $s['foto']; $tautan = trim((string) ($f['tautan'] ?? '')); ?>
                    <div class="banner-foto">
                        <img src="<?= esc($f['url'], 'attr') ?>" alt="<?= esc($f['judul'], 'attr') ?>" class="banner-gambar"<?= $i > 0 ? ' loading="lazy"' : '' ?>>
                        <div class="banner-teks">
                            <?php if (! empty($f['label'])): ?>
                                <p class="banner-label mb-1"><?= esc($f['label']) ?></p>
                            <?php endif; ?>
                            <p class="banner-judul"><?= esc($f['judul']) ?></p>
                            <?php if ($tautan !== ''): ?>
                                <a href="<?= esc($tautan, 'attr') ?>" class="banner-tautan"<?= str_starts_with($tautan, base_url()) ? '' : ' target="_blank" rel="noopener"' ?>><?= esc($f['aksi'] ?? 'Selengkapnya') ?><?= ikon('arrow-right', 'h-4 w-4') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: [$teksTombol, $urlTombol] = $s['tombol']; ?>
                    <div class="banner-kotak <?= $s['gelap'] ? 'banner-kotak-gelap' : 'banner-kotak-terang' ?>">
                        <div class="banner-isi">
                            <p class="banner-label"><?= esc($s['label']) ?></p>
                            <p class="banner-judul-besar"><?= esc($s['judul']) ?></p>
                            <div class="mt-6">
                                <a href="<?= esc($urlTombol, 'attr') ?>" class="tombol tombol-besar <?= $s['gelap'] ? 'tombol-putih' : 'tombol-utama' ?>"><?= esc($teksTombol) ?></a>
                            </div>
                        </div>
                        <?php if (! empty($s['logo']) && $logo): ?>
                            <span class="banner-sisi"><img src="<?= esc($logo, 'attr') ?>" alt="" class="h-full w-full object-contain"></span>
                        <?php elseif (! empty($s['ilu'])): ?>
                            <span class="banner-sisi"><img src="<?= LayananPublik::ilustrasi($s['ilu']) ?>" alt="" class="h-full w-full object-contain"></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($n > 1): ?>
            <button type="button" class="banner-geser banner-geser-kiri" @click="mundurTombol" aria-label="Sorotan sebelumnya"><?= ikon('angle-left') ?></button>
            <button type="button" class="banner-geser banner-geser-kanan" @click="majuTombol" aria-label="Sorotan berikutnya"><?= ikon('angle-right') ?></button>
            <div class="banner-bawah">
                <?php for ($i = 0; $i < $n; $i++): ?>
                    <button type="button" class="banner-titik" data-i="<?= $i ?>" @click="pilihTitik($event)" :aria-current="i === <?= $i ?>" aria-label="Tampilkan sorotan <?= $i + 1 ?>"></button>
                <?php endfor; ?>
                <button type="button" class="banner-jeda" @click="alihMain" :aria-pressed="tanpaMain" aria-label="Hentikan geser otomatis">
                    <span x-show="main"><?= ikon('pause') ?></span><span x-show="tanpaMain" x-cloak><?= ikon('play') ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Jelajahi layanan: 4 kelompok pengguna ===== -->
<section id="layanan" class="seksi scroll-mt-24">
    <div class="wadah">
        <div class="seksi-kepala"><h2 class="seksi-judul">Jelajahi layanan</h2></div>
        <ul class="ubin-grid">
            <?php foreach (LayananPublik::kelompok() as $g): ?>
                <li>
                    <a href="<?= esc($g['url'], 'attr') ?>" class="ubin" title="<?= esc($g['ringkas'], 'attr') ?>">
                        <img src="<?= LayananPublik::ilustrasi($g['ilu']) ?>" alt="" class="ubin-gambar" width="72" height="72">
                        <span class="ubin-label"><?= esc($g['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($jadwalOn): ?>
            <!-- Jam pelajaran sekarang (satu baris → jadwal kelas) -->
            <a href="<?= site_url('jadwal-kelas') ?>" class="baris-sekarang mt-5">
                <span class="baris-sekarang-ikon"><?= ikon('clock') ?></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm text-teks-2"><?= esc($now['hariNama'] ?? '') ?>, <?= esc(LayananPublik::tanggal(date('Y-m-d H:i:s'), false)) ?></span>
                    <span class="block font-semibold text-teks">
                        <?php if (! empty($now['label'])): ?><?= esc($now['label']) ?>
                        <?php elseif (! empty($now['hariId'])): ?>Di luar jam pelajaran
                        <?php else: ?>Tidak ada kegiatan belajar hari ini<?php endif; ?>
                    </span>
                </span>
                <span class="seksi-tautan">Jadwal kelas<?= ikon('arrow-right') ?></span>
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Layanan unggulan: kisi ikon ===== -->
<section class="seksi border-t border-garis">
    <div class="wadah">
        <div class="seksi-kepala">
            <h2 class="seksi-judul">Layanan unggulan</h2>
            <a href="<?= site_url('cari') ?>" class="seksi-tautan" data-buka-daftar>Lihat semua<?= ikon('arrow-right') ?></a>
        </div>
        <?= view('public/partials/kisi_layanan', ['daftar' => LayananPublik::unggulan($setting, 8), 'kelas' => 'kisi-layanan-lebar'], ['saveData' => false]) ?>
    </div>
</section>

<!-- ===== Informasi terbaru: 3 baris ===== -->
<section class="seksi border-t border-garis">
    <div class="wadah">
        <div class="seksi-kepala">
            <h2 class="seksi-judul">Informasi terbaru</h2>
            <?php if (! empty($pengumuman)): ?><a href="<?= site_url('pengumuman') ?>" class="seksi-tautan">Lihat semua<?= ikon('arrow-right') ?></a><?php endif; ?>
        </div>
        <?php if (empty($pengumuman)): ?>
            <p class="rounded-lg border border-garis bg-white px-4 py-6 text-center text-sm text-teks-2">Belum ada pengumuman dari sekolah.</p>
        <?php else: ?>
            <ul class="daftar-baris overflow-hidden rounded-lg border border-garis bg-white">
                <?php foreach (array_slice($pengumuman, 0, 3) as $p): ?>
                    <li class="relative flex items-center gap-3 px-4 py-3 transition-colors hover:bg-permukaan sm:px-5">
                        <span class="min-w-0 flex-1">
                            <a href="<?= site_url('pengumuman/' . (int) $p['id']) ?>" class="tautan-penuh block font-semibold text-teks"><?= esc((string) $p['judul']) ?></a>
                            <span class="mt-0.5 block text-sm text-teks-2"><?= esc(LayananPublik::tanggal((string) $p['created_at'], false)) ?></span>
                        </span>
                        <span class="text-teks-2" aria-hidden="true"><?= ikon('chevron-right', 'h-5 w-5') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?= $this->endSection() ?>
