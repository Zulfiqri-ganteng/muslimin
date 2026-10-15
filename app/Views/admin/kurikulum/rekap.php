<?php
/**
 * Rekap Beban Mengajar per guru (rombak tampilan F6: kartu + tabel kit). Lencana beban: Lebih (merah) / Kurang (kuning) / Pas (hijau)
 * terhadap Maks beban guru. Export Excel/PDF di kepala halaman; per guru: jadwal PDF & Excel.
 *
 * @var array $grouped Daftar guru: guru_id, kode_guru, guru_nama, total, max_beban, items[mapel, kelas, jp]
 */
?>
<?= $this->extend('layouts/admin') ?>

<?php if (! empty($grouped)): ?>
    <?= $this->section('aksi') ?>
    <a href="<?= site_url('admin/cetak/rekap/excel') ?>" class="btn btn-netral" title="Excel dengan rumus yang tetap hidup"><?= ikon('download', 'h-4 w-4') ?>Export Excel</a>
    <a href="<?= site_url('admin/cetak/rekap/pdf') ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('printer', 'h-4 w-4') ?>Export PDF</a>
    <?= $this->endSection() ?>
<?php endif; ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'rekap',
    'helpTitle' => 'Rekap Beban Mengajar',
    'helpBody'  => '<p>Total jam mengajar (JP) tiap guru berdasarkan <b>Penugasan</b>. Lencana menunjukkan apakah beban <b>kurang</b>, <b>pas</b>, atau <b>lebih</b> dari Maks beban guru. Export Excel (rumus hidup) / PDF ada di kanan atas; jadwal per guru lewat tombol PDF/Excel di tiap kartu.</p>',
]) ?>

<?php if (empty($grouped)): ?>
    <div class="kartu">
        <?= ui_kosong(['ikon' => 'chalkboard-user', 'judul' => 'Belum ada penugasan', 'teks' => 'Atur penugasan mengajar terlebih dahulu.',
            'aksi' => '<a href="' . site_url('admin/master/pengampu') . '" class="btn btn-utama">' . ikon('chalkboard-user', 'h-4 w-4') . 'Buka Penugasan</a>']) ?>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($grouped as $g):
            $total = (int) $g['total'];
            $max   = (int) $g['max_beban'];
            [$lencana, $stat] = $total > $max ? ['lencana-merah', 'Lebih'] : ($total < $max ? ['lencana-kuning', 'Kurang'] : ['lencana-hijau', 'Pas']);
        ?>
            <section class="kartu" aria-label="<?= esc($g['kode_guru'] . ' ' . $g['guru_nama'], 'attr') ?>">
                <div class="kartu-kepala">
                    <p class="min-w-0 truncate text-base font-semibold text-gray-900 dark:text-white"><span class="font-normal text-gray-500 dark:text-gray-400"><?= esc($g['kode_guru']) ?></span> · <?= esc($g['guru_nama']) ?></p>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total <b class="text-gray-900 dark:text-white"><?= $total ?></b> / <?= $max ?> JP</span>
                        <span class="lencana <?= $lencana ?>"><?= $stat ?></span>
                        <a href="<?= site_url('admin/cetak/jadwal-guru/' . $g['guru_id'] . '/pdf') ?>" target="_blank" rel="noopener" class="btn btn-netral btn-xs" title="Jadwal guru (PDF)"><?= ikon('printer', 'h-3.5 w-3.5') ?>PDF</a>
                        <a href="<?= site_url('admin/cetak/jadwal-guru/' . $g['guru_id'] . '/excel') ?>" class="btn btn-netral btn-xs" title="Jadwal guru (Excel)"><?= ikon('download', 'h-3.5 w-3.5') ?>Excel</a>
                    </div>
                </div>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th scope="col">Mata pelajaran</th><th scope="col" class="w-40">Kelas</th><th scope="col" class="w-24 text-right">JP</th></tr></thead>
                        <tbody>
                            <?php foreach ($g['items'] as $it): ?>
                                <tr><th scope="row"><?= esc($it['mapel']) ?></th><td><?= esc($it['kelas']) ?></td><td class="text-right font-semibold text-gray-900 dark:text-white"><?= (int) $it['jp'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
