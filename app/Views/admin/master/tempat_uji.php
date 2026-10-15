<?php
/**
 * Halaman Master Tempat Uji UKK — pola halaman data template Flowbite (rombak tampilan F5).
 *
 * @var string                        $q       Kata kunci pencarian
 * @var int                           $per     Baris per halaman
 * @var array                         $rows    Baris tempat uji halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager   Paginasi
 * @var int                           $total   Total seluruh data
 * @var array<int,string>             $labOpts Opsi lab (untuk tautan)
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'tempat_uji',
    'helpTitle' => 'Master Tempat Uji UKK',
    'helpKelas' => '',
    'helpBody'  => '<p>Lokasi pelaksanaan Uji Kompetensi Keahlian (UKK). Boleh <b>lab sekolah</b> (tautkan ke Master Laboratorium) atau <b>lokasi luar</b> seperti bengkel/kantor mitra DUDI.</p>
        <p class="mt-1">Tempat uji dipakai saat membuat <b>Jadwal UKK</b>.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/tempat-uji') ?>" data-entity="tempat uji"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'alamat' => '',
         'kapasitas' => '', 'lab_id' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/tempat-uji'),
        'cari'        => 'Cari kode, nama, atau alamat…',
        'q'           => $q,
        'per'         => $per,
        'eksporUrl'   => site_url('admin/master/tempat-uji/export'),
        'eksporTitle' => 'Keluarkan semua tempat uji ke file Excel',
        'massalUrl'   => site_url('admin/master/tempat-uji/bulk-delete'),
        'massalLabel' => 'tempat uji',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua tempat uji di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Alamat</th>
                    <th scope="col" class="text-center">Kapasitas</th>
                    <th scope="col">Lab tertaut</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7">
                            <?= ui_kosong(['ikon' => 'map-pin', 'judul' => $q !== '' ? 'Tempat uji tidak ditemukan' : 'Belum ada data tempat uji',
                                'teks' => $q !== '' ? 'Tidak ada tempat uji yang cocok dengan pencarian ini. Tekan Reset.' : 'Tambah tempat uji satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih tempat uji <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td class="font-medium"><?= esc($r['nama']) ?></td>
                        <td class="sel-ket"><?= $r['alamat'] !== null && $r['alamat'] !== '' ? esc($r['alamat']) : '—' ?></td>
                        <td class="sel-ket text-center"><?= $r['kapasitas'] !== null ? (int) $r['kapasitas'] : '—' ?></td>
                        <td><?= $r['lab_nama'] !== null ? esc($r['lab_nama']) : '<span class="sel-ket">—</span>' ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/tempat-uji/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus tempat uji ' . $r['nama'] . '?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'satuan' => 'tempat uji']) ?>

    <?= ui_crud_form_buka(['benda' => 'Tempat Uji', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="tu-kode">Kode <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="tu-kode" name="kode" x-model="form.kode" maxlength="30" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="tu-kapasitas">Kapasitas</label>
                <input type="number" id="tu-kapasitas" name="kapasitas" x-model="form.kapasitas" min="0" max="1000" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="tu-nama">Nama <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="tu-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="tu-alamat">Alamat</label>
                <input type="text" id="tu-alamat" name="alamat" x-model="form.alamat" maxlength="255" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="tu-lab">Tautkan ke lab</label>
                <select id="tu-lab" name="lab_id" x-model="form.lab_id" class="inp">
                    <option value="">— Bukan di lab sekolah / tak ditautkan —</option>
                    <?php foreach ($labOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="tu-ket">Keterangan</label>
                <input type="text" id="tu-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Tempat Uji',
        'aksi'        => site_url('admin/master/tempat-uji/import-preview'),
        'templateUrl' => site_url('admin/master/tempat-uji/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
