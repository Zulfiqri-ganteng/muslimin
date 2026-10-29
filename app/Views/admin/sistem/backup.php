<?php
/**
 * Sistem → Backup & Restore (docs/DESAIN-ALAT-SISTEM.md F3 & F4). Buat backup (database / berkas unggahan), daftar backup
 * (unduh = halaman sandi, pulihkan = halaman konfirmasi, hapus = form + dialog kit), pulihkan dari berkas komputer. Tanpa JS halaman.
 *
 * @var list<array> $daftar      BackupSistem::daftar()
 * @var int         $total       ukuran semua backup (byte)
 * @var array|null  $terakhir    backup database terbaru (BackupSistem::terakhirDb) — backup dibuat MANUAL, tanpa cron
 * @var bool        $maintenance Mode Maintenance aktif
 */
use App\Controllers\Admin\Backup;
use App\Libraries\BackupSistem;
use App\Libraries\PulihkanBackup;

$tgl  = static fn (string $w): string => $w !== '' ? date('d-m-Y H:i', strtotime($w)) : '—';
$hari = BackupSistem::hariSejakTerakhir();
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'backup',
    'helpTitle' => 'Backup & Restore',
    'helpBody'  => '<p><b>Backup</b> = salinan cadangan. <b>Database</b> berisi semua data (siswa, guru, PKL, surat, akun); <b>berkas unggahan</b> berisi foto, logo, arsip dokumen, dan tanda tangan. Backup disimpan di server (tidak bisa dibuka dari internet) dan bisa diunduh ke komputer. <b>Restore</b> = mengembalikan database ke isi backup — semua data sesudah backup itu hilang, jadi sistem selalu membuat backup data saat ini lebih dulu.</p>',
]) ?>

<div class="grid gap-4 lg:grid-cols-2">
    <section class="kartu" aria-labelledby="judul-buat-backup">
        <div class="kartu-kepala">
            <h2 id="judul-buat-backup" class="kartu-judul">Buat backup sekarang</h2>
        </div>
        <div class="kartu-isi space-y-3">
            <div class="flex flex-wrap gap-2">
                <form method="post" action="<?= site_url('admin/sistem/backup/buat') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="isi" value="db">
                    <button type="submit" class="btn btn-utama"><?= ikon('archive', 'h-4 w-4') ?>Backup database</button>
                </form>
                <form method="post" action="<?= site_url('admin/sistem/backup/buat') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="isi" value="berkas">
                    <button type="submit" class="btn btn-netral"><?= ikon('folder-open', 'h-4 w-4') ?>Backup berkas unggahan</button>
                </form>
            </div>
            <p class="hint">Disimpan: <?= BackupSistem::JENIS['manual'][1] ?> backup manual terbaru per jenis. Total ukuran semua backup: <?= esc(Backup::ukuran($total)) ?>.</p>
            <?php if ($terakhir === null): ?>
                <?= ui_peringatan('info', 'Tekan "Backup database" untuk membuat yang pertama. Sebaiknya backup sebelum perubahan besar (impor data, restore, kenaikan kelas) dan rutin seminggu sekali.', ['judul' => 'Belum ada backup database']) ?>
            <?php elseif ($hari >= BackupSistem::INGATKAN_HARI): ?>
                <?= ui_peringatan('awas', 'Backup database terakhir ' . $tgl($terakhir['waktu']) . ' (' . $hari . ' hari lalu). Sebaiknya buat backup baru.', ['judul' => 'Backup sudah lama']) ?>
            <?php else: ?>
                <p class="text-sm text-gray-700 dark:text-gray-300">Backup database terakhir: <b><?= esc($tgl($terakhir['waktu'])) ?></b> (<?= $hari === 0 ? 'hari ini' : $hari . ' hari lalu' ?>).</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="kartu" id="pulihkan-unggah" aria-labelledby="judul-pulihkan-unggah">
        <div class="kartu-kepala">
            <h2 id="judul-pulihkan-unggah" class="kartu-judul">Pulihkan dari berkas komputer</h2>
        </div>
        <?php if (! $maintenance): ?>
            <div class="kartu-isi">
                <?= ui_peringatan('info', 'Restore hanya bisa saat Mode Maintenance aktif, supaya tidak ada yang mengubah data selama proses.', ['judul' => 'Aktifkan Mode Maintenance dulu',
                    'html' => 'Restore hanya bisa saat Mode Maintenance aktif, supaya tidak ada yang mengubah data selama proses. <a href="' . site_url('admin/sistem/maintenance') . '" class="font-semibold underline">Buka Mode Maintenance</a>']) ?>
            </div>
        <?php else: ?>
            <form method="post" action="<?= site_url('admin/sistem/backup/pulihkan') ?>" enctype="multipart/form-data" class="kartu-isi space-y-3"
                  data-confirm="SEMUA data database sekarang diganti isi berkas ini. Data sekarang dibackup otomatis lebih dulu." data-confirm-judul="Pulihkan database?" data-confirm-ya="Ya, pulihkan" data-confirm-jenis="bahaya">
                <?= csrf_field() ?>
                <div>
                    <label class="lbl" for="f_berkas">Berkas backup (.sql atau .sql.gz)</label>
                    <input id="f_berkas" type="file" name="berkas" accept=".sql,.gz" required class="inp">
                    <p class="hint">Backup dari halaman ini, atau ekspor phpMyAdmin database aplikasi ini.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="lbl" for="f_sandi_u">Sandi Anda</label>
                        <input id="f_sandi_u" type="password" name="sandi" required autocomplete="current-password" class="inp">
                    </div>
                    <div>
                        <label class="lbl" for="f_kata_u">Ketik <?= PulihkanBackup::KATA ?></label>
                        <input id="f_kata_u" type="text" name="kata" required autocomplete="off" class="inp">
                    </div>
                </div>
                <button type="submit" class="btn btn-bahaya"><?= ikon('upload', 'h-4 w-4') ?>Pulihkan dari berkas ini</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="kartu mt-4 overflow-hidden" aria-labelledby="judul-daftar-backup">
    <div class="kartu-kepala">
        <h2 id="judul-daftar-backup" class="kartu-judul flex items-center gap-2">Daftar backup<span class="lencana lencana-abu"><?= count($daftar) ?></span></h2>
    </div>
    <?php if ($daftar === []): ?>
        <?= ui_kosong(['ikon' => 'archive', 'judul' => 'Belum ada backup', 'teks' => 'Tekan "Backup database" untuk membuat yang pertama.']) ?>
    <?php else: ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Isi</th>
                        <th scope="col">Jenis</th>
                        <th scope="col" class="text-end">Ukuran</th>
                        <th scope="col">Keterangan</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $b): ?>
                        <tr>
                            <td class="text-xs whitespace-nowrap tabular-nums"><?= esc($tgl($b['waktu'])) ?></td>
                            <td><span class="lencana <?= $b['isi'] === 'db' ? 'lencana-merek' : 'lencana-biru' ?>"><?= $b['isi'] === 'db' ? 'Database' : 'Berkas unggahan' ?></span></td>
                            <td class="text-sm"><?= esc($b['label_jenis']) ?></td>
                            <td class="text-end text-xs whitespace-nowrap tabular-nums"><?= esc(Backup::ukuran($b['ukuran'])) ?></td>
                            <td class="text-xs text-gray-600 dark:text-gray-400"><?= $b['tabel'] !== null ? (int) $b['tabel'] . ' tabel' : '' ?><?= $b['oleh'] ? ' · oleh ' . esc($b['oleh']) : '' ?></td>
                            <td class="whitespace-nowrap text-end">
                                <a href="<?= site_url('admin/sistem/backup/unduh') . '?kunci=' . rawurlencode($b['kunci']) ?>" class="btn btn-teks btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh</a>
                                <?php if ($b['isi'] === 'db'): ?>
                                    <a href="<?= site_url('admin/sistem/backup/pulihkan') . '?kunci=' . rawurlencode($b['kunci']) ?>" class="btn btn-teks btn-sm"><?= ikon('clock-arrow', 'h-4 w-4') ?>Pulihkan</a>
                                <?php endif; ?>
                                <form method="post" action="<?= site_url('admin/sistem/backup/hapus') ?>" class="inline" data-confirm="Hapus backup <?= esc($tgl($b['waktu']), 'attr') ?>? Tidak bisa dibatalkan." data-confirm-judul="Hapus backup?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="kunci" value="<?= esc($b['kunci'], 'attr') ?>">
                                    <button type="submit" class="btn btn-teks btn-sm text-red-700 dark:text-red-400" aria-label="Hapus backup <?= esc($tgl($b['waktu']), 'attr') ?>"><?= ikon('trash-bin', 'h-4 w-4') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?= $this->endSection() ?>
