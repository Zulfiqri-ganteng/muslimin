<?php
/**
 * LAYANAN SISWA — formulir Surat Pernyataan Orang Tua PKL (T4). Data orang tua sudah terisi dari data sekolah (atau isian
 * terakhir); siswa memeriksa/melengkapi lalu mengirim → surat tanpa nomor & tanpa ACC, langsung siap dicetak operator.
 * Tanpa JavaScript (POST layanan/surat/pernyataan). Aturan: LayananSiswa::ajukanPernyataan.
 *
 * @var array                 $setting
 * @var string                $kunci  'pernyataan'
 * @var array                 $siswa  LayananSiswa::profil
 * @var array                 $form   LayananSiswa::formPernyataan: ortu{nama,pekerjaan,alamat,hp}, hp_anak, aktif
 * @var string                $pesan
 * @var array<string, string> $galat  galat per kolom
 */

use App\Libraries\LayananSiswa;

[$judul, $ket] = LayananSiswa::SURAT[$kunci];
$aktif   = $form['aktif'];
$dicetak = $aktif !== null && $aktif['cetak_ke'] > 0;
$nilai   = static fn (string $k, string $bawaan): string => (string) (old($k) ?? $bawaan);
$err     = static fn (string $k): string => isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
$maks    = LayananSiswa::MAKS_ORTU;
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => $judul, 'keterangan' => $ket]) ?>

    <?php if ($pesan !== ''): ?>
        <div class="peringatan peringatan-galat mt-4" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($pesan) ?></p></div>
    <?php endif; ?>
    <?php if ($aktif !== null): ?>
        <div class="peringatan <?= $dicetak ? 'peringatan-awas' : 'peringatan-info' ?> mt-4">
            <?= ikon('info-circle') ?>
            <p><?= $dicetak
                ? 'Surat pernyataanmu (' . esc($aktif['kode']) . ') sudah dicetak sekolah. Bila datanya salah, minta operator sekolah membetulkannya.'
                : 'Kamu sudah meminta surat ini (' . esc($aktif['kode']) . '). Kamu masih bisa memperbaiki data orang tua di bawah sebelum dicetak sekolah.' ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('layanan/surat/pernyataan') ?>" class="kartu-langkah mt-4">
        <?= csrf_field() ?>
        <div class="kartu-langkah-kepala"><span class="kartu-langkah-no">1</span><h2 class="kartu-langkah-judul">Data orang tua / wali</h2></div>
        <div class="kartu-langkah-isi space-y-3">
            <p class="text-sm text-teks-2">Sudah diisi dari data sekolah. Periksa lalu betulkan bila ada yang salah atau kosong — data ini <b>dicetak di surat</b>.</p>
            <div>
                <label class="lbl" for="nama">Nama orang tua / wali <span class="tanda-wajib">*</span></label>
                <input id="nama" name="nama" type="text" maxlength="<?= $maks['nama'] ?>" class="inp" value="<?= esc($nilai('nama', $form['ortu']['nama']), 'attr') ?>" autocomplete="off">
                <?= $err('nama') ?>
            </div>
            <div>
                <label class="lbl" for="pekerjaan">Pekerjaan</label>
                <input id="pekerjaan" name="pekerjaan" type="text" maxlength="<?= $maks['pekerjaan'] ?>" class="inp" value="<?= esc($nilai('pekerjaan', $form['ortu']['pekerjaan']), 'attr') ?>" placeholder="Contoh: Karyawan swasta">
                <?= $err('pekerjaan') ?>
            </div>
            <div>
                <label class="lbl" for="alamat">Alamat <span class="tanda-wajib">*</span></label>
                <textarea id="alamat" name="alamat" rows="3" maxlength="<?= $maks['alamat'] ?>" class="inp"><?= esc($nilai('alamat', $form['ortu']['alamat'])) ?></textarea>
                <?= $err('alamat') ?>
            </div>
            <div>
                <label class="lbl" for="hp">No. HP orang tua</label>
                <input id="hp" name="hp" type="tel" inputmode="tel" maxlength="20" class="inp" value="<?= esc($nilai('hp', $form['ortu']['hp']), 'attr') ?>" placeholder="Contoh: 081234567890">
                <?= $err('hp') ?>
            </div>
        </div>

        <div class="kartu-langkah-kepala border-t border-garis"><span class="kartu-langkah-no">2</span><h2 class="kartu-langkah-judul">Data kamu</h2></div>
        <div class="kartu-langkah-isi space-y-3">
            <p class="text-sm text-teks"><b><?= esc($siswa['nama']) ?></b><?= $siswa['nama_kelas'] ? ' · Kelas ' . esc($siswa['nama_kelas']) : '' ?></p>
            <div>
                <label class="lbl" for="hp_anak">No. HP / WhatsApp kamu</label>
                <input id="hp_anak" name="hp_anak" type="tel" inputmode="tel" maxlength="20" class="inp" value="<?= esc($nilai('hp_anak', $form['hp_anak']), 'attr') ?>" placeholder="Contoh: 081234567890">
                <?= $err('hp_anak') ?>
            </div>
            <p class="text-sm text-teks-2">Surat ini tanpa ACC: setelah dikirim langsung siap dicetak sekolah. Ambil di Tata Usaha, lalu minta orang tua menandatanganinya di atas <b>materai Rp10.000</b>.</p>
        </div>

        <div class="kartu-langkah-isi border-t border-garis">
            <?php if ($dicetak): ?>
                <a href="<?= site_url('layanan/saya') ?>#status" class="tombol tombol-netral tombol-besar tombol-blok">Lihat status suratku</a>
            <?php else: ?>
                <button type="submit" class="tombol tombol-utama tombol-besar tombol-blok"><?= ikon('check') ?><?= $aktif !== null ? 'Simpan perbaikan' : 'Ajukan surat' ?></button>
            <?php endif; ?>
        </div>
    </form>

    <p class="mt-4 text-center text-sm"><a href="<?= site_url('layanan/saya') ?>" class="font-semibold text-brand-700 hover:underline">Kembali</a></p>
</div>
<?= $this->endSection() ?>
