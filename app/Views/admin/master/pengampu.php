<?php
/**
 * Halaman Penugasan Mengajar (Pengampu) — pola halaman data template Flowbite (rombak tampilan F5). Kiri bilah alat = pemilih kelas
 * + total JP kelas (bukan kotak cari). Komponen "pengampuPage" (master/pengampu.js): guru disaring sesuai kompetensi mapel, JP terisi
 * dari standar mapel. Siap Alpine versi CSP.
 *
 * @var array $kelasOpts Opsi kelas (id => label)
 * @var int   $kelasId   Kelas terpilih
 * @var array $rows      Penugasan kelas terpilih
 * @var int   $totalJp   Total JP kelas terpilih
 * @var array $mapelOpts Opsi mapel (id => label)
 * @var array $mapelJp   JP standar per mapel (id => jp)
 * @var array $guruOpts  Opsi guru (id => label)
 * @var array $kompMap   Peta kompetensi (mapel_id => [guru_id])
 */
$guruJs = [];
foreach ($guruOpts as $id => $label) {
    $guruJs[] = ['id' => (int) $id, 'label' => $label];
}

// Kiri bilah alat: pemilih kelas (menggantikan kotak cari) + total JP kelas.
ob_start(); ?>
<form method="get" class="flex flex-wrap items-center gap-2">
    <label class="text-sm font-medium text-gray-900 dark:text-white" for="pengampu-kelas">Kelas</label>
    <select id="pengampu-kelas" name="kelas_id" data-autosubmit class="inp alat-crud-pilih min-w-52">
        <option value=""<?= $kelasId === 0 ? ' selected' : '' ?>><?= empty($kelasOpts) ? '— belum ada kelas —' : '— pilih kelas —' ?></option>
        <?php foreach ($kelasOpts as $id => $label): ?>
            <option value="<?= (int) $id ?>"<?= $kelasId === (int) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<?php if ($kelasId > 0): ?>
    <span class="text-sm text-gray-500 dark:text-gray-400">Total JP kelas: <b class="text-base font-bold text-gray-900 dark:text-white"><?= (int) $totalJp ?></b> JP/minggu</span>
<?php endif; ?>
<?php $kiriHtml = (string) ob_get_clean(); ?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'pengampu',
    'helpTitle' => 'Penugasan Mengajar (Pengampu) — langkah kunci sebelum penjadwalan',
    'helpKelas' => '',
    'helpBody'  => '<p>Di sinilah ditetapkan: <b>untuk satu kelas, mapel apa diajar oleh siapa dan berapa JP/minggu.</b> Pilih kelas dulu di atas, lalu tambah penugasannya.</p>
        <p class="mt-1">Pilihan guru otomatis <b>disaring sesuai kompetensi</b> (dari menu Mata Pelajaran), dan JP terisi otomatis dari standar mapel (bisa diubah). <b>Total JP kelas</b> dihitung otomatis. Data inilah yang nanti mengisi sel-sel jadwal &amp; menjadi acuan kuota JP.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="pengampuPage" data-base="<?= site_url('admin/master/pengampu') ?>"
     data-all-guru="<?= esc(json_encode($guruJs), 'attr') ?>"
     data-komp-map="<?= esc(json_encode($kompMap ?: new \stdClass()), 'attr') ?>"
     data-mapel-jp="<?= esc(json_encode($mapelJp ?: new \stdClass()), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'          => site_url('admin/master/pengampu'),
        'kiriHtml'         => $kiriHtml,
        'tambah'           => $kelasId > 0,
        'labelTambah'      => 'Tambah penugasan',
        'impor'            => false,
        'eksporUrl'        => site_url('admin/master/pengampu/export'),
        'eksporTitle'      => 'Keluarkan seluruh penugasan (semua kelas) ke file Excel',
        'massalUrl'        => site_url('admin/master/pengampu/bulk-delete'),
        'massalHidden'     => ['kelas_id' => $kelasId],
        'massalLabel'      => 'penugasan pada kelas ini',
        'massalPeringatan' => 'Jadwal yang memakai penugasan tersebut ikut terhapus.',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua penugasan kelas ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Mata pelajaran</th>
                    <th scope="col">Guru pengampu</th>
                    <th scope="col">JP</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6">
                            <?= ui_kosong($kelasId
                                ? ['ikon' => 'chalkboard-user', 'judul' => 'Belum ada penugasan untuk kelas ini', 'teks' => 'Tekan "Tambah penugasan" untuk menetapkan mapel, guru, dan JP.']
                                : ['ikon' => 'school', 'judul' => 'Pilih kelas terlebih dahulu', 'teks' => 'Pilih kelas di kiri atas (atau tambahkan kelas di Master Kelas).']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $beda = (int) $r['jp'] !== (int) $r['jp_default']; ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih penugasan <?= esc($r['kode_mapel'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode_mapel']) ?></td>
                        <td class="font-medium"><?= esc($r['nama_mapel']) ?></td>
                        <td><span class="sel-ket"><?= esc($r['kode_guru']) ?></span> · <?= esc($r['guru_nama']) ?></td>
                        <td class="whitespace-nowrap">
                            <span class="font-semibold"><?= esc($r['jp']) ?> JP</span>
                            <?php if ($beda): ?><span class="ms-1 text-xs text-yellow-700 dark:text-yellow-400" title="Standar mapel <?= esc($r['jp_default'], 'attr') ?> JP">(std <?= esc($r['jp_default']) ?>)</span><?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/pengampu/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus penugasan ' . $r['nama_mapel'] . ' (' . $r['guru_nama'] . ')? Jadwal yang memakainya ikut terhapus.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rows), 'satuan' => 'penugasan' . ($kelasId > 0 ? ' · total ' . (int) $totalJp . ' JP/minggu' : '')]) ?>

    <!-- Dialog Tambah penugasan (tampilan dialog kit; keadaan open milik pengampuPage) -->
    <div class="dialog" :data-buka="open" @keydown.escape.stop="tutupForm()" role="dialog" aria-modal="true" aria-labelledby="dialog-tambah-judul">
        <div class="dialog-latar" @click="tutupForm()" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-sm" tabindex="-1">
            <div class="dialog-kepala">
                <h3 id="dialog-tambah-judul" class="dialog-judul">Tambah penugasan</h3>
                <button type="button" class="tombol-tutup" @click="tutupForm()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <form method="post" action="<?= site_url('admin/master/pengampu') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="kelas_id" value="<?= (int) $kelasId ?>">
                <div class="dialog-isi">
                    <div>
                        <label class="lbl" for="pa-mapel">Mata pelajaran <span class="text-red-600">*</span></label>
                        <select id="pa-mapel" name="mapel_id" x-model.number="form.mapel_id" @change="onMapelChange()" required class="inp">
                            <option value="">— pilih mapel —</option>
                            <?php foreach ($mapelOpts as $id => $label): ?><option value="<?= (int) $id ?>"><?= esc($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="lbl" for="pa-guru">Guru pengampu <span class="text-red-600">*</span></label>
                        <select id="pa-guru" name="guru_id" x-model.number="form.guru_id" required class="inp">
                            <option value="">— pilih guru —</option>
                            <template x-for="g in filteredGuru" :key="g.id">
                                <option :value="g.id" x-text="g.label"></option>
                            </template>
                        </select>
                        <p class="hint mt-1" x-show="form.mapel_id && filteredGuru.length < allGuru.length" x-cloak>Hanya menampilkan guru berkompetensi mapel ini.</p>
                    </div>
                    <div>
                        <label class="lbl" for="pa-jp">Jumlah JP / minggu <span class="text-red-600">*</span></label>
                        <input type="number" id="pa-jp" name="jp" x-model.number="form.jp" min="1" max="50" required class="inp">
                    </div>
                </div>
                <div class="dialog-kaki">
                    <button type="button" class="btn btn-netral" @click="tutupForm()">Batal</button>
                    <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dialog Ubah penugasan -->
    <div class="dialog" :data-buka="editOpen" @keydown.escape.stop="tutupEdit()" role="dialog" aria-modal="true" aria-labelledby="dialog-ubah-judul">
        <div class="dialog-latar" @click="tutupEdit()" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-sm" tabindex="-1">
            <div class="dialog-kepala">
                <div class="min-w-0">
                    <h3 id="dialog-ubah-judul" class="dialog-judul">Ubah penugasan</h3>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="editMapel"></p>
                </div>
                <button type="button" class="tombol-tutup" @click="tutupEdit()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <form method="post" :action="editUrl">
                <?= csrf_field() ?>
                <div class="dialog-isi">
                    <div>
                        <label class="lbl" for="pu-guru">Guru pengampu <span class="text-red-600">*</span></label>
                        <select id="pu-guru" name="guru_id" x-model.number="eform.guru_id" required class="inp">
                            <template x-for="g in editGuru" :key="g.id">
                                <option :value="g.id" x-text="g.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="lbl" for="pu-jp">Jumlah JP / minggu <span class="text-red-600">*</span></label>
                        <input type="number" id="pu-jp" name="jp" x-model.number="eform.jp" min="1" max="50" required class="inp">
                    </div>
                </div>
                <div class="dialog-kaki">
                    <button type="button" class="btn btn-netral" @click="tutupEdit()">Batal</button>
                    <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/pengampu.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/pengampu.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
