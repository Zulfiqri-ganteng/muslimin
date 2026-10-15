<?php
/**
 * Halaman Master Penguji Eksternal UKK — pola halaman data template Flowbite (rombak tampilan F5).
 *
 * @var string                        $q     Kata kunci pencarian
 * @var int                           $per   Baris per halaman
 * @var array                         $rows  Baris penguji eksternal halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager Paginasi
 * @var int                           $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'penguji_eksternal',
    'helpTitle' => 'Master Penguji Eksternal',
    'helpKelas' => '',
    'helpBody'  => '<p>Penguji UKK dari <b>luar sekolah</b> (DUDI/industri/asosiasi profesi) — bukan guru, jadi disimpan di tabel tersendiri.</p>
        <p class="mt-1">Penguji internal (guru sekolah) diambil langsung dari <b>Master Guru</b> saat penugasan di <b>Jadwal UKK</b> — tidak perlu didaftarkan di sini.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/penguji-eksternal') ?>" data-entity="penguji eksternal"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'instansi' => '',
         'jabatan' => '', 'no_hp' => '', 'email' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/penguji-eksternal'),
        'cari'        => 'Cari kode, nama, atau instansi…',
        'q'           => $q,
        'per'         => $per,
        'eksporUrl'   => site_url('admin/master/penguji-eksternal/export'),
        'eksporTitle' => 'Keluarkan semua penguji eksternal ke file Excel',
        'massalUrl'   => site_url('admin/master/penguji-eksternal/bulk-delete'),
        'massalLabel' => 'penguji eksternal',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua penguji di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Instansi / jabatan</th>
                    <th scope="col">Kontak</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6">
                            <?= ui_kosong(['ikon' => 'user-graduate', 'judul' => $q !== '' ? 'Penguji tidak ditemukan' : 'Belum ada data penguji eksternal',
                                'teks' => $q !== '' ? 'Tidak ada penguji eksternal yang cocok dengan pencarian ini. Tekan Reset.' : 'Tambah penguji satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih penguji <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td class="font-medium"><?= esc($r['nama']) ?></td>
                        <td>
                            <div><?= $r['instansi'] !== null && $r['instansi'] !== '' ? esc($r['instansi']) : '<span class="sel-ket">—</span>' ?></div>
                            <?php if (! empty($r['jabatan'])): ?><div class="sel-ket"><?= esc($r['jabatan']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div class="whitespace-nowrap"><?= $r['no_hp'] !== null && $r['no_hp'] !== '' ? esc($r['no_hp']) : '<span class="sel-ket">—</span>' ?></div>
                            <?php if (! empty($r['email'])): ?><div class="sel-ket"><?= esc($r['email']) ?></div><?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/penguji-eksternal/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus data penguji eksternal ' . $r['nama'] . '?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'satuan' => 'penguji eksternal']) ?>

    <?= ui_crud_form_buka(['benda' => 'Penguji Eksternal', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="pe-kode">Kode <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="pe-kode" name="kode" x-model="form.kode" maxlength="30" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="pe-hp">No HP</label>
                <input type="text" id="pe-hp" name="no_hp" x-model="form.no_hp" maxlength="25" class="inp" inputmode="tel">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="pe-nama">Nama <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="pe-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="pe-instansi">Instansi</label>
                <input type="text" id="pe-instansi" name="instansi" x-model="form.instansi" maxlength="150" class="inp">
            </div>
            <div>
                <label class="lbl" for="pe-jabatan">Jabatan</label>
                <input type="text" id="pe-jabatan" name="jabatan" x-model="form.jabatan" maxlength="100" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="pe-email">Email</label>
                <input type="email" id="pe-email" name="email" x-model="form.email" maxlength="100" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="pe-ket">Keterangan</label>
                <input type="text" id="pe-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Penguji Eksternal',
        'aksi'        => site_url('admin/master/penguji-eksternal/import-preview'),
        'templateUrl' => site_url('admin/master/penguji-eksternal/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
