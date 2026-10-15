<?php
/**
 * Pintu masuk Penilaian UKK: pilih jadwal, lihat progres nilai. Rombak tampilan F8: kartu + tabel kit, bilah progres
 * <progress class="progres"> (tanpa style=), tombol Nilai ber-ikon, ui_kosong.
 *
 * @var array             $rows             Semua jadwal_ukk::withRelations()
 * @var array<int,int>    $totalPerJadwal   jadwal_ukk_id => jumlah peserta
 * @var array<int,int>    $dinilaiPerJadwal jadwal_ukk_id => jumlah sudah dinilai
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'penilaian_ukk',
    'helpTitle' => 'Penilaian UKK',
    'helpBody'  => '<p>Pilih jadwal untuk menilai peserta pada jadwal tersebut. Nilai diisi <b>per penguji</b>
        (internal & eksternal masing-masing punya kolom sendiri) — nilai akhir peserta otomatis jadi rata-rata
        seluruh penguji setelah minimal satu penguji mengisi nilai.</p>',
]) ?>

<section class="kartu overflow-hidden" aria-labelledby="judul-pilih-jadwal">
    <div class="kartu-kepala"><h2 id="judul-pilih-jadwal" class="kartu-judul">Pilih jadwal untuk dinilai</h2></div>
    <?php if (empty($rows)): ?>
        <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada jadwal UKK', 'teks' => 'Buat dulu di menu Jadwal UKK.',
            'aksi' => '<a href="' . site_url('admin/jadwal-ukk') . '" class="btn btn-netral">' . ikon('calendar-plus', 'h-4 w-4') . 'Buka Jadwal UKK</a>']) ?>
    <?php else: ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr>
                        <th scope="col">Paket soal</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Tempat uji</th>
                        <th scope="col" class="w-56">Progres nilai</th>
                        <th scope="col" class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $total   = $totalPerJadwal[$r['id']] ?? 0;
                        $dinilai = $dinilaiPerJadwal[$r['id']] ?? 0;
                        $pct     = $total > 0 ? (int) round($dinilai / $total * 100) : 0; ?>
                        <tr>
                            <td>
                                <p class="font-semibold text-gray-900 dark:text-white"><?= esc($r['paket_kode'] ?? '—') ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($r['paket_nama'] ?? '—') ?></p>
                            </td>
                            <td class="whitespace-nowrap"><?= $tgl($r['tanggal_mulai']) ?></td>
                            <td><?= esc($r['tempat_nama'] ?? '—') ?></td>
                            <td>
                                <?php if ($total === 0): ?>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Belum ada peserta</span>
                                <?php else: ?>
                                    <div class="flex items-center gap-2">
                                        <progress class="progres progres-hijau h-2 flex-1" value="<?= $pct ?>" max="100" aria-label="<?= $dinilai ?> dari <?= $total ?> peserta sudah dinilai"><?= $pct ?>%</progress>
                                        <span class="w-16 text-right text-xs text-gray-500 tabular-nums dark:text-gray-400"><?= $dinilai ?>/<?= $total ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <a href="<?= site_url('admin/penilaian-ukk/jadwal/' . (int) $r['id']) ?>" class="btn btn-utama btn-sm"><?= ikon('pen-nib', 'h-4 w-4') ?>Nilai</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?= $this->endSection() ?>
