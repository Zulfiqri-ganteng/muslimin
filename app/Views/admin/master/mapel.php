<?php
/**
 * Halaman Master Mata Pelajaran — pola halaman data template Flowbite (rombak tampilan F5). Komponen "mapelPage" (master/mapel.js) =
 * masterList + dialog "Atur Guru" (guru berkompetensi per mapel, dipakai saat membuat Penugasan).
 *
 * @var string                         $q            Kata kunci pencarian
 * @var string                         $kelompok     Filter kelompok mapel
 * @var int                            $per          Baris per halaman
 * @var array                          $rows         Baris mapel halaman ini
 * @var \CodeIgniter\Pager\Pager|null  $pager        Paginasi
 * @var int                            $total        Total seluruh data
 * @var array                          $allGuru      Opsi guru (id => "kode - nama")
 * @var array                          $kompMap      Peta kompetensi (mapel_id => [guru_id])
 * @var string[]                       $kelompokList Pilihan kelompok mapel
 */
$guruJs = [];
foreach ($allGuru as $id => $label) {
    $guruJs[] = ['id' => (int) $id, 'label' => $label];
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'mapel',
    'helpTitle' => 'Master Mata Pelajaran',
    'helpKelas' => '',
    'helpBody'  => '<p>Daftar mata pelajaran beserta <b>JP/minggu standar</b> (jumlah jam pelajaran yang dibutuhkan tiap minggu, mis. Pemrograman Dasar = 8 JP).</p>
        <p class="mt-1">• <b>Import (memasukkan data)</b> — unggah Excel, baris akan <b>ditambahkan otomatis</b> (kode mapel yang sama diperbarui).<br>
        • <b>Export (mengeluarkan data)</b> — mengunduh seluruh mapel yang ada menjadi file Excel.<br>
        • <b>Hapus terpilih / Hapus semua</b> — centang baris atau hapus seluruh data sekaligus.</p>
        <p class="mt-1">Tombol <b>Atur guru</b> menentukan guru mana saja yang <b>berkompetensi</b> mengajar mapel tersebut, dipakai saat membuat <b>Penugasan</b>.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="mapelPage" data-base="<?= site_url('admin/master/mapel') ?>" data-entity="mata pelajaran"
     data-defaults="<?= esc(json_encode(['kode_mapel' => '', 'nama_mapel' => '', 'kelompok' => '', 'jp_default' => 2]), 'attr') ?>"
     data-all-guru="<?= esc(json_encode($guruJs), 'attr') ?>"
     data-komp-map="<?= esc(json_encode($kompMap ?: new \stdClass()), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'          => site_url('admin/master/mapel'),
        'cari'             => 'Cari nama atau kode mapel…',
        'q'                => $q,
        'filter'           => [['name' => 'kelompok', 'value' => $kelompok, 'all' => 'Semua kelompok', 'options' => array_combine($kelompokList, $kelompokList)]],
        'per'              => $per,
        'eksporUrl'        => site_url('admin/master/mapel/export'),
        'eksporTitle'      => 'Keluarkan semua mata pelajaran ke file Excel',
        'massalUrl'        => site_url('admin/master/mapel/bulk-delete'),
        'massalLabel'      => 'mata pelajaran',
        'massalPeringatan' => 'Penugasan & jadwal yang memakai mapel tersebut ikut terhapus.',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua mapel di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama mapel</th>
                    <th scope="col">Kelompok</th>
                    <th scope="col">JP</th>
                    <th scope="col">Guru pengampu</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7">
                            <?= ui_kosong(['ikon' => 'book-open', 'judul' => $q !== '' || $kelompok !== '' ? 'Mapel tidak ditemukan' : 'Belum ada mata pelajaran',
                                'teks' => $q !== '' || $kelompok !== '' ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Tambah mapel satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $jml = count($kompMap[(int) $r['id']] ?? []); ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih mapel <?= esc($r['kode_mapel'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode_mapel']) ?></td>
                        <td class="font-medium"><?= esc($r['nama_mapel']) ?></td>
                        <td class="sel-ket"><?= esc($r['kelompok'] ?: '—') ?></td>
                        <td class="sel-ket whitespace-nowrap"><?= esc($r['jp_default']) ?> JP</td>
                        <td>
                            <button type="button" class="btn btn-netral btn-xs" data-atur-guru data-id="<?= (int) $r['id'] ?>" data-nama="<?= esc($r['nama_mapel'], 'attr') ?>">
                                <?= ikon('users', 'h-4 w-4') ?>Atur guru <span class="lencana lencana-merek px-1.5"><?= $jml ?></span>
                            </button>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/mapel/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus mapel ' . $r['nama_mapel'] . '? Penugasan & jadwal yang memakainya ikut terhapus.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'kelompok', 'per'], 'satuan' => 'mata pelajaran']) ?>

    <?= ui_crud_form_buka(['benda' => 'Mata Pelajaran', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="mapel-kode">Kode mapel <span class="text-red-600">*</span></label>
                <input type="text" id="mapel-kode" name="kode_mapel" x-model="form.kode_mapel" maxlength="20" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="mapel-jp">JP / minggu</label>
                <input type="number" id="mapel-jp" name="jp_default" x-model="form.jp_default" min="0" max="50" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="mapel-nama">Nama mapel <span class="text-red-600">*</span></label>
                <input type="text" id="mapel-nama" name="nama_mapel" x-model="form.nama_mapel" maxlength="150" required class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="mapel-kelompok">Kelompok</label>
                <input list="kelompokOpt" id="mapel-kelompok" name="kelompok" x-model="form.kelompok" maxlength="50" class="inp">
                <datalist id="kelompokOpt">
                    <?php foreach ($kelompokList as $k): ?><option value="<?= esc($k, 'attr') ?>"></option><?php endforeach; ?>
                </datalist>
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <!-- Dialog "Atur guru" (kompetensi) — tampilan dialog kit, keadaan kompOpen milik mapelPage -->
    <div class="dialog" :data-buka="kompOpen" @keydown.escape.stop="tutupKompetensi()" role="dialog" aria-modal="true" aria-labelledby="dialog-komp-judul">
        <div class="dialog-latar" @click="tutupKompetensi()" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-sm flex max-h-[85vh] flex-col" tabindex="-1">
            <div class="dialog-kepala">
                <div class="min-w-0">
                    <h3 id="dialog-komp-judul" class="dialog-judul">Guru pengampu</h3>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="kompMapel"></p>
                </div>
                <button type="button" class="tombol-tutup" @click="tutupKompetensi()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <form method="post" :action="kompUrl" class="flex min-h-0 flex-1 flex-col">
                <?= csrf_field() ?>
                <div class="dialog-isi min-h-0 flex-1 overflow-y-auto">
                    <template x-if="allGuru.length === 0">
                        <p class="py-6 text-center text-sm text-gray-500">Belum ada data guru. Tambahkan guru terlebih dahulu.</p>
                    </template>
                    <div class="space-y-1">
                        <template x-for="g in allGuru" :key="g.id">
                            <label class="flex cursor-pointer items-center rounded-lg px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-600">
                                <input type="checkbox" name="guru_ids[]" :value="g.id" x-model.number="selected" class="cek">
                                <span class="cek-label" x-text="g.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <div class="dialog-kaki justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 dark:text-white" x-text="selected.length"></b> guru dipilih</span>
                    <div class="flex gap-3">
                        <button type="button" class="btn btn-netral" @click="tutupKompetensi()">Batal</button>
                        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?= ui_crud_impor([
        'judul'       => 'Import Mata Pelajaran',
        'aksi'        => site_url('admin/master/mapel/import-preview'),
        'templateUrl' => site_url('admin/master/mapel/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data akan tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode mapel yang sudah ada akan diperbarui (tidak dobel).',
    ]) ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/mapel.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/mapel.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
