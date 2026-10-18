<?php
/**
 * Foto Sekolah — foto asli sekolah untuk situs publik (banner beranda, pita "Tentang sekolah", galeri Profil). Pola halaman data
 * template Flowbite seperti Pengumuman: ui_crud_alat, tabel-crud, ui_crud_aksi, dialog formulir kit (multipart) — komponen
 * "fotoSekolahPage" = masterList + pratinjau foto di assets/js/admin/foto-sekolah.js → siap CSP.
 *
 * @var list<array> $rows semua foto (urutan kecil dulu) + 'url' (null bila berkas hilang)
 */
$base = site_url('admin/foto-sekolah');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'foto-sekolah',
    'helpTitle' => 'Foto Sekolah',
    'helpKelas' => '',
    'helpBody'  => '<p>Foto yang berstatus <b>Aktif</b> tampil di situs publik sekolah: di <b>banner geser beranda</b> (bila "Tampilkan di banner" menyala), '
        . 'di bagian <b>Tentang sekolah</b> beranda (foto pertama), dan di <b>galeri halaman Profil</b>.</p>'
        . '<p class="mt-2">Pakai foto asli sekolah yang <b>mendatar (landscape)</b>, lebar minimal ±1600 px, dengan bagian penting di tengah — tepi foto '
        . 'bisa terpotong di layar HP. Berkas JPG, PNG, atau WEBP maks 5 MB; diperkecil dan diubah ke WEBP otomatis supaya ringan.</p>'
        . '<p class="mt-2"><b>Urutan</b>: angka kecil tampil lebih dulu. Jangan unggah foto yang memperlihatkan wajah siswa dengan jelas tanpa izin.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="fotoSekolahPage" data-base="<?= $base ?>" data-entity="foto"
     data-defaults="<?= esc((string) json_encode(['id' => '', 'judul' => '', 'keterangan' => '', 'tautan' => '', 'urutan' => 0, 'tampil_banner' => true, 'is_aktif' => true, 'url' => '']), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => $base,
        'cari'        => null,
        'kiriHtml'    => '<p class="text-sm text-gray-500 dark:text-gray-400">Foto untuk banner beranda dan halaman Profil di situs publik.</p>',
        'labelTambah' => 'Tambah foto',
        'impor'       => false,
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Foto</th>
                    <th scope="col">Judul</th>
                    <th scope="col">Tampil</th>
                    <th scope="col">Urutan</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5"><?= ui_kosong(['ikon' => 'image', 'judul' => 'Belum ada foto sekolah', 'teks' => 'Klik Tambah foto. Selama kosong, banner beranda tetap tampil berisi sorotan teks.']) ?></td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="w-32">
                            <?php if ($r['url']): ?>
                                <a href="<?= esc($r['url'], 'attr') ?>" target="_blank" rel="noopener" class="block w-28" title="Buka foto ukuran penuh">
                                    <img src="<?= esc($r['url'], 'attr') ?>" alt="<?= esc($r['judul'], 'attr') ?>" loading="lazy"
                                         class="aspect-[16/9] w-28 rounded-lg border border-gray-200 bg-gray-100 object-cover dark:border-gray-700 dark:bg-gray-800">
                                </a>
                            <?php else: ?>
                                <span class="lencana-merah">Berkas hilang</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <p class="sel-judul"><?= esc($r['judul']) ?></p>
                            <?php if (! empty($r['keterangan'])): ?>
                                <p class="sel-ket max-w-md truncate"><?= esc($r['keterangan']) ?></p>
                            <?php endif; ?>
                            <?php if (! empty($r['tautan'])): ?>
                                <p class="sel-ket max-w-md truncate"><?= ikon('link', 'mr-1 inline h-3.5 w-3.5') ?><?= esc($r['tautan']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                <?php if ($r['is_aktif']): ?>
                                    <span class="titik-status titik-status-hijau font-medium">Aktif</span>
                                <?php else: ?>
                                    <span class="titik-status font-medium">Nonaktif</span>
                                <?php endif; ?>
                                <?php if ($r['tampil_banner']): ?>
                                    <span class="lencana-merek">Banner</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="tabular-nums"><?= (int) $r['urutan'] ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => ['id' => $r['id'], 'judul' => $r['judul'], 'keterangan' => $r['keterangan'], 'tautan' => $r['tautan'],
                                    'urutan' => $r['urutan'], 'tampil_banner' => $r['tampil_banner'], 'is_aktif' => $r['is_aktif'], 'url' => $r['url']],
                                'hapusUrl'   => $base . '/delete/' . (int) $r['id'],
                                'konfirmasi' => 'Hapus foto "' . $r['judul'] . '"? Berkas fotonya ikut dihapus.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rows), 'satuan' => 'foto']) ?>

    <?= ui_crud_form_buka(['benda' => 'Foto', 'ukuran' => 'lg', 'enctype' => 'multipart/form-data']) ?>
        <div>
            <label class="lbl" for="fs-foto">Berkas foto <span class="text-red-600 dark:text-red-400" x-show="mode === 'add'">*</span></label>
            <img :src="pratinjau" x-show="pratinjau" x-cloak alt="Pratinjau foto"
                 class="mb-3 aspect-[16/7] w-full rounded-lg border border-gray-200 bg-gray-100 object-cover dark:border-gray-700 dark:bg-gray-800">
            <input type="file" id="fs-foto" name="foto" accept="image/jpeg,image/png,image/webp" :required="mode === 'add'" @change="pilihFoto" class="inp-berkas">
            <p class="hint mt-1" x-show="mode === 'add'">JPG, PNG, atau WEBP, maks 5 MB. Foto mendatar lebar ±1600 px paling bagus untuk banner.</p>
            <p class="hint mt-1" x-show="mode !== 'add'" x-cloak>Kosongkan bila tidak ingin mengganti foto.</p>
        </div>
        <div>
            <label class="lbl" for="fs-judul">Judul <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="text" id="fs-judul" name="judul" x-model="form.judul" maxlength="150" required class="inp" placeholder="Contoh: Upacara Hari Pendidikan Nasional">
            <p class="hint mt-1">Tampil sebagai keterangan foto di banner dan galeri.</p>
        </div>
        <div>
            <label class="lbl" for="fs-ket">Keterangan</label>
            <input type="text" id="fs-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp" placeholder="Satu kalimat (opsional)">
        </div>
        <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
            <div>
                <label class="lbl" for="fs-tautan">Tautan</label>
                <input type="url" id="fs-tautan" name="tautan" x-model="form.tautan" maxlength="255" class="inp" placeholder="https://… (opsional)">
                <p class="hint mt-1">Bila diisi, banner menampilkan tautan "Selengkapnya".</p>
            </div>
            <div>
                <label class="lbl" for="fs-urutan">Urutan</label>
                <input type="number" id="fs-urutan" name="urutan" x-model="form.urutan" min="0" max="999" class="inp">
            </div>
        </div>
        <label class="saklar">
            <input type="checkbox" name="tampil_banner" value="1" x-model="form.tampil_banner" class="peer sr-only">
            <span class="saklar-jalur"></span>
            <span class="saklar-label">Tampilkan di banner beranda</span>
        </label>
        <label class="saklar">
            <input type="checkbox" name="is_aktif" value="1" x-model="form.is_aktif" class="peer sr-only">
            <span class="saklar-jalur"></span>
            <span class="saklar-label">Tampilkan di situs publik (aktif)</span>
        </label>
    <?= ui_crud_form_tutup() ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/foto-sekolah.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/foto-sekolah.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
