<?php

/**
 * Penggunaan penyimpanan + pemeriksaan kesehatan arsip dokumen.
 * Rombak tampilan F10: Kembali / Ubah pagu di kepala halaman, kartu angka kit, bilah = <progress class="progres"> (dulu style=
 * sebaris; warna per jenis berkas lewat varian progres-*), ui_peringatan, kartu kit, hapus berkas yatim = data-confirm kit
 * (dulu onsubmit confirm).
 *
 * @var array $pemakaian total_byte, total_berkas, per_kategori, sampah_byte
 * @var int   $kuotaByte
 * @var int   $maksByte
 * @var array $hilang    baris yang berkasnya tak ada di disk
 * @var array $yatim     berkas di disk tanpa baris di basis data
 * @var int   $yatimByte
 */
helper('dokumen');

$pakai  = (int) $pemakaian['total_byte'];
$persen = $kuotaByte > 0 ? min(100, round($pakai / $kuotaByte * 100)) : 0;

$warnaKategori = [
    'pdf'         => 'progres-merah',
    'dokumen'     => 'progres-biru',
    'spreadsheet' => 'progres-hijau',
    'presentasi'  => 'progres-kuning',
    'gambar'      => 'progres-ungu',
    'audio'       => 'progres-pink',
    'video'       => 'progres-pink',
    'arsip'       => 'progres-kuning',
    'lainnya'     => 'progres-abu',
];
$maksKategori = 0;
foreach ($pemakaian['per_kategori'] as $k) {
    $maksKategori = max($maksKategori, (int) $k['byte_total']);
}
$tile = static fn (string $label, string $angka, string $ket): string => '<p class="stat-label text-sm">' . esc($label) . '</p><p class="stat-angka mt-2 tabular-nums">' . esc($angka) . '</p>'
    . '<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">' . esc($ket) . '</p>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/dokumen') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Dokumen</a>
<a href="<?= site_url('admin/settings') ?>" class="btn btn-netral"><?= ikon('cog', 'h-4 w-4') ?>Ubah pagu di Pengaturan</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dokumen_penyimpanan',
    'helpTitle' => 'Penggunaan Penyimpanan',
    'helpBody'  => '<p>Ringkasan ruang yang dipakai arsip dokumen, beserta pemeriksaan kesehatan arsip.</p>
        <p class="mt-1">• <b>Berkas hilang</b> = datanya masih tercatat tapi berkasnya sudah tidak ada di server (mis. terhapus manual lewat File Manager). Dokumen seperti ini tidak bisa diunduh.<br>
        • <b>Berkas yatim</b> = berkas menumpuk di server tapi tidak lagi tercatat di sistem. Aman dihapus, dan ruangnya langsung kembali.<br>
        • Ruang dari berkas di <b>Tempat Sampah</b> baru benar-benar kembali setelah dihapus permanen.</p>',
]) ?>

<div class="space-y-4">
    <!-- Ringkasan -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Ringkasan penyimpanan">
        <div class="kartu kartu-isi">
            <?= $tile('Terpakai', dokumen_ukuran_manusia($pakai), 'dari pagu ' . dokumen_ukuran_manusia($kuotaByte)) ?>
            <progress class="progres mt-3<?= $persen >= 90 ? ' progres-merah' : ($persen >= 70 ? ' progres-kuning' : '') ?>" value="<?= (int) $persen ?>" max="100" aria-label="Penyimpanan terpakai <?= (int) $persen ?>%"><?= (int) $persen ?>%</progress>
        </div>
        <div class="kartu kartu-isi"><?= $tile('Jumlah berkas', (string) (int) $pemakaian['total_berkas'], 'batas ' . dokumen_ukuran_manusia($maksByte) . ' per berkas') ?></div>
        <div class="kartu kartu-isi"><?= $tile('Tertahan di tempat sampah', dokumen_ukuran_manusia((int) $pemakaian['sampah_byte']), 'kembali setelah dihapus permanen') ?></div>
    </section>

    <?php if ($persen >= 80): ?>
        <?= ui_peringatan('awas', 'Kosongkan tempat sampah, hapus berkas yatim, atau naikkan pagu di Pengaturan bila kuota disk hosting memang masih lega.', ['judul' => 'Penyimpanan hampir penuh (' . $persen . '%)']) ?>
    <?php endif; ?>

    <!-- Per kategori -->
    <section class="kartu overflow-hidden" aria-labelledby="judul-per-jenis">
        <div class="kartu-kepala"><h2 id="judul-per-jenis" class="kartu-judul text-base">Pemakaian per jenis berkas</h2></div>
        <?php if ($pemakaian['per_kategori'] === []): ?>
            <?= ui_kosong(['ikon' => 'folder-open', 'judul' => 'Belum ada berkas tersimpan']) ?>
        <?php else: ?>
            <div class="kartu-isi space-y-3">
                <?php foreach ($pemakaian['per_kategori'] as $k):
                    $b = (int) $k['byte_total'];
                    $w = $maksKategori > 0 ? max(2, round($b / $maksKategori * 100)) : 0; ?>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-900 dark:text-white"><?= esc(ucfirst((string) $k['kategori'])) ?> <span class="text-gray-500 dark:text-gray-400">(<?= (int) $k['jml'] ?> berkas)</span></span>
                            <span class="tabular-nums text-gray-500 dark:text-gray-400"><?= esc(dokumen_ukuran_manusia($b)) ?></span>
                        </div>
                        <progress class="progres h-2 <?= $warnaKategori[$k['kategori']] ?? $warnaKategori['lainnya'] ?>" value="<?= (int) $w ?>" max="100" aria-label="<?= esc(ucfirst((string) $k['kategori']), 'attr') ?>"><?= (int) $w ?>%</progress>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Kesehatan arsip -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="kartu kartu-isi<?= $hilang !== [] ? ' border-red-300 dark:border-red-800' : '' ?>" aria-labelledby="judul-hilang">
            <h2 id="judul-hilang" class="text-base font-semibold text-gray-900 dark:text-white">Berkas hilang dari penyimpanan</h2>
            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Masih tercatat di sistem, tapi berkasnya sudah tidak ada di server.</p>
            <?php if ($hilang === []): ?>
                <?= ui_peringatan('sukses', 'Semua dokumen punya berkasnya. Tidak ada yang hilang.') ?>
            <?php else: ?>
                <ul class="max-h-64 space-y-1.5 overflow-auto text-sm">
                    <?php foreach ($hilang as $h): ?>
                        <li class="flex items-center gap-2">
                            <a href="<?= site_url('admin/dokumen/pratinjau/' . $h['id']) ?>" class="truncate font-medium text-brand-700 hover:underline dark:text-brand-400"><?= esc($h['judul']) ?></a>
                            <span class="ms-auto shrink-0 font-mono text-[10px] text-gray-500 dark:text-gray-400"><?= esc((string) $h['path_rel']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="hint">Unggah ulang berkasnya, atau hapus catatannya lewat tempat sampah.</p>
            <?php endif; ?>
        </section>

        <section class="kartu kartu-isi<?= $yatim !== [] ? ' border-yellow-300 dark:border-yellow-800' : '' ?>" aria-labelledby="judul-yatim">
            <h2 id="judul-yatim" class="text-base font-semibold text-gray-900 dark:text-white">Berkas yatim di server</h2>
            <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Menumpuk di penyimpanan tapi tidak tercatat di sistem.</p>
            <?php if ($yatim === []): ?>
                <?= ui_peringatan('sukses', 'Tidak ada berkas yatim. Penyimpanan bersih.') ?>
            <?php else: ?>
                <p class="mb-2 text-sm text-gray-700 dark:text-gray-300"><b><?= count($yatim) ?></b> berkas · <b><?= esc(dokumen_ukuran_manusia($yatimByte)) ?></b> bisa dibebaskan.</p>
                <ul class="mb-3 max-h-48 space-y-1 overflow-auto font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    <?php foreach (array_slice($yatim, 0, 50) as $y): ?>
                        <li class="flex justify-between gap-2"><span class="truncate"><?= esc($y['path']) ?></span><span class="shrink-0"><?= esc(dokumen_ukuran_manusia((int) $y['ukuran'])) ?></span></li>
                    <?php endforeach; ?>
                    <?php if (count($yatim) > 50): ?><li>… dan <?= count($yatim) - 50 ?> berkas lain</li><?php endif; ?>
                </ul>
                <form method="post" action="<?= site_url('admin/dokumen/penyimpanan/bersihkan') ?>"
                      data-confirm="Hapus <?= count($yatim) ?> berkas yatim dari server? Berkas ini tidak tercatat di sistem dan tidak bisa dikembalikan." data-confirm-judul="Hapus berkas yatim?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus berkas yatim</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
