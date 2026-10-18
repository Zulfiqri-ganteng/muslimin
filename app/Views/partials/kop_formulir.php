<?php
/**
 * Kop formulir publik gaya portal (bingkai layouts/formulir.php, publik.css): garis aksen lilac, logo, nama sekolah, judul formulir,
 * keterangan singkat, tahun pelajaran. Logo hanya bila berkasnya ADA (dicek server — tanpa onerror sebaris).
 * Pengganti partials/kop_form.php (Tailwind v3) yang masih dipakai Lapor PKL, Biodata, dan Kesediaan guru sampai halamannya dirombak.
 *
 * @var array       $setting
 * @var string      $judul
 * @var string|null $keterangan (opsional)
 * @var string|null $tahunLabel (opsional) bawaan "Tahun Pelajaran"
 */
$logoBerkas = (string) ($setting['logo'] ?? '');
$logoKop    = $logoBerkas !== '' && is_file(FCPATH . 'uploads/' . $logoBerkas) ? base_url('uploads/' . rawurlencode($logoBerkas)) : null;
?>
<header class="kop-formulir">
    <div class="kop-formulir-aksen" aria-hidden="true"></div>
    <div class="kop-formulir-isi">
        <?php if ($logoKop): ?>
            <img src="<?= esc($logoKop, 'attr') ?>" alt="Logo sekolah" class="kop-formulir-logo">
        <?php endif; ?>
        <div class="min-w-0">
            <p class="kop-formulir-sekolah"><?= esc($setting['school_name'] ?? '') ?></p>
            <h1 class="kop-formulir-judul"><?= esc($judul) ?></h1>
            <?php if (! empty($keterangan)): ?><p class="kop-formulir-ket"><?= esc($keterangan) ?></p><?php endif; ?>
        </div>
    </div>
    <?php if (! empty($setting['academic_year'])): ?>
        <div class="kop-formulir-tahun"><?= esc($tahunLabel ?? 'Tahun Pelajaran') ?> <?= esc($setting['academic_year']) ?></div>
    <?php endif; ?>
</header>
