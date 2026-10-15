<?php

/**
 * Kerangka bersama halaman galat (F10) — di-include oleh error_404.php, error_400.php dan production.php. Bukan view()/layout:
 * halaman galat dirender penangan pengecualian CodeIgniter, bisa saat basis data atau sesi bermasalah, jadi tidak memakai layout,
 * basis data, maupun tema (helper dipakai hanya bila sudah termuat). Dipakai halaman publik & panel admin; di bawah /admin tombolnya
 * "Ke Dasbor". Gaya = assets/css/galat.css (mandiri, tanpa build). Siap CSP: tanpa skrip, tanpa gaya sebaris.
 *
 * @var string      $galatKode  angka kode, mis. '404' ('' = tanpa angka)
 * @var string      $galatJudul
 * @var string      $galatTeks
 * @var string      $galatIkon  nama ikon Flowbite (resources/ikon.json)
 * @var string|null $galatPesan pesan teknis — hanya diisi di luar production
 */
$galatPesan = $galatPesan ?? null;
$alamat     = static fn (string $jalur): string => function_exists('site_url') ? site_url($jalur) : '/' . ltrim($jalur, '/');
$berkasCss  = 'assets/css/galat.css';
$alamatCss  = (function_exists('base_url') ? base_url($berkasCss) : '/' . $berkasCss) . '?v=' . @filemtime(FCPATH . $berkasCss);
$gambar     = static fn (string $nama, string $kelas = ''): string => function_exists('ikon') ? ikon($nama, $kelas) : '';
$diAdmin    = preg_match('#/admin(?:[/?]|$)#', (string) ($_SERVER['REQUEST_URI'] ?? '')) === 1;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc($galatJudul) ?></title>
    <link rel="stylesheet" href="<?= esc($alamatCss, 'attr') ?>">
</head>
<body>
    <main class="galat">
        <span class="galat-ikon" aria-hidden="true"><?= $gambar($galatIkon) ?></span>
        <?php if ($galatKode !== ''): ?>
            <p class="galat-kode"><?= esc($galatKode) ?></p>
        <?php endif; ?>
        <h1 class="galat-judul"><?= esc($galatJudul) ?></h1>
        <p class="galat-teks"><?= esc($galatTeks) ?></p>

        <?php if ($galatPesan !== null && trim($galatPesan) !== ''): ?>
            <pre class="galat-pesan"><?= esc($galatPesan) ?></pre>
        <?php endif; ?>

        <div class="galat-aksi">
            <?php if ($diAdmin): ?>
                <a href="<?= esc($alamat('admin'), 'attr') ?>" class="tombol tombol-utama"><?= $gambar('home') ?>Ke Dasbor</a>
                <a href="<?= esc($alamat('/'), 'attr') ?>" class="tombol tombol-netral"><?= $gambar('school') ?>Beranda situs</a>
            <?php else: ?>
                <a href="<?= esc($alamat('/'), 'attr') ?>" class="tombol tombol-utama"><?= $gambar('home') ?>Kembali ke Beranda</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
