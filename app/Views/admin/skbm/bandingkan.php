<?php
/**
 * Perbandingan SKBM dengan data Penugasan (pengampu) — KHUSUS ADMIN. Hanya MEMBANDINGKAN: tidak mengubah Penugasan,
 * Jadwal KBM, maupun honor. Pasangan yang dibandingkan: guru × kelas (hanya guru yang ada di SKBM).
 * Rombak tampilan F7: kartu angka, kartu per bagian dengan tabel kit, lencana jumlah, peringatan kit.
 *
 * @var string $tahun  @var array $b  hasil Skbm::bandingkanDenganPengampu()  @var string $back
 */
$maks = 300;
// [kunci, judul, uraian, lencana jumlah, kolom mapel penugasan?]
$bagian = [
    ['hanya_skbm', 'Ada di SKBM, belum ada di Penugasan', 'SK menugaskan guru ini di kelas tersebut, tetapi menu Penugasan belum memuatnya. Periksa: apakah Penugasan perlu ditambah (Jadwal KBM & Rekap Beban memakai Penugasan)?', 'lencana-kuning', false],
    ['hanya_pengampu', 'Ada di Penugasan, tidak ada di SKBM', 'Penugasan memuat pasangan ini, tetapi SKBM tidak. Periksa: apakah SKBM belum lengkap, atau Penugasan yang kelebihan?', 'lencana-merah', false],
    ['jp_beda', 'JP berbeda', 'Pasangan guru–kelas sama di keduanya, tetapi jumlah JP per minggu berbeda.', 'lencana-biru', true],
];
$strip = '<span class="text-gray-300 dark:text-gray-600">&mdash;</span>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= esc($back, 'attr') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke SKBM</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4">
    <section class="kartu kartu-isi" aria-labelledby="judul-banding">
        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">SKBM &middot; perbandingan dengan Penugasan</p>
        <h2 id="judul-banding" class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">SKBM <?= esc($tahun) ?> dibandingkan dengan Penugasan</h2>
        <p class="mt-1 max-w-3xl text-sm leading-relaxed text-gray-500 dark:text-gray-400">Halaman ini hanya <b>membandingkan</b>: data Penugasan, Jadwal KBM, dan honor tidak diubah. Yang dicocokkan ialah pasangan <b>guru &times; kelas</b>, hanya untuk guru yang ada di SKBM.</p>

        <?php if (! $b['ada']): ?>
            <div class="mt-4"><?= ui_peringatan('info', 'SKBM ' . $tahun . ' belum diisi, jadi belum ada yang dibandingkan.') ?></div>
        <?php else: ?>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <?php foreach ([
                    ['Guru di SKBM', (int) $b['guru_skbm'], ''],
                    ['Sama', (int) $b['sama'], 'text-green-700 dark:text-green-400'],
                    ['Hanya di SKBM', count($b['hanya_skbm']), $b['hanya_skbm'] !== [] ? 'text-yellow-700 dark:text-yellow-400' : ''],
                    ['Hanya di Penugasan', count($b['hanya_pengampu']), $b['hanya_pengampu'] !== [] ? 'text-red-700 dark:text-red-400' : ''],
                    ['JP beda', count($b['jp_beda']), $b['jp_beda'] !== [] ? 'text-blue-700 dark:text-blue-400' : ''],
                ] as [$lbl, $n, $warna]): ?>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-700/50">
                        <p class="text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"><?= esc($lbl) ?></p>
                        <p class="text-xl font-bold tabular-nums <?= $warna !== '' ? $warna : 'text-gray-900 dark:text-white' ?>"><?= $n ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($b['hanya_skbm'] === [] && $b['hanya_pengampu'] === [] && $b['jp_beda'] === []): ?>
                <div class="mt-4"><?= ui_peringatan('sukses', 'Semua sama: Penugasan sesuai dengan SKBM untuk guru-guru yang ada di SKBM.') ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($b['ada']): ?>
        <?php foreach ($bagian as [$kunci, $judul, $uraian, $lencana, $adaJp]): $isi = $b[$kunci]; ?>
            <?php if ($isi === []) { continue; } ?>
            <section class="kartu overflow-hidden" aria-label="<?= esc($judul, 'attr') ?>">
                <div class="kartu-kepala">
                    <div class="min-w-0">
                        <h3 class="flex flex-wrap items-center gap-2 text-base font-semibold text-gray-900 dark:text-white"><?= esc($judul) ?> <span class="lencana <?= $lencana ?>"><?= count($isi) ?></span></h3>
                        <p class="kartu-ket max-w-3xl"><?= esc($uraian) ?></p>
                    </div>
                </div>
                <div class="tabel-bungkus panel-gulir max-h-[32rem]">
                    <table class="tabel min-w-[40rem]">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th scope="col">Guru</th><th scope="col">Kelas</th><th scope="col">Mapel di SKBM</th>
                                <?php if ($adaJp): ?><th scope="col">Mapel di Penugasan</th><?php endif; ?>
                                <th scope="col" class="text-right">JP SKBM</th><th scope="col" class="text-right">JP Penugasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($isi, 0, $maks) as $r): ?>
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-white"><?= esc($r['guru']) ?></td>
                                    <td><?= esc($r['kelas']) ?></td>
                                    <td><?= $kunci === 'hanya_pengampu' ? $strip : esc($r['mapel']) ?></td>
                                    <?php if ($adaJp): ?><td><?= esc($r['mapel_pengampu'] ?? '') ?></td><?php endif; ?>
                                    <td class="text-right tabular-nums"><?= $r['jp_skbm'] === null ? $strip : (int) $r['jp_skbm'] ?></td>
                                    <td class="text-right tabular-nums"><?= $r['jp_pengampu'] === null ? $strip : (int) $r['jp_pengampu'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($isi) > $maks): ?><p class="kartu-kaki text-xs text-gray-500 dark:text-gray-400">Ditampilkan <?= $maks ?> baris pertama dari <?= count($isi) ?>.</p><?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($b['guru_tanpa_skbm'] !== []): ?>
            <section class="kartu kartu-isi" aria-labelledby="judul-tanpa-skbm">
                <h3 id="judul-tanpa-skbm" class="flex flex-wrap items-center gap-2 text-base font-semibold text-gray-900 dark:text-white">Punya Penugasan tetapi tidak ada di SKBM <span class="lencana lencana-abu"><?= count($b['guru_tanpa_skbm']) ?> guru</span></h3>
                <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">Tidak dibandingkan per kelas karena gurunya memang belum masuk SKBM <?= esc($tahun) ?>. Bila seharusnya ada, tambahkan di halaman SKBM.</p>
                <p class="mt-2 text-sm leading-relaxed text-gray-900 dark:text-white"><?= esc(implode(' · ', $b['guru_tanpa_skbm'])) ?></p>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
