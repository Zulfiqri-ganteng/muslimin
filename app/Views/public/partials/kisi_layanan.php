<?php
/**
 * Kisi ikon layanan (2026-10-22, acuan aplikasi Android "KisiLayanan"): ilustrasi dalam kotak abu muda + nama layanan, tanpa
 * paragraf. Keterangan satu kalimat tetap ada sebagai tooltip (title). Formulir yang sedang ditutup diberi teks kecil "Sedang ditutup".
 * Dipakai beranda (Layanan unggulan) dan halaman Siswa / Guru / Orang Tua.
 *
 * @var list<array> $daftar butir LayananPublik::semua()
 * @var string      $kelas  kelas tambahan pada <ul> (mis. 'kisi-layanan-lebar')
 */

use App\Libraries\LayananPublik;

$kelas ??= '';
?>
<ul class="kisi-layanan <?= esc($kelas, 'attr') ?>">
    <?php foreach ($daftar as $l): $tutup = ($l['status'] ?? null) === LayananPublik::TUTUP; ?>
        <li>
            <a href="<?= esc($l['url'], 'attr') ?>" class="kisi-item" title="<?= esc($l['ket'], 'attr') ?>"<?= ! empty($l['luar']) ? ' target="_blank" rel="noopener"' : '' ?>>
                <span class="kisi-ikon">
                    <?php if (! empty($l['ilu'])): ?>
                        <img src="<?= LayananPublik::ilustrasi($l['ilu']) ?>" alt="" width="44" height="44" loading="lazy">
                    <?php else: ?>
                        <?= ikon($l['ikon'], 'h-7 w-7 text-brand-700') ?>
                    <?php endif; ?>
                </span>
                <span><?= esc($l['judul']) ?></span>
                <?php if ($tutup): ?><span class="kisi-status">Sedang ditutup</span><?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
