<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Hasil pencarian portal (Publik::cari): layanan & halaman, kelas, guru — dikelompokkan. Tanpa data siswa.
 *
 * @var string $q @var array{layanan: list<array>, kelas: list<array>, guru: list<array>} $hasil @var int $jumlah
 * @var list<array> $saran layanan unggulan (saat kosong)
 */
$pendek = $q !== '' && mb_strlen($q) < 2;
$ket    = match (true) {
    $q === ''  => 'Ketik nama layanan, nama kelas, atau nama guru.',
    $pendek    => 'Ketik paling sedikit 2 huruf.',
    $jumlah > 0 => $jumlah . ' hasil ditemukan.',
    default    => 'Tidak ada hasil yang cocok.',
};
?>
<?= view('public/partials/pembuka', [
    'remah'     => [['Pencarian', null]],
    'ikon'      => 'search',
    'label'     => 'Pencarian',
    'judul'     => $q !== '' && ! $pendek ? 'Hasil pencarian "' . $q . '"' : 'Cari layanan sekolah',
    'ket'       => $ket,
    'bawahHtml' => '<div class="max-w-2xl">' . view('public/partials/cari', ['idCari' => 'cari-halaman', 'nilai' => $q, 'besar' => true], ['saveData' => false]) . '</div>',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah space-y-10">
        <?php if ($hasil['layanan'] !== []): ?>
            <div>
                <h2 class="judul-kelompok">Layanan &amp; halaman</h2>
                <div class="grid-layanan">
                    <?php foreach ($hasil['layanan'] as $l): ?>
                        <?= view('public/partials/kartu_layanan', ['l' => $l + ['luar' => false, 'status' => null]], ['saveData' => false]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasil['kelas'] !== []): ?>
            <div>
                <h2 class="judul-kelompok">Jadwal kelas</h2>
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($hasil['kelas'] as $k): ?>
                        <li class="kartu relative flex items-center gap-3 px-4 py-3 transition-colors hover:border-brand-300">
                            <span class="kartu-layanan-ikon h-10 w-10"><?= ikon('calendar-week', 'h-5 w-5') ?></span>
                            <a href="<?= site_url('jadwal-kelas') . '?kelas_id=' . $k['id'] ?>" class="tautan-penuh font-semibold text-teks">Kelas <?= esc($k['nama']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($hasil['guru'] !== []): ?>
            <div>
                <h2 class="judul-kelompok">Jadwal mengajar guru</h2>
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($hasil['guru'] as $g): ?>
                        <li class="kartu relative flex items-center gap-3 px-4 py-3 transition-colors hover:border-brand-300">
                            <span class="kartu-layanan-ikon h-10 w-10"><?= ikon('calendar-month', 'h-5 w-5') ?></span>
                            <a href="<?= site_url('jadwal-guru') . '?guru_id=' . (int) $g['id'] ?>" class="tautan-penuh font-semibold text-teks"><?= esc($g['nama']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($q !== '' && ! $pendek && $jumlah === 0): ?>
            <div class="kartu">
                <div class="kosong">
                    <span class="kosong-ikon"><?= ikon('search') ?></span>
                    <p class="kosong-judul">Tidak ada hasil untuk "<?= esc($q) ?>"</p>
                    <p class="kosong-teks">Periksa ejaan atau gunakan kata lain, misalnya "jadwal", "PKL", atau nama kelas seperti "X TKJ".</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($jumlah === 0 && $saran !== []): ?>
            <div>
                <h2 class="judul-kelompok">Layanan yang sering dibuka</h2>
                <div class="grid-layanan">
                    <?php foreach ($saran as $l): ?>
                        <?= view('public/partials/kartu_layanan', ['l' => $l], ['saveData' => false]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
