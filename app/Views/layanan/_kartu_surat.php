<?php
/**
 * Satu kartu layanan surat (gaya .kartu-layanan kit publik). Dipakai beranda Layanan Siswa (belum masuk) dan halaman siswa.
 *
 * @var string      $kunci  kunci LayananSiswa::SURAT
 * @var string      $url    tujuan bila kartu bisa dipakai ('' = tidak bisa diklik)
 * @var string      $label  label pojok kanan ('' = tanpa label)
 * @var string      $kelasLabel kelas label kit (label-buka | label-tutup | …)
 * @var string      $catatan kalimat tambahan di bawah keterangan (mis. alasan belum bisa)
 * @var string      $kaki   ajakan di kaki kartu
 */

use App\Libraries\LayananSiswa;

[$judul, $ket, $ikon] = LayananSiswa::SURAT[$kunci];
?>
<article id="<?= esc($kunci, 'attr') ?>" class="kartu-layanan scroll-mt-6"<?= $url === '' ? ' data-tutup' : '' ?>>
    <div class="kartu-layanan-kepala">
        <span class="kartu-layanan-ikon"><?= ikon($ikon) ?></span>
        <?php if ($label !== ''): ?><span class="label <?= esc($kelasLabel, 'attr') ?>"><?= esc($label) ?></span><?php endif; ?>
    </div>
    <h3 class="kartu-layanan-judul"><?php if ($url !== ''): ?><a href="<?= esc($url, 'attr') ?>" class="tautan-penuh"><?= esc($judul) ?></a><?php else: ?><?= esc($judul) ?><?php endif; ?></h3>
    <p class="kartu-layanan-ket"><?= esc($ket) ?></p>
    <?php if ($catatan !== ''): ?><p class="mt-2 text-sm font-medium text-amber-800"><?= esc($catatan) ?></p><?php endif; ?>
    <p class="kartu-layanan-kaki" aria-hidden="true"><?= esc($kaki) ?><?= $url !== '' ? ikon('arrow-right') : '' ?></p>
</article>
