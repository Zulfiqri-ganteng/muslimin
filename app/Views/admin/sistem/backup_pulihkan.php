<?php
/**
 * Kemajuan restore (docs/DESAIN-ALAT-SISTEM.md F4). Selama tahap siap/jalan, assets/js/admin/pulihkan.js memanggil POST …/langkah
 * berulang (±8 detik per langkah) dan memperbarui bilah; sesudah selesai/gagal halaman dimuat ulang (hasil dirender server).
 *
 * @var array $t           tugas PulihkanBackup
 * @var int   $persen
 * @var bool  $maintenance
 */
use App\Controllers\Admin\Backup;

$jalan = in_array($t['tahap'], ['siap', 'jalan'], true);
$v     = static fn (string $b): string => base_url($b) . '?v=' . @filemtime(FCPATH . $b);
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<section class="kartu max-w-2xl" aria-labelledby="judul-kemajuan"
    <?php if ($jalan): ?>data-pulihkan data-url="<?= esc(site_url('admin/sistem/backup/pulihkan/' . $t['id'] . '/langkah'), 'attr') ?>"<?php endif; ?>>
    <div class="kartu-kepala">
        <h2 id="judul-kemajuan" class="kartu-judul">Pulihkan dari <?= esc($t['sumber']) ?></h2>
        <p class="kartu-ket">Dimulai <?= esc(date('d-m-Y H:i', strtotime($t['mulai']))) ?> oleh <?= esc($t['oleh']) ?></p>
    </div>
    <div class="kartu-isi space-y-4">
        <?php if ($jalan): ?>
            <?= ui_peringatan('awas', 'Jangan tutup halaman ini sampai selesai. Bila koneksi terputus, buka lagi halaman ini — restore melanjutkan dari posisi terakhir.', ['judul' => 'Sedang memulihkan']) ?>
            <div>
                <progress class="h-3 w-full" max="100" value="<?= (int) $persen ?>" data-bilah aria-label="Kemajuan restore"><?= (int) $persen ?>%</progress>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300" data-teks role="status" aria-live="polite"><?= esc(Backup::teksTahap($t)) ?> (<?= (int) $persen ?>%)</p>
            </div>
        <?php elseif ($t['tahap'] === 'selesai'): ?>
            <?= ui_peringatan('sukses', array_merge(['Database berhasil dipulihkan: ' . number_format($t['perintah'], 0, ',', '.') . ' perintah dijalankan.'], $t['catatan']), ['judul' => 'Restore selesai']) ?>
            <?php if ($maintenance): ?>
                <p class="text-sm text-gray-700 dark:text-gray-300">Periksa beberapa data penting, lalu <b>matikan Mode Maintenance</b> agar situs dan aplikasi dibuka kembali.</p>
                <a href="<?= site_url('admin/sistem/maintenance') ?>" class="btn btn-sukses"><?= ikon('tools', 'h-4 w-4') ?>Ke Mode Maintenance</a>
            <?php endif; ?>
        <?php else: ?>
            <?= ui_peringatan('galat', array_merge([(string) $t['galat']], $t['catatan']), ['judul' => 'Restore gagal']) ?>
            <?php if ($t['cadangan']): ?>
                <p class="text-sm text-gray-700 dark:text-gray-300">Database mungkin hanya terpulihkan sebagian. Kembalikan ke keadaan sebelum restore dengan backup otomatis yang dibuat tadi:</p>
                <a href="<?= site_url('admin/sistem/backup/pulihkan') . '?kunci=' . rawurlencode($t['cadangan']) ?>" class="btn btn-bahaya"><?= ikon('clock-arrow', 'h-4 w-4') ?>Pulihkan backup sebelum restore</a>
            <?php endif; ?>
        <?php endif; ?>
        <div><a href="<?= site_url('admin/sistem/backup') ?>" class="btn btn-netral">Kembali ke Backup & Restore</a></div>
    </div>
</section>

<?= $this->endSection() ?>

<?php if ($jalan): ?>
<?= $this->section('scripts') ?>
<script defer src="<?= $v('assets/js/admin/pulihkan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
<?php endif; ?>
