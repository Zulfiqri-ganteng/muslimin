<?php
/**
 * Laporan Laboratorium (rekap gabungan) — rombak tampilan F8: Export PDF/Excel ber-ikon di kepala halaman, kartu saringan
 * periode, kartu angka (stat-*), bilah kondisi aset = <progress class="progres"> (tanpa style=), tabel kit, lencana.
 *
 * @var string $dari
 * @var string $sampai
 * @var int    $asetTotal
 * @var array  $asetKondisi
 * @var array  $asetStatus
 * @var array  $asetPerLab
 * @var int    $pmTotal
 * @var array  $pmStatus
 * @var int    $pmSedang
 * @var int    $pmTerlambat
 * @var int    $krTotal
 * @var array  $krStatus
 * @var int    $pbTotal
 * @var array  $pbJenis
 * @var float  $pbBiaya
 * @var int    $spTotal
 * @var array  $spMenipis
 * @var int    $spMenipisCount
 * @var int    $jrTotal
 * @var array  $jrPerLab
 */
$rp   = static fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
$qs   = 'dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai);
$base = site_url('admin/laporan-lab');
// Kartu angka kit: label di atas, angka besar berwarna.
$tile = static fn (string $label, $value, string $warna = 'text-gray-900 dark:text-white'): string => '<div class="kartu kartu-isi">'
    . '<p class="stat-label text-sm">' . esc($label) . '</p><p class="stat-angka mt-2 tabular-nums ' . $warna . '">' . $value . '</p></div>';
$judul = static fn (string $teks, string $ket): string => '<h2 class="flex flex-wrap items-baseline gap-2 text-base font-semibold text-gray-900 dark:text-white">' . $teks
    . ' <span class="text-sm font-normal text-gray-500 dark:text-gray-400">' . $ket . '</span></h2>';
$hijau = 'text-green-700 dark:text-green-400';
$merek = 'text-brand-700 dark:text-brand-400';
$kuning = 'text-yellow-700 dark:text-yellow-400';
$merah = 'text-red-700 dark:text-red-400';
$kosongSel = '<span class="text-gray-500 dark:text-gray-400">(tanpa lab)</span>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= $base ?>/pdf?<?= esc($qs, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('printer', 'h-4 w-4') ?>Cetak PDF</a>
<a href="<?= $base ?>/excel?<?= esc($qs, 'attr') ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'laporan_lab',
    'helpTitle' => 'Laporan Laboratorium',
    'helpBody'  => '<p>Rekap gabungan seluruh modul lab: aset & kondisinya, peminjaman, kerusakan/perbaikan, stok sparepart menipis, dan pemakaian lab. Angka aset & sparepart bersifat <b>kini</b> (snapshot); peminjaman/kerusakan/perbaikan/jurnal mengikuti <b>rentang tanggal</b>.</p>
        <p class="mt-1">Gunakan <b>Export PDF/Excel</b> untuk arsip atau laporan ke pimpinan.</p>',
]) ?>

<div class="space-y-6">
    <!-- Saringan periode -->
    <form method="get" class="kartu kartu-isi flex flex-wrap items-end gap-3">
        <div>
            <label class="lbl" for="ll-dari">Dari</label>
            <input type="date" id="ll-dari" name="dari" value="<?= esc($dari, 'attr') ?>" class="inp">
        </div>
        <div>
            <label class="lbl" for="ll-sampai">Sampai</label>
            <input type="date" id="ll-sampai" name="sampai" value="<?= esc($sampai, 'attr') ?>" class="inp">
        </div>
        <button type="submit" class="btn btn-utama"><?= ikon('search', 'h-4 w-4') ?>Terapkan</button>
    </form>

    <!-- Aset -->
    <section class="space-y-4" aria-label="Aset / inventaris">
        <?= $judul('Aset / inventaris', '(kini)') ?>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <?= $tile('Total aset', (int) $asetTotal, $merek) ?>
            <?= $tile('Tersedia', (int) $asetStatus['tersedia'], $hijau) ?>
            <?= $tile('Dipinjam', (int) $asetStatus['dipinjam'], $merek) ?>
            <?= $tile('Perbaikan', (int) $asetStatus['perbaikan'], $kuning) ?>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="kartu kartu-isi">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Kondisi aset</h3>
                <div class="space-y-3">
                    <?php foreach (['baik' => 'progres-hijau', 'rusak_ringan' => 'progres-kuning', 'rusak_berat' => 'progres-merah'] as $k => $warna):
                        $v = (int) ($asetKondisi[$k] ?? 0);
                        $frac = $asetTotal > 0 ? max(3, round($v / $asetTotal * 100)) : 0; ?>
                        <div class="flex items-center gap-3">
                            <div class="w-24 text-xs text-gray-500 dark:text-gray-400"><?= ucfirst(str_replace('_', ' ', $k)) ?></div>
                            <progress class="progres <?= $warna ?> h-3 flex-1" value="<?= (int) $frac ?>" max="100" aria-label="<?= esc(ucfirst(str_replace('_', ' ', $k)), 'attr') ?>: <?= $v ?> aset"><?= $v ?></progress>
                            <div class="w-8 text-right text-sm font-semibold text-gray-900 tabular-nums dark:text-white"><?= $v ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="kartu overflow-hidden">
                <div class="kartu-kepala"><h3 class="text-sm font-semibold text-gray-900 dark:text-white">Aset per lab</h3></div>
                <?php if (empty($asetPerLab)): ?>
                    <?= ui_kosong(['ikon' => 'desktop-pc', 'judul' => 'Belum ada aset']) ?>
                <?php else: ?>
                    <div class="tabel-bungkus">
                        <table class="tabel">
                            <tbody>
                                <?php foreach ($asetPerLab as $row): ?>
                                    <tr><td><?= $row['lab_nama'] !== null ? esc($row['lab_nama']) : $kosongSel ?></td><td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white"><?= (int) $row['c'] ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Peminjaman -->
    <section class="space-y-4" aria-label="Peminjaman">
        <?= $judul('Peminjaman', '(' . esc($dari) . ' s/d ' . esc($sampai) . ')') ?>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <?= $tile('Total periode', (int) $pmTotal, $merek) ?>
            <?= $tile('Dikembalikan', (int) $pmStatus['dikembalikan'], $hijau) ?>
            <?= $tile('Hilang', (int) $pmStatus['hilang'], $merah) ?>
            <?= $tile('Sedang dipinjam', (int) $pmSedang, $merek) ?>
            <?= $tile('Terlambat (kini)', (int) $pmTerlambat, $merah) ?>
            <?= $tile('Dipinjam (periode)', (int) $pmStatus['dipinjam']) ?>
        </div>
    </section>

    <!-- Kerusakan & Perbaikan -->
    <section class="space-y-4" aria-label="Kerusakan dan perbaikan">
        <?= $judul('Kerusakan &amp; perbaikan', '(periode)') ?>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <?= $tile('Total kerusakan', (int) $krTotal, $kuning) ?>
            <?= $tile('Selesai', (int) $krStatus['selesai'], $hijau) ?>
            <?= $tile('Total perbaikan', (int) $pbTotal, $merek) ?>
            <?= $tile('Biaya perbaikan', $rp($pbBiaya)) ?>
        </div>
        <div class="kartu kartu-isi">
            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <?php foreach ($pbJenis as $k => $v): ?>
                    <div class="flex items-center justify-between"><span class="text-gray-500 dark:text-gray-400"><?= esc(ucfirst($k)) ?></span><span class="font-semibold text-gray-900 tabular-nums dark:text-white"><?= (int) $v ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Sparepart & Jurnal -->
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="kartu overflow-hidden" aria-labelledby="judul-sparepart">
            <div class="kartu-kepala">
                <h3 id="judul-sparepart" class="text-sm font-semibold text-gray-900 dark:text-white">Sparepart <span class="font-normal text-gray-500 dark:text-gray-400">(kini)</span></h3>
                <div class="flex flex-wrap gap-2">
                    <span class="lencana lencana-abu">Total jenis: <?= (int) $spTotal ?></span>
                    <span class="lencana <?= $spMenipisCount > 0 ? 'lencana-merah' : 'lencana-hijau' ?>">Stok menipis: <?= (int) $spMenipisCount ?></span>
                </div>
            </div>
            <?php if (! empty($spMenipis)): ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th scope="col">Sparepart</th><th scope="col" class="text-right">Stok</th></tr></thead>
                        <tbody>
                            <?php foreach ($spMenipis as $s): ?>
                                <tr><td><?= esc($s['nama']) ?></td><td class="text-right"><span class="font-semibold text-red-700 tabular-nums dark:text-red-400"><?= (int) $s['stok'] ?></span> <span class="text-xs text-gray-500 dark:text-gray-400">/<?= (int) $s['stok_minimum'] ?> <?= esc($s['satuan']) ?></span></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Semua stok aman']) ?>
            <?php endif; ?>
        </section>
        <section class="kartu overflow-hidden" aria-labelledby="judul-pemakaian">
            <div class="kartu-kepala">
                <h3 id="judul-pemakaian" class="text-sm font-semibold text-gray-900 dark:text-white">Pemakaian lab <span class="font-normal text-gray-500 dark:text-gray-400">(jurnal, periode)</span></h3>
                <span class="lencana lencana-merek">Total sesi: <?= (int) $jrTotal ?></span>
            </div>
            <?php if (! empty($jrPerLab)): ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <tbody>
                            <?php foreach ($jrPerLab as $row): ?>
                                <tr><td><?= $row['lab_nama'] !== null ? esc($row['lab_nama']) : $kosongSel ?></td><td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white"><?= (int) $row['c'] ?> sesi</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= ui_kosong(['ikon' => 'book-open', 'judul' => 'Belum ada catatan pemakaian', 'teks' => 'Pada periode ini belum ada jurnal pemakaian lab.']) ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
