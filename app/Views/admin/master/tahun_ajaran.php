<?php
/**
 * Halaman Master Tahun Ajaran — pola halaman data template Flowbite (rombak tampilan F5). Tombol "Aktifkan" (hijau) per baris yang
 * belum aktif; status = titik hijau "Aktif" / abu "Nonaktif" (pola kolom Status template).
 *
 * @var string                         $q     Kata kunci pencarian
 * @var int                            $per   Baris per halaman
 * @var array                          $rows  Baris tahun ajaran halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager Paginasi
 * @var int                            $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'tahun_ajaran',
    'helpTitle' => 'Master Tahun Ajaran',
    'helpKelas' => '',
    'helpBody'  => '<p>Daftar tahun ajaran + semester (mis. 2026/2027 Ganjil). Ini jadi acuan waktu untuk modul <b>Kurikulum &amp; Pembelajaran</b> (KKM, Struktur Kurikulum, Penilaian, dst).</p>
        <p class="mt-1">Tepat <b>satu</b> tahun ajaran yang aktif pada satu waktu — pakai tombol <b>Aktifkan</b> pada baris yang berlaku sekarang. Mengaktifkan satu otomatis menonaktifkan yang lain.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/tahun-ajaran') ?>" data-entity="tahun ajaran"
     data-defaults="<?= esc(json_encode(['tahun' => '', 'semester' => 'Ganjil']), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/tahun-ajaran'),
        'cari'        => 'Cari tahun ajaran…',
        'q'           => $q,
        'per'         => $per,
        'eksporUrl'   => site_url('admin/master/tahun-ajaran/export'),
        'eksporTitle' => 'Keluarkan semua tahun ajaran ke file Excel',
        'massalUrl'   => site_url('admin/master/tahun-ajaran/bulk-delete'),
        'massalLabel' => 'tahun ajaran',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua tahun ajaran di halaman ini"></th>
                    <th scope="col">Tahun ajaran</th>
                    <th scope="col">Semester</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5">
                            <?= ui_kosong(['ikon' => 'calendar-month', 'judul' => $q !== '' ? 'Tahun ajaran tidak ditemukan' : 'Belum ada data tahun ajaran',
                                'teks' => $q !== '' ? 'Coba kata kunci lain, atau tekan Reset.' : 'Tambah tahun ajaran, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $label = $r['tahun'] . ' ' . $r['semester']; ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih tahun ajaran <?= esc($label, 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['tahun']) ?></td>
                        <td><?= esc($r['semester']) ?></td>
                        <td>
                            <?php if ($r['is_aktif']): ?>
                                <span class="titik-status titik-status-hijau font-medium">Aktif</span>
                            <?php else: ?>
                                <span class="titik-status sel-ket">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?php ob_start(); ?>
                            <?php if (! $r['is_aktif']): ?>
                                <form method="post" action="<?= site_url('admin/master/tahun-ajaran/' . $r['id'] . '/aktifkan') ?>" class="inline-block">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sukses btn-sm"
                                            data-confirm="Aktifkan tahun ajaran <?= esc($label, 'attr') ?>? Tahun ajaran lain otomatis dinonaktifkan."
                                            data-confirm-judul="Aktifkan tahun ajaran?" data-confirm-ya="Ya, aktifkan"><?= ikon('check-circle', 'h-4 w-4') ?>Aktifkan</button>
                                </form>
                            <?php endif; ?>
                            <?php $aktifkan = (string) ob_get_clean(); ?>
                            <?= ui_crud_aksi([
                                'row'          => $r,
                                'hapusUrl'     => site_url('admin/master/tahun-ajaran/delete/' . $r['id']),
                                'konfirmasi'   => 'Hapus tahun ajaran ' . $label . '?',
                                'tambahanHtml' => $aktifkan,
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'satuan' => 'tahun ajaran']) ?>

    <?= ui_crud_form_buka(['benda' => 'Tahun Ajaran']) ?>
        <div>
            <label class="lbl" for="ta-tahun">Tahun ajaran <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="text" id="ta-tahun" name="tahun" x-model="form.tahun" maxlength="20" required class="inp" placeholder="2026/2027">
        </div>
        <div>
            <label class="lbl" for="ta-semester">Semester <span class="text-red-600 dark:text-red-400">*</span></label>
            <select id="ta-semester" name="semester" x-model="form.semester" required class="inp">
                <option value="Ganjil">Ganjil</option>
                <option value="Genap">Genap</option>
            </select>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Tahun Ajaran',
        'aksi'        => site_url('admin/master/tahun-ajaran/import-preview'),
        'templateUrl' => site_url('admin/master/tahun-ajaran/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Tahun+semester yang sama akan diperbarui. Status aktif tidak diubah lewat import — gunakan tombol Aktifkan.',
    ]) ?>
</div>
<?= $this->endSection() ?>
