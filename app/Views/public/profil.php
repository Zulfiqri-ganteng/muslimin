<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Profil sekolah (acuan halaman ruang: pembuka + banner lebar): identitas dari Pengaturan Sekolah + galeri foto sekolah (menu Foto
 * Sekolah) + sekolah dalam angka + kontak. Foto utama (FotoSekolahModel::utama) tampil lebar di bawah judul.
 *
 * @var array $setting @var array $stats @var array $siswaStat @var list<array> $foto foto sekolah aktif (+ 'url')
 */
$sekolah = (string) ($setting['school_name'] ?? 'Sekolah');
$utama   = \App\Models\FotoSekolahModel::utama($foto);
$angka   = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$isi     = static fn (string $k): string => trim((string) ($setting[$k] ?? ''));
$identitas = array_filter([
    'Nama sekolah'    => $sekolah,
    'Jenjang'         => $isi('school_level'),
    'Akreditasi'      => $isi('school_accreditation'),
    'Kepala sekolah'  => $isi('headmaster_name'),
    'Alamat'          => $isi('address'),
    'Kota/Kabupaten'  => $isi('city'),
    'Kode pos'        => $isi('school_postal_code'),
    'Telepon'         => $isi('phone'),
    'Email'           => $isi('email'),
    'Situs web'       => $isi('website'),
    'Tahun pelajaran' => $isi('academic_year'),
], static fn (string $v): bool => $v !== '');
?>
<?= view('public/partials/pembuka', [
    'remah' => [['Profil Sekolah', null]],
    'ikon'  => 'school',
    'ilu'   => 'sekolah',
    'label' => 'Profil sekolah',
    'judul' => $sekolah,
    'ket'   => trim((string) ($setting['profil_singkat'] ?? '')) !== '' ? (string) $setting['profil_singkat'] : trim(implode(' · ', array_filter([$isi('school_level'), $isi('city')]))),
    'bawahHtml' => $utama ? '<img src="' . esc($utama['url'], 'attr') . '" alt="' . esc($utama['judul'], 'attr') . '" class="pembuka-foto">' : null,
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="space-y-6 lg:col-span-2">
            <div class="kartu">
                <div class="kartu-kepala"><h2 class="kartu-judul">Identitas sekolah</h2></div>
                <dl class="daftar-data px-5">
                    <?php foreach ($identitas as $label => $nilai): ?>
                        <div><dt><?= $label ?></dt><dd><?= esc($nilai) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </div>
            <?php if ($foto !== []): ?>
                <div>
                    <h2 class="judul-sub mb-4">Foto sekolah</h2>
                    <ul class="galeri lg:grid-cols-3">
                        <?php foreach ($foto as $f): ?>
                            <li>
                                <a href="<?= esc($f['url'], 'attr') ?>" class="galeri-item" target="_blank" rel="noopener" title="Buka foto ukuran penuh">
                                    <img src="<?= esc($f['url'], 'attr') ?>" alt="" class="galeri-gambar" loading="lazy">
                                    <p class="galeri-teks"><?= esc($f['judul']) ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="judul-sub mb-4">Sekolah dalam angka</h2>
                <?= view('public/partials/grafik_sekolah', ['stats' => $stats, 'siswaStat' => $siswaStat, 'bagian' => 'angka'], ['saveData' => false]) ?>
                <?= view('public/partials/grafik_sekolah', ['stats' => $stats, 'siswaStat' => $siswaStat, 'bagian' => 'grafik', 'kolom' => 2], ['saveData' => false]) ?>
            </div>
        </div>
        <?= view('public/partials/kontak', ['setting' => $setting], ['saveData' => false]) ?>
    </div>
</section>
<?= $this->endSection() ?>
