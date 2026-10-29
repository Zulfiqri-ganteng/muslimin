<?php
/**
 * Konfirmasi Backup & Restore (docs/DESAIN-ALAT-SISTEM.md F3/F4): mode 'unduh' (ketik sandi — isi backup = data pribadi) atau
 * 'pulihkan' (Mode Maintenance wajib aktif + sandi + ketik PULIHKAN).
 *
 * @var string $mode        unduh | pulihkan
 * @var array  $b           satu baris BackupSistem::daftar()
 * @var bool   $maintenance Mode Maintenance aktif
 */
use App\Controllers\Admin\Backup;
use App\Libraries\PulihkanBackup;

$pulihkan = $mode === 'pulihkan';
$waktu    = $b['waktu'] !== '' ? date('d-m-Y H:i', strtotime($b['waktu'])) : '—';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<section class="kartu max-w-2xl" aria-labelledby="judul-konfirmasi-backup">
    <div class="kartu-kepala">
        <h2 id="judul-konfirmasi-backup" class="kartu-judul"><?= $pulihkan ? 'Pulihkan database dari backup ini?' : 'Unduh backup' ?></h2>
    </div>
    <div class="kartu-isi space-y-4">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500 dark:text-gray-400">Dibuat</dt><dd class="font-medium text-gray-900 dark:text-white"><?= esc($waktu) ?></dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Isi · jenis</dt><dd><?= $b['isi'] === 'db' ? 'Database' : 'Berkas unggahan' ?> · <?= esc($b['label_jenis']) ?></dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Ukuran</dt><dd><?= esc(Backup::ukuran($b['ukuran'])) ?><?= $b['tabel'] !== null ? ' · ' . (int) $b['tabel'] . ' tabel' : '' ?></dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Oleh</dt><dd><?= esc($b['oleh'] ?: '—') ?></dd></div>
        </dl>

        <?php if ($pulihkan): ?>
            <?= ui_peringatan('galat', [
                'SEMUA data database sekarang diganti isi backup ini. Data yang dibuat sesudah ' . $waktu . ' akan hilang.',
                'Sebelum mulai, sistem otomatis membuat backup data sekarang (jenis "Otomatis sebelum restore") — bisa dipulihkan lagi bila salah pilih.',
                'Proses berjalan bertahap; jangan tutup halaman sampai selesai. Berkas unggahan (foto, dokumen) tidak ikut berubah.',
            ], ['judul' => 'Perhatian']) ?>
            <?php if (! $maintenance): ?>
                <?= ui_peringatan('info', '', ['judul' => 'Aktifkan Mode Maintenance dulu',
                    'html' => 'Restore hanya bisa saat Mode Maintenance aktif. <a href="' . site_url('admin/sistem/maintenance') . '" class="font-semibold underline">Buka Mode Maintenance</a>, lalu kembali ke halaman ini.']) ?>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-gray-700 dark:text-gray-300">Berkas backup berisi data pribadi siswa, guru, dan akun. Simpan di tempat aman dan jangan dibagikan.</p>
        <?php endif; ?>

        <?php if (! $pulihkan || $maintenance): ?>
            <form method="post" action="<?= site_url($pulihkan ? 'admin/sistem/backup/pulihkan' : 'admin/sistem/backup/unduh') ?>" class="space-y-3"
                  <?php if ($pulihkan): ?>data-confirm="SEMUA data database sekarang diganti isi backup <?= esc($waktu, 'attr') ?>." data-confirm-judul="Pulihkan database?" data-confirm-ya="Ya, pulihkan" data-confirm-jenis="bahaya"<?php endif; ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="kunci" value="<?= esc($b['kunci'], 'attr') ?>">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="lbl" for="f_sandi">Sandi Anda</label>
                        <input id="f_sandi" type="password" name="sandi" required autocomplete="current-password" class="inp">
                    </div>
                    <?php if ($pulihkan): ?>
                        <div>
                            <label class="lbl" for="f_kata">Ketik <?= PulihkanBackup::KATA ?></label>
                            <input id="f_kata" type="text" name="kata" required autocomplete="off" class="inp">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if ($pulihkan): ?>
                        <button type="submit" class="btn btn-bahaya"><?= ikon('clock-arrow', 'h-4 w-4') ?>Pulihkan sekarang</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-utama"><?= ikon('download', 'h-4 w-4') ?>Unduh</button>
                    <?php endif; ?>
                    <a href="<?= site_url('admin/sistem/backup') ?>" class="btn btn-netral">Batal</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?= $this->endSection() ?>
