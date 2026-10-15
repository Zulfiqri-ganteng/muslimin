<?php

/**
 * Tempat sampah Manajemen Dokumen. Rombak tampilan F10: Kembali / Kosongkan sampah (merah) di kepala halaman, kartu kit, tabel
 * kit, lencana jenis, tombol kit ber-ikon (Unduh, Pulihkan hijau, Hapus permanen merah), konfirmasi = data-confirm kit (dulu
 * onsubmit confirm), ui_kosong, paginasi kit.
 *
 * @var array                         $rows    Dokumen terhapus (halaman ini)
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var array                         $folders Folder terhapus
 */
helper('dokumen');

$tgl = static fn ($d) => $d ? date('d/m/Y H:i', strtotime((string) $d)) : '—';
$tautanHal = $pager !== null ? trim((string) $pager->links('dok', 'admin')) : '';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/dokumen') ?>" class="btn btn-netral"><?= ikon('chevron-left', 'h-4 w-4') ?>Kembali ke Dokumen</a>
<?php if ($rows !== [] || $folders !== []): ?>
    <form method="post" action="<?= site_url('admin/dokumen/sampah/kosongkan') ?>"
          data-confirm="Kosongkan tempat sampah? Seluruh berkas di dalamnya dihapus PERMANEN dari server dan tidak bisa dikembalikan." data-confirm-judul="Kosongkan sampah?" data-confirm-ya="Ya, kosongkan" data-confirm-jenis="bahaya">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Kosongkan sampah</button>
    </form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'dokumen_sampah',
    'helpTitle' => 'Tempat Sampah Dokumen',
    'helpBody'  => '<p>Berkas dan folder yang dihapus ditampung di sini lebih dulu — belum benar-benar hilang, jadi masih bisa <b>dipulihkan</b>.</p>
        <p class="mt-1">• <b>Memulihkan folder</b> sekaligus memulihkan seluruh isi yang dulu ikut terhapus bersamanya.<br>
        • Bila folder asal sebuah berkas masih berada di tempat sampah, berkas yang dipulihkan akan ditaruh di folder utama.<br>
        • <b>Hapus permanen</b> membuang berkas dari penyimpanan server dan <b>tidak bisa dibatalkan</b>.<br>
        • Ruang penyimpanan baru benar-benar kembali setelah berkas dihapus permanen.</p>',
]) ?>

<div class="space-y-4">
    <?php if ($folders !== []): ?>
        <section class="kartu overflow-hidden" aria-labelledby="judul-folder-sampah">
            <div class="kartu-kepala"><h2 id="judul-folder-sampah" class="kartu-judul text-base">Folder terhapus</h2></div>
            <div class="kartu-isi grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($folders as $f): ?>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 p-2.5 dark:border-gray-700">
                        <?= ikon('folder-open', 'h-7 w-7 shrink-0 text-gray-500 dark:text-gray-400', 'solid') ?>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white"><?= esc($f['nama']) ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Dibuang <?= esc($tgl($f['deleted_at'])) ?></p>
                        </div>
                        <form method="post" action="<?= site_url('admin/dokumen/folder/' . $f['id'] . '/pulihkan') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sukses btn-sm"><?= ikon('clock-arrow', 'h-4 w-4') ?>Pulihkan</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="kartu overflow-hidden" aria-label="Berkas terhapus">
        <?php if ($rows === []): ?>
            <?= ui_kosong(['ikon' => 'trash-bin', 'judul' => 'Tempat sampah kosong']) ?>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col" class="hidden md:table-cell">Jenis</th>
                            <th scope="col" class="hidden text-right sm:table-cell">Ukuran</th>
                            <th scope="col" class="hidden lg:table-cell">Dibuang</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <span class="font-medium text-gray-900 dark:text-white"><?= esc($r['judul']) ?></span>
                                    <?php if ($r['nama_asli']): ?>
                                        <span class="block max-w-[280px] truncate text-xs text-gray-500 dark:text-gray-400"><?= esc($r['nama_asli']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="hidden md:table-cell"><span class="lencana lencana-abu"><?= esc(strtoupper($r['ekstensi'] ?: $r['kategori'])) ?></span></td>
                                <td class="hidden text-right tabular-nums sm:table-cell"><?= $r['tipe'] === 'tautan' ? '—' : esc(dokumen_ukuran_manusia((int) $r['ukuran'])) ?></td>
                                <td class="hidden text-xs lg:table-cell"><?= esc($tgl($r['deleted_at'])) ?></td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($r['tipe'] !== 'tautan'): ?>
                                            <a href="<?= site_url('admin/dokumen/unduh/' . $r['id']) ?>" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh</a>
                                        <?php endif; ?>
                                        <form method="post" action="<?= site_url('admin/dokumen/' . $r['id'] . '/pulihkan') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sukses btn-sm"><?= ikon('clock-arrow', 'h-4 w-4') ?>Pulihkan</button>
                                        </form>
                                        <form method="post" action="<?= site_url('admin/dokumen/' . $r['id'] . '/hapus-permanen') ?>"
                                              data-confirm="Hapus permanen &quot;<?= esc($r['judul'], 'attr') ?>&quot;? Berkasnya dibuang dari server dan TIDAK BISA dikembalikan." data-confirm-judul="Hapus permanen?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-bahaya btn-sm"><?= ikon('trash-bin', 'h-4 w-4') ?>Hapus permanen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($tautanHal !== ''): ?><div class="kartu-kaki"><?= $tautanHal ?></div><?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>
