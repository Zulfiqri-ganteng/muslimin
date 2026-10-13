<?php
/**
 * Lapor diterima PKL — terkirim.
 *
 * @var array                $setting
 * @var array<string, mixed> $info    kode, perusahaan, periode, pelapor, anggota (list nama), ulang (kirim ulang?)
 */
?>
<?= $this->extend('layouts/pkl') ?>
<?= $this->section('content') ?>
<div class="mx-auto max-w-lg px-4 py-6 sm:py-10">
    <?= view('partials/kop_form', ['setting' => $setting, 'judul' => 'Lapor Diterima PKL', 'keterangan' => 'Diisi siswa lewat HP, tanpa login, setelah diterima perusahaan.']) ?>
    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="mt-4 text-center text-xl font-bold text-slate-800"><?= ! empty($info['ulang']) ? 'Laporan diperbarui' : 'Laporan terkirim' ?></h2>
        <p class="mt-2 text-center text-sm leading-relaxed text-slate-500">Terima kasih, <?= esc((string) ($info['pelapor'] ?? '')) ?>. Sekolah akan memeriksa laporanmu lalu membuatkan <b>Surat Balasan</b> untuk perusahaan.</p>
        <dl class="mt-5 space-y-2 rounded-lg bg-slate-50 px-4 py-3 text-sm">
            <div><dt class="text-xs text-slate-400">Ajuan PKL</dt><dd class="font-semibold text-slate-800"><?= esc((string) ($info['kode'] ?? '')) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Perusahaan</dt><dd class="font-semibold text-slate-800"><?= esc((string) ($info['perusahaan'] ?? '')) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Periode PKL</dt><dd class="font-semibold text-slate-800"><?= esc((string) ($info['periode'] ?? '')) ?></dd></div>
            <?php if (! empty($info['anggota'])): ?><div><dt class="text-xs text-slate-400">Anggota kelompok</dt><dd class="text-slate-700"><?= esc(implode(', ', (array) $info['anggota'])) ?></dd></div><?php endif; ?>
        </dl>
        <p class="mt-4 text-xs leading-relaxed text-slate-500">Simpan / tangkap layar halaman ini. Bila sekolah mengembalikan laporan, buka lagi form lapor untuk melihat alasannya dan memperbaikinya. Teman sekelompokmu <b>tidak perlu</b> melapor lagi.</p>
        <a href="<?= site_url('pkl/lapor') ?>" class="btn-nav-secondary mt-5 flex w-full justify-center">Kembali ke form lapor</a>
    </div>
</div>
<?= $this->endSection() ?>
