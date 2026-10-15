<?php
/**
 * Tab Rekap — ringkasan satu periode + pintu ke cetakan PDF/Excel (rombak tampilan F7: kartu angka stat-*, tabel kit,
 * tombol cetak ber-ikon, ui_kosong).
 *
 * Angkanya berasal dari App\Libraries\UjianReport::hitung(), sumber yang
 * sama dengan berkas cetak, jadi layar dan cetakan tidak mungkin berbeda.
 *
 * @var array            $periode
 * @var string           $base
 * @var string           $qtp
 * @var int              $jadwalTotal
 * @var array            $jadwalPerTingkat
 * @var int              $pengawasTotal
 * @var int              $takHadirTotal
 * @var array            $perStatus
 * @var array            $perAlasan
 * @var array<int,array> $perKelas
 * @var array<int,array> $perMapel
 * @var array<int,array> $siswaTerbanyak
 */
$labelAlasan = ['sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya'];
$labelStatusSusulan = ['belum' => 'Belum dijadwalkan', 'dijadwalkan' => 'Sudah dijadwalkan', 'selesai' => 'Selesai', 'batal' => 'Batal'];
$kartu = [
    ['Sesi terjadwal', $jadwalTotal, 'X ' . $jadwalPerTingkat['X'] . ' · XI ' . $jadwalPerTingkat['XI'] . ' · XII ' . $jadwalPerTingkat['XII'], 'calendar-week', 'text-brand-700 dark:text-brand-400'],
    ['Penugasan pengawas', $pengawasTotal, 'Opsional', 'users', 'text-gray-900 dark:text-white'],
    ['Siswa tidak hadir', $takHadirTotal, 'Total catatan', 'exclamation-circle', 'text-yellow-700 dark:text-yellow-400'],
    ['Susulan selesai', $perStatus['selesai'], 'Sudah dilaksanakan', 'check-circle', 'text-green-700 dark:text-green-400'],
];
$kosongTakHadir = ui_kosong(['ikon' => 'check-circle', 'judul' => 'Belum ada catatan ketidakhadiran']);
?>

<div class="space-y-4">
    <!-- Tombol cetak -->
    <div class="kartu kartu-isi flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Rekap periode</h2>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Angka di layar ini sama persis dengan isi berkas cetaknya.</p>
        </div>
        <a href="<?= $base ?>/laporan/pdf<?= $qtp ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('printer', 'h-4 w-4') ?>Rekap PDF</a>
        <a href="<?= $base ?>/laporan/excel<?= $qtp ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Rekap Excel</a>
    </div>

    <!-- Kartu ringkasan -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php foreach ($kartu as [$judul, $angka, $ket, $ik, $warna]): ?>
            <div class="kartu kartu-isi">
                <p class="stat-label flex items-center gap-2 text-sm"><?= ikon($ik, 'h-4 w-4 shrink-0') ?><?= esc($judul) ?></p>
                <p class="stat-angka mt-2 tabular-nums <?= $warna ?>"><?= number_format((int) $angka, 0, ',', '.') ?></p>
                <p class="stat-ket"><?= esc($ket) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <!-- Status susulan -->
        <section class="kartu overflow-hidden" aria-labelledby="judul-status-susulan">
            <div class="kartu-kepala"><h3 id="judul-status-susulan" class="kartu-judul">Status ujian susulan</h3></div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <tbody>
                        <?php foreach ($labelStatusSusulan as $k => $lbl): ?>
                            <tr>
                                <th scope="row" class="font-normal"><?= esc($lbl) ?></th>
                                <td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white"><?= (int) ($perStatus[$k] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Alasan -->
        <section class="kartu overflow-hidden" aria-labelledby="judul-alasan">
            <div class="kartu-kepala"><h3 id="judul-alasan" class="kartu-judul">Alasan ketidakhadiran</h3></div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <tbody>
                        <?php foreach ($labelAlasan as $k => $lbl): ?>
                            <tr>
                                <th scope="row" class="font-normal"><?= esc($lbl) ?></th>
                                <td class="text-right font-semibold text-gray-900 tabular-nums dark:text-white"><?= (int) ($perAlasan[$k] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Rekap per kelas -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-per-kelas">
        <div class="kartu-kepala"><h3 id="judul-per-kelas" class="kartu-judul">Rekap per kelas</h3></div>
        <?php if ($perKelas === []): ?>
            <?= $kosongTakHadir ?>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col">Kelas</th>
                            <th scope="col">Tingkat</th>
                            <th scope="col" class="text-center">Tidak hadir</th>
                            <th scope="col" class="text-center">Belum</th>
                            <th scope="col" class="text-center">Dijadwalkan</th>
                            <th scope="col" class="text-center">Selesai</th>
                            <th scope="col" class="text-center">Batal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perKelas as $r): ?>
                            <tr>
                                <td class="font-semibold text-gray-900 dark:text-white"><?= esc($r['nama_kelas'] ?? '—') ?></td>
                                <td><?= esc($r['tingkat'] ?? '—') ?></td>
                                <td class="text-center font-semibold text-yellow-700 dark:text-yellow-400"><?= (int) $r['total'] ?></td>
                                <td class="text-center"><?= (int) $r['belum'] ?></td>
                                <td class="text-center"><?= (int) $r['dijadwalkan'] ?></td>
                                <td class="text-center text-green-700 dark:text-green-400"><?= (int) $r['selesai'] ?></td>
                                <td class="text-center text-gray-500 dark:text-gray-400"><?= (int) $r['batal'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Rekap per mapel -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-per-mapel">
        <div class="kartu-kepala"><h3 id="judul-per-mapel" class="kartu-judul">Rekap per mata pelajaran</h3></div>
        <?php if ($perMapel === []): ?>
            <?= $kosongTakHadir ?>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col">Mata pelajaran</th>
                            <th scope="col" class="text-center">Tidak hadir</th>
                            <th scope="col" class="text-center">Belum</th>
                            <th scope="col" class="text-center">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perMapel as $r): ?>
                            <tr>
                                <td>
                                    <span class="font-semibold text-gray-900 dark:text-white"><?= esc($r['nama_mapel'] ?? '—') ?></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?= $r['kode_mapel'] ? ' · ' . esc($r['kode_mapel']) : '' ?></span>
                                </td>
                                <td class="text-center font-semibold text-yellow-700 dark:text-yellow-400"><?= (int) $r['total'] ?></td>
                                <td class="text-center"><?= (int) $r['belum'] ?></td>
                                <td class="text-center text-green-700 dark:text-green-400"><?= (int) $r['selesai'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($siswaTerbanyak !== []): ?>
        <section class="kartu overflow-hidden" aria-labelledby="judul-perhatian">
            <div class="kartu-kepala">
                <div>
                    <h3 id="judul-perhatian" class="kartu-judul">Perlu perhatian</h3>
                    <p class="kartu-ket">Siswa yang tidak hadir pada lebih dari satu mata pelajaran.</p>
                </div>
            </div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col">Siswa</th>
                            <th scope="col">Kelas</th>
                            <th scope="col" class="text-center">Jumlah mapel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($siswaTerbanyak as $r): ?>
                            <tr>
                                <td>
                                    <p class="font-semibold text-gray-900 dark:text-white"><?= esc($r['nama'] ?? '—') ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">NIS <?= esc($r['nis'] ?? '—') ?></p>
                                </td>
                                <td><?= esc($r['nama_kelas'] ?? '—') ?></td>
                                <td class="text-center"><span class="lencana lencana-kuning"><?= (int) $r['total'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
