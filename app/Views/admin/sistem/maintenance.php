<?php
/**
 * Sistem → Mode Maintenance (docs/DESAIN-ALAT-SISTEM.md F2). Status + akibatnya, formulir pesan & perkiraan selesai, tombol
 * Aktifkan / Perbarui / Matikan (dialog konfirmasi kit), pratinjau halaman pengunjung. Tanpa JS halaman.
 *
 * @var array $s ModeMaintenance::status()
 */
use App\Libraries\ModeMaintenance;

$aktif   = $s['aktif'];
$pesan   = (string) old('pesan', $s['pesan']);
$selesai = (string) old('selesai', $s['selesai'] !== null ? str_replace(' ', 'T', $s['selesai']) : '');
$tgl     = static fn (?string $w): string => $w ? date('d-m-Y H:i', strtotime($w)) : '—';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/sistem/maintenance/pratinjau') ?>" target="_blank" rel="noopener" class="btn btn-netral"><?= ikon('eye', 'h-4 w-4') ?>Lihat tampilan pengunjung</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'maintenance',
    'helpTitle' => 'Mode Maintenance',
    'helpBody'  => '<p>Pakai saat sistem sedang diperbaiki (mis. sebelum memulihkan backup). Selama aktif, pengunjung situs, siswa, dan aplikasi Android melihat pesan perawatan; <b>hanya Admin</b> yang tetap bisa bekerja. Jangan lupa mematikannya lagi sesudah selesai.</p>',
]) ?>

<?php if ($aktif): ?>
    <?= ui_peringatan('galat', [
        'Sejak ' . $tgl($s['mulai']) . ' oleh ' . ($s['oleh'] ?: '—') . '.',
        $s['selesai'] !== null ? 'Perkiraan selesai: ' . ModeMaintenance::labelWaktu($s['selesai']) . '.' : 'Tanpa perkiraan selesai.',
    ], ['judul' => 'Mode Maintenance AKTIF']) ?>
<?php else: ?>
    <?= ui_peringatan('sukses', $s['dimatikan'] !== null ? 'Terakhir dimatikan ' . $tgl($s['dimatikan']) . ' oleh ' . ($s['dimatikan_oleh'] ?: '—') . '.' : 'Mode maintenance belum pernah dipakai.',
        ['judul' => 'Situs berjalan normal']) ?>
<?php endif; ?>

<div class="mt-4 grid gap-4 lg:grid-cols-5">
    <section class="kartu lg:col-span-3" aria-labelledby="judul-atur-mm">
        <div class="kartu-kepala">
            <h2 id="judul-atur-mm" class="kartu-judul"><?= $aktif ? 'Ubah pesan perawatan' : 'Aktifkan mode maintenance' ?></h2>
        </div>
        <form method="post" action="<?= site_url('admin/sistem/maintenance/nyalakan') ?>" class="kartu-isi space-y-4"
              <?php if (! $aktif): ?>data-confirm="Situs publik, semua layanan siswa, dan aplikasi Android akan ditutup sementara. Hanya Admin yang tetap bisa bekerja." data-confirm-judul="Aktifkan mode maintenance?" data-confirm-ya="Ya, aktifkan" data-confirm-jenis="bahaya"<?php endif; ?>>
            <?= csrf_field() ?>
            <div>
                <label class="lbl" for="f_pesan">Pesan untuk pengunjung</label>
                <textarea id="f_pesan" name="pesan" rows="3" maxlength="<?= ModeMaintenance::MAKS_PESAN ?>" class="inp"><?= esc($pesan) ?></textarea>
                <p class="hint">Tampil di situs, form siswa, dan aplikasi Android. Paling banyak <?= ModeMaintenance::MAKS_PESAN ?> huruf.</p>
            </div>
            <div>
                <label class="lbl" for="f_selesai">Perkiraan selesai <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
                <input id="f_selesai" type="datetime-local" name="selesai" value="<?= esc($selesai, 'attr') ?>" class="inp max-w-xs">
                <p class="hint">Hanya informasi untuk pengunjung — mode maintenance <b>tidak</b> mati sendiri.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($aktif): ?>
                    <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan perubahan</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-bahaya"><?= ikon('tools', 'h-4 w-4') ?>Aktifkan mode maintenance</button>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($aktif): ?>
            <form method="post" action="<?= site_url('admin/sistem/maintenance/matikan') ?>" class="kartu-kaki"
                  data-confirm="Situs, layanan siswa, dan aplikasi Android dibuka kembali untuk semua orang." data-confirm-judul="Matikan mode maintenance?" data-confirm-ya="Ya, matikan">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sukses"><?= ikon('check-circle', 'h-4 w-4') ?>Matikan mode maintenance</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="kartu lg:col-span-2" aria-labelledby="judul-akibat-mm">
        <div class="kartu-kepala">
            <h2 id="judul-akibat-mm" class="kartu-judul">Selama aktif</h2>
        </div>
        <ul class="kartu-isi list-inside list-disc space-y-1.5 text-sm text-gray-700 dark:text-gray-300">
            <li>Situs publik dan <b>semua layanan siswa</b> (Ajukan PKL, Layanan Siswa, Biodata, form guru) menampilkan halaman perawatan.</li>
            <li>Aplikasi Android menampilkan layar perawatan.</li>
            <li>Staf selain Admin tidak bisa masuk; yang sedang masuk melihat halaman perawatan.</li>
            <li><b>Admin tetap bekerja</b> seperti biasa (web dan aplikasi).</li>
            <li>Notifikasi terjadwal tetap berjalan.</li>
            <li>Darurat (tidak bisa masuk web): di Terminal cPanel jalankan <code class="text-xs">phpm spark maintenance off</code>.</li>
        </ul>
    </section>
</div>

<?= $this->endSection() ?>
