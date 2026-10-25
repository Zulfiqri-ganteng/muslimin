<?php
/**
 * LAYANAN SISWA — formulir Surat Izin TKA dengan gelombang PER SISWA (2026-10-25; kasus PT Inti Pantja: teman satu tempat PKL bisa
 * beda gelombang). Langkah: (1) gelombang TKA kamu, (2) gelombang teman satu tempat PKL — kosong = tidak diubah / biar teman memilih
 * sendiri, (3) tujuan surat. Satu surat per perusahaan per gelombang. Tanpa JavaScript (POST layanan/surat/izin_tka: jadwal_id,
 * teman[siswa_id]). Aturan: LayananSiswa::ajukanTka.
 *
 * @var array  $setting
 * @var string $kunci  'izin_tka'
 * @var array  $siswa  LayananSiswa::profil
 * @var array  $form   LayananSiswa::formTka: jadwal, perusahaan, saya{penempatan, jadwal_id, terkunci}, teman[{id, nama, kelas, penempatan, jadwal_id, terkunci, sendiri}]
 * @var string $pesan  galat kiriman sebelumnya
 */

use App\Libraries\LayananSiswa;

[$judul, $ket] = LayananSiswa::SURAT[$kunci];
$jadwal    = $form['jadwal'];
$saya      = $form['saya'];
$lamaJ     = (int) (old('jadwal_id') ?? 0);
$pilihan   = $lamaJ ?: ($saya['jadwal_id'] ?: (count($jadwal) === 1 ? (int) $jadwal[0]['id'] : 0));
$lamaT     = is_array(old('teman')) ? old('teman') : [];
$bisaKirim = $form['perusahaan'] !== null && (! $saya['terkunci'] || $saya['jadwal_id'] > 0);
$tempatnya = static fn (array $t): string => $t['label'] . ' (' . $t['kode'] . ') · ' . $t['status_teks']
    . ($t['sendiri'] ? ' · dipilih sendiri' : ($t['diisikan'] !== '' ? ' · diisikan oleh ' . $t['diisikan'] : ''));
$pil = 'inline-flex cursor-pointer items-center gap-2 rounded-lg border border-garis bg-white px-3 py-2 text-sm text-teks has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50';
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => $judul, 'keterangan' => $ket]) ?>

    <?php if ($pesan !== ''): ?>
        <div class="peringatan peringatan-galat mt-4" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($pesan) ?></p></div>
    <?php endif; ?>

    <div class="peringatan peringatan-info mt-4">
        <?= ikon('info-circle') ?>
        <p>Satu siswa hanya ikut <b>satu gelombang</b> TKA. Lihat gelombangmu di kartu peserta / kartu login TKA.</p>
    </div>
    <?php if (count($saya['penempatan']) > 1): ?>
        <div class="peringatan peringatan-awas mt-4" role="alert">
            <?= ikon('exclamation-circle') ?>
            <p>Namamu tercantum di lebih dari satu gelombang: <?= esc(implode('; ', array_map($tempatnya, $saya['penempatan']))) ?>. Minta operator sekolah membetulkannya.</p>
        </div>
    <?php elseif ($saya['penempatan'] !== []): ?>
        <div class="peringatan <?= $saya['terkunci'] ? 'peringatan-awas' : 'peringatan-info' ?> mt-4">
            <?= ikon('info-circle') ?>
            <p>Sekarang namamu tercantum di surat <?= esc($tempatnya($saya['penempatan'][0])) ?>.
                <?= $saya['terkunci'] ? 'Surat itu sudah disetujui sekolah — kalau gelombangmu salah, minta operator sekolah memindahkanmu.' : 'Kalau gelombangmu salah, pilih gelombang yang benar lalu kirim: namamu dipindahkan.' ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('layanan/surat/' . $kunci) ?>" class="kartu-langkah mt-4">
        <?= csrf_field() ?>
        <div class="kartu-langkah-kepala"><span class="kartu-langkah-no">1</span><h2 class="kartu-langkah-judul">Gelombang TKA kamu</h2></div>
        <div class="kartu-langkah-isi">
            <fieldset class="space-y-2">
                <legend class="sr-only">Gelombang TKA kamu</legend>
                <?php foreach ($jadwal as $j): $mati = $saya['terkunci'] && (int) $j['id'] !== $saya['jadwal_id']; ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-garis bg-white px-4 py-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50<?= $mati ? ' cursor-default opacity-60' : '' ?>">
                        <input type="radio" name="jadwal_id" value="<?= (int) $j['id'] ?>" class="mt-1 h-4 w-4 accent-brand-700"<?= $mati ? ' disabled' : '' ?><?= (int) $j['id'] === $pilihan ? ' checked' : '' ?> required>
                        <span class="min-w-0">
                            <span class="block font-semibold text-teks"><?= esc($j['label']) ?></span>
                            <span class="block text-sm text-teks-2"><?= esc($j['hari_tanggal']) ?> · <?= esc($j['tempat']) ?></span>
                            <?php if ($j['luar_periode']): ?>
                                <span class="mt-1 block text-sm text-amber-800">Catatan: periode PKL-mu yang tercatat tidak mencakup tanggal ini. Tetap bisa diajukan.</span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        </div>

        <?php if ($form['teman'] !== []): ?>
            <div class="kartu-langkah-kepala border-t border-garis"><span class="kartu-langkah-no">2</span><h2 class="kartu-langkah-judul">Gelombang teman PKL-mu</h2></div>
            <div class="kartu-langkah-isi space-y-3">
                <p class="text-sm text-teks-2">Teman kelas XII di tempat PKL yang sama. Isi gelombang mereka hanya bila kamu tahu <b>pasti</b> (lihat kartu peserta TKA mereka). Kalau ragu, biarkan saja — temanmu bisa mengajukan sendiri lewat layanan ini.</p>
                <?php foreach ($form['teman'] as $t): $tid = (int) $t['id']; $lama = (string) ($lamaT[$tid] ?? ''); ?>
                    <div class="rounded-lg border border-garis bg-white px-4 py-3" role="radiogroup" aria-labelledby="teman-<?= $tid ?>">
                        <p id="teman-<?= $tid ?>" class="font-semibold text-teks"><?= esc($t['nama']) ?><?= $t['kelas'] !== '' ? ' <span class="font-normal text-teks-2">· ' . esc($t['kelas']) . '</span>' : '' ?></p>
                        <p class="text-sm text-teks-2"><?= $t['penempatan'] !== [] ? 'Sekarang: ' . esc(implode('; ', array_map($tempatnya, $t['penempatan']))) : 'Belum tercantum di surat TKA mana pun.' ?></p>
                        <?php if ($t['terkunci']): ?>
                            <p class="mt-1 text-sm text-amber-800"><?= $t['sendiri'] ? 'Sudah memilih gelombangnya sendiri — tidak bisa diubah dari sini.' : 'Suratnya sudah disetujui sekolah — tidak bisa diubah dari sini.' ?></p>
                        <?php else: ?>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <label class="<?= $pil ?>"><input type="radio" name="teman[<?= $tid ?>]" value="" class="h-4 w-4 accent-brand-700"<?= $lama === '' ? ' checked' : '' ?>><?= $t['penempatan'] !== [] ? 'Tetap seperti sekarang' : 'Biar dia pilih sendiri' ?></label>
                                <?php foreach ($jadwal as $j): ?>
                                    <label class="<?= $pil ?>"><input type="radio" name="teman[<?= $tid ?>]" value="<?= (int) $j['id'] ?>" class="h-4 w-4 accent-brand-700"<?= $lama === (string) (int) $j['id'] ? ' checked' : '' ?>><?= esc($j['label']) ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="kartu-langkah-kepala border-t border-garis"><span class="kartu-langkah-no"><?= $form['teman'] !== [] ? 3 : 2 ?></span><h2 class="kartu-langkah-judul">Periksa tujuan surat</h2></div>
        <div class="kartu-langkah-isi space-y-3 text-sm">
            <?php if ($form['perusahaan'] !== null): ?>
                <div>
                    <p class="text-teks-2">Surat ditujukan ke tempat PKL-mu:</p>
                    <p class="text-base font-semibold text-teks"><?= esc($form['perusahaan']['nama']) ?></p>
                    <?php if ($form['perusahaan']['alamat'] !== ''): ?><p class="text-teks-2"><?= esc($form['perusahaan']['alamat']) ?></p><?php endif; ?>
                </div>
                <p class="text-teks-2"><b>Satu surat untuk tiap gelombang.</b> Teman yang gelombangnya sama denganmu tercantum di surat yang sama; teman yang beda gelombang dibuatkan surat sendiri.</p>
            <?php else: ?>
                <div class="peringatan peringatan-awas"><?= ikon('exclamation-circle') ?><p>Data PKL-mu belum lengkap untuk surat ini. Hubungi operator sekolah.</p></div>
            <?php endif; ?>
            <p class="text-teks-2">Setelah dikirim, surat menunggu ACC operator. Bila statusnya <b>Siap diambil</b>, ambil di Tata Usaha lalu serahkan ke perusahaan.</p>
        </div>

        <div class="kartu-langkah-isi border-t border-garis">
            <?php if ($bisaKirim): ?>
                <button type="submit" class="tombol tombol-utama tombol-besar tombol-blok"><?= ikon('check') ?>Ajukan surat</button>
            <?php else: ?>
                <a href="<?= site_url('layanan/saya') ?>#status" class="tombol tombol-netral tombol-besar tombol-blok">Lihat status suratku</a>
            <?php endif; ?>
        </div>
    </form>

    <p class="mt-4 text-center text-sm"><a href="<?= site_url('layanan/saya') ?>" class="font-semibold text-brand-700 hover:underline">Kembali</a></p>
</div>
<?= $this->endSection() ?>
