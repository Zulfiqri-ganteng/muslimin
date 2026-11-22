<?php
/**
 * Halaman Master Jurusan — pola halaman data template Flowbite (content/crud/users.html; rombak tampilan F5): kepala putih selebar
 * layar + bilah alat (cari, Tambah, Import, Export, hapus massal), tabel selebar layar, bilah halaman di bawah, dialog formulir.
 * Komponen Alpine "masterList" (app.js); semua isian Alpine sederhana → siap Alpine versi CSP.
 *
 * @var string                         $q     Kata kunci pencarian
 * @var int                            $per   Baris per halaman
 * @var array                          $rows  Baris jurusan halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager Paginasi
 * @var int                            $total Total seluruh data
 */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'jurusan',
    'helpTitle' => 'Master Jurusan',
    'helpKelas' => '',
    'helpBody'  => '<p>Kompetensi keahlian sekolah (mis. TKJT, MPLB, AKL). Jurusan dipakai untuk mengelompokkan <b>Kelas</b> dan ditampilkan pada laporan/jadwal.</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, tampil pratinjau yang bisa diedit sebelum disimpan (kode sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — unduh seluruh jurusan ke Excel. • <b>Hapus terpilih / Hapus semua</b> muncul setelah baris dicentang.</p>
        <p class="mt-3"><b>Label di jadwal ujian</b><br>
        • Tulisan jurusan di judul kolom <b>Jadwal Ujian</b> (menu Ujian). Mis. jurusan berkode <b>MP</b> ditulis <b>MPLB</b>, kode <b>AK</b> ditulis <b>AKL</b>, sehingga judul kolom menjadi "XII MPLB", "XI AKL".<br>
        • Kosong = memakai kode jurusan. Cukup diisi sekali, dipakai di semua ujian.<br>
        • <b>Urutan di jadwal ujian</b> = urutan kolom jurusan di Jadwal Ujian dalam satu tingkat, angka kecil di kiri. Mis. MPLB/MP = 1, AKL/AK = 2, TKJ = 3 → "X MPLB, X AKL, X TKJ". Kosong = di paling kanan.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/jurusan') ?>" data-entity="jurusan"
     data-defaults="<?= esc(json_encode(['kode' => '', 'nama' => '', 'label_ujian' => '', 'urutan_ujian' => '']), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/jurusan'),
        'cari'        => 'Cari kode atau nama jurusan…',
        'q'           => $q,
        'per'         => $per,
        'eksporUrl'   => site_url('admin/master/jurusan/export'),
        'eksporTitle' => 'Keluarkan semua jurusan ke file Excel',
        'massalUrl'   => site_url('admin/master/jurusan/bulk-delete'),
        'massalLabel' => 'jurusan',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua jurusan di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama jurusan</th>
                    <th scope="col">Label di jadwal ujian</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="5">
                            <?= ui_kosong(['ikon' => 'school', 'judul' => $q !== '' ? 'Jurusan tidak ditemukan' : 'Belum ada data jurusan',
                                'teks' => $q !== '' ? 'Coba kata kunci lain, atau tekan Reset.' : 'Tambah jurusan satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih jurusan <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td><?= esc($r['nama']) ?></td>
                        <td class="sel-ket"><?= ($r['label_ujian'] ?? '') !== '' && $r['label_ujian'] !== null ? esc($r['label_ujian']) : '<span title="Kosong = memakai kode">' . esc($r['kode']) . '</span>' ?><?= ($r['urutan_ujian'] ?? null) !== null ? ' <span class="lencana lencana-abu" title="Urutan kolom di jadwal ujian">urutan ' . (int) $r['urutan_ujian'] . '</span>' : '' ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/jurusan/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus jurusan ' . $r['kode'] . ' — ' . $r['nama'] . '?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'satuan' => 'jurusan']) ?>

    <?= ui_crud_form_buka(['benda' => 'Jurusan']) ?>
        <div>
            <label class="lbl" for="jurusan-kode">Kode <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="text" id="jurusan-kode" name="kode" x-model="form.kode" maxlength="20" required class="inp uppercase" placeholder="mis. TKJT">
        </div>
        <div>
            <label class="lbl" for="jurusan-nama">Nama jurusan <span class="text-red-600 dark:text-red-400">*</span></label>
            <input type="text" id="jurusan-nama" name="nama" x-model="form.nama" maxlength="100" required class="inp" placeholder="mis. Teknik Komputer dan Jaringan Telekomunikasi">
        </div>
        <div>
            <label class="lbl" for="jurusan-label-ujian">Label di jadwal ujian</label>
            <input type="text" id="jurusan-label-ujian" name="label_ujian" x-model="form.label_ujian" maxlength="50" class="inp uppercase" placeholder="mis. MPLB">
            <p class="hint">Tulisan jurusan di judul kolom Jadwal Ujian. Kosongkan bila sama dengan kode.</p>
        </div>
        <div>
            <label class="lbl" for="jurusan-urutan-ujian">Urutan di jadwal ujian</label>
            <input type="number" id="jurusan-urutan-ujian" name="urutan_ujian" x-model="form.urutan_ujian" min="0" max="999" class="inp" placeholder="mis. 1">
            <p class="hint">Urutan kolom di Jadwal Ujian (angka kecil di kiri). Kosong = paling kanan.</p>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Jurusan',
        'aksi'        => site_url('admin/master/jurusan/import-preview'),
        'templateUrl' => site_url('admin/master/jurusan/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
