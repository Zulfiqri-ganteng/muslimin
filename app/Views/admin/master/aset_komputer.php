<?php
/**
 * Sub-halaman Detail Komputer untuk sebuah aset (aset_komputer 1:1).
 *
 * @var array $aset   Baris aset induk
 * @var array $detail Detail komputer saat ini (kosong bila belum ada)
 */
$v = static fn (string $k) => esc((string) ($detail[$k] ?? ''), 'attr');
$fields = [
    ['hostname', 'Hostname / Nama Komputer', 'PC-LAB01-01'],
    ['processor', 'Processor', 'Intel Core i5-10400'],
    ['ram', 'RAM', '8 GB DDR4'],
    ['storage', 'Storage', 'SSD 256 GB'],
    ['gpu', 'GPU / VGA', 'Intel UHD 630'],
    ['os', 'Sistem Operasi', 'Windows 11 Pro'],
    ['mac_address', 'MAC Address', '00:1A:2B:3C:4D:5E'],
    ['ip_address', 'IP Address', '192.168.1.10'],
    ['monitor', 'Monitor', 'LG 22" LED'],
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <a href="<?= site_url('admin/master/aset') ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke Master Aset
    </a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-3xl">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
        <h2 class="font-bold text-slate-800">Detail Komputer</h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <span class="font-mono font-semibold text-brand-700"><?= esc($aset['nomor_aset']) ?></span>
            &middot; <?= esc($aset['nama']) ?>
        </p>
    </div>

    <?php if (session('errors')): ?>
        <div class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
            <?php foreach ((array) session('errors') as $e): ?>
                <div><?= esc($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('admin/master/aset/komputer/' . (int) $aset['id']) ?>" class="p-6">
        <?= csrf_field() ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($fields as [$name, $label, $ph]): ?>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1"><?= esc($label) ?></label>
                    <input type="text" name="<?= $name ?>" value="<?= $v($name) ?>" maxlength="100" placeholder="<?= esc($ph, 'attr') ?>"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                </div>
            <?php endforeach; ?>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-600 mb-1">Keterangan</label>
                <input type="text" name="keterangan" value="<?= $v('keterangan') ?>" maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
            <a href="<?= site_url('admin/master/aset') ?>" class="rounded-lg border border-slate-300 text-slate-600 text-sm font-semibold px-4 py-2.5 hover:bg-slate-50">Batal</a>
            <button class="rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2.5">Simpan Detail</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
