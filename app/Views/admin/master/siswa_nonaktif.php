<?php
/**
 * Nonaktifkan siswa (SiswaStatus): pilih Lulus / Pindah sekolah / Keluar + alasan (opsional, ditambahkan ke keterangan). Tanpa JS.
 *
 * @var array  $d         SiswaDetail::muat()
 * @var string $urlDaftar
 */
use App\Libraries\SiswaStatus;

$s     = $d['siswa'];
$pilih = (string) old('status', $s['status'] !== 'aktif' ? $s['status'] : '');
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<section class="kartu max-w-xl" aria-labelledby="judul-nonaktif">
    <div class="kartu-kepala">
        <h2 id="judul-nonaktif" class="kartu-judul">Nonaktifkan <?= esc($s['nama']) ?></h2>
        <p class="kartu-ket">NIS <?= esc($s['nis']) ?><?= ! empty($s['nama_kelas']) ? ' · ' . esc($s['nama_kelas']) : '' ?></p>
    </div>
    <form method="post" action="<?= site_url('admin/master/siswa/' . (int) $s['id'] . '/nonaktifkan') ?>" class="kartu-isi space-y-4">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="lbl">Status baru</legend>
            <div class="mt-1 space-y-2">
                <?php foreach (SiswaStatus::NONAKTIF as $k => $label): ?>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white">
                        <input type="radio" name="status" value="<?= $k ?>" required class="cek"<?= $pilih === $k ? ' checked' : '' ?>> <?= esc($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div>
            <label class="lbl" for="f_catatan">Alasan / catatan <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
            <input id="f_catatan" type="text" name="catatan" maxlength="120" value="<?= esc((string) old('catatan'), 'attr') ?>" class="inp" placeholder="mis. Pindah ke SMK Negeri 1 Bekasi">
            <p class="hint">Ditambahkan ke kolom Keterangan siswa beserta tanggal hari ini.</p>
        </div>
        <?= ui_peringatan('info', 'Data dan riwayat siswa (PKL, surat, biodata, dll.) tetap tersimpan. Siswa tidak muncul lagi di formulir siswa (Layanan Siswa, Biodata, PKL) dan tidak dihitung sebagai siswa aktif. Bisa diaktifkan kembali kapan saja.') ?>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan status</button>
            <a href="<?= site_url('admin/master/siswa/' . (int) $s['id']) ?>" class="btn btn-netral">Batal</a>
        </div>
    </form>
</section>

<?= $this->endSection() ?>
