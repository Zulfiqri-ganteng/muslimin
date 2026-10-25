<?php
/**
 * LAYANAN SISWA — formulir Surat Izin Ujian (T3). Siswa memilih jadwal yang dibuka sekolah; surat ditujukan ke perusahaan tempat
 * PKL-nya dan memuat semua siswa PKL di perusahaan itu. Tanpa JavaScript: pilihan radio + kirim (POST layanan/surat/{kunci}).
 * Aturan: LayananSiswa::ajukanIzin. Surat Izin TKA (gelombang per siswa) memakai layanan/surat_tka.php.
 *
 * @var array                                   $setting
 * @var string                                  $kunci  izin_ujian | izin_tka
 * @var array                                   $siswa  LayananSiswa::profil
 * @var array{jadwal: list<array>, perusahaan: array|null, teman: list<string>} $form LayananSiswa::formIzin
 * @var string                                  $pesan  galat kiriman sebelumnya
 */

use App\Libraries\LayananSiswa;

[$judul, $ket] = LayananSiswa::SURAT[$kunci];
$jadwal  = $form['jadwal'];
$bisa    = array_values(array_filter($jadwal, static fn (array $j): bool => $j['sudah'] === null));
$pilihan = count($bisa) === 1 ? (int) $bisa[0]['id'] : 0;
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => $judul, 'keterangan' => $ket]) ?>

    <?php if ($pesan !== ''): ?>
        <div class="peringatan peringatan-galat mt-4" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($pesan) ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('layanan/surat/' . $kunci) ?>" class="kartu-langkah mt-4">
        <?= csrf_field() ?>
        <div class="kartu-langkah-kepala"><span class="kartu-langkah-no">1</span><h2 class="kartu-langkah-judul">Pilih jadwal</h2></div>
        <div class="kartu-langkah-isi">
            <fieldset class="space-y-2">
                <legend class="sr-only">Jadwal kegiatan</legend>
                <?php foreach ($jadwal as $j): $sudah = $j['sudah']; ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-garis bg-white px-4 py-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50<?= $sudah !== null ? ' cursor-default opacity-80' : '' ?>">
                        <input type="radio" name="jadwal_id" value="<?= (int) $j['id'] ?>" class="mt-1 h-4 w-4 accent-brand-700"<?= $sudah !== null ? ' disabled' : '' ?><?= (int) $j['id'] === $pilihan ? ' checked' : '' ?> required>
                        <span class="min-w-0">
                            <span class="block font-semibold text-teks"><?= esc($j['label']) ?></span>
                            <span class="block text-sm text-teks-2"><?= esc($j['hari_tanggal']) ?> · <?= esc($j['tempat']) ?></span>
                            <?php if ($sudah !== null): ?>
                                <span class="mt-1 block text-sm font-semibold text-emerald-800">Sudah kamu ajukan (<?= esc($sudah['kode']) ?>). <a href="<?= site_url('layanan/saya') ?>#status" class="underline">Lihat status</a></span>
                            <?php elseif ($j['luar_periode']): ?>
                                <span class="mt-1 block text-sm text-amber-800">Catatan: periode PKL-mu yang tercatat tidak mencakup tanggal ini. Tetap bisa diajukan.</span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        </div>

        <div class="kartu-langkah-kepala border-t border-garis"><span class="kartu-langkah-no">2</span><h2 class="kartu-langkah-judul">Periksa tujuan surat</h2></div>
        <div class="kartu-langkah-isi space-y-3 text-sm">
            <?php if ($form['perusahaan'] !== null): ?>
                <div>
                    <p class="text-teks-2">Surat ditujukan ke tempat PKL-mu:</p>
                    <p class="text-base font-semibold text-teks"><?= esc($form['perusahaan']['nama']) ?></p>
                    <?php if ($form['perusahaan']['alamat'] !== ''): ?><p class="text-teks-2"><?= esc($form['perusahaan']['alamat']) ?></p><?php endif; ?>
                </div>
                <?php if ($form['teman'] !== []): ?>
                    <div class="peringatan peringatan-info">
                        <?= ikon('users') ?>
                        <p>Satu surat untuk satu perusahaan. Teman PKL-mu di sini ikut tercantum, jadi mereka <b>tidak perlu mengajukan lagi</b>: <?= esc(implode(', ', $form['teman'])) ?>.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <p class="text-teks-2">Setelah dikirim, surat menunggu ACC operator. Bila statusnya <b>Siap diambil</b>, ambil di Tata Usaha lalu serahkan ke perusahaan.</p>
        </div>

        <div class="kartu-langkah-isi border-t border-garis">
            <?php if ($bisa === []): ?>
                <p class="text-sm text-teks-2">Semua jadwal yang dibuka sudah kamu ajukan. <a href="<?= site_url('layanan/saya') ?>#status" class="font-semibold text-brand-700 underline">Lihat statusnya</a>.</p>
            <?php else: ?>
                <button type="submit" class="tombol tombol-utama tombol-besar tombol-blok"><?= ikon('check') ?>Ajukan surat</button>
            <?php endif; ?>
        </div>
    </form>

    <p class="mt-4 text-center text-sm"><a href="<?= site_url('layanan/saya') ?>" class="font-semibold text-brand-700 hover:underline">Kembali</a></p>
</div>
<?= $this->endSection() ?>
