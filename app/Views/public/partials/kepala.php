<?php
/**
 * Kepala portal publik — struktur sama dengan rumah.pendidikan.go.id: bayangan (bukan garis), wadah selebar layar; kiri logo
 * (+ di HP: tombol kisi "Daftar layanan"); tengah kotak cari (layar ≥ md); kanan ikon Bantuan · tombol kisi · Masuk.
 * Tombol kisi membuka LACI SAMPING berisi kisi ilustrasi HD (acuan: sheet "Daftar ruang"); di HP laci itu juga memuat tombol Masuk.
 * (2026-10-20: laci "Menu" HP + tombol garis tiga dihapus atas permintaan user — isinya sama dengan Daftar layanan.)
 * Komponen: kepalaPortal (publik/portal.js).
 *
 * @var array       $setting
 * @var string      $schoolName
 * @var string|null $logoUrl
 * @var string      $kini       uri_string() halaman ini
 */
use App\Libraries\LayananPublik;

$kelompok = LayananPublik::kelompok();
$layanan  = LayananPublik::semua($setting, false);
$tahun    = trim((string) ($setting['academic_year'] ?? ''));
$aktif    = static fn (string $url): string => trim($kini, '/') === trim(str_replace(site_url(), '', $url), '/') ? ' aria-current="page"' : '';

// Kisi "Daftar layanan": kelompok pengguna lalu layanan (nama fitur proyek ini).
$kisi = [];
foreach ($kelompok as $g) {
    $kisi[] = [$g['url'], $g['label'], $g['ilu'], false];
}
foreach (['jadwal_kelas', 'jadwal_guru', 'absensi', 'pengumuman', 'pkl', 'lapor_pkl', 'biodata', 'dokumen', 'kesediaan', 'aplikasi', 'bantuan'] as $k) {
    if (isset($layanan[$k])) {
        $kisi[] = [$layanan[$k]['url'], $layanan[$k]['judul'], $layanan[$k]['ilu'], ! empty($layanan[$k]['luar'])];
    }
}
?>
<header class="kepala-portal" x-data="kepalaPortal" @keydown.escape.window="tutupSemua">
    <nav class="kepala-nav" aria-label="Navigasi <?= esc($schoolName, 'attr') ?>">
        <div class="kepala-kiri">
            <a href="<?= site_url('/') ?>" class="merek" aria-label="<?= esc($schoolName, 'attr') ?> — Beranda">
                <?php if ($logoUrl): ?>
                    <img src="<?= esc($logoUrl, 'attr') ?>" alt="" class="merek-logo" width="44" height="44">
                <?php else: ?>
                    <span class="merek-huruf" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($schoolName, 0, 1))) ?></span>
                <?php endif; ?>
                <span class="merek-teks">
                    <span class="merek-nama"><?= esc($schoolName) ?></span>
                    <?php if ($tahun !== ''): ?><span class="merek-ket">Tahun Pelajaran <?= esc($tahun) ?></span><?php endif; ?>
                </span>
            </a>
            <div class="flex h-12 items-center gap-1 md:hidden">
                <button type="button" class="tombol-ikon" @click="bukaDaftar" :aria-expanded="daftar" aria-haspopup="dialog" aria-controls="daftar-layanan" aria-label="Daftar layanan"><?= ikon('grid', 'h-6 w-6') ?></button>
            </div>
        </div>

        <div class="kepala-tengah">
            <div class="w-full md:mx-auto md:max-w-xl lg:min-w-[36rem]">
                <?= view('public/partials/cari', ['idCari' => 'cari-atas'], ['saveData' => false]) ?>
            </div>
        </div>
        <div class="kepala-cari-hp">
            <?= view('public/partials/cari', ['idCari' => 'cari-hp'], ['saveData' => false]) ?>
        </div>

        <div class="kepala-kanan">
            <div class="flex gap-1">
                <a href="<?= site_url('bantuan') ?>" class="tombol-ikon" aria-label="Pusat Bantuan" title="Pusat Bantuan"<?= $aktif(site_url('bantuan')) ?>><?= ikon('question-circle', 'h-6 w-6') ?></a>
                <button type="button" class="tombol-ikon" @click="bukaDaftar" :aria-expanded="daftar" aria-haspopup="dialog" aria-controls="daftar-layanan" aria-label="Daftar layanan" title="Daftar layanan"><?= ikon('grid', 'h-6 w-6') ?></button>
            </div>
            <a href="<?= site_url('admin/login') ?>" class="tombol tombol-utama px-6">Masuk</a>
        </div>
    </nav>

    <!-- Laci "Daftar layanan" (acuan: sheet daftar ruang berisi kisi ikon HD) -->
    <div id="daftar-layanan" class="laci-portal" :data-buka="daftar">
        <div class="laci-latar" @click="tutupSemua" aria-hidden="true"></div>
        <div class="laci-panel laci-lebar" role="dialog" aria-modal="true" aria-labelledby="daftar-judul" tabindex="-1" @keydown.tab="kunciTab($event)">
            <div class="laci-kepala">
                <p id="daftar-judul" class="laci-judul">Daftar layanan <?= esc($schoolName) ?></p>
                <button type="button" class="tombol-ikon" @click="tutupSemua" aria-label="Tutup daftar layanan"><?= ikon('close', 'h-6 w-6') ?></button>
            </div>
            <nav class="laci-isi" aria-label="Daftar layanan">
                <ul class="laci-kisi">
                    <?php foreach ($kisi as [$url, $label, $ilu, $luar]): ?>
                        <li>
                            <a href="<?= esc($url, 'attr') ?>"<?= $luar ? ' target="_blank" rel="noopener"' : $aktif($url) ?>>
                                <img src="<?= LayananPublik::ilustrasi($ilu) ?>" alt="" width="56" height="56" decoding="async"><?php /* tanpa lazy: diunduh sejak awal agar tidak muncul mendadak saat laci bergeser */ ?>
                                <span><?= esc($label) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <!-- Di HP tombol Masuk di kepala tersembunyi (kepala-kanan) → disediakan di sini. -->
            <div class="laci-kaki md:hidden">
                <a href="<?= site_url('admin/login') ?>" class="tombol tombol-utama tombol-blok tombol-besar">Masuk</a>
            </div>
        </div>
    </div>
</header>
