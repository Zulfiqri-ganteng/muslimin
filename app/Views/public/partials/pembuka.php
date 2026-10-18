<?php
/**
 * Pembuka halaman dalam (acuan halaman ruang rumah.pendidikan.go.id): remah roti, label ber-ikon, H1, satu kalimat keterangan.
 * Panggil: view('public/partials/pembuka', ['remah' => [['Siswa', url], ['Jadwal kelas', null]], 'ikon' => 'calendar-week',
 *          'label' => 'Layanan siswa', 'judul' => 'Jadwal kelas', 'ket' => '…', 'aksiHtml' => '…'], ['saveData' => false]).
 *
 * @var list<array{0: string, 1: string|null}> $remah    tanpa "Beranda" (selalu ditambahkan di depan); butir terakhir = halaman ini
 * @var string|null                          $ikon
 * @var string|null                          $label
 * @var string                               $judul
 * @var string|null                          $ket
 * @var string|null                          $aksiHtml tombol di kanan judul (HTML tepercaya dari view)
 * @var string|null                          $bawahHtml isi tambahan di bawah keterangan (mis. kotak cari besar)
 */
$remah = $remah ?? [];
?>
<section class="pembuka">
    <div class="wadah">
        <nav aria-label="Remah roti">
            <ol class="remah">
                <li><a href="<?= site_url('/') ?>">Beranda</a></li>
                <?php foreach ($remah as $i => [$teks, $url]): ?>
                    <li class="flex items-center gap-1.5">
                        <?= ikon('chevron-right') ?>
                        <?php if ($url !== null && $i < count($remah) - 1): ?>
                            <a href="<?= esc($url, 'attr') ?>"><?= esc($teks) ?></a>
                        <?php else: ?>
                            <span aria-current="page"><?= esc($teks) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <?php if (! empty($label)): ?>
                    <p class="pembuka-label">
                        <?php if (! empty($ilu)): ?><img src="<?= \App\Libraries\LayananPublik::ilustrasi($ilu) ?>" alt="" class="pembuka-gambar" width="56" height="56">
                        <?php elseif (! empty($ikon)): ?><span class="pembuka-ikon"><?= ikon($ikon, 'h-6 w-6') ?></span><?php endif; ?>
                        <?= esc($label) ?>
                    </p>
                <?php endif; ?>
                <h1 class="judul-halaman"><?= esc($judul) ?></h1>
                <?php if (! empty($ket)): ?><p class="ket-halaman"><?= esc($ket) ?></p><?php endif; ?>
            </div>
            <?php if (! empty($aksiHtml)): ?><div class="flex shrink-0 flex-wrap gap-2"><?= $aksiHtml ?></div><?php endif; ?>
        </div>
        <?php if (! empty($bawahHtml)): ?><div class="mt-6"><?= $bawahHtml ?></div><?php endif; ?>
    </div>
</section>
