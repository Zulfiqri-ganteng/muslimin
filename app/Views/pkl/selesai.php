<?php
/**
 * @var array  $setting
 * @var array  $info    ['nama','kelas','no','revisi','perusahaan','mulai','selesai','jumlah']
 * @var string $urlForm
 */

use App\Libraries\IsianBantu;

$jumlah = (int) ($info['jumlah'] ?? 1);
?>
<?= $this->extend('layouts/pkl') ?>
<?= $this->section('content') ?>
<div class="max-w-lg mx-auto px-4 py-6 sm:py-10">
    <?= view('pkl/_header', ['setting' => $setting]) ?>
    <div class="rise rise-2 mt-6 bg-white rounded-2xl shadow-sm border border-slate-200 p-7 text-center">
        <div class="pop-anim ring-pulse mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
            <svg class="w-9 h-9 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="mt-5 text-xl font-extrabold text-slate-800"><?= ! empty($info['revisi']) ? 'Perbaikan Terkirim!' : 'Ajuan PKL Terkirim!' ?></h2>
        <p class="mt-2 text-slate-500 leading-relaxed">
            Terima kasih, <b class="text-slate-700"><?= esc($info['nama'] ?? '') ?></b><?php if (! empty($info['kelas'])): ?> (<?= esc($info['kelas']) ?>)<?php endif; ?>.
            Ajuan PKL-mu sudah kami terima dan akan diperiksa oleh sekolah.
        </p>

        <div class="rise rise-3 mt-5 rounded-xl border border-brand-100 bg-gradient-to-br from-brand-50 to-white px-5 py-4">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Nomor bukti ajuan</p>
            <p class="mt-1 text-2xl font-extrabold text-brand-700 tracking-wider"><?= esc($info['no'] ?? '') ?></p>
            <p class="mt-1 text-xs text-slate-400">Simpan / tangkap layar halaman ini sebagai bukti.</p>
        </div>

        <dl class="rise rise-4 mt-4 rounded-xl border border-slate-200 divide-y divide-slate-100 text-left text-sm">
            <div class="grid grid-cols-[6.5rem_1fr] gap-3 px-4 py-2.5">
                <dt class="text-slate-500">Perusahaan</dt>
                <dd class="font-semibold text-slate-800 break-words"><?= esc($info['perusahaan'] ?? '') ?></dd>
            </div>
            <div class="grid grid-cols-[6.5rem_1fr] gap-3 px-4 py-2.5">
                <dt class="text-slate-500">Periode</dt>
                <dd class="font-semibold text-slate-800"><?= esc(IsianBantu::tanggalIndo($info['mulai'] ?? null)) ?> &ndash; <?= esc(IsianBantu::tanggalIndo($info['selesai'] ?? null)) ?></dd>
            </div>
            <div class="grid grid-cols-[6.5rem_1fr] gap-3 px-4 py-2.5">
                <dt class="text-slate-500">Jumlah siswa</dt>
                <dd class="font-semibold text-slate-800"><?= $jumlah ?> siswa<?= $jumlah > 1 ? ' (kamu + ' . ($jumlah - 1) . ' teman)' : '' ?></dd>
            </div>
        </dl>

        <p class="mt-5 text-sm text-slate-500">
            Kamu <b>tidak perlu mengisi lagi</b>; temanmu yang tercantum juga tidak perlu mengisi.
            Jika ada data yang salah atau sekolah meminta perbaikan, hubungi operator sekolah atau Waka Hubin.
        </p>
        <a href="<?= esc($urlForm, 'attr') ?>" class="mt-5 inline-block text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Kembali ke halaman awal</a>
    </div>
</div>
<?= $this->endSection() ?>
