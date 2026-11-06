<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Unduh Aplikasi Android (2026-11-07; aplikasi belum rilis di Play Store). Isi dari Libraries\AplikasiPublik — SAMA dengan API
 * `publik/aplikasi`: info berkas APK terbaru, tombol unduh, langkah memasang, tanya-jawab, bagikan tautan (WhatsApp, tanpa JS).
 *
 * @var array                                   $setting
 * @var array<string, mixed>                    $info      AplikasiPublik::info()
 * @var list<array{judul: string, isi: string}> $langkah
 * @var list<array{0: string, 1: string}>       $tanya
 * @var string|null                             $luar      tautan Play Store / lain dari Pengaturan Sekolah
 * @var string                                  $urlHalaman
 * @var string                                  $teksBagikan
 */
use App\Libraries\IsianBantu;

$sekolah = (string) ($setting['school_name'] ?? 'Sekolah');
$ada     = (bool) $info['tersedia'];
$tombolUnduh = $ada
    ? '<a href="' . esc((string) $info['url_unduh'], 'attr') . '" class="tombol tombol-utama tombol-besar" download>' . ikon('download') . 'Unduh aplikasi' . ($info['ukuran_teks'] !== '' ? ' (' . esc($info['ukuran_teks']) . ')' : '') . '</a>'
    : '';
$diperbarui = $info['diperbarui'] ? IsianBantu::tanggalIndo(substr((string) $info['diperbarui'], 0, 10)) : '—';
?>
<?= view('public/partials/pembuka', [
    'remah'    => [['Aplikasi Android', null]],
    'ikon'     => 'mobile-phone',
    'ilu'      => 'aplikasi',
    'label'    => 'Aplikasi sekolah',
    'judul'    => 'Aplikasi Android',
    'ket'      => 'Aplikasi resmi ' . $sekolah . ' untuk HP Android. Belum tersedia di Play Store, jadi dipasang langsung dari berkas di halaman ini.',
    'aksiHtml' => $tombolUnduh,
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="space-y-6 lg:col-span-2">
            <?php if (! $ada): ?>
                <div class="kartu">
                    <div class="kosong">
                        <span class="kosong-ikon"><?= ikon('mobile-phone') ?></span>
                        <p class="kosong-judul">Aplikasi belum tersedia untuk diunduh</p>
                        <p class="kosong-teks">Sekolah sedang menyiapkan berkas aplikasinya. Semua layanan tetap bisa dipakai lewat situs ini.</p>
                        <?php if ($luar !== null): ?>
                            <a href="<?= esc($luar, 'attr') ?>" class="tombol tombol-utama mt-4" target="_blank" rel="noopener"><?= ikon('arrow-right') ?>Buka tautan aplikasi</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="kartu">
                    <div class="kartu-kepala">
                        <div class="min-w-0">
                            <h2 class="kartu-judul">Aplikasi <?= esc($sekolah) ?></h2>
                            <p class="kartu-ket">Untuk siswa, orang tua, guru, dan staf.</p>
                        </div>
                        <span class="label label-buka shrink-0">Versi <?= esc($info['versi'] !== '' ? $info['versi'] : '-') ?></span>
                    </div>
                    <div class="kartu-isi space-y-4">
                        <dl class="fakta-grid sm:grid-cols-3">
                            <div class="fakta"><dt>Versi</dt><dd><?= esc($info['versi'] !== '' ? $info['versi'] : '-') ?><?= $info['build'] > 0 ? ' <span class="font-normal text-teks-2">(build ' . (int) $info['build'] . ')</span>' : '' ?></dd></div>
                            <div class="fakta"><dt>Ukuran</dt><dd><?= esc($info['ukuran_teks'] !== '' ? $info['ukuran_teks'] : '-') ?></dd></div>
                            <div class="fakta col-span-2 sm:col-span-1"><dt>Diperbarui</dt><dd><?= esc($diperbarui) ?></dd></div>
                        </dl>
                        <a href="<?= esc((string) $info['url_unduh'], 'attr') ?>" class="tombol tombol-utama tombol-besar tombol-blok" download><?= ikon('download') ?>Unduh aplikasi<?= $info['ukuran_teks'] !== '' ? ' (' . esc($info['ukuran_teks']) . ')' : '' ?></a>
                        <p class="hint">Sebaiknya unduh memakai Wi-Fi. Nama berkas: <b><?= esc((string) $info['nama_berkas']) ?></b>. Hanya untuk HP Android; pengguna iPhone memakai situs ini.</p>
                        <?php if ($luar !== null): ?>
                            <a href="<?= esc($luar, 'attr') ?>" class="tombol tombol-netral tombol-blok" target="_blank" rel="noopener"><?= ikon('arrow-right') ?>Buka di Play Store / tautan resmi</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($info['catatan'] !== ''): ?>
                    <div class="kartu kartu-isi">
                        <h2 class="kartu-judul">Yang baru di versi ini</h2>
                        <p class="mt-2 text-sm leading-relaxed whitespace-pre-line text-teks"><?= esc($info['catatan']) ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <h2 class="judul-sub mb-4">Cara memasang</h2>
                    <ol class="kartu kartu-isi space-y-4">
                        <?php foreach ($langkah as $i => $l): ?>
                            <li class="flex gap-3">
                                <span class="kartu-langkah-no shrink-0"><?= $i + 1 ?></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-teks"><?= esc($l['judul']) ?></p>
                                    <p class="mt-0.5 text-sm leading-relaxed text-teks-2"><?= esc($l['isi']) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <div>
                <h2 class="judul-sub mb-4">Pertanyaan yang sering ditanyakan</h2>
                <?= view('public/partials/tanya_jawab', ['tanya' => $tanya, 'bukaPertama' => false], ['saveData' => false]) ?>
            </div>
        </div>

        <div class="space-y-6">
            <?php if ($ada): ?>
                <div class="kartu kartu-isi">
                    <h2 class="kartu-judul">Bagikan ke teman</h2>
                    <p class="kartu-ket">Kirim alamat halaman ini, bukan berkas aplikasinya, supaya teman selalu mendapat versi terbaru.</p>
                    <label class="lbl mt-4" for="tautanAplikasi">Alamat halaman</label>
                    <input id="tautanAplikasi" type="text" class="inp" value="<?= esc($urlHalaman, 'attr') ?>" readonly>
                    <p class="hint">Tekan lama alamat di atas untuk menyalin.</p>
                    <a href="https://wa.me/?text=<?= rawurlencode($teksBagikan) ?>" class="tombol tombol-netral tombol-blok mt-3" target="_blank" rel="noopener"><?= ikon('share-nodes') ?>Bagikan lewat WhatsApp</a>
                </div>
            <?php endif; ?>
            <div class="kartu kartu-isi">
                <h2 class="kartu-judul">Masih kesulitan?</h2>
                <p class="kartu-ket">Semua layanan tetap bisa dipakai lewat situs ini tanpa memasang aplikasi.</p>
                <div class="mt-4 grid gap-2">
                    <a href="<?= site_url('bantuan') ?>" class="tombol tombol-netral tombol-blok"><?= ikon('info-circle') ?>Bantuan &amp; kontak sekolah</a>
                    <a href="<?= site_url('/') ?>" class="tombol tombol-netral tombol-blok"><?= ikon('arrow-right') ?>Kembali ke beranda</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
