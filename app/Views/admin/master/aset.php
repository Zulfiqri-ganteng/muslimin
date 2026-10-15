<?php
/**
 * Halaman Master Aset / Inventaris — pola halaman data template Flowbite (rombak tampilan F5). Tombol per baris: Foto (galeri aset),
 * Komputer (Detail Komputer — hanya kategori komputer/laptop), Ubah, Hapus. Export mengikuti saringan aktif.
 *
 * @var string                        $q            Kata kunci pencarian
 * @var int                           $labId        Filter lab aktif
 * @var string                        $kategori     Filter kategori aktif
 * @var string                        $kondisi      Filter kondisi aktif
 * @var string                        $status       Filter status aktif
 * @var int                           $per          Baris per halaman
 * @var array                         $rows         Baris aset halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager        Paginasi
 * @var int                           $total        Total seluruh data
 * @var array<int,string>             $labOpts      Opsi lab (lokasi)
 * @var array                         $kategoriList Daftar kategori sah
 * @var array                         $kondisiList  Daftar kondisi sah
 * @var array                         $statusList   Daftar status sah
 */
$mk = static fn (array $list) => array_combine($list, array_map(static fn ($v) => ucfirst(str_replace('_', ' ', $v)), $list));
$opsiKategori = $mk($kategoriList);
$opsiKondisi  = $mk($kondisiList);
$opsiStatus   = $mk($statusList);

$lencanaKondisi = ['baik' => 'lencana-hijau', 'rusak_ringan' => 'lencana-kuning', 'rusak_berat' => 'lencana-merah'];
$lencanaStatus  = ['tersedia' => 'lencana-hijau', 'dipinjam' => 'lencana-merek', 'perbaikan' => 'lencana-kuning', 'dihapus' => 'lencana-abu'];
$adaSaring      = $q !== '' || $labId || $kategori !== '' || $kondisi !== '' || $status !== '';

$qsExport = array_filter([
    'lab_id'   => $labId ?: '',
    'kategori' => $kategori,
    'kondisi'  => $kondisi,
    'status'   => $status,
], static fn ($v) => $v !== '');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'aset',
    'helpTitle' => 'Master Aset / Inventaris',
    'helpKelas' => '',
    'helpBody'  => '<p>Seluruh barang/inventaris lab beserta <b>nomor aset</b>, <b>kondisi</b>, dan lokasinya (lab). Kosongkan Nomor aset saat menambah untuk <b>penomoran otomatis</b> (format <i>KODELAB-KAT-001</i>).</p>
        <p class="mt-1">• Aset kategori <b>komputer/laptop</b> punya tombol <b>Komputer</b> (spesifikasi CPU/RAM/OS, IP/MAC). Tombol <b>Foto</b> membuka galeri foto aset.<br>
        • <b>Import/Export</b> Excel tersedia — kolom <b>Kode Lab</b> diisi kode dari Master Laboratorium. Export mengikuti saringan yang aktif.<br>
        • <b>Status</b> otomatis berubah saat aset dipinjam atau diperbaiki.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/aset') ?>" data-entity="aset"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'nomor_aset' => '', 'nama' => '', 'kategori' => 'komputer', 'lab_id' => '',
         'merk' => '', 'spesifikasi' => '', 'tahun_pengadaan' => '', 'sumber_dana' => '',
         'harga' => '', 'kondisi' => 'baik', 'status' => 'tersedia', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/aset'),
        'cari'        => 'Cari nomor aset, nama, atau merk…',
        'q'           => $q,
        'per'         => $per,
        'filter'      => [
            ['name' => 'lab_id', 'value' => (string) ($labId ?: ''), 'all' => 'Semua lab', 'options' => $labOpts],
            ['name' => 'kategori', 'value' => $kategori, 'all' => 'Semua kategori', 'options' => $opsiKategori],
            ['name' => 'kondisi', 'value' => $kondisi, 'all' => 'Semua kondisi', 'options' => $opsiKondisi],
            ['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => $opsiStatus],
        ],
        'eksporUrl'   => site_url('admin/master/aset/export') . ($qsExport !== [] ? '?' . http_build_query($qsExport) : ''),
        'eksporTitle' => 'Keluarkan data aset (mengikuti filter aktif) ke file Excel',
        'massalUrl'   => site_url('admin/master/aset/bulk-delete'),
        'massalLabel' => 'aset',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua aset di halaman ini"></th>
                    <th scope="col">Nomor aset</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Lab</th>
                    <th scope="col">Kondisi</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8">
                            <?= ui_kosong(['ikon' => 'archive', 'judul' => $adaSaring ? 'Aset tidak ditemukan' : 'Belum ada data aset',
                                'teks' => $adaSaring ? 'Tidak ada aset yang cocok dengan saringan ini. Tekan Reset.' : 'Tambah aset satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $isKomputer = in_array($r['kategori'], ['komputer', 'laptop'], true); ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih aset <?= esc($r['nomor_aset'], 'attr') ?>"></td>
                        <td class="font-mono font-semibold whitespace-nowrap text-gray-900 dark:text-white"><?= esc($r['nomor_aset']) ?></td>
                        <td class="min-w-40">
                            <div class="font-medium"><?= esc($r['nama']) ?></div>
                            <?php if (! empty($r['merk'])): ?><div class="sel-ket"><?= esc($r['merk']) ?></div><?php endif; ?>
                        </td>
                        <td class="sel-ket"><?= esc(ucfirst($r['kategori'])) ?></td>
                        <td class="sel-ket"><?= $r['lab_nama'] !== null ? esc($r['lab_nama']) : '—' ?></td>
                        <td><span class="lencana <?= $lencanaKondisi[$r['kondisi']] ?? 'lencana-abu' ?>"><?= esc(ucfirst(str_replace('_', ' ', $r['kondisi']))) ?></span></td>
                        <td><span class="lencana <?= $lencanaStatus[$r['status']] ?? 'lencana-abu' ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'          => $r,
                                'hapusUrl'     => site_url('admin/master/aset/delete/' . $r['id']),
                                'konfirmasi'   => 'Hapus aset ' . $r['nomor_aset'] . '? Detail komputernya (bila ada) ikut terhapus.',
                                'tambahanHtml' => '<a href="' . site_url('admin/lab-gambar/aset/' . $r['id']) . '" class="btn btn-netral btn-sm">' . ikon('image', 'h-4 w-4') . 'Foto</a>'
                                    . ($isKomputer ? '<a href="' . site_url('admin/master/aset/komputer/' . $r['id']) . '" class="btn btn-netral btn-sm" title="Detail komputer (spesifikasi & jaringan)">' . ikon('desktop-pc', 'h-4 w-4') . 'Komputer</a>' : ''),
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'lab_id', 'kategori', 'kondisi', 'status', 'per'], 'satuan' => 'aset']) ?>

    <?= ui_crud_form_buka(['benda' => 'Aset', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="aset-nomor">Nomor aset</label>
                <input type="text" id="aset-nomor" name="nomor_aset" x-model="form.nomor_aset" maxlength="50" class="inp uppercase">
                <p class="hint mt-1">Kosongkan untuk penomoran otomatis.</p>
            </div>
            <div>
                <label class="lbl" for="aset-kategori">Kategori</label>
                <select id="aset-kategori" name="kategori" x-model="form.kategori" class="inp">
                    <?php foreach ($opsiKategori as $val => $lbl): ?>
                        <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="aset-nama">Nama aset <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="aset-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="aset-lab">Lokasi (lab)</label>
                <select id="aset-lab" name="lab_id" x-model="form.lab_id" class="inp">
                    <option value="">— Tanpa lab —</option>
                    <?php foreach ($labOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="aset-merk">Merk</label>
                <input type="text" id="aset-merk" name="merk" x-model="form.merk" maxlength="100" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="aset-spek">Spesifikasi ringkas</label>
                <input type="text" id="aset-spek" name="spesifikasi" x-model="form.spesifikasi" maxlength="255" class="inp">
            </div>
            <div>
                <label class="lbl" for="aset-tahun">Tahun pengadaan</label>
                <input type="number" id="aset-tahun" name="tahun_pengadaan" x-model="form.tahun_pengadaan" min="1990" max="2100" class="inp">
            </div>
            <div>
                <label class="lbl" for="aset-dana">Sumber dana</label>
                <input type="text" id="aset-dana" name="sumber_dana" x-model="form.sumber_dana" maxlength="100" class="inp">
            </div>
            <div>
                <label class="lbl" for="aset-harga">Harga (Rp)</label>
                <input type="number" id="aset-harga" name="harga" x-model="form.harga" min="0" step="1" class="inp">
            </div>
            <div>
                <label class="lbl" for="aset-kondisi">Kondisi</label>
                <select id="aset-kondisi" name="kondisi" x-model="form.kondisi" class="inp">
                    <?php foreach ($opsiKondisi as $val => $lbl): ?>
                        <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="aset-status">Status</label>
                <select id="aset-status" name="status" x-model="form.status" class="inp">
                    <?php foreach ($opsiStatus as $val => $lbl): ?>
                        <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="aset-ket">Keterangan</label>
                <input type="text" id="aset-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Aset',
        'aksi'        => site_url('admin/master/aset/import-preview'),
        'templateUrl' => site_url('admin/master/aset/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. <b>Nomor aset</b> boleh dikosongkan (otomatis). Kolom <b>Kode Lab</b> diisi kode dari Master Laboratorium. Nomor aset yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
