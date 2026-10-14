<?php
/**
 * Sub-halaman Detail Komputer untuk sebuah aset (aset_komputer 1:1) — pola kartu formulir template Flowbite (rombak tampilan F5).
 * Galat simpan tampil sebagai toast dari layout; isian yang sudah diketik dipertahankan (old()) bila simpan gagal.
 *
 * @var array $aset   Baris aset induk
 * @var array $detail Detail komputer saat ini (kosong bila belum ada)
 */
$v = static fn (string $k): string => (string) old($k, (string) ($detail[$k] ?? ''));
$fields = [
    ['hostname', 'Hostname / nama komputer', 'PC-LAB01-01'],
    ['processor', 'Processor', 'Intel Core i5-10400'],
    ['ram', 'RAM', '8 GB DDR4'],
    ['storage', 'Storage', 'SSD 256 GB'],
    ['gpu', 'GPU / VGA', 'Intel UHD 630'],
    ['os', 'Sistem operasi', 'Windows 11 Pro'],
    ['mac_address', 'MAC address', '00:1A:2B:3C:4D:5E'],
    ['ip_address', 'IP address', '192.168.1.10'],
    ['monitor', 'Monitor', 'LG 22" LED'],
];
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/master/aset') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Master Aset</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/master/aset/komputer/' . (int) $aset['id']) ?>" class="kartu max-w-3xl">
    <?= csrf_field() ?>
    <div class="kartu-kepala">
        <div>
            <h2 class="kartu-judul">Spesifikasi &amp; jaringan</h2>
            <p class="kartu-ket"><span class="font-mono font-semibold text-gray-900 dark:text-white"><?= esc($aset['nomor_aset']) ?></span> &middot; <?= esc($aset['nama']) ?></p>
        </div>
    </div>
    <div class="kartu-isi">
        <div class="grid gap-4 sm:grid-cols-2">
            <?php foreach ($fields as [$name, $label, $ph]): ?>
                <div>
                    <label class="lbl" for="kom-<?= $name ?>"><?= esc($label) ?></label>
                    <input type="text" id="kom-<?= $name ?>" name="<?= $name ?>" value="<?= esc($v($name), 'attr') ?>" maxlength="100" placeholder="<?= esc($ph, 'attr') ?>" class="inp">
                </div>
            <?php endforeach; ?>
            <div class="sm:col-span-2">
                <label class="lbl" for="kom-keterangan">Keterangan</label>
                <input type="text" id="kom-keterangan" name="keterangan" value="<?= esc($v('keterangan'), 'attr') ?>" maxlength="255" class="inp">
            </div>
        </div>
    </div>
    <div class="kartu-kaki flex flex-wrap justify-end gap-3">
        <a href="<?= site_url('admin/master/aset') ?>" class="btn btn-netral">Batal</a>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan detail</button>
    </div>
</form>
<?= $this->endSection() ?>
