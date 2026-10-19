<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Beranda publik — susunan mengikuti beranda rumah.pendidikan.go.id (warna lilac sekolah): banner geser · "Jelajahi layanan" (ubin) ·
 * pita tentang sekolah + angka · layanan unggulan · cek jadwal · "Informasi untuk Anda" · tanya-jawab. Isi beranda lama tetap ada
 * (status jam pelajaran, pilih kelas, layanan, pengumuman, data sekolah). Data siswa hanya ANGKA AGREGAT.
 * Banner: foto sekolah yang dicentang "Tampilkan di banner" (menu Foto Sekolah) lebih dulu, lalu sorotan dari data (sambutan,
 * formulir yang sedang dibuka, pengumuman terbaru). Tentang sekolah: foto utama (FotoSekolahModel::utama) bila ada.
 *
 * @var array $setting @var array $stats @var array $siswaStat @var array $kelasOpts @var list<array> $pengumuman
 * @var list<array> $foto foto sekolah aktif (+ 'url') @var array $now (hariId, hariNama, label) @var bool $jadwalOn
 */
use App\Libraries\LayananPublik;
use App\Models\FotoSekolahModel;

$sekolah  = (string) ($setting['school_name'] ?? 'Sekolah');
$tahun    = trim((string) ($setting['academic_year'] ?? ''));
$angka    = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$layanan  = LayananPublik::semua($setting);
$logoB    = (string) ($setting['logo'] ?? '');
$logo     = $logoB !== '' && is_file(FCPATH . 'uploads/' . $logoB) ? base_url('uploads/' . rawurlencode($logoB)) : null;
$ringkas  = static fn (string $t, int $n = 150): string => mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $t)), 0, $n, '…');

// Tentang sekolah: profil singkat dari Pengaturan; bila kosong, kalimat faktual dari data sekolah (sama dengan API aplikasi).
$tentang = LayananPublik::tentang($setting, $stats);

// Sorotan banner (acuan: banner geser berisi program & berita terbaru): foto sekolah dulu, lalu kotak sorotan.
$slaid = [];
foreach ($foto as $f) {
    if ((int) $f['tampil_banner'] === 1) {
        $slaid[] = ['foto' => $f];
    }
}
$fotoTentang = FotoSekolahModel::utama($foto);
// Kotak sorotan bergantian gelap/terang, dimulai gelap.
$nKotak = 0;
$gelap  = static function () use (&$nKotak): bool {
    return $nKotak++ % 2 === 0;
};
$slaid[] = [
    'gelap'  => $gelap(),
    'label'  => $tahun !== '' ? 'Tahun Pelajaran ' . $tahun : 'Selamat datang',
    'judul'  => 'Selamat datang di ' . $sekolah,
    'teks'   => 'Jadwal pelajaran, pengumuman, pengajuan PKL, dan profil sekolah dalam satu tempat.',
    'tombol' => [['Jelajahi layanan', '#layanan', true], ['Profil sekolah', site_url('profil'), false]],
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
        $slaid[] = ['gelap' => $gelap(), 'label' => $label, 'judul' => $judul, 'teks' => $layanan[$k]['ket'],
            'tombol' => [[$aksi, $layanan[$k]['url'], true]], 'ilu' => $layanan[$k]['ilu']];
    }
}
$gambarP0 = ! empty($pengumuman) ? LayananPublik::gambar('pengumuman', $pengumuman[0]['gambar'] ?? null) : null;
if ($gambarP0) {
    // Pengumuman terbaru bergambar → slide foto (acuan: banner berita bergambar).
    $p0      = $pengumuman[0];
    $slaid[] = ['foto' => ['url' => $gambarP0, 'label' => 'Pengumuman terbaru', 'judul' => (string) $p0['judul'], 'keterangan' => $ringkas((string) $p0['isi'], 140),
        'tautan' => site_url('pengumuman/' . (int) $p0['id']), 'aksi' => 'Baca pengumuman']];
} elseif (! empty($pengumuman)) {
    $p0      = $pengumuman[0];
    $slaid[] = ['gelap' => $gelap(), 'label' => 'Pengumuman terbaru', 'judul' => (string) $p0['judul'], 'teks' => $ringkas((string) $p0['isi'], 140),
        'tombol' => [['Baca pengumuman', site_url('pengumuman/' . (int) $p0['id']), true]], 'ilu' => 'pengumuman'];
}
$n = count($slaid);

// Fakta singkat di samping "Tentang" (yang kosong tidak tampil).
$fakta = array_filter([
    'Akreditasi'      => trim((string) ($setting['school_accreditation'] ?? '')),
    'Kepala sekolah'  => trim((string) ($setting['headmaster_name'] ?? '')),
    'Jenjang'         => trim((string) ($setting['school_level'] ?? '')),
    'Tahun pelajaran' => $tahun,
], static fn (string $v): bool => $v !== '');
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
                            <?php if (! empty($f['keterangan'])): ?>
                                <p class="banner-ket"><?= esc($f['keterangan']) ?></p>
                            <?php endif; ?>
                            <?php if ($tautan !== ''): ?>
                                <a href="<?= esc($tautan, 'attr') ?>" class="banner-tautan"<?= str_starts_with($tautan, base_url()) ? '' : ' target="_blank" rel="noopener"' ?>><?= esc($f['aksi'] ?? 'Selengkapnya') ?><?= ikon('arrow-right', 'h-4 w-4') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="banner-kotak <?= $s['gelap'] ? 'banner-kotak-gelap' : 'banner-kotak-terang' ?>">
                        <div class="banner-isi">
                            <p class="banner-label"><?= esc($s['label']) ?></p>
                            <p class="banner-judul-besar"><?= esc($s['judul']) ?></p>
                            <p class="banner-teks-isi"><?= esc($s['teks']) ?></p>
                            <div class="mt-6 flex flex-wrap gap-3">
                                <?php foreach ($s['tombol'] as [$teks, $url, $utama]): ?>
                                    <a href="<?= esc($url, 'attr') ?>" class="tombol tombol-besar <?= $s['gelap'] ? ($utama ? 'tombol-putih' : 'tombol-garis-putih') : ($utama ? 'tombol-utama' : 'tombol-garis') ?>"><?= esc($teks) ?></a>
                                <?php endforeach; ?>
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

<!-- ===== Jelajahi layanan (acuan: "Jelajahi Ruang") ===== -->
<section id="layanan" class="bagian">
    <div class="wadah">
        <div class="bagian-kepala">
            <h2 class="judul-bagian">Jelajahi layanan di <?= esc($sekolah) ?></h2>
        </div>
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
    </div>
</section>

<!-- ===== Tentang sekolah + sekolah dalam angka (acuan: "Semangat Rumah Pendidikan") ===== -->
<section class="bagian bg-brand-50">
    <div class="wadah">
        <div class="grid gap-8 lg:grid-cols-[1fr_1.1fr] lg:items-center lg:gap-12">
            <div>
                <p class="pembuka-label mt-0"><img src="<?= LayananPublik::ilustrasi('sekolah') ?>" alt="" class="pembuka-gambar" width="56" height="56">Tentang sekolah</p>
                <h2 class="mt-3 text-display-sm text-brand-800 sm:text-display-md"><?= esc($sekolah) ?></h2>
                <p class="mt-4 text-base leading-relaxed text-teks"><?= esc($tentang) ?></p>
                <?php if ($fakta !== []): ?>
                    <dl class="fakta-grid mt-6">
                        <?php foreach ($fakta as $label => $nilai): ?>
                            <div class="fakta"><dt><?= $label ?></dt><dd><?= esc($nilai) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <a href="<?= site_url('profil') ?>" class="tombol tombol-utama tombol-besar mt-6">Pelajari selengkapnya<?= ikon('arrow-right') ?></a>
            </div>
            <?php if ($fotoTentang): ?>
                <img src="<?= esc($fotoTentang['url'], 'attr') ?>" alt="<?= esc($fotoTentang['judul'], 'attr') ?>" class="tentang-foto" loading="lazy">
            <?php else: ?>
                <?= view('public/partials/grafik_sekolah', ['stats' => $stats, 'siswaStat' => $siswaStat, 'bagian' => 'angka'], ['saveData' => false]) ?>
            <?php endif; ?>
        </div>
        <?php if ($fotoTentang): ?>
            <div class="mt-8">
                <?= view('public/partials/grafik_sekolah', ['stats' => $stats, 'siswaStat' => $siswaStat, 'bagian' => 'angka', 'kolom' => 4], ['saveData' => false]) ?>
            </div>
        <?php endif; ?>
        <?= view('public/partials/grafik_sekolah', ['stats' => $stats, 'siswaStat' => $siswaStat, 'bagian' => 'grafik'], ['saveData' => false]) ?>
    </div>
</section>

<!-- ===== Layanan unggulan ===== -->
<section class="bagian">
    <div class="wadah">
        <div class="bagian-kepala">
            <h2 class="judul-bagian">Layanan unggulan di <?= esc($sekolah) ?></h2>
        </div>
        <div class="grid-layanan">
            <?php foreach (LayananPublik::unggulan($setting) as $l): ?>
                <?= view('public/partials/kartu_layanan', ['l' => $l], ['saveData' => false]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($jadwalOn): ?>
<!-- ===== Cek jadwal (fitur beranda lama) ===== -->
<section class="bagian bagian-abu">
    <div class="wadah">
        <div class="bagian-kepala">
            <h2 class="judul-bagian">Cek jadwal pelajaran</h2>
            <p class="ket-bagian">Pilih kelas untuk melihat jadwal mingguan. Jadwal bisa diunduh sebagai PDF.</p>
        </div>
        <div class="mx-auto grid max-w-4xl gap-4 md:grid-cols-[16rem_1fr]">
            <div class="kartu kartu-isi">
                <p class="text-sm text-teks-2"><?= esc($now['hariNama'] ?? '') ?>, <?= esc(LayananPublik::tanggal(date('Y-m-d H:i:s'), false)) ?></p>
                <p class="mt-1 text-heading-sm text-teks">
                    <?php if (! empty($now['label'])): ?><?= esc($now['label']) ?>
                    <?php elseif (! empty($now['hariId'])): ?>Di luar jam pelajaran
                    <?php else: ?>Tidak ada kegiatan belajar hari ini<?php endif; ?>
                </p>
                <p class="mt-1 text-sm text-teks-2">Saat ini</p>
            </div>
            <form action="<?= site_url('jadwal-kelas') ?>" method="get" class="kartu kartu-isi flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="pilihKelasHome" class="lbl">Kelas</label>
                    <select id="pilihKelasHome" name="kelas_id" class="inp" required>
                        <option value="">Pilih kelas</option>
                        <?php foreach ($kelasOpts as $id => $label): ?>
                            <option value="<?= (int) $id ?>"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="tombol tombol-utama h-11"><?= ikon('calendar-week') ?>Tampilkan jadwal</button>
            </form>
        </div>
        <p class="mt-4 text-center text-sm text-teks-2">Mencari jadwal mengajar? <a href="<?= site_url('jadwal-guru') ?>" class="font-semibold text-brand-700 hover:underline">Lihat jadwal per guru</a></p>
    </div>
</section>
<?php endif; ?>

<!-- ===== Informasi untuk Anda ===== -->
<section class="bagian">
    <div class="wadah">
        <div class="bagian-kepala">
            <h2 class="judul-bagian">Informasi untuk Anda</h2>
        </div>
        <?php if (empty($pengumuman)): ?>
            <div class="kartu"><div class="kosong"><span class="kosong-ikon"><?= ikon('bullhorn') ?></span><p class="kosong-judul">Belum ada pengumuman</p><p class="kosong-teks">Pengumuman dan berita dari sekolah akan tampil di sini.</p></div></div>
        <?php else: ?>
            <div class="grid-info">
                <?php foreach (array_slice($pengumuman, 0, 3) as $p): ?>
                    <?= view('public/partials/kartu_info', ['p' => $p, 'logo' => $logo], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </div>
            <div class="bagian-kaki"><a href="<?= site_url('pengumuman') ?>" class="tombol tombol-garis tombol-besar">Lihat lebih banyak</a></div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Tanya-jawab ===== -->
<section class="bagian bagian-abu">
    <div class="wadah max-w-3xl">
        <div class="bagian-kepala">
            <h2 class="judul-bagian">Pertanyaan yang paling sering ditanyakan</h2>
        </div>
        <?= view('public/partials/tanya_jawab', ['tanya' => array_slice(LayananPublik::tanyaJawab($setting), 0, 5)], ['saveData' => false]) ?>
        <div class="bagian-kaki"><a href="<?= site_url('bantuan') ?>" class="tombol tombol-utama tombol-besar"><?= ikon('question-circle') ?>Ke Pusat Bantuan</a></div>
    </div>
</section>

<?= $this->endSection() ?>
