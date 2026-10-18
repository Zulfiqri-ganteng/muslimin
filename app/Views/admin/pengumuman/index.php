<?php
/**
 * Pengumuman — pola halaman data template Flowbite (rombak tampilan F10: mode CRUD, komponen "pengumumanPage" =
 * masterList di assets/js/admin/pengumuman.js — dulu fungsi global pengPage() di skrip sebaris + @click berisi JSON;
 * ui_crud_alat, tabel-crud, status bertitik, ui_crud_aksi, ui_crud_kaki, dialog formulir kit) → siap CSP.
 * P2b rombak publik: + kategori, + gambar opsional (dialog multipart + pratinjau, centang "Hapus gambar" saat mengubah).
 *
 * @var list<array> $rows     semua pengumuman (terbaru dulu) + 'url' gambar (null bila tanpa gambar)
 * @var list<string> $kategori PengumumanModel::KATEGORI
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
    'helpBody'  => '<p>Pengumuman yang berstatus <b>Aktif</b> tampil di situs publik sekolah: kartu <b>Informasi untuk Anda</b> di beranda dan halaman Pengumuman. Cocok untuk info libur, kegiatan, atau pengumuman jadwal.</p>'
        . '<p class="mt-2"><b>Kategori</b> tampil sebagai label di kartu. <b>Gambar</b> boleh dikosongkan (kartu memakai logo sekolah); bila diisi, pakai foto/poster mendatar. '
        . 'JPG, PNG, atau WEBP maks 5 MB — diperkecil dan diubah ke WEBP otomatis.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="pengumumanPage" data-base="<?= $base ?>" data-entity="pengumuman"
     data-defaults="<?= esc((string) json_encode(['id' => '', 'judul' => '', 'isi' => '', 'kategori' => $kategori[0], 'is_aktif' => true, 'url' => '', 'hapus_gambar' => false]), 'attr') ?>">

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
                    <th scope="col">Kategori</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5"><?= ui_kosong(['ikon' => 'bullhorn', 'judul' => 'Belum ada pengumuman', 'teks' => 'Klik Tambah pengumuman untuk membuat yang pertama.']) ?></td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <?php if ($r['url']): ?>
                                    <img src="<?= esc($r['url'], 'attr') ?>" alt="" loading="lazy"
                                         class="aspect-[4/3] w-16 shrink-0 rounded-lg border border-gray-200 bg-gray-100 object-cover dark:border-gray-700 dark:bg-gray-800">
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="sel-judul"><?= esc($r['judul']) ?></p>
                                    <p class="sel-ket max-w-md truncate"><?= esc(mb_substr($r['isi'], 0, 80)) ?></p>
                                </div>
                            </div>
                        </td>
                        <td><span class="lencana-merek"><?= esc((string) ($r['kategori'] ?? $kategori[0])) ?></span></td>
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
                                'row'        => ['id' => $r['id'], 'judul' => $r['judul'], 'isi' => $r['isi'], 'kategori' => $r['kategori'] ?? $kategori[0],
                                    'is_aktif' => $r['is_aktif'], 'url' => $r['url']],
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

    <?= ui_crud_form_buka(['benda' => 'Pengumuman', 'ukuran' => 'lg', 'enctype' => 'multipart/form-data']) ?>
        <div class="grid gap-4 sm:grid-cols-[1fr_11rem]">
            <div>
                <label class="lbl" for="pg-judul">Judul <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="pg-judul" name="judul" x-model="form.judul" maxlength="200" required class="inp">
            </div>
            <div>
                <label class="lbl" for="pg-kategori">Kategori</label>
                <select id="pg-kategori" name="kategori" x-model="form.kategori" class="inp">
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?= esc($k, 'attr') ?>"><?= esc($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="lbl" for="pg-isi">Isi <span class="text-red-600 dark:text-red-400">*</span></label>
            <textarea id="pg-isi" name="isi" x-model="form.isi" rows="5" required class="inp"></textarea>
        </div>
        <div>
            <label class="lbl" for="pg-gambar">Gambar <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span></label>
            <img :src="pratinjau" x-show="pratinjau" x-cloak alt="Pratinjau gambar"
                 class="mb-3 aspect-[16/9] w-full max-w-sm rounded-lg border border-gray-200 bg-gray-100 object-cover dark:border-gray-700 dark:bg-gray-800">
            <input type="file" id="pg-gambar" name="gambar" accept="image/jpeg,image/png,image/webp" @change="pilihGambar" class="inp-berkas">
            <p class="hint mt-1">JPG, PNG, atau WEBP, maks 5 MB. Kosong = kartu memakai logo sekolah.</p>
            <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300" x-show="punyaGambarLama" x-cloak>
                <input type="checkbox" name="hapus_gambar" value="1" x-model="form.hapus_gambar" class="cek">
                Hapus gambar yang tersimpan
            </label>
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
