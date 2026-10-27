<?php
/**
 * LAYANAN SISWA — halaman siswa yang sudah masuk: status PKL, surat yang bisa diminta (dengan alasan bila belum bisa), dan daftar
 * permintaan surat beserta statusnya. Controller: Controllers\Layanan::saya; aturan: Libraries\LayananSiswa.
 *
 * @var array                       $setting
 * @var array                       $siswa      LayananSiswa::profil (+ 'ajuan' = ajuan PKL aktif atau null)
 * @var array<string, array>        $kelayakan  LayananSiswa::kelayakan
 * @var list<array>                 $permintaan LayananSiswa::permintaan
 * @var list<array>                 $isian      butir LayananPublik (biodata, pkl, lapor_pkl)
 * @var string                      $sukses
 */

use App\Libraries\IsianBantu;
use App\Libraries\LayananSiswa;
use App\Models\PklPengajuanModel;

$ajuan = $siswa['ajuan'];
[$teksAjuan, $labelAjuan] = match ((string) ($ajuan['status'] ?? '')) {
    'menunggu'  => ['Menunggu keputusan sekolah', 'label-kuning'],
    'perbaikan' => ['Dikembalikan untuk diperbaiki', 'label-merah'],
    'disetujui' => ['Disetujui sekolah', 'label-buka'],
    default     => ['', ''],
};
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Layanan Siswa', 'keterangan' => 'Minta surat dari sekolah dan pantau statusnya.']) ?>

    <?php if ($sukses !== ''): ?>
        <div class="peringatan peringatan-sukses mt-4" role="status"><?= ikon('check-circle') ?><p><?= esc($sukses) ?></p></div>
    <?php endif; ?>
    <?php if (($galat ?? '') !== ''): ?>
        <div class="peringatan peringatan-awas mt-4" role="alert"><?= ikon('exclamation-circle') ?><p><?= esc($galat) ?></p></div>
    <?php endif; ?>

    <!-- Identitas + keluar -->
    <section class="kartu mt-4">
        <div class="kartu-isi flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-teks-2">Kamu masuk sebagai</p>
                <p class="text-heading-md text-teks"><?= esc($siswa['nama']) ?></p>
                <p class="text-sm text-teks-2"><?= $siswa['nama_kelas'] ? 'Kelas ' . esc($siswa['nama_kelas']) : '' ?></p>
            </div>
            <form method="post" action="<?= site_url('layanan/keluar') ?>" class="shrink-0">
                <?= csrf_field() ?>
                <button type="submit" class="tombol tombol-netral"><?= ikon('arrow-right-to-bracket') ?>Keluar</button>
            </form>
        </div>
        <div class="border-t border-garis px-4 py-3 text-sm sm:px-5">
            <?php if ($ajuan !== null): ?>
                <p class="flex flex-wrap items-center gap-2 text-teks">
                    <span>PKL: <b><?= esc((string) $ajuan['perusahaan_nama']) ?></b> (<?= esc(PklPengajuanModel::kode((int) $ajuan['id'])) ?>)</span>
                    <span class="label <?= $labelAjuan ?>"><?= esc($teksAjuan) ?></span>
                </p>
            <?php else: ?>
                <p class="text-teks-2">Kamu belum punya ajuan PKL yang aktif.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Ajukan surat -->
    <section class="mt-6" aria-labelledby="judul-surat">
        <h2 id="judul-surat" class="text-heading-md text-teks">Ajukan surat</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <?php foreach (LayananSiswa::SURAT as $kunci => $_):
                $k = $kelayakan[$kunci];
                if (! $k['siap']) {
                    $opsi = ['url' => '', 'label' => 'Segera', 'kelasLabel' => 'label-tutup', 'catatan' => '', 'kaki' => 'Segera tersedia'];
                } elseif ($k['boleh']) {
                    $opsi = ['url' => site_url('layanan/surat/' . $kunci), 'label' => 'Bisa diajukan', 'kelasLabel' => 'label-buka', 'catatan' => '', 'kaki' => 'Ajukan surat'];
                } else {
                    $opsi = ['url' => '', 'label' => 'Belum bisa', 'kelasLabel' => 'label-tutup', 'catatan' => $k['alasan'], 'kaki' => 'Belum bisa diajukan'];
                }
            ?>
                <?= view('layanan/_kartu_surat', ['kunci' => $kunci] + $opsi, ['saveData' => false]) ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Status permintaan -->
    <section id="status" class="mt-8 scroll-mt-6" aria-labelledby="judul-status">
        <h2 id="judul-status" class="text-heading-md text-teks">Permintaan suratku</h2>
        <div class="kartu mt-3 overflow-hidden">
            <?php if ($permintaan === []): ?>
                <p class="px-4 py-6 text-center text-sm text-teks-2 sm:px-5">Belum ada permintaan surat. Permintaan yang kamu ajukan akan muncul di sini beserta statusnya.</p>
            <?php else: ?>
                <ul class="daftar-baris">
                    <?php foreach ($permintaan as $r): ?>
                        <li class="baris">
                            <div class="baris-isi">
                                <p class="baris-judul"><?= esc($r['judul']) ?></p>
                                <?php if ($r['perusahaan'] !== ''): ?><p class="baris-ket">Untuk <?= esc($r['perusahaan']) ?></p><?php endif; ?>
                                <p class="baris-info">Diajukan <?= esc(IsianBantu::tanggalIndo(substr($r['dibuat'], 0, 10))) ?><?= $r['nomor'] !== '' ? ' · Nomor ' . esc($r['nomor']) : '' ?></p>
                                <p class="mt-1 text-sm text-teks"><?= esc($r['status_ket']) ?></p>
                                <?php if (($r['catatan'] ?? '') !== ''): ?><p class="mt-1 text-sm text-teks-2"><?= esc($r['catatan']) ?></p><?php endif; ?>
                            </div>
                            <div class="baris-aksi"><span class="label <?= esc($r['status_kelas'], 'attr') ?>"><?= esc($r['status_teks']) ?></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($isian !== []): ?>
        <section class="mt-8" aria-labelledby="judul-isian">
            <h2 id="judul-isian" class="text-heading-md text-teks">Isi data &amp; pengajuan</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <?php foreach ($isian as $l): ?>
                    <?= view('public/partials/kartu_layanan', ['l' => $l], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <p class="mt-6 text-center text-xs text-teks-2">Demi keamanan, kamu otomatis keluar setelah 30 menit tidak aktif. Di HP atau komputer milik orang lain, tekan <b>Keluar</b> setelah selesai.</p>
</div>
<?= $this->endSection() ?>
