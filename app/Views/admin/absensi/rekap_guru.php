<?php
/**
 * Rincian rekap absensi satu guru pada satu periode (rombak tampilan F6: kartu + tabel kit; lencana status absensi warna tetap:
 * hadir hijau, telat kuning, izin biru, sakit pink, alpa merah). Tombol Kembali ke Rekap membawa periode.
 *
 * @var array  $guru    Baris guru
 * @var array  $jabatan Jabatan guru (nama, is_struktural)
 * @var array  $ringkas total, hadir, telat, izin, sakit, alpa
 * @var array  $detail  Catatan ketidakhadiran
 * @var string $dari    Awal periode (Y-m-d)
 * @var string $sampai  Akhir periode (Y-m-d)
 */
$statusOpts = ['hadir' => 'Hadir', 'telat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];
$lencana    = ['hadir' => 'lencana-hijau', 'telat' => 'lencana-kuning', 'izin' => 'lencana-biru', 'sakit' => 'lencana-pink', 'alpa' => 'lencana-merah'];
$bulan      = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
$namaHari   = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$qs         = '?dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai);
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/absensi/rekap') . $qs ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke rekap</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="space-y-4">
    <!-- Kepala guru + ringkasan -->
    <div class="kartu kartu-isi flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?= esc($guru['nama']) ?></h2>
            <p class="text-sm text-gray-500 dark:text-gray-400"><?= esc($guru['kode_guru']) ?> &middot; Periode <?= esc($dari) ?> s/d <?= esc($sampai) ?></p>
            <?php if (! empty($jabatan)): ?>
                <div class="mt-1.5 flex flex-wrap gap-1">
                    <?php foreach ($jabatan as $j): ?>
                        <span class="lencana <?= $j['is_struktural'] ? 'lencana-kuning' : 'lencana-abu' ?>"><?= esc($j['nama']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="lencana lencana-abu">Total sesi: <?= (int) $ringkas['total'] ?></span>
            <?php foreach ($statusOpts as $k => $lbl): ?>
                <span class="lencana <?= $lencana[$k] ?>"><?= $lbl ?>: <?= (int) $ringkas[$k] ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($detail)): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Hadir penuh', 'teks' => 'Tidak ada catatan ketidakhadiran pada periode ini.']) ?></div>
    <?php else: ?>
        <section class="kartu" aria-labelledby="judul-rincian">
            <div class="kartu-kepala">
                <h2 id="judul-rincian" class="text-base font-semibold text-gray-900 dark:text-white">Rincian ketidakhadiran</h2>
                <span class="lencana lencana-abu"><?= count($detail) ?> catatan</span>
            </div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Jam</th>
                            <th scope="col">Kelas &amp; mapel</th>
                            <th scope="col">Status</th>
                            <th scope="col">Jam masuk</th>
                            <th scope="col">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail as $d): $ts = strtotime($d['tanggal']); ?>
                            <tr>
                                <td class="whitespace-nowrap"><span class="font-semibold text-gray-900 dark:text-white"><?= (int) date('j', $ts) ?> <?= $bulan[(int) date('n', $ts)] ?> <?= date('Y', $ts) ?></span><br><span class="text-xs text-gray-500 dark:text-gray-400"><?= $namaHari[(int) date('w', $ts)] ?></span></td>
                                <td class="whitespace-nowrap"><?php if ($d['jam_ke'] !== null): ?>Jam <?= esc($d['jam_ke']) ?> <span class="text-xs text-gray-500 dark:text-gray-400">(<?= esc(substr((string) $d['waktu_mulai'], 0, 5)) ?>–<?= esc(substr((string) $d['waktu_selesai'], 0, 5)) ?>)</span><?php else: ?>—<?php endif; ?></td>
                                <td>
                                    <?php if (! empty($d['label'])): ?>
                                        <span class="lencana lencana-merah"><?= esc($d['label']) ?></span>
                                    <?php elseif (! empty($d['kehadiran_kerja'])): ?>
                                        <span class="lencana lencana-abu">Kehadiran kerja</span>
                                    <?php else: ?>
                                        <?= esc($d['nama_kelas'] ?? '—') ?> · <span class="font-medium text-gray-900 dark:text-white"><?= esc($d['nama_mapel'] ?? '—') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="lencana <?= $lencana[$d['status']] ?? 'lencana-biru' ?>"><?= esc($statusOpts[$d['status']] ?? $d['status']) ?></span></td>
                                <td><?= $d['jam_masuk'] ? esc(substr($d['jam_masuk'], 0, 5)) : '—' ?></td>
                                <td><?= $d['keterangan'] ? esc($d['keterangan']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
