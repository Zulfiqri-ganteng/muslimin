<?php
/**
 * Halaman Master Hari — pola halaman data template Flowbite (rombak tampilan F5). Komponen "hariPage" (master/hari.js) = masterList
 * + pemetaan kolom aktif (0/1) ke kotak centang.
 *
 * @var string                         $q      Kata kunci pencarian
 * @var string                         $status Filter status ('' | '1' | '0')
 * @var int                            $per    Baris per halaman
 * @var array                          $rows   Baris hari halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager  Paginasi
 * @var int                            $total  Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'hari',
    'helpTitle' => 'Master Hari',
    'helpKelas' => '',
    'helpBody'  => '<p>Hari yang berstatus <b>Aktif</b> akan muncul sebagai kolom pada grid Jadwal KBM nanti. Nonaktifkan hari yang tidak ada kegiatan belajar (mis. bila Sabtu libur). <b>Urutan</b> menentukan posisi tampil.</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, tampil pratinjau yang bisa diedit sebelum disimpan (nama hari sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — unduh seluruh hari ke Excel. • <b>Hapus terpilih / Hapus semua</b> muncul setelah baris dicentang.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="hariPage" data-base="<?= site_url('admin/master/hari') ?>" data-entity="hari"
     data-defaults="<?= esc(json_encode(['nama' => '', 'urutan' => 1, 'aktif' => true]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'          => site_url('admin/master/hari'),
        'cari'             => 'Cari nama hari…',
        'q'                => $q,
        'filter'           => [['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => ['1' => 'Aktif', '0' => 'Nonaktif']]],
        'per'              => $per,
        'eksporUrl'        => site_url('admin/master/hari/export'),
        'eksporTitle'      => 'Keluarkan semua hari ke file Excel',
        'massalUrl'        => site_url('admin/master/hari/bulk-delete'),
        'massalLabel'      => 'hari',
        'massalPeringatan' => 'Jadwal pada hari tersebut ikut terhapus.',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua hari di halaman ini"></th>
                    <th scope="col">Urutan</th>
                    <th scope="col">Nama hari</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5">
                            <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => $q !== '' || $status !== '' ? 'Hari tidak ditemukan' : 'Belum ada data hari',
                                'teks' => $q !== '' || $status !== '' ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Tambah hari, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih hari <?= esc($r['nama'], 'attr') ?>"></td>
                        <td class="sel-ket"><?= esc($r['urutan']) ?></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['nama']) ?></td>
                        <td>
                            <?php if ($r['aktif']): ?>
                                <span class="titik-status titik-status-hijau font-medium">Aktif</span>
                            <?php else: ?>
                                <span class="titik-status sel-ket">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/hari/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus hari ' . $r['nama'] . '? Jadwal pada hari tersebut ikut terhapus.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'status', 'per'], 'satuan' => 'hari']) ?>

    <?= ui_crud_form_buka(['benda' => 'Hari']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="hari-nama">Nama hari <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="hari-nama" name="nama" x-model="form.nama" maxlength="15" required class="inp" placeholder="mis. Senin">
            </div>
            <div>
                <label class="lbl" for="hari-urutan">Urutan <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="number" id="hari-urutan" name="urutan" x-model="form.urutan" min="1" required class="inp">
            </div>
        </div>
        <label class="flex items-center">
            <input type="checkbox" name="aktif" value="1" x-model="form.aktif" class="cek">
            <span class="cek-label">Aktif (tampil di grid jadwal)</span>
        </label>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Hari',
        'aksi'        => site_url('admin/master/hari/import-preview'),
        'templateUrl' => site_url('admin/master/hari/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Nama hari yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/hari.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/hari.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
