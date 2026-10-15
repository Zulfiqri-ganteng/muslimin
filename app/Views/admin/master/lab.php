<?php
/**
 * Halaman Master Laboratorium — pola halaman data template Flowbite (rombak tampilan F5). Tombol "Foto" per baris (galeri lab).
 *
 * @var string                        $q           Kata kunci pencarian
 * @var string                        $jenis       Filter jenis aktif
 * @var int                           $per         Baris per halaman
 * @var array                         $rows        Baris lab halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var int                           $total       Total seluruh data
 * @var array<int,string>             $teknisiOpts Opsi teknisi (penanggung jawab)
 * @var array                         $jenisList   Daftar jenis sah
 */
$opsiJenis = [];
foreach ($jenisList as $j) {
    $opsiJenis[$j] = ucfirst($j);
}
$lencanaJenis = ['komputer' => 'lencana-merek', 'jaringan' => 'lencana-biru', 'multimedia' => 'lencana-pink', 'lainnya' => 'lencana-abu'];
$adaSaring    = $q !== '' || $jenis !== '';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'lab',
    'helpTitle' => 'Master Laboratorium',
    'helpKelas' => '',
    'helpBody'  => '<p>Data laboratorium sekolah (mis. Lab Komputer 1, Lab Jaringan). Tiap lab bisa punya <b>penanggung jawab</b> dari Master Teknisi.</p>
        <p class="mt-1">Lab menjadi <b>lokasi</b> bagi Aset/Inventaris dan acuan <b>Jadwal</b> serta <b>Jurnal</b> pemakaian lab. Tambahkan <b>Teknisi</b> lebih dulu bila ingin mengisi penanggung jawab. Tombol <b>Foto</b> membuka galeri foto lab.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/lab') ?>" data-entity="lab"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'jenis' => 'komputer',
         'ruang' => '', 'kapasitas' => '', 'teknisi_id' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/lab'),
        'cari'        => 'Cari kode, nama, atau ruang lab…',
        'q'           => $q,
        'per'         => $per,
        'filter'      => [['name' => 'jenis', 'value' => $jenis, 'all' => 'Semua jenis', 'options' => $opsiJenis]],
        'eksporUrl'   => site_url('admin/master/lab/export'),
        'eksporTitle' => 'Keluarkan semua laboratorium ke file Excel',
        'massalUrl'   => site_url('admin/master/lab/bulk-delete'),
        'massalLabel' => 'laboratorium',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua lab di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama lab</th>
                    <th scope="col">Jenis</th>
                    <th scope="col">Ruang</th>
                    <th scope="col" class="text-center">Kapasitas</th>
                    <th scope="col">Penanggung jawab</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8">
                            <?= ui_kosong(['ikon' => 'desktop-pc', 'judul' => $adaSaring ? 'Lab tidak ditemukan' : 'Belum ada data laboratorium',
                                'teks' => $adaSaring ? 'Tidak ada lab yang cocok dengan pencarian/filter ini. Tekan Reset.' : 'Tambah lab satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih lab <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td class="font-medium"><?= esc($r['nama']) ?></td>
                        <td><span class="lencana <?= $lencanaJenis[$r['jenis']] ?? 'lencana-abu' ?>"><?= esc(ucfirst($r['jenis'])) ?></span></td>
                        <td class="sel-ket"><?= $r['ruang'] !== null && $r['ruang'] !== '' ? esc($r['ruang']) : '—' ?></td>
                        <td class="sel-ket text-center"><?= $r['kapasitas'] !== null ? (int) $r['kapasitas'] : '—' ?></td>
                        <td><?= $r['teknisi_nama'] !== null ? esc($r['teknisi_nama']) : '<span class="sel-ket">Belum ditentukan</span>' ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'          => $r,
                                'hapusUrl'     => site_url('admin/master/lab/delete/' . $r['id']),
                                'konfirmasi'   => 'Hapus laboratorium ' . $r['nama'] . '? Aset di dalamnya akan dilepas dari lab (tidak ikut terhapus).',
                                'tambahanHtml' => '<a href="' . site_url('admin/lab-gambar/lab/' . $r['id']) . '" class="btn btn-netral btn-sm">' . ikon('image', 'h-4 w-4') . 'Foto</a>',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'jenis', 'per'], 'satuan' => 'laboratorium']) ?>

    <?= ui_crud_form_buka(['benda' => 'Laboratorium', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="lab-kode">Kode <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="lab-kode" name="kode" x-model="form.kode" maxlength="30" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="lab-jenis">Jenis</label>
                <select id="lab-jenis" name="jenis" x-model="form.jenis" class="inp">
                    <?php foreach ($opsiJenis as $val => $label): ?>
                        <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="lab-nama">Nama lab <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="lab-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="lab-ruang">Ruang</label>
                <input type="text" id="lab-ruang" name="ruang" x-model="form.ruang" maxlength="100" class="inp">
            </div>
            <div>
                <label class="lbl" for="lab-kapasitas">Kapasitas</label>
                <input type="number" id="lab-kapasitas" name="kapasitas" x-model="form.kapasitas" min="0" max="1000" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="lab-teknisi">Penanggung jawab</label>
                <select id="lab-teknisi" name="teknisi_id" x-model="form.teknisi_id" class="inp">
                    <option value="">— Belum ditentukan —</option>
                    <?php foreach ($teknisiOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint mt-1">Diambil dari Master Teknisi.</p>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="lab-ket">Keterangan</label>
                <input type="text" id="lab-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Laboratorium',
        'aksi'        => site_url('admin/master/lab/import-preview'),
        'templateUrl' => site_url('admin/master/lab/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Kolom <b>Kode Teknisi</b> diisi kode dari Master Teknisi (boleh dikosongkan). Kode lab yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
