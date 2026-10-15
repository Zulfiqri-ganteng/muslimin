<?php
/**
 * Halaman Master Fase (Kurikulum Merdeka) — pola halaman data template Flowbite (rombak tampilan F5, sama dengan Master Jurusan).
 *
 * @var string                         $q     Kata kunci pencarian
 * @var int                            $per   Baris per halaman
 * @var array                          $rows  Baris fase halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager Paginasi
 * @var int                            $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'fase',
    'helpTitle' => 'Master Fase',
    'helpKelas' => '',
    'helpBody'  => '<p>Fase Kurikulum Merdeka (A-F) — dasar pengelompokan <b>Capaian Pembelajaran</b> per mata pelajaran. Untuk SMK umumnya dipakai <b>Fase E</b> (kelas X) dan <b>Fase F</b> (kelas XI-XII).</p>
        <p class="mt-1">Setiap kelas di menu <b>Master Data ▸ Kelas</b> sudah otomatis ditandai fasenya berdasar tingkat — bisa diubah manual di sana bila ada pengecualian.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/fase') ?>" data-entity="fase"
     data-defaults="<?= esc(json_encode(['kode' => '', 'nama' => '', 'urutan' => '', 'deskripsi' => '']), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/fase'),
        'cari'        => 'Cari kode atau nama fase…',
        'q'           => $q,
        'per'         => $per,
        'eksporUrl'   => site_url('admin/master/fase/export'),
        'eksporTitle' => 'Keluarkan semua fase ke file Excel',
        'massalUrl'   => site_url('admin/master/fase/bulk-delete'),
        'massalLabel' => 'fase',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua fase di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama fase</th>
                    <th scope="col">Deskripsi</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5">
                            <?= ui_kosong(['ikon' => 'layers', 'judul' => $q !== '' ? 'Fase tidak ditemukan' : 'Belum ada data fase',
                                'teks' => $q !== '' ? 'Coba kata kunci lain, atau tekan Reset.' : 'Tambah fase satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih fase <?= esc($r['kode'], 'attr') ?>"></td>
                        <td><span class="lencana lencana-abu"><?= esc($r['kode']) ?></span></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['nama']) ?></td>
                        <td class="sel-ket"><?= esc($r['deskripsi'] ?: '—') ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/fase/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus ' . $r['nama'] . '?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'satuan' => 'fase']) ?>

    <?= ui_crud_form_buka(['benda' => 'Fase']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="fase-kode">Kode <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="fase-kode" name="kode" x-model="form.kode" maxlength="2" required class="inp uppercase" placeholder="mis. E">
            </div>
            <div>
                <label class="lbl" for="fase-urutan">Urutan</label>
                <input type="number" id="fase-urutan" name="urutan" x-model="form.urutan" min="0" class="inp">
            </div>
        </div>
        <div>
            <label class="lbl" for="fase-nama">Nama fase <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="text" id="fase-nama" name="nama" x-model="form.nama" maxlength="30" required class="inp" placeholder="Fase E">
        </div>
        <div>
            <label class="lbl" for="fase-deskripsi">Deskripsi</label>
            <input type="text" id="fase-deskripsi" name="deskripsi" x-model="form.deskripsi" maxlength="255" class="inp">
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Fase',
        'aksi'        => site_url('admin/master/fase/import-preview'),
        'templateUrl' => site_url('admin/master/fase/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sama diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
