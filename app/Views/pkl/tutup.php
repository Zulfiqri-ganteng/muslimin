<?php
/**
 * Form PKL belum bisa diisi.
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
<?= $this->extend('layouts/pkl') ?>
<?= $this->section('content') ?>
<div class="max-w-lg mx-auto px-4 py-6 sm:py-10">
    <?= view('pkl/_header', ['setting' => $setting]) ?>
    <div class="mt-6 bg-white rounded-lg shadow-sm border border-slate-200 p-7 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
            <svg class="w-9 h-9 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h2 class="mt-5 text-xl font-extrabold text-slate-800"><?= esc($judul) ?></h2>
        <p class="mt-2 text-slate-500 leading-relaxed"><?= esc($isi) ?></p>
    </div>
    <?php if (! empty($laporTerbuka)): ?>
        <a href="<?= site_url('pkl/lapor') ?>" class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 transition hover:bg-green-100 sm:px-5">
            <span><b>Sudah diterima perusahaan?</b> Ajuan PKL-mu sudah disetujui dan perusahaan menerimamu — laporkan di sini supaya sekolah membuatkan Surat Balasan.</span>
            <span class="shrink-0 font-bold">Lapor &rarr;</span>
        </a>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
