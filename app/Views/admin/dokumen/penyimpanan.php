<?php

/**
 * Penggunaan penyimpanan + pemeriksaan kesehatan arsip dokumen.
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
    'pdf'         => 'bg-red-500',
    'dokumen'     => 'bg-blue-500',
    'spreadsheet' => 'bg-emerald-500',
    'presentasi'  => 'bg-orange-500',
    'gambar'      => 'bg-violet-500',
    'audio'       => 'bg-pink-500',
    'video'       => 'bg-rose-500',
    'arsip'       => 'bg-amber-500',
    'lainnya'     => 'bg-slate-400',
];
$maksKategori = 0;
foreach ($pemakaian['per_kategori'] as $k) {
    $maksKategori = max($maksKategori, (int) $k['byte_total']);
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dokumen_penyimpanan',
    'helpTitle' => 'Penggunaan Penyimpanan',
    'helpBody'  => '<p>Ringkasan ruang yang dipakai arsip dokumen, beserta pemeriksaan kesehatan arsip.</p>
        <p class="mt-1">• <b>Berkas hilang</b> = datanya masih tercatat tapi berkasnya sudah tidak ada di server (mis. terhapus manual lewat File Manager). Dokumen seperti ini tidak bisa diunduh.<br>
        • <b>Berkas yatim</b> = berkas menumpuk di server tapi tidak lagi tercatat di sistem. Aman dihapus, dan ruangnya langsung kembali.<br>
        • Ruang dari berkas di <b>Tempat Sampah</b> baru benar-benar kembali setelah dihapus permanen.</p>',
]) ?>

<div class="flex items-center gap-2 mb-4 flex-wrap">
    <a href="<?= site_url('admin/dokumen') ?>"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Dokumen
    </a>
    <a href="<?= site_url('admin/settings') ?>" class="px-3 py-2 rounded-xl border border-slate-300 text-sm font-medium text-slate-600 hover:bg-slate-50">Ubah pagu di Pengaturan</a>
</div>

<!-- Ringkasan -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs text-slate-500">Terpakai</p>
        <p class="text-2xl font-extrabold text-slate-800 mt-1"><?= esc(dokumen_ukuran_manusia($pakai)) ?></p>
        <p class="text-xs text-slate-400 mt-0.5">dari pagu <?= esc(dokumen_ukuran_manusia($kuotaByte)) ?></p>
        <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full <?= $persen >= 90 ? 'bg-red-500' : ($persen >= 70 ? 'bg-amber-500' : 'bg-brand-600') ?>" style="width: <?= $persen ?>%"></div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs text-slate-500">Jumlah berkas</p>
        <p class="text-2xl font-extrabold text-slate-800 mt-1"><?= (int) $pemakaian['total_berkas'] ?></p>
        <p class="text-xs text-slate-400 mt-0.5">batas <?= esc(dokumen_ukuran_manusia($maksByte)) ?> per berkas</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs text-slate-500">Tertahan di tempat sampah</p>
        <p class="text-2xl font-extrabold text-slate-800 mt-1"><?= esc(dokumen_ukuran_manusia((int) $pemakaian['sampah_byte'])) ?></p>
        <p class="text-xs text-slate-400 mt-0.5">kembali setelah dihapus permanen</p>
    </div>
</div>

<?php if ($persen >= 80) { ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 mb-4">
        <p class="text-sm font-semibold text-amber-800">Penyimpanan hampir penuh (<?= $persen ?>%)</p>
        <p class="text-xs text-amber-700 mt-1">Kosongkan tempat sampah, hapus berkas yatim, atau naikkan pagu di Pengaturan bila kuota disk hosting memang masih lega.</p>
    </div>
<?php } ?>

<!-- Per kategori -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-4">
    <h3 class="font-semibold text-slate-800 text-sm mb-3">Pemakaian per jenis berkas</h3>
    <?php if ($pemakaian['per_kategori'] === []) { ?>
        <p class="text-sm text-slate-400 py-4 text-center">Belum ada berkas tersimpan.</p>
    <?php } else { ?>
        <div class="space-y-2.5">
            <?php foreach ($pemakaian['per_kategori'] as $k) {
                $b = (int) $k['byte_total'];
                $w = $maksKategori > 0 ? max(2, round($b / $maksKategori * 100)) : 0;
                ?>
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-medium text-slate-700"><?= esc(ucfirst((string) $k['kategori'])) ?> <span class="text-slate-400">(<?= (int) $k['jml'] ?> berkas)</span></span>
                        <span class="text-slate-500"><?= esc(dokumen_ukuran_manusia($b)) ?></span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full <?= $warnaKategori[$k['kategori']] ?? $warnaKategori['lainnya'] ?>" style="width: <?= $w ?>%"></div>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<!-- Kesehatan arsip -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl border <?= $hilang !== [] ? 'border-red-200' : 'border-slate-200' ?> shadow-sm p-4">
        <h3 class="font-semibold text-slate-800 text-sm mb-1">Berkas hilang dari penyimpanan</h3>
        <p class="text-xs text-slate-500 mb-3">Masih tercatat di sistem, tapi berkasnya sudah tidak ada di server.</p>
        <?php if ($hilang === []) { ?>
            <p class="text-sm text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2">Semua dokumen punya berkasnya. Tidak ada yang hilang.</p>
        <?php } else { ?>
            <ul class="space-y-1.5 text-xs max-h-64 overflow-auto">
                <?php foreach ($hilang as $h) { ?>
                    <li class="flex items-center gap-2">
                        <a href="<?= site_url('admin/dokumen/pratinjau/' . $h['id']) ?>" class="text-brand-700 hover:underline truncate"><?= esc($h['judul']) ?></a>
                        <span class="text-slate-400 ml-auto shrink-0 font-mono text-[10px]"><?= esc((string) $h['path_rel']) ?></span>
                    </li>
                <?php } ?>
            </ul>
            <p class="text-[11px] text-slate-500 mt-2">Unggah ulang berkasnya, atau hapus catatannya lewat tempat sampah.</p>
        <?php } ?>
    </div>

    <div class="bg-white rounded-2xl border <?= $yatim !== [] ? 'border-amber-200' : 'border-slate-200' ?> shadow-sm p-4">
        <h3 class="font-semibold text-slate-800 text-sm mb-1">Berkas yatim di server</h3>
        <p class="text-xs text-slate-500 mb-3">Menumpuk di penyimpanan tapi tidak tercatat di sistem.</p>
        <?php if ($yatim === []) { ?>
            <p class="text-sm text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2">Tidak ada berkas yatim. Penyimpanan bersih.</p>
        <?php } else { ?>
            <p class="text-sm text-slate-700 mb-2"><b><?= count($yatim) ?></b> berkas · <b><?= esc(dokumen_ukuran_manusia($yatimByte)) ?></b> bisa dibebaskan.</p>
            <ul class="space-y-1 text-[11px] font-mono text-slate-500 max-h-48 overflow-auto mb-3">
                <?php foreach (array_slice($yatim, 0, 50) as $y) { ?>
                    <li class="flex justify-between gap-2"><span class="truncate"><?= esc($y['path']) ?></span><span class="shrink-0"><?= esc(dokumen_ukuran_manusia((int) $y['ukuran'])) ?></span></li>
                <?php } ?>
                <?php if (count($yatim) > 50) { ?><li class="text-slate-400">… dan <?= count($yatim) - 50 ?> berkas lain</li><?php } ?>
            </ul>
            <form method="post" action="<?= site_url('admin/dokumen/penyimpanan/bersihkan') ?>"
                  onsubmit="return confirm('Hapus <?= count($yatim) ?> berkas yatim dari server? Berkas ini tidak tercatat di sistem dan tidak bisa dikembalikan.')">
                <?= csrf_field() ?>
                <button class="px-3 py-2 rounded-xl bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700">Hapus Berkas Yatim</button>
            </form>
        <?php } ?>
    </div>
</div>

<?= $this->endSection() ?>
