<?php
/**
 * Pengumuman — pola halaman data template Flowbite (rombak tampilan F10: mode CRUD, komponen "pengumumanPage" =
 * masterList di assets/js/admin/pengumuman.js — dulu fungsi global pengPage() di skrip sebaris + @click berisi JSON;
 * ui_crud_alat, tabel-crud, status bertitik, ui_crud_aksi, ui_crud_kaki, dialog formulir kit) → siap CSP.
 *
 * @var list<array> $rows semua pengumuman (terbaru dulu)
 */
$base = site_url('admin/pengumuman');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'pengumuman',
    'helpTitle' => 'Pengumuman',
    'helpKelas' => '',
    'helpBody'  => '<p>Pengumuman yang berstatus <b>Aktif</b> akan tampil di beranda situs publik sekolah. Cocok untuk info libur, kegiatan, atau pengumuman jadwal.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="pengumumanPage" data-base="<?= $base ?>" data-entity="pengumuman"
     data-defaults="<?= esc((string) json_encode(['id' => '', 'judul' => '', 'isi' => '', 'is_aktif' => true]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => $base,
        'cari'        => null,
        'kiriHtml'    => '<p class="text-sm text-gray-500 dark:text-gray-400">Kelola pengumuman yang tampil di beranda publik.</p>',
        'labelTambah' => 'Tambah pengumuman',
        'impor'       => false,
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Judul</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="4"><?= ui_kosong(['ikon' => 'bullhorn', 'judul' => 'Belum ada pengumuman', 'teks' => 'Klik Tambah pengumuman untuk membuat yang pertama.']) ?></td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <p class="sel-judul"><?= esc($r['judul']) ?></p>
                            <p class="sel-ket max-w-md truncate"><?= esc(mb_substr($r['isi'], 0, 80)) ?></p>
                        </td>
                        <td>
                            <?php if ($r['is_aktif']): ?>
                                <span class="titik-status titik-status-hijau font-medium">Aktif</span>
                            <?php else: ?>
                                <span class="titik-status font-medium">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap"><?= esc(date('d-m-Y', strtotime($r['created_at']))) ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => ['id' => $r['id'], 'judul' => $r['judul'], 'isi' => $r['isi'], 'is_aktif' => $r['is_aktif']],
                                'hapusUrl'   => $base . '/delete/' . (int) $r['id'],
                                'konfirmasi' => 'Hapus pengumuman "' . $r['judul'] . '"?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rows), 'satuan' => 'pengumuman']) ?>

    <?= ui_crud_form_buka(['benda' => 'Pengumuman', 'ukuran' => 'lg']) ?>
        <div>
            <label class="lbl" for="pg-judul">Judul <span class="text-red-600">*</span></label>
            <input type="text" id="pg-judul" name="judul" x-model="form.judul" maxlength="200" required class="inp">
        </div>
        <div>
            <label class="lbl" for="pg-isi">Isi <span class="text-red-600">*</span></label>
            <textarea id="pg-isi" name="isi" x-model="form.isi" rows="5" required class="inp"></textarea>
        </div>
        <label class="saklar">
            <input type="checkbox" name="is_aktif" value="1" x-model="form.is_aktif" class="peer sr-only">
            <span class="saklar-jalur"></span>
            <span class="saklar-label">Tampilkan di beranda publik (aktif)</span>
        </label>
    <?= ui_crud_form_tutup() ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/pengumuman.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pengumuman.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
