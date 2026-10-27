<?php
/**
 * LAYANAN SISWA — beranda satu link (publik, tanpa akun). Bagian 1: minta surat dari sekolah (perlu masuk: NISN / tanggal lahir).
 * Bagian 2: isian yang sudah ada (Biodata, Pengajuan PKL, Lapor hasil PKL) — masing-masing punya cara masuk sendiri.
 * Controller: Controllers\Layanan::index; aturan: Libraries\LayananSiswa.
 *
 * @var array       $setting
 * @var list<array> $isian  butir LayananPublik (biodata, pkl, lapor_pkl) lengkap dengan status buka/tutup
 * @var array|null  $siswa  siswa yang sedang masuk (LayananSiswa::profil) atau null
 */

use App\Libraries\LayananSiswa;

$sukses = (string) (session()->getFlashdata('layanan_sukses') ?? '');
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir">
    <?= view('partials/kop_formulir', ['setting' => $setting, 'judul' => 'Layanan Siswa', 'keterangan' => 'Satu tempat untuk meminta surat dari sekolah, mengajukan PKL, dan mengisi biodata.']) ?>

    <?php if ($sukses !== ''): ?>
        <div class="peringatan peringatan-sukses mt-4" role="status"><?= ikon('check-circle') ?><p><?= esc($sukses) ?></p></div>
    <?php endif; ?>
    <?php if ($siswa !== null): ?>
        <div class="peringatan peringatan-info mt-4">
            <?= ikon('user') ?>
            <p>Kamu masuk sebagai <b><?= esc($siswa['nama']) ?></b><?= $siswa['nama_kelas'] ? ' (' . esc($siswa['nama_kelas']) . ')' : '' ?>. <a href="<?= site_url('layanan/saya') ?>" class="font-semibold underline">Buka halaman suratku</a></p>
        </div>
    <?php endif; ?>

    <section class="mt-6" aria-labelledby="judul-surat">
        <h2 id="judul-surat" class="text-heading-md text-teks">Ajukan surat dari sekolah</h2>
        <p class="mt-1 text-sm text-teks-2">Pilih surat yang kamu perlukan. Kamu cukup memilih kelas dan namamu, lalu mengisi <b>NISN atau tanggal lahir</b>. Surat yang sudah jadi diambil di Tata Usaha sekolah.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <?php foreach (array_keys(LayananSiswa::SURAT) as $kunci):
                $siap = in_array($kunci, LayananSiswa::SIAP, true);
                $url  = $siap ? ($siswa !== null ? site_url('layanan/surat/' . $kunci) : site_url('layanan/masuk') . '?tujuan=' . $kunci) : '';
            ?>
                <?= view('layanan/_kartu_surat', ['kunci' => $kunci, 'url' => $url, 'label' => $siap ? '' : 'Segera', 'kelasLabel' => 'label-tutup',
                    'catatan' => '', 'kaki' => $siap ? 'Ajukan surat' : 'Segera tersedia'], ['saveData' => false]) ?>
            <?php endforeach; ?>
            <article class="kartu-layanan">
                <div class="kartu-layanan-kepala"><span class="kartu-layanan-ikon"><?= ikon('clock') ?></span></div>
                <h3 class="kartu-layanan-judul"><a href="<?= $siswa !== null ? site_url('layanan/saya') . '#status' : site_url('layanan/masuk') . '?tujuan=status' ?>" class="tautan-penuh">Cek status suratku</a></h3>
                <p class="kartu-layanan-ket">Lihat apakah surat yang kamu minta masih menunggu ACC, sedang disiapkan, atau sudah bisa diambil.</p>
                <p class="kartu-layanan-kaki" aria-hidden="true">Lihat status<?= ikon('arrow-right') ?></p>
            </article>
        </div>
    </section>

    <?php if ($isian !== []): ?>
        <section class="mt-8" aria-labelledby="judul-isian">
            <h2 id="judul-isian" class="text-heading-md text-teks">Isi data &amp; pengajuan</h2>
            <p class="mt-1 text-sm text-teks-2">Formulir ini punya langkah masuknya sendiri.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <?php foreach ($isian as $l): ?>
                    <?= view('public/partials/kartu_layanan', ['l' => $l], ['saveData' => false]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
