<?php
/**
 * Ajuan PKL terkirim (P6a rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama).
 *
 * @var array  $setting
 * @var array  $info    ['nama','kelas','no','revisi','perusahaan','jumlah','batas','hari']
 * @var string $urlForm
 */

use App\Libraries\IsianBantu;

$jumlah = (int) ($info['jumlah'] ?? 1);
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('pkl/_header', ['setting' => $setting]) ?>
    <div class="kartu mt-6 px-6 py-8 text-center sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700"><?= ikon('check-circle', 'h-8 w-8') ?></span>
        <h2 class="mt-5 text-heading-lg text-teks"><?= ! empty($info['revisi']) ? 'Perbaikan terkirim' : 'Ajuan PKL terkirim' ?></h2>
        <p class="mt-2 leading-relaxed text-teks-2">
            Terima kasih, <b class="text-teks"><?= esc($info['nama'] ?? '') ?></b><?php if (! empty($info['kelas'])): ?> (<?= esc($info['kelas']) ?>)<?php endif; ?>.
            Ajuan PKL-mu sudah kami terima dan diteruskan ke Waka Hubin untuk diputuskan.
        </p>

        <div class="mt-5 rounded-lg border border-brand-100 bg-brand-50 px-5 py-4">
            <p class="text-xs font-semibold tracking-wide text-teks-2 uppercase">Nomor bukti ajuan</p>
            <p class="mt-1 text-display-sm tracking-wider text-brand-800"><?= esc($info['no'] ?? '') ?></p>
            <p class="mt-1 text-sm text-teks-2">Simpan / tangkap layar halaman ini sebagai bukti.</p>
        </div>

        <dl class="mt-4 divide-y divide-garis rounded-lg border border-garis text-left text-sm">
            <div class="grid grid-cols-[6.5rem_1fr] gap-3 px-4 py-2.5">
                <dt class="text-teks-2">Perusahaan</dt>
                <dd class="font-semibold break-words text-teks"><?= esc($info['perusahaan'] ?? '') ?></dd>
            </div>
            <?php if (! empty($info['batas'])): ?>
                <div class="grid grid-cols-[6.5rem_1fr] gap-3 px-4 py-2.5">
                    <dt class="text-teks-2">Keputusan</dt>
                    <dd class="font-semibold text-teks">paling lambat <?= esc(IsianBantu::tanggalIndo($info['batas'])) ?></dd>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-[6.5rem_1fr] gap-3 px-4 py-2.5">
                <dt class="text-teks-2">Jumlah siswa</dt>
                <dd class="font-semibold text-teks"><?= $jumlah ?> siswa<?= $jumlah > 1 ? ' (kamu + ' . ($jumlah - 1) . ' teman)' : '' ?></dd>
            </div>
        </dl>

        <p class="mt-5 text-sm leading-relaxed text-teks-2">
            Kamu <b>tidak perlu mengisi lagi</b>; temanmu yang tercantum juga tidak perlu mengisi.
            <b>Jangan mengajukan ulang</b> — tunggu keputusan sekolah<?= ! empty($info['hari']) ? ' (paling lambat ' . (int) $info['hari'] . ' hari)' : '' ?>.
            Jika ada data yang salah atau sekolah meminta perbaikan, hubungi operator sekolah atau Waka Hubin.
        </p>
        <a href="<?= esc($urlForm, 'attr') ?>" class="tombol tombol-teks mt-5"><?= ikon('angle-left', 'h-4 w-4') ?>Kembali ke halaman awal</a>
    </div>
</div>
<?= $this->endSection() ?>
