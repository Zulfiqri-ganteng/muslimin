<?php
/**
 * Rekap Hasil UKK — rombak tampilan F8 (pola Laporan Lab): Cetak PDF / Unduh Excel ber-ikon di kepala halaman, kartu saringan
 * tahun ajaran, kartu angka (stat-*) per status, kartu rata-rata nilai, dua tabel kit (per paket soal, per jurusan), ui_kosong.
 *
 * @var int                $tahunId
 * @var array<int,string>  $tahunOpts
 * @var int                $totalPeserta
 * @var array              $perStatus
 * @var float|null         $rataNilai
 * @var array              $perPaket
 * @var array              $perJurusan
 */
$base = site_url('admin/laporan-ukk');
$qs   = 'tahun_ajaran_id=' . (int) $tahunId;
$num  = static fn ($v) => $v !== null ? number_format((float) $v, 1) : '—';
$statusLabel = [
    'terdaftar' => 'Terdaftar', 'hadir' => 'Hadir', 'tidak_hadir' => 'Tidak hadir',
    'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak lulus',
];
$hijau = 'text-green-700 dark:text-green-400';
$merah = 'text-red-700 dark:text-red-400';
$statusWarna = [
    'terdaftar'   => 'text-gray-900 dark:text-white',
    'hadir'       => 'text-brand-700 dark:text-brand-400',
    'tidak_hadir' => 'text-yellow-700 dark:text-yellow-400',
    'lulus'       => $hijau,
    'tidak_lulus' => $merah,
];
// Kartu angka kit: label di atas, angka besar berwarna.
$tile = static fn (string $label, $value, string $warna = 'text-gray-900 dark:text-white'): string => '<div class="kartu kartu-isi">'
    . '<p class="stat-label text-sm">' . esc($label) . '</p><p class="stat-angka mt-2 tabular-nums ' . $warna . '">' . $value . '</p></div>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= $base ?>/pdf?<?= $qs ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('printer', 'h-4 w-4') ?>Cetak PDF</a>
<a href="<?= $base ?>/excel?<?= $qs ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'laporan_ukk',
    'helpTitle' => 'Rekap Hasil UKK',
    'helpBody'  => '<p>Rekap seluruh peserta UKK: total per status, per paket soal, dan per jurusan. Filter opsional
        per tahun ajaran. Bisa diunduh sebagai PDF (siap cetak dengan kop resmi) atau Excel.</p>',
]) ?>

<div class="space-y-6">
    <!-- Saringan tahun ajaran -->
    <form method="get" class="kartu kartu-isi flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-72">
            <label class="lbl" for="lu-tahun">Tahun ajaran</label>
            <select id="lu-tahun" name="tahun_ajaran_id" data-autosubmit class="inp">
                <option value="">Semua tahun ajaran</option>
                <?php foreach ($tahunOpts as $id => $lbl): ?>
                    <option value="<?= (int) $id ?>"<?= $tahunId === (int) $id ? ' selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <!-- Ringkasan peserta -->
    <section class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6" aria-label="Ringkasan peserta per status">
        <?= $tile('Total peserta', (int) $totalPeserta) ?>
        <?php foreach ($perStatus as $k => $v): ?>
            <?= $tile($statusLabel[$k] ?? ucfirst(str_replace('_', ' ', $k)), (int) $v, $statusWarna[$k] ?? 'text-gray-900 dark:text-white') ?>
        <?php endforeach; ?>
    </section>

    <section class="kartu kartu-isi" aria-label="Rata-rata nilai akhir">
        <p class="stat-label text-sm">Rata-rata nilai akhir (seluruh peserta yang sudah dinilai)</p>
        <p class="stat-angka mt-2 tabular-nums text-brand-700 dark:text-brand-400"><?= $num($rataNilai) ?></p>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <!-- Per paket soal -->
        <section class="kartu overflow-hidden" aria-labelledby="judul-rekap-paket">
            <div class="kartu-kepala"><h2 id="judul-rekap-paket" class="kartu-judul">Rekap per paket soal</h2></div>
            <?php if (empty($perPaket)): ?>
                <?= ui_kosong(['ikon' => 'chart-pie', 'judul' => 'Belum ada data']) ?>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col">Paket soal</th>
                                <th scope="col" class="text-right">Total</th>
                                <th scope="col" class="text-right">Lulus</th>
                                <th scope="col" class="text-right">Tidak lulus</th>
                                <th scope="col" class="text-right">Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perPaket as $r): ?>
                                <tr>
                                    <td>
                                        <p class="font-semibold text-gray-900 dark:text-white"><?= esc($r['paket_kode']) ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($r['paket_nama']) ?></p>
                                    </td>
                                    <td class="text-right tabular-nums"><?= (int) $r['total'] ?></td>
                                    <td class="text-right font-semibold tabular-nums <?= $hijau ?>"><?= (int) $r['lulus'] ?></td>
                                    <td class="text-right font-semibold tabular-nums <?= $merah ?>"><?= (int) $r['tidak_lulus'] ?></td>
                                    <td class="text-right tabular-nums"><?= $num($r['rata_nilai']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Per jurusan -->
        <section class="kartu overflow-hidden" aria-labelledby="judul-rekap-jurusan">
            <div class="kartu-kepala"><h2 id="judul-rekap-jurusan" class="kartu-judul">Rekap per jurusan</h2></div>
            <?php if (empty($perJurusan)): ?>
                <?= ui_kosong(['ikon' => 'chart-pie', 'judul' => 'Belum ada data']) ?>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col">Jurusan</th>
                                <th scope="col" class="text-right">Total</th>
                                <th scope="col" class="text-right">Lulus</th>
                                <th scope="col" class="text-right">Tidak lulus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perJurusan as $r): ?>
                                <tr>
                                    <td class="font-medium text-gray-900 dark:text-white"><?= $r['jurusan_nama'] !== null ? esc($r['jurusan_nama']) : '<span class="text-gray-500 dark:text-gray-400">(tanpa jurusan)</span>' ?></td>
                                    <td class="text-right tabular-nums"><?= (int) $r['total'] ?></td>
                                    <td class="text-right font-semibold tabular-nums <?= $hijau ?>"><?= (int) $r['lulus'] ?></td>
                                    <td class="text-right font-semibold tabular-nums <?= $merah ?>"><?= (int) $r['tidak_lulus'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
