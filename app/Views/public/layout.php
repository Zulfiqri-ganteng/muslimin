<?php
/**
 * Bingkai portal publik (beranda, Siswa/Guru/Orang Tua, profil, pengumuman, jadwal, absensi, dokumen, berbagi, cari, bantuan).
 * Konsep rumah.pendidikan.go.id: kepala putih lengket (logo · kotak cari · Bantuan · Daftar layanan · Masuk), kaki gelap berisi
 * identitas sekolah. Siap CSP: gaya & skrip di berkas (publik.css, publik/portal.js, Alpine versi CSP lokal), tanpa sebaris.
 * Rancangan: docs/RENCANA-TAMPILAN-PUBLIK.md.
 *
 * Data dari view: $setting (wajib), $title, $deskripsi (opsional, meta description), section 'content' & 'scripts'.
 */
use App\Libraries\LayananPublik;

$setting    = is_array($setting ?? null) ? $setting : [];
$schoolName = (string) ($setting['school_name'] ?? 'Sekolah');
$logoBerkas = (string) ($setting['logo'] ?? '');
$logoUrl    = $logoBerkas !== '' && is_file(FCPATH . 'uploads/' . $logoBerkas) ? base_url('uploads/' . rawurlencode($logoBerkas)) : null;
$deskripsi  = (string) ($deskripsi ?? '');
if ($deskripsi === '') {
    $deskripsi = trim((string) ($setting['profil_singkat'] ?? '')) !== ''
        ? mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $setting['profil_singkat'])), 0, 160, '…')
        : 'Layanan informasi ' . $schoolName . ': jadwal pelajaran, pengumuman, pengajuan PKL, dan profil sekolah.';
}

// Aksesibilitas pilihan pengunjung (cookie dari panel Aksesibilitas) → ditulis di <html> oleh server, tanpa kedipan.
$req    = service('request');
$kue    = static fn (string $n): string => method_exists($req, 'getCookie') ? (string) ($req->getCookie($n) ?? '') : '';
$aksHtml = '';
if (in_array($kue('aks_huruf'), ['besar', 'sangat'], true)) {
    $aksHtml .= ' data-aks-huruf="' . $kue('aks_huruf') . '"';
}
$aksHtml .= $kue('aks_kontras') === '1' ? ' data-aks-kontras' : '';
$aksHtml .= $kue('aks_garis') === '1' ? ' data-aks-garis' : '';

$bingkai = ['setting' => $setting, 'schoolName' => $schoolName, 'logoUrl' => $logoUrl, 'kini' => uri_string()];
$js      = static fn (string $berkas): string => '<script defer src="' . base_url($berkas) . '?v=' . @filemtime(FCPATH . $berkas) . '" ' . csp_script_nonce() . '></script>';
?>
<!DOCTYPE html>
<html lang="id"<?= $aksHtml ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">
    <title><?= esc($title ?? 'Beranda') ?> &mdash; <?= esc($schoolName) ?></title>
    <meta name="description" content="<?= esc($deskripsi, 'attr') ?>">
    <meta property="og:title" content="<?= esc(($title ?? 'Beranda') . ' — ' . $schoolName, 'attr') ?>">
    <meta property="og:description" content="<?= esc($deskripsi, 'attr') ?>">
    <?php if ($logoUrl): ?><meta property="og:image" content="<?= esc($logoUrl, 'attr') ?>"><?php endif; ?>
    <?= view('partials/favicon') ?>
    <link rel="preload" href="<?= base_url('assets/fonts/inter/inter-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/publik.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/publik.css') ?>" <?= csp_style_nonce() ?>>
</head>
<body class="flex min-h-screen flex-col">
<a href="#konten" class="tautan-lompat">Langsung ke konten utama</a>

<?= view('public/partials/kepala', $bingkai, ['saveData' => false]) ?>

<main id="konten" class="flex-1" tabindex="-1">
    <?= $this->renderSection('content') ?>
</main>

<?= view('public/partials/kaki', $bingkai, ['saveData' => false]) ?>
<?= view('public/partials/aksesibilitas', [], ['saveData' => false]) ?>

<!-- Data saran kotak cari (bukan skrip yang dijalankan → aman CSP) -->
<script type="application/json" id="data-saran-portal"><?= json_encode(LayananPublik::saran($setting), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?= $js('assets/js/publik/portal.js') ?>
<?= $this->renderSection('scripts') ?>
<?= $js('assets/js/vendor/alpine-csp.min.js') ?>
</body>
</html>
