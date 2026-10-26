<?php
/**
 * LAYANAN SISWA — formulir Surat Perpanjangan PKL (2026-10-26; docs/DESAIN-PERPANJANGAN-PKL.md bagian 7): siswa yang PKL-nya diperpanjang
 * perusahaan meminta surat untuk dirinya (+ teman satu tempat PKL yang dicentang) → menunggu ACC operator. Bisa diminta mulai H-x sebelum
 * PKL selesai sampai H+y sesudahnya (Pengaturan PKL); hanya boleh satu kali. Tanpa JavaScript: tanggal cepat = pilihan radio yang langsung
 * menyebut tanggalnya (POST layanan/surat/perpanjangan). Aturan: LayananSiswa::ajukanPerpanjangan → SuratPerpanjangan::buat.
 *
 * @var array                 $setting
 * @var string                $kunci 'perpanjangan'
 * @var array                 $siswa LayananSiswa::profil
 * @var array                 $form  LayananSiswa::formPerpanjangan
 * @var string                $pesan
 * @var array<string, string> $galat galat per kolom
 */

use App\Libraries\IsianBantu;
use App\Libraries\LayananSiswa;
use App\Libraries\SuratPerpanjangan;

[$judul, $ket] = LayananSiswa::SURAT[$kunci];
$lama    = $form['lama'];
$jendela = $form['jendela'];
$tgl     = static fn (?string $t): string => IsianBantu::tanggalIndo((string) $t);
$err     = static fn (string $k): string => isset($galat[$k]) ? '<p class="err-msg">' . esc($galat[$k]) . '</p>' : '';
$kotak   = 'flex cursor-pointer items-start gap-3 rounded-lg border border-garis bg-white px-4 py-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50';
$pilihT  = (string) (old('pilih_tanggal') ?? '');
$pilihA  = (string) (old('alasan_pilih') ?? '');
$temanL  = array_map('intval', (array) (old('teman') ?? []));
$sisa    = $lama !== null ? SuratPerpanjangan::selisih(date('Y-m-d'), $lama) : null;
$sisaT   = $sisa === null ? '' : ($sisa > 0 ? $sisa . ' hari lagi' : ($sisa === 0 ? 'hari ini' : 'sudah lewat ' . abs($sisa) . ' hari'));
$no      = 0;
$langkah = static function (string $teks, bool $garis = true) use (&$no): string {
    $no++;

    return '<div class="kartu-langkah-kepala' . ($garis && $no > 1 ? ' border-t border-garis' : '') . '"><span class="kartu-langkah-no">' . $no . '</span><h2 class="kartu-langkah-judul">' . $teks . '</h2></div>';
};
$info = static fn (string $html): string => '<div class="peringatan peringatan-info mt-4">' . ikon('info-circle') . '<p>' . $html . '</p></div>';
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => $judul, 'keterangan' => $ket]) ?>

    <?php if ($pesan !== ''): ?>
        <div class="peringatan peringatan-galat mt-4" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($pesan) ?></p></div>
    <?php endif; ?>

    <?php if ($form['sudah'] !== null): ?>
        <?= $info('Kamu sudah punya Surat Perpanjangan PKL (' . esc($form['sudah']['kode']) . ') sampai <b>' . esc($tgl($form['sudah']['selesai_baru'])) . '</b>. Perpanjangan PKL hanya boleh <b>satu kali</b>. <a href="' . site_url('layanan/saya') . '#status" class="font-semibold underline">Lihat statusnya</a>.') ?>
    <?php elseif ($form['ditarik'] !== null): ?>
        <?= $info('Kamu sedang ditarik dari tempat PKL ini (' . esc($form['ditarik']) . '), jadi surat perpanjangan tidak bisa diminta. Hubungi operator sekolah bila ada yang salah.') ?>
    <?php elseif ($jendela !== null && $jendela['status'] === 'dini'): ?>
        <?= $info('PKL-mu di <b>' . esc($form['perusahaan']) . '</b> berakhir <b>' . esc($tgl($lama)) . '</b>. Surat perpanjangan bisa diminta mulai <b>' . esc($tgl($jendela['buka_mulai'])) . '</b>.') ?>
    <?php elseif ($jendela !== null && $jendela['status'] === 'lewat'): ?>
        <?= $info('PKL-mu di <b>' . esc($form['perusahaan']) . '</b> sudah berakhir <b>' . esc($tgl($lama)) . '</b> dan batas meminta surat perpanjangan sudah lewat (' . esc($tgl($jendela['tutup'])) . '). Hubungi operator sekolah.') ?>
    <?php else: ?>
        <form method="post" action="<?= site_url('layanan/surat/perpanjangan') ?>" class="kartu-langkah mt-4">
            <?= csrf_field() ?>

            <?= $langkah('PKL-mu sekarang') ?>
            <div class="kartu-langkah-isi">
                <p class="text-sm text-teks-2">Tempat PKL</p>
                <p class="text-base font-semibold text-teks"><?= esc($form['perusahaan']) ?></p>
                <?php if ($lama !== null): ?>
                    <p class="mt-2 text-sm text-teks">Selesai <b><?= esc($tgl($lama)) ?></b> <span class="text-teks-2">(<?= esc($sisaT) ?>)</span></p>
                <?php else: ?>
                    <label class="lbl mt-3" for="selesai_lama">PKL-mu selesai tanggal <span class="tanda-wajib">*</span></label>
                    <input id="selesai_lama" name="selesai_lama" type="date" class="inp" value="<?= esc((string) (old('selesai_lama') ?? ''), 'attr') ?>" required>
                    <p class="hint">Tanggal selesai PKL-mu belum tercatat di sekolah. Lihat Surat Balasan PKL-mu atau tanyakan pembimbing di perusahaan.</p>
                    <?= $err('selesai_lama') ?>
                <?php endif; ?>
            </div>

            <?= $langkah('Diperpanjang sampai') ?>
            <div class="kartu-langkah-isi space-y-2">
                <?php if ($form['cepat'] !== []): ?>
                    <fieldset class="space-y-2">
                        <legend class="sr-only">Pilih lama perpanjangan</legend>
                        <?php foreach ($form['cepat'] as $c): ?>
                            <label class="<?= $kotak ?>">
                                <input type="radio" name="pilih_tanggal" value="<?= esc($c['tanggal'], 'attr') ?>" class="mt-1 h-4 w-4 accent-brand-700"<?= $pilihT === $c['tanggal'] ? ' checked' : '' ?> required>
                                <span class="text-sm text-teks"><b><?= esc($c['label']) ?></b> — sampai <?= esc($tgl($c['tanggal'])) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="<?= $kotak ?>">
                            <input type="radio" name="pilih_tanggal" value="lain" class="mt-1 h-4 w-4 accent-brand-700"<?= $pilihT === 'lain' ? ' checked' : '' ?>>
                            <span class="text-sm text-teks"><b>Tanggal lain</b> — isi di bawah</span>
                        </label>
                    </fieldset>
                    <label class="lbl" for="selesai_baru">Tanggal lain</label>
                <?php else: ?>
                    <input type="hidden" name="pilih_tanggal" value="lain">
                    <label class="lbl" for="selesai_baru">PKL-mu diperpanjang sampai tanggal <span class="tanda-wajib">*</span></label>
                <?php endif; ?>
                <input id="selesai_baru" name="selesai_baru" type="date" class="inp" value="<?= esc((string) (old('selesai_baru') ?? ''), 'attr') ?>"<?= $form['cepat'] === [] ? ' required' : '' ?>>
                <?= $err('pilih_tanggal') . $err('selesai_baru') ?>
                <p class="hint">Sesuai yang disepakati dengan perusahaan. Paling lama <?= SuratPerpanjangan::MAKS_HARI ?> hari.</p>
            </div>

            <?= $langkah('Kenapa diperpanjang?') ?>
            <div class="kartu-langkah-isi space-y-2">
                <fieldset class="space-y-2">
                    <legend class="sr-only">Alasan perpanjangan</legend>
                    <?php foreach (SuratPerpanjangan::ALASAN as $i => [, $pendek]): ?>
                        <label class="<?= $kotak ?>">
                            <input type="radio" name="alasan_pilih" value="<?= (int) $i ?>" class="mt-1 h-4 w-4 accent-brand-700"<?= $pilihA === (string) $i ? ' checked' : '' ?> required>
                            <span class="text-sm text-teks"><?= esc($pendek) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <label class="<?= $kotak ?>">
                        <input type="radio" name="alasan_pilih" value="lain" class="mt-1 h-4 w-4 accent-brand-700"<?= $pilihA === 'lain' ? ' checked' : '' ?>>
                        <span class="text-sm text-teks">Alasan lain (tulis di bawah)</span>
                    </label>
                </fieldset>
                <textarea name="alasan_lain" rows="2" maxlength="200" class="inp" aria-label="Alasan lain" placeholder="Isi bila memilih &quot;Alasan lain&quot;"><?= esc((string) (old('alasan_lain') ?? '')) ?></textarea>
                <?= $err('alasan') ?>
            </div>

            <?php if ($form['teman'] !== []): ?>
                <?= $langkah('Teman yang juga diperpanjang') ?>
                <div class="kartu-langkah-isi space-y-2">
                    <p class="text-sm text-teks-2">Centang teman satu tempat PKL yang PKL-nya <b>juga diperpanjang sampai tanggal yang sama</b>. Kalau tidak ada, biarkan kosong.</p>
                    <?php foreach ($form['teman'] as $t): ?>
                        <label class="<?= $t['bisa'] ? $kotak : 'flex items-start gap-3 rounded-lg border border-garis bg-white px-4 py-3 opacity-60' ?>">
                            <input type="checkbox" name="teman[]" value="<?= (int) $t['id'] ?>" class="mt-1 h-4 w-4 accent-brand-700"<?= in_array($t['id'], $temanL, true) ? ' checked' : '' ?><?= $t['bisa'] ? '' : ' disabled' ?>>
                            <span class="text-sm text-teks"><?= esc($t['nama']) ?> <span class="text-teks-2">(<?= esc($t['kelas']) ?>)</span><?= $t['ket'] !== '' ? ' — <span class="text-teks-2">' . esc($t['ket']) . '</span>' : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?= $err('siswa') ?>
                </div>
            <?php endif; ?>

            <div class="kartu-langkah-isi border-t border-garis space-y-3">
                <p class="text-sm text-teks-2">Setelah dikirim, surat menunggu ACC operator. Perpanjangan PKL hanya boleh <b>satu kali</b>, jadi pastikan tanggalnya sudah benar.</p>
                <button type="submit" class="tombol tombol-utama tombol-besar tombol-blok"><?= ikon('check') ?>Kirim permintaan</button>
            </div>
        </form>
    <?php endif; ?>

    <p class="mt-4 text-center text-sm"><a href="<?= site_url('layanan/saya') ?>" class="font-semibold text-brand-700 hover:underline">Kembali</a></p>
</div>
<?= $this->endSection() ?>
