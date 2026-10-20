<?php
/**
 * Lapor diterima PKL — terkirim (P6b rombak publik: bingkai layouts/formulir.php + kit publik.css; isi sama).
 *
 * @var array                $setting
 * @var array<string, mixed> $info    kode, perusahaan, periode, pelapor, anggota (list nama), ulang (kirim ulang?)
 */
?>
<?= $this->extend('layouts/formulir') ?>
<?= $this->section('content') ?>
<div class="formulir formulir-sempit">
    <?= view('pkl/_kop_lapor', ['setting' => $setting]) ?>
    <div class="kartu mt-6 px-6 py-8 sm:px-8">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700"><?= ikon('check-circle', 'h-8 w-8') ?></span>
        <h2 class="mt-4 text-center text-heading-lg text-teks"><?= ! empty($info['ulang']) ? 'Laporan diperbarui' : 'Laporan terkirim' ?></h2>
        <p class="mt-2 text-center text-sm leading-relaxed text-teks-2">Terima kasih, <?= esc((string) ($info['pelapor'] ?? '')) ?>. <?= ($info['hasil'] ?? 'semua') === 'ditolak' ? 'Sekolah akan memeriksa laporanmu. Setelah dikonfirmasi, kalian boleh mengajukan tempat PKL lain lewat form pengajuan PKL.' : 'Sekolah akan memeriksa laporanmu lalu membuatkan <b>Surat Balasan</b> untuk siswa yang diterima perusahaan.' ?></p>
        <dl class="mt-5 divide-y divide-garis rounded-lg border border-garis text-sm">
            <div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5"><dt class="text-teks-2">Ajuan PKL</dt><dd class="font-semibold text-teks"><?= esc((string) ($info['kode'] ?? '')) ?></dd></div>
            <div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5"><dt class="text-teks-2">Perusahaan</dt><dd class="font-semibold break-words text-teks"><?= esc((string) ($info['perusahaan'] ?? '')) ?></dd></div>
            <div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5"><dt class="text-teks-2">Periode PKL</dt><dd class="font-semibold text-teks"><?= esc((string) ($info['periode'] ?? '') !== '' ? (string) $info['periode'] : '—') ?></dd></div>
            <?php if (! empty($info['hasil_teks'])): ?><div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5"><dt class="text-teks-2">Jawaban perusahaan</dt><dd class="font-semibold text-teks"><?= esc((string) $info['hasil_teks']) ?></dd></div><?php endif; ?>
            <?php if (($info['hasil'] ?? '') === 'sebagian'): ?><div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5"><dt class="text-teks-2">Diterima</dt><dd class="text-teks"><?= esc(implode(', ', (array) ($info['diterima'] ?? []))) ?></dd></div>
            <div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5"><dt class="text-teks-2">Tidak diterima</dt><dd class="text-teks"><?= esc(implode(', ', (array) ($info['ditolak'] ?? []))) ?> <span class="text-teks-2">(akan dikeluarkan dari kelompok setelah diperiksa sekolah, lalu boleh mengajukan tempat lain)</span></dd></div><?php endif; ?>
            <?php if (! empty($info['anggota'])): ?><div class="grid grid-cols-[7rem_1fr] gap-3 px-4 py-2.5"><dt class="text-teks-2">Anggota kelompok</dt><dd class="text-teks"><?= esc(implode(', ', (array) $info['anggota'])) ?></dd></div><?php endif; ?>
        </dl>
        <p class="mt-4 text-sm leading-relaxed text-teks-2">Simpan / tangkap layar halaman ini. Bila sekolah mengembalikan laporan, buka lagi form lapor untuk melihat alasannya dan memperbaikinya. Teman sekelompokmu <b>tidak perlu</b> melapor lagi.</p>
        <a href="<?= site_url('pkl/lapor') ?>" class="tombol tombol-netral tombol-besar tombol-blok mt-5"><?= ikon('angle-left') ?>Kembali ke form lapor</a>
    </div>
</div>
<?= $this->endSection() ?>
