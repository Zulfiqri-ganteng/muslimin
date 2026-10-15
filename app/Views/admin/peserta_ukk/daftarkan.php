<?php
/**
 * Daftarkan peserta UKK: pilih paket soal + kelas → checklist siswa. Rombak tampilan F8: Kembali di kepala halaman, kartu kit
 * per langkah, daftar centang dengan komponen kit "daftarCentang" (dulu objek sebaris + @click berisi $el & larik id),
 * ui_kosong, tombol Daftarkan ber-ikon → siap CSP.
 *
 * @var int                $paketId
 * @var int                $kelasId
 * @var array|null         $paket
 * @var array              $siswaTersedia
 * @var int                $sudahTerdaftarCount
 * @var array<int,string>  $paketOpts
 * @var array<int,string>  $kelasOpts
 * @var array<int,string>  $jadwalOpts
 */
$base = site_url('admin/peserta-ukk/daftarkan');
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/peserta-ukk') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke daftar peserta</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'peserta_ukk_daftarkan',
    'helpTitle' => 'Daftarkan Peserta UKK',
    'helpBody'  => '<p>Pilih <b>Paket Soal</b> dan <b>Kelas</b> terlebih dahulu. Siswa yang sudah terdaftar pada paket
        soal tersebut otomatis disembunyikan dari daftar. Centang siswa yang ikut, lalu klik Daftarkan.</p>',
]) ?>

<div class="space-y-4">
    <!-- Langkah 1: pilih paket soal + kelas -->
    <section class="kartu" aria-labelledby="judul-langkah-1">
        <div class="kartu-kepala"><h2 id="judul-langkah-1" class="kartu-judul">1. Pilih paket soal &amp; kelas</h2></div>
        <form method="get" action="<?= $base ?>" class="kartu-isi grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="du-paket">Paket soal <span class="text-red-600">*</span></label>
                <select id="du-paket" name="paket_soal_id" data-autosubmit required class="inp">
                    <option value="">— Pilih paket soal —</option>
                    <?php foreach ($paketOpts as $id => $lbl): ?>
                        <option value="<?= (int) $id ?>"<?= $paketId === (int) $id ? ' selected' : '' ?>><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="du-kelas">Kelas <span class="text-red-600">*</span></label>
                <select id="du-kelas" name="kelas_id" data-autosubmit required class="inp">
                    <option value="">— Pilih kelas —</option>
                    <?php foreach ($kelasOpts as $id => $lbl): ?>
                        <option value="<?= (int) $id ?>"<?= $kelasId === (int) $id ? ' selected' : '' ?>><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </section>

    <?php if ($paketId > 0 && $kelasId > 0): ?>
        <?php if (! $paket): ?>
            <div class="kartu"><?= ui_kosong(['ikon' => 'file-search', 'judul' => 'Paket soal tidak ditemukan']) ?></div>
        <?php else: ?>
            <!-- Langkah 2: checklist siswa -->
            <section class="kartu" aria-labelledby="judul-langkah-2">
                <div class="kartu-kepala">
                    <h2 id="judul-langkah-2" class="kartu-judul">2. Pilih siswa</h2>
                    <?php if ($sudahTerdaftarCount > 0): ?>
                        <span class="lencana lencana-abu"><?= (int) $sudahTerdaftarCount ?> siswa di paket ini sudah terdaftar (disembunyikan)</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($siswaTersedia)): ?>
                    <?= ui_kosong(['ikon' => 'users', 'judul' => 'Tidak ada siswa yang bisa didaftarkan', 'teks' => 'Semua siswa aktif di kelas ini sudah terdaftar pada paket soal ini, atau kelas belum punya siswa aktif.']) ?>
                <?php else: ?>
                    <form method="post" action="<?= site_url('admin/peserta-ukk/daftarkan') ?>" x-data="daftarCentang" data-satuan="siswa dipilih" @change="hitungCentang()">
                        <?= csrf_field() ?>
                        <input type="hidden" name="paket_soal_id" value="<?= (int) $paketId ?>">

                        <div class="kartu-isi space-y-4">
                            <?php if (! empty($jadwalOpts)): ?>
                                <div class="max-w-sm">
                                    <label class="lbl" for="du-jadwal">Jadwal UKK (opsional)</label>
                                    <select id="du-jadwal" name="jadwal_ukk_id" class="inp">
                                        <option value="">— Belum ditentukan —</option>
                                        <?php foreach ($jadwalOpts as $id => $lbl): ?>
                                            <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                <label class="flex cursor-pointer items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-700 dark:text-white">
                                    <input type="checkbox" class="cek" @change="pilihSemuaCentang($event)">
                                    Pilih semua (<?= count($siswaTersedia) ?> siswa)
                                </label>
                                <div class="panel-gulir max-h-96 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($siswaTersedia as $s): ?>
                                        <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/60">
                                            <input type="checkbox" name="siswa_ids[]" value="<?= (int) $s['id'] ?>" data-centang class="cek">
                                            <span class="flex-1">
                                                <span class="font-medium text-gray-900 dark:text-white"><?= esc($s['nama']) ?></span>
                                                <span class="ms-1 text-xs text-gray-500 dark:text-gray-400"><?= esc($s['nis']) ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="kartu-kaki flex flex-wrap items-center justify-between gap-3">
                            <span class="lencana lencana-merek py-1" x-text="teksCentang()">0 siswa dipilih</span>
                            <button type="submit" class="btn btn-utama" :disabled="belumDicentang()"><?= ikon('user-graduate', 'h-4 w-4') ?>Daftarkan</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
