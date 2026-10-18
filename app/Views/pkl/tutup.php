<?php
/**
 * Form PKL belum bisa diisi (P6a rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama).
 *
 * @var array  $setting
 * @var string $alasan  belum_dibuka | sudah_ditutup | belum_siap
 */
$pesan = [
    'belum_dibuka'  => ['Pengajuan PKL Belum Dibuka', 'Formulir pengajuan PKL belum dibuka oleh sekolah. Tunggu informasi dari wali kelas atau Waka Hubin, lalu buka tautan ini lagi.'],
    'sudah_ditutup' => ['Masa Pengajuan Sudah Berakhir', 'Masa pengisian formulir pengajuan PKL sudah berakhir. Jika kamu belum mengajukan, hubungi wali kelas, operator sekolah, atau Waka Hubin.'],
    'belum_siap'    => ['Formulir Sedang Disiapkan', 'Sekolah masih menyiapkan formulir pengajuan PKL. Coba buka lagi nanti, atau tunggu informasi dari wali kelas.'],
];
[$judul, $isi] = $pesan[$alasan ?? 'belum_dibuka'] ?? $pesan['belum_dibuka'];
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('pkl/_header', ['setting' => $setting]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-amber-700"><?= ikon('clock', 'h-8 w-8') ?></span>
        <h2 class="mt-5 text-heading-lg text-teks"><?= esc($judul) ?></h2>
        <p class="mt-2 leading-relaxed text-teks-2"><?= esc($isi) ?></p>
    </div>
    <?php if (! empty($laporTerbuka)): ?>
        <a href="<?= site_url('pkl/lapor') ?>" class="peringatan peringatan-sukses mt-4 items-center justify-between transition-colors hover:bg-emerald-100">
            <span><b>Sudah diterima perusahaan?</b> Ajuan PKL-mu sudah disetujui dan perusahaan menerimamu — laporkan di sini supaya sekolah membuatkan Surat Balasan.</span>
            <span class="flex shrink-0 items-center gap-1 font-semibold">Lapor<?= ikon('arrow-right', 'h-4 w-4') ?></span>
        </a>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
