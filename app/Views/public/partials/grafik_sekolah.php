<?php
/**
 * "Sekolah dalam angka" (beranda & profil): kotak angka berilustrasi + tiga grafik — siswa per tingkat (batang tegak), siswa per
 * jurusan (batang mendatar), laki-laki/perempuan (donat). SVG digambar server: tanpa pustaka grafik, tanpa gaya sebaris (siap CSP;
 * ukuran lewat atribut SVG). Grafik tumbuh halus saat bagiannya terlihat (publik/portal.js menandai [data-grafik] dengan .terlihat),
 * langsung jadi bila "kurangi gerakan". Angka tidak berhitung naik. Data siswa hanya ANGKA AGREGAT.
 *
 * @var array       $stats     siswa, guru, kelas, mapel
 * @var array       $siswaStat SiswaModel::statistik()
 * @var string      $bagian    'angka' (kotak angka) | 'grafik' (tiga grafik)
 * @var int|null    $kolom     jumlah kolom di layar lebar: grafik bawaan 3 (atau 2); angka bawaan 2 (atau 4 = satu baris)
 */
use App\Libraries\LayananPublik;

$angka = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$bulat = static fn (float $n): string => rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');

if (($bagian ?? 'angka') === 'angka'):
    $kotak = [
        ['Siswa aktif', $stats['siswa'] ?? 0, 'siswa'],
        ['Guru', $stats['guru'] ?? 0, 'guru'],
        ['Kelas', $stats['kelas'] ?? 0, 'sekolah'],
        ['Mata pelajaran', $stats['mapel'] ?? 0, 'jadwal-kelas'],
    ];
    ?>
<dl class="stat-grid<?= (int) ($kolom ?? 2) === 4 ? ' stat-grid-4' : '' ?>">
    <?php foreach ($kotak as [$label, $nilai, $ilu]): ?>
        <div class="stat-ubin">
            <img src="<?= LayananPublik::ilustrasi($ilu) ?>" alt="" width="56" height="56" loading="lazy">
            <div class="flex min-w-0 flex-col"><dt class="stat-label"><?= $label ?></dt><dd class="stat-nilai order-first"><?= $angka($nilai) ?></dd></div>
        </div>
    <?php endforeach; ?>
</dl>
<?php
else:
    $perTingkat = array_filter((array) ($siswaStat['per_tingkat'] ?? []), static fn ($v): bool => (int) $v > 0);
    uksort($perTingkat, static fn ($a, $b): int => (['X' => 1, 'XI' => 2, 'XII' => 3][$a] ?? 9) <=> (['X' => 1, 'XI' => 2, 'XII' => 3][$b] ?? 9));
    $perJurusan = array_slice(array_values(array_filter((array) ($siswaStat['per_jurusan'] ?? []), static fn ($j): bool => (int) $j['jumlah'] > 0)), 0, 6);
    $lk         = (int) ($siswaStat['jenis_kelamin']['L'] ?? 0);
    $pr         = (int) ($siswaStat['jenis_kelamin']['P'] ?? 0);
    if ($perTingkat === [] && $perJurusan === [] && $lk + $pr === 0) {
        return;
    }
    $kelasGrid = (int) ($kolom ?? 3) === 2 ? 'grafik-grid grafik-grid-2' : 'grafik-grid';
    ?>
<div class="<?= $kelasGrid ?>" data-grafik>
    <?php if ($perTingkat !== []):
        // Batang tegak: area gambar x 20–300, alas y 170, tinggi maksimal 130.
        $n    = count($perTingkat);
        $maks = max(1, max($perTingkat));
        $slot = 280 / $n;
        $lebar = min(64, $slot * 0.55);
        $ringkas = [];
        foreach ($perTingkat as $t => $v) {
            $ringkas[] = 'kelas ' . $t . ' ' . $angka($v);
        }
        ?>
        <figure class="grafik-kartu">
            <figcaption><p class="grafik-judul">Siswa per tingkat</p><p class="grafik-ket">Jumlah siswa aktif tiap jenjang kelas</p></figcaption>
            <svg class="grafik-svg" viewBox="0 0 320 200" role="img" aria-label="Siswa per tingkat: <?= esc(implode(', ', $ringkas), 'attr') ?>">
                <line class="grafik-garis-putus" x1="16" y1="40" x2="304" y2="40"/>
                <line class="grafik-garis-putus" x1="16" y1="105" x2="304" y2="105"/>
                <line class="grafik-garis" x1="16" y1="170" x2="304" y2="170"/>
                <?php $i = 0; foreach ($perTingkat as $t => $v):
                    $tinggi = max(4, round((int) $v / $maks * 130, 1));
                    $x      = round(20 + $i * $slot + ($slot - $lebar) / 2, 1);
                    $tengah = round($x + $lebar / 2, 1);
                    ?>
                    <rect class="grafik-batang tunda-<?= $i + 1 ?>" x="<?= $x ?>" y="<?= 170 - $tinggi ?>" width="<?= round($lebar, 1) ?>" height="<?= $tinggi ?>" rx="6"/>
                    <text class="grafik-nilai" x="<?= $tengah ?>" y="<?= 170 - $tinggi - 8 ?>" text-anchor="middle"><?= $angka($v) ?></text>
                    <text class="grafik-label" x="<?= $tengah ?>" y="190" text-anchor="middle">Kelas <?= esc((string) $t) ?></text>
                <?php $i++; endforeach; ?>
            </svg>
        </figure>
    <?php endif; ?>

    <?php if ($perJurusan !== []):
        // Batang mendatar: label 0–84, jalur 90–280.
        $maks  = max(1, max(array_column($perJurusan, 'jumlah')));
        $tinggiSvg = count($perJurusan) * 34 + 4;
        ?>
        <figure class="grafik-kartu">
            <figcaption><p class="grafik-judul">Siswa per jurusan</p><p class="grafik-ket"><?= count($perJurusan) < count((array) ($siswaStat['per_jurusan'] ?? [])) ? 'Enam jurusan dengan siswa terbanyak' : 'Jumlah siswa aktif tiap jurusan' ?></p></figcaption>
            <svg class="grafik-svg" viewBox="0 0 320 <?= $tinggiSvg ?>" role="img" aria-label="Siswa per jurusan: <?= esc(implode(', ', array_map(static fn ($j): string => $j['nama'] . ' ' . $angka($j['jumlah']), $perJurusan)), 'attr') ?>">
                <?php foreach ($perJurusan as $i => $j):
                    $y     = $i * 34 + 8;
                    $lebar = max(6, round((int) $j['jumlah'] / $maks * 190, 1));
                    $label = trim((string) $j['kode']) !== '' ? mb_strimwidth((string) $j['kode'], 0, 10, '…') : mb_strimwidth((string) $j['nama'], 0, 12, '…');
                    ?>
                    <g>
                        <title><?= esc((string) $j['nama']) ?>: <?= $angka($j['jumlah']) ?> siswa</title>
                        <text class="grafik-label" x="0" y="<?= $y + 11 ?>"><?= esc($label) ?></text>
                        <rect class="grafik-jalur" x="90" y="<?= $y ?>" width="190" height="14" rx="7"/>
                        <rect class="grafik-batang-datar tunda-<?= $i + 1 ?>" x="90" y="<?= $y ?>" width="<?= $lebar ?>" height="14" rx="7"/>
                        <text class="grafik-nilai" x="<?= 90 + $lebar + 6 ?>" y="<?= $y + 12 ?>"><?= $angka($j['jumlah']) ?></text>
                    </g>
                <?php endforeach; ?>
            </svg>
        </figure>
    <?php endif; ?>

    <?php if ($lk + $pr > 0):
        // Donat: r 58, keliling 2πr.
        $total = $lk + $pr;
        $kel   = 2 * M_PI * 58;
        $pjL   = round($kel * $lk / $total, 2);
        $pjP   = round($kel - $pjL, 2);
        $kelR  = round($kel, 2);
        ?>
        <figure class="grafik-kartu">
            <figcaption><p class="grafik-judul">Laki-laki dan perempuan</p><p class="grafik-ket">Perbandingan siswa aktif</p></figcaption>
            <svg class="grafik-svg mx-auto max-w-[190px]" viewBox="0 0 160 160" role="img" aria-label="Laki-laki <?= $angka($lk) ?>, perempuan <?= $angka($pr) ?> dari <?= $angka($total) ?> siswa">
                <circle class="grafik-donat-jalur" cx="80" cy="80" r="58"/>
                <?php if ($lk > 0): ?><circle class="grafik-donat grafik-donat-l" cx="80" cy="80" r="58" stroke-dasharray="<?= $pjL ?> <?= $kelR ?>" transform="rotate(-90 80 80)"/><?php endif; ?>
                <?php if ($pr > 0): ?><circle class="grafik-donat grafik-donat-p tunda-2" cx="80" cy="80" r="58" stroke-dasharray="<?= $pjP ?> <?= $kelR ?>" stroke-dashoffset="-<?= $pjL ?>" transform="rotate(-90 80 80)"/><?php endif; ?>
                <text class="grafik-tengah" x="80" y="82" text-anchor="middle"><?= $angka($total) ?></text>
                <text class="grafik-label" x="80" y="102" text-anchor="middle">siswa</text>
            </svg>
            <div class="legenda">
                <div><span class="flex items-center gap-2"><span class="legenda-warna legenda-l"></span>Laki-laki</span><b class="tabular-nums"><?= $angka($lk) ?> (<?= $bulat($lk / $total * 100) ?>%)</b></div>
                <div><span class="flex items-center gap-2"><span class="legenda-warna legenda-p"></span>Perempuan</span><b class="tabular-nums"><?= $angka($pr) ?> (<?= $bulat($pr / $total * 100) ?>%)</b></div>
            </div>
        </figure>
    <?php endif; ?>
</div>
<?php endif; ?>
