<?php
/**
 * Audit Log — jejak aktivitas admin. Rombak tampilan F10: Bersihkan >90 hari (merah + dialog konfirmasi kit) di kepala halaman,
 * kartu kit dengan bilah cari (saringan aksi/tabel langsung), tabel kit, lencana aksi kit, ui_kosong, paginasi kit.
 *
 * @var list<array>                   $rows
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var int                           $total
 * @var string                        $q
 * @var string                        $aksi
 * @var string                        $tabel
 * @var list<string>                  $tabelList
 */
$badge = ['create' => 'lencana-hijau', 'update' => 'lencana-merek', 'delete' => 'lencana-merah', 'import' => 'lencana-biru'];
$labelAksi = ['create' => 'Tambah', 'update' => 'Ubah', 'delete' => 'Hapus', 'import' => 'Import'];
$adaSaring = $q !== '' || $aksi !== '' || $tabel !== '';
$tautanHal = $pager ? trim((string) $pager->only(['q', 'aksi', 'tabel'])->links('default', 'admin')) : '';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/audit/purge') ?>" class="btn btn-bahaya" data-method="post"
   data-confirm="Hapus semua log yang lebih lama dari 90 hari?" data-confirm-judul="Bersihkan log lama?" data-confirm-ya="Ya, bersihkan" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Bersihkan &gt;90 hari</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'audit',
    'helpTitle' => 'Audit Log',
    'helpBody'  => '<p>Jejak seluruh aktivitas admin (tambah/ubah/hapus/import) pada data sistem — beserta waktu, pelaku, dan alamat IP. Berguna untuk pelacakan & akuntabilitas. Gunakan tombol bersihkan untuk membuang log lama agar database tetap ringan.</p>',
]) ?>

<section class="kartu overflow-hidden" aria-labelledby="judul-audit">
    <div class="kartu-kepala">
        <h2 id="judul-audit" class="kartu-judul flex items-center gap-2">Riwayat aktivitas<span class="lencana lencana-abu"><?= (int) $total ?></span></h2>
    </div>

    <div class="alat-crud">
        <form method="get" class="alat-crud-cari" role="search">
            <label class="alat-crud-kotak">
                <span class="sr-only">Cari keterangan</span>
                <?= ikon('search', '') ?>
                <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari keterangan…" class="inp">
            </label>
            <select name="aksi" data-autosubmit class="inp alat-crud-pilih" aria-label="Aksi">
                <option value="">Semua aksi</option>
                <?php foreach ($labelAksi as $k => $v): ?>
                    <option value="<?= $k ?>"<?= $aksi === $k ? ' selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tabel" data-autosubmit class="inp alat-crud-pilih" aria-label="Tabel">
                <option value="">Semua tabel</option>
                <?php foreach ($tabelList as $t): ?>
                    <option value="<?= esc($t, 'attr') ?>"<?= $tabel === $t ? ' selected' : '' ?>><?= esc($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
            <?php if ($adaSaring): ?><a href="<?= site_url('admin/audit') ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a><?php endif; ?>
        </form>
    </div>

    <?php if (empty($rows)): ?>
        <?= ui_kosong(['ikon' => 'clipboard-list', 'judul' => $adaSaring ? 'Tidak ada aktivitas yang cocok' : 'Belum ada aktivitas tercatat',
            'teks' => $adaSaring ? 'Ubah pencarian/saringan, atau tekan Reset.' : '']) ?>
    <?php else: ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Admin</th>
                        <th scope="col">Aksi</th>
                        <th scope="col">Tabel</th>
                        <th scope="col">Keterangan</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="text-xs whitespace-nowrap tabular-nums"><?= esc(date('d-m-Y H:i', strtotime($r['created_at']))) ?></td>
                            <td class="font-medium text-gray-900 dark:text-white"><?= esc($r['admin_nama'] ?: '—') ?></td>
                            <td><span class="lencana <?= $badge[$r['aksi']] ?? 'lencana-abu' ?>"><?= esc($labelAksi[$r['aksi']] ?? $r['aksi']) ?></span></td>
                            <td class="text-xs whitespace-nowrap"><?= esc($r['tabel']) ?><?= $r['record_id'] ? ' #' . esc($r['record_id']) : '' ?></td>
                            <td><?= esc($r['deskripsi'] ?: '—') ?></td>
                            <td class="font-mono text-xs text-gray-500 dark:text-gray-400"><?= esc($r['ip_address'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($tautanHal !== ''): ?><div class="kartu-kaki"><?= $tautanHal ?></div><?php endif; ?>
    <?php endif; ?>
</section>

<?= $this->endSection() ?>
