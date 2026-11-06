<?= $this->extend('public/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Unduh Aplikasi Android (2026-11-07; aplikasi belum rilis di Play Store). Isi dari Libraries\AplikasiPublik — SAMA dengan API
 * `publik/aplikasi`: info berkas APK terbaru, tombol unduh, langkah memasang, tanya-jawab, bagikan tautan (WhatsApp, tanpa JS).
 * Tampilan (2026-11-07 malam): SATU tombol unduh saja (di kartu unduh, bukan juga di kepala halaman), fakta versi/ukuran/tanggal
 * di kartu yang sama, isi aplikasi singkat, langkah pasang berupa kartu bernomor, kolom samping = bagikan + bantuan.
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

$sekolah    = (string) ($setting['school_name'] ?? 'Sekolah');
$ada        = (bool) $info['tersedia'];
$versi      = $info['versi'] !== '' ? (string) $info['versi'] : '-';
$diperbarui = $info['diperbarui'] ? IsianBantu::tanggalIndo(substr((string) $info['diperbarui'], 0, 10)) : '—';
// Isi aplikasi (fitur yang sudah ada di aplikasi Android sekolah) — ringkas, tanpa janji fitur baru.
$isiAplikasi = [
    ['calendar-month', 'Jadwal pelajaran', 'Jadwal kelas dan jadwal mengajar guru, selalu terbaru.'],
    ['bell', 'Pemberitahuan langsung', 'Kabar sekolah dan pengingat jadwal masuk ke HP.'],
    ['briefcase', 'PKL & surat sekolah', 'Pantau pengajuan PKL dan surat dari sekolah.'],
    ['file-lines', 'Pengumuman & dokumen', 'Baca pengumuman dan dokumen resmi sekolah.'],
];
?>
<?= view('public/partials/pembuka', [
    'remah' => [['Aplikasi Android', null]],
    'ikon'  => 'mobile-phone',
    'ilu'   => 'aplikasi',
    'label' => 'Aplikasi sekolah',
    'judul' => 'Aplikasi Android',
    'ket'   => 'Aplikasi resmi ' . $sekolah . ' untuk siswa, orang tua, guru, dan staf. Belum ada di Play Store, jadi dipasang langsung dari halaman ini.',
], ['saveData' => false]) ?>

<section class="isi-halaman">
    <div class="wadah grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="space-y-8 lg:col-span-2">
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
                <!-- Kartu unduh: satu-satunya tombol unduh di halaman -->
                <div class="kartu overflow-hidden">
                    <div class="kartu-isi flex flex-col gap-5 sm:flex-row sm:items-center">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700"><?= ikon('mobile-phone', 'h-8 w-8') ?></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-bold text-teks">Aplikasi <?= esc($sekolah) ?></p>
                            <p class="mt-0.5 text-sm text-teks-2">Android · Versi <?= esc($versi) ?><?= $info['ukuran_teks'] !== '' ? ' · ' . esc($info['ukuran_teks']) : '' ?> · Diperbarui <?= esc($diperbarui) ?></p>
                            <p class="mt-2 flex items-center gap-1.5 text-sm text-emerald-800"><?= ikon('check-circle', 'h-4 w-4 shrink-0') ?>Berkas resmi dari sekolah</p>
                        </div>
                    </div>
                    <div class="kartu-isi space-y-3 border-t border-garis">
                        <a href="<?= esc((string) $info['url_unduh'], 'attr') ?>" class="tombol tombol-utama tombol-besar tombol-blok" download="<?= esc((string) $info['nama_berkas'], 'attr') ?>"><?= ikon('download') ?>Unduh aplikasi<?= $info['ukuran_teks'] !== '' ? ' (' . esc($info['ukuran_teks']) . ')' : '' ?></a>
                        <?php if ($luar !== null): ?>
                            <a href="<?= esc($luar, 'attr') ?>" class="tombol tombol-netral tombol-blok" target="_blank" rel="noopener"><?= ikon('arrow-right') ?>Buka di Play Store / tautan resmi</a>
                        <?php endif; ?>
                        <p class="hint">Sebaiknya unduh memakai Wi-Fi. Nama berkas: <b><?= esc((string) $info['nama_berkas']) ?></b><?= $info['build'] > 0 ? ' (build ' . (int) $info['build'] . ')' : '' ?>. Hanya untuk HP Android; pengguna iPhone cukup memakai situs ini.</p>
                    </div>
                </div>

                <div>
                    <h2 class="judul-sub mb-4">Isi aplikasi</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <?php foreach ($isiAplikasi as [$ik, $jdl, $ket]): ?>
                            <div class="kartu kartu-isi flex gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><?= ikon($ik, 'h-5 w-5') ?></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-teks"><?= esc($jdl) ?></p>
                                    <p class="mt-0.5 text-sm leading-relaxed text-teks-2"><?= esc($ket) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                    <ol class="grid gap-3 sm:grid-cols-2">
                        <?php foreach ($langkah as $i => $l): ?>
                            <li class="kartu kartu-isi flex gap-3">
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

        <aside class="space-y-6 lg:sticky lg:top-24">
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
                <a href="<?= site_url('bantuan') ?>" class="tombol tombol-netral tombol-blok mt-4"><?= ikon('info-circle') ?>Bantuan &amp; kontak sekolah</a>
            </div>
        </aside>
    </div>
</section>
<?= $this->endSection() ?>
