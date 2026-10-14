<?php
/**
 * Halaman Master Kelas / Rombel — pola halaman data template Flowbite (rombak tampilan F5).
 *
 * @var string                         $q           Kata kunci pencarian
 * @var string                         $tingkat     Filter tingkat (X/XI/XII)
 * @var string                         $shift       Filter shift (pagi/siang)
 * @var int                            $per         Baris per halaman
 * @var array                          $rows        Baris kelas halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager       Paginasi
 * @var int                            $total       Total seluruh data
 * @var array                          $jurusanOpts Opsi jurusan (id => label)
 * @var array                          $guruOpts    Opsi guru (id => label)
 * @var array                          $faseOpts    Opsi fase Kurikulum Merdeka (id => label)
 */
$adaSaring = $q !== '' || $tingkat !== '' || $shift !== '';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'kelas_v2',
    'helpTitle' => 'Master Kelas / Rombel',
    'helpKelas' => '',
    'helpBody'  => '<p>Daftar rombongan belajar (mis. X TKJT 1). Tetapkan <b>tingkat</b> (X/XI/XII), <b>jurusan</b>, <b>wali kelas</b>, dan <b>shift</b>.</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, baris akan <b>ditambahkan otomatis</b> (nama kelas yang sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh kelas yang ada menjadi file Excel.<br>
        • <b>Hapus terpilih / Hapus semua</b> — centang baris atau hapus seluruh data sekaligus.</p>
        <p class="mt-1"><b>Shift</b> (Pagi/Siang) menentukan set jam pelajaran yang dipakai kelas tersebut saat penjadwalan.</p>
        <p class="mt-1"><b>Terkait data siswa:</b> siswa didaftarkan di menu <b>Master Data ▸ Siswa</b> dan <b>ditautkan ke kelas di sini</b>. Tingkat dan jurusan siswa <b>mengikuti kelasnya</b> — jadi bila kelas dipindah jurusan, data siswanya ikut menyesuaikan sendiri tanpa perlu diedit ulang. Menghapus kelas <b>tidak menghapus siswanya</b>; siswa hanya menjadi "belum ada kelas" dan bisa ditetapkan ulang.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/kelas') ?>" data-entity="kelas"
     data-defaults="<?= esc(json_encode(['nama_kelas' => '', 'tingkat' => 'X', 'fase_id' => '', 'shift' => 'pagi', 'jurusan_id' => '', 'wali_kelas_id' => '']), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'          => site_url('admin/master/kelas'),
        'cari'             => 'Cari nama kelas…',
        'q'                => $q,
        'filter'           => [
            ['name' => 'tingkat', 'value' => $tingkat, 'all' => 'Semua tingkat', 'options' => ['X' => 'X', 'XI' => 'XI', 'XII' => 'XII']],
            ['name' => 'shift', 'value' => $shift, 'all' => 'Semua shift', 'options' => ['pagi' => 'Pagi', 'siang' => 'Siang']],
        ],
        'per'              => $per,
        'eksporUrl'        => site_url('admin/master/kelas/export'),
        'eksporTitle'      => 'Keluarkan semua kelas ke file Excel',
        'massalUrl'        => site_url('admin/master/kelas/bulk-delete'),
        'massalLabel'      => 'kelas',
        'massalPeringatan' => 'Penugasan & jadwal kelas tersebut ikut terhapus.',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua kelas di halaman ini"></th>
                    <th scope="col">Nama kelas</th>
                    <th scope="col">Tingkat</th>
                    <th scope="col">Fase</th>
                    <th scope="col">Jurusan</th>
                    <th scope="col">Wali kelas</th>
                    <th scope="col">Shift</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8">
                            <?= ui_kosong(['ikon' => 'school', 'judul' => $adaSaring ? 'Kelas tidak ditemukan' : 'Belum ada kelas',
                                'teks' => $adaSaring ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Tambah kelas satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih kelas <?= esc($r['nama_kelas'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['nama_kelas']) ?></td>
                        <td><span class="lencana lencana-abu"><?= esc($r['tingkat']) ?></span></td>
                        <td class="sel-ket"><?= esc($r['fase_kode'] ?: '—') ?></td>
                        <td class="sel-ket"><?= esc($r['jurusan_kode'] ?: '—') ?></td>
                        <td><?= esc($r['wali_nama'] ?: '—') ?></td>
                        <td><?= $r['shift'] === 'pagi' ? '<span class="lencana lencana-kuning">Pagi</span>' : '<span class="lencana lencana-merek">Siang</span>' ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/kelas/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus kelas ' . $r['nama_kelas'] . '? Penugasan & jadwal kelas ini ikut terhapus; siswanya tetap ada (menjadi belum berkelas).',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'tingkat', 'shift', 'per'], 'satuan' => 'kelas']) ?>

    <?= ui_crud_form_buka(['benda' => 'Kelas', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="lbl" for="kelas-nama">Nama kelas <span class="text-red-600">*</span></label>
                <input type="text" id="kelas-nama" name="nama_kelas" x-model="form.nama_kelas" maxlength="50" required class="inp" placeholder="contoh: X TKJT 1">
            </div>
            <div>
                <label class="lbl" for="kelas-tingkat">Tingkat <span class="text-red-600">*</span></label>
                <select id="kelas-tingkat" name="tingkat" x-model="form.tingkat" class="inp">
                    <option value="X">X</option><option value="XI">XI</option><option value="XII">XII</option>
                </select>
            </div>
            <div>
                <label class="lbl" for="kelas-shift">Shift <span class="text-red-600">*</span></label>
                <select id="kelas-shift" name="shift" x-model="form.shift" class="inp">
                    <option value="pagi">Pagi</option><option value="siang">Siang</option>
                </select>
            </div>
            <div>
                <label class="lbl" for="kelas-fase">Fase</label>
                <select id="kelas-fase" name="fase_id" x-model="form.fase_id" class="inp">
                    <option value="">— Otomatis dari tingkat —</option>
                    <?php foreach ($faseOpts as $id => $label): ?><option value="<?= (int) $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="kelas-jurusan">Jurusan</label>
                <select id="kelas-jurusan" name="jurusan_id" x-model="form.jurusan_id" class="inp">
                    <option value="">—</option>
                    <?php foreach ($jurusanOpts as $id => $label): ?><option value="<?= (int) $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="kelas-wali">Wali kelas</label>
                <select id="kelas-wali" name="wali_kelas_id" x-model="form.wali_kelas_id" class="inp">
                    <option value="">—</option>
                    <?php foreach ($guruOpts as $id => $label): ?><option value="<?= (int) $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Kelas',
        'aksi'        => site_url('admin/master/kelas/import-preview'),
        'templateUrl' => site_url('admin/master/kelas/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data akan tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Nama kelas yang sudah ada akan diperbarui. Jurusan dicocokkan dari kode, wali dari kode/nama guru.',
    ]) ?>
</div>
<?= $this->endSection() ?>
