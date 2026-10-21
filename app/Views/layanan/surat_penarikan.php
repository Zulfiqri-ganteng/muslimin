<?php
/**
 * LAYANAN SISWA — formulir Surat Penarikan PKL (T4): siswa meminta surat penarikan DIRINYA dari tempat PKL ajuannya — pilih alasan
 * (atau tulis sendiri) + tanggal mulai berlaku → menunggu ACC operator. Status PKL tidak berubah. Tanpa JavaScript
 * (POST layanan/surat/penarikan). Aturan: LayananSiswa::ajukanPenarikan.
 *
 * @var array                 $setting
 * @var string                $kunci 'penarikan'
 * @var array                 $siswa LayananSiswa::profil
 * @var array                 $form  LayananSiswa::formPenarikan: aktif, perusahaan
 * @var string                $pesan
 * @var array<string, string> $galat galat per kolom
 */

use App\Libraries\LayananSiswa;

[$judul, $ket] = LayananSiswa::SURAT[$kunci];
$aktif = $form['aktif'];
$pilih = (string) (old('alasan_pilih') ?? '');
$err   = static fn (string $k): string => isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
$opsi  = LayananSiswa::ALASAN_PENARIKAN + ['lain' => 'Alasan lain (tulis di bawah)'];
$kotak = 'flex cursor-pointer items-start gap-3 rounded-lg border border-garis bg-white px-4 py-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50';
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => $judul, 'keterangan' => $ket]) ?>

    <?php if ($pesan !== ''): ?>
        <div class="peringatan peringatan-galat mt-4" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($pesan) ?></p></div>
    <?php endif; ?>

    <?php if ($aktif !== null): ?>
        <div class="peringatan peringatan-info mt-4">
            <?= ikon('info-circle') ?>
            <p>Kamu sudah meminta Surat Penarikan PKL (<?= esc($aktif['kode']) ?>). <a href="<?= site_url('layanan/saya') ?>#status" class="font-semibold underline">Lihat statusnya</a>. Bila ada yang perlu diubah, hubungi operator sekolah.</p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= site_url('layanan/surat/penarikan') ?>" class="kartu-langkah mt-4">
            <?= csrf_field() ?>
            <div class="kartu-langkah-kepala"><span class="kartu-langkah-no">1</span><h2 class="kartu-langkah-judul">Tempat PKL</h2></div>
            <div class="kartu-langkah-isi">
                <p class="text-sm text-teks-2">Surat ini untuk menarik kamu dari:</p>
                <p class="text-base font-semibold text-teks"><?= esc($form['perusahaan']) ?></p>
                <p class="mt-1 text-sm text-teks-2">Hanya namamu yang tercantum. Teman yang juga ditarik mengajukan sendiri.</p>
            </div>

            <div class="kartu-langkah-kepala border-t border-garis"><span class="kartu-langkah-no">2</span><h2 class="kartu-langkah-judul">Alasan & tanggal</h2></div>
            <div class="kartu-langkah-isi space-y-3">
                <fieldset class="space-y-2">
                    <legend class="lbl">Alasan penarikan <span class="tanda-wajib">*</span></legend>
                    <?php foreach ($opsi as $nilai => $teks): ?>
                        <label class="<?= $kotak ?>">
                            <input type="radio" name="alasan_pilih" value="<?= esc((string) $nilai, 'attr') ?>" class="mt-1 h-4 w-4 accent-brand-700"<?= $pilih === (string) $nilai ? ' checked' : '' ?> required>
                            <span class="text-sm text-teks"><?= esc($teks) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?= $err('alasan_pilih') ?>
                </fieldset>
                <div>
                    <label class="lbl" for="alasan_lain">Alasan lain</label>
                    <textarea id="alasan_lain" name="alasan_lain" rows="2" maxlength="300" class="inp" placeholder="Isi bila memilih &quot;Alasan lain&quot;"><?= esc((string) (old('alasan_lain') ?? '')) ?></textarea>
                    <?= $err('alasan_lain') ?>
                </div>
                <div>
                    <label class="lbl" for="tgl_efektif">Ditarik mulai tanggal <span class="tanda-wajib">*</span></label>
                    <input id="tgl_efektif" name="tgl_efektif" type="date" class="inp" value="<?= esc((string) (old('tgl_efektif') ?? date('Y-m-d')), 'attr') ?>" required>
                    <?= $err('tgl_efektif') ?>
                </div>
                <p class="text-sm text-teks-2">Setelah dikirim, surat menunggu ACC operator. Surat ini <b>tidak</b> mengubah status PKL-mu di sistem: bila kamu akan mengajukan tempat PKL baru, minta operator sekolah atau Waka Hubin mengeluarkanmu dulu dari ajuan yang lama.</p>
            </div>

            <div class="kartu-langkah-isi border-t border-garis">
                <button type="submit" class="tombol tombol-utama tombol-besar tombol-blok"><?= ikon('check') ?>Ajukan surat</button>
            </div>
        </form>
    <?php endif; ?>

    <p class="mt-4 text-center text-sm"><a href="<?= site_url('layanan/saya') ?>" class="font-semibold text-brand-700 hover:underline">Kembali</a></p>
</div>
<?= $this->endSection() ?>
