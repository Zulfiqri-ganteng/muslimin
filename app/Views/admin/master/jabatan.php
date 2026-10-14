<?php
/**
 * Halaman Master Jabatan — pola halaman data template Flowbite (rombak tampilan F5). Komponen "jabatanPage" (master/jabatan.js).
 *
 * @var string                        $q            Kata kunci pencarian
 * @var string                        $kategori     Filter kategori aktif
 * @var int                           $per          Baris per halaman
 * @var array                         $rows         Baris jabatan halaman ini
 * @var array<int,int>                $jumlahGuru   jabatan_id => jumlah penyandang
 * @var \CodeIgniter\Pager\Pager|null $pager        Paginasi
 * @var int                           $total        Total seluruh data
 * @var array                         $kategoriList Daftar kategori sah
 * @var array<int,string>             $indukOpts    Opsi induk jabatan
 * @var array<int,string>             $jurusanOpts  Opsi jurusan
 */
$labelKategori = [
    'struktural' => 'Struktural',
    'kurikulum'  => 'Kurikulum',
    'kesiswaan'  => 'Kesiswaan',
    'wali_kelas' => 'Wali Kelas',
    'mapel'      => 'Mata Pelajaran',
    'pembina'    => 'Pembina',
    'lainnya'    => 'Lainnya',
];
$opsiKategori = [];
foreach ($kategoriList as $k) {
    $opsiKategori[$k] = $labelKategori[$k] ?? $k;
}
$adaSaring = $q !== '' || $kategori !== '';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'jabatan',
    'helpTitle' => 'Master Jabatan',
    'helpKelas' => '',
    'helpBody'  => '<p>Daftar jabatan yang disandang guru — <b>bebas dibuat sendiri</b> (Kurikulum, Guru MTK, Ketua Program, Pembina OSIS, dsb.). Satu guru boleh menyandang beberapa jabatan sekaligus; diatur di <b>Master Guru</b>.</p>
        <p class="mt-1">• <b>Induk jabatan</b> membentuk hierarki (Kepala Sekolah → Wakil Kepala → Ketua Program).<br>
        • <b>Jurusan</b> diisi bila jabatan melekat pada satu jurusan, mis. Ketua Program TKJ.<br>
        • <b>Struktural</b> menandai jabatan yang <b>wajib hadir walau tidak punya jadwal mengajar</b> — penyandangnya otomatis muncul di panel <b>Kehadiran Kerja</b> halaman Absensi, termasuk hari Sabtu/Minggu.</p>
        <p class="mt-1">• <b>Import/Export</b> Excel tersedia. Menghapus jabatan tidak menghapus gurunya, hanya melepas jabatannya.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="jabatanPage" data-base="<?= site_url('admin/master/jabatan') ?>" data-entity="jabatan"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'kategori' => 'lainnya',
         'parent_id' => '', 'jurusan_id' => '', 'level' => 5,
         'is_struktural' => false, 'hadir_harian' => false, 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'          => site_url('admin/master/jabatan'),
        'cari'             => 'Cari kode atau nama jabatan…',
        'q'                => $q,
        'per'              => $per,
        'filter'           => [['name' => 'kategori', 'value' => $kategori, 'all' => 'Semua kategori', 'options' => $opsiKategori]],
        'eksporUrl'        => site_url('admin/master/jabatan/export'),
        'eksporTitle'      => 'Keluarkan semua jabatan ke file Excel',
        'massalUrl'        => site_url('admin/master/jabatan/bulk-delete'),
        'massalLabel'      => 'jabatan',
        'massalPeringatan' => 'Guru yang menyandangnya akan kehilangan jabatan tersebut.',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua jabatan di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama jabatan</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Induk</th>
                    <th scope="col">Jurusan</th>
                    <th scope="col" class="text-center">Guru</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8">
                            <?= ui_kosong(['ikon' => 'profile-card', 'judul' => $adaSaring ? 'Jabatan tidak ditemukan' : 'Belum ada data jabatan',
                                'teks' => $adaSaring ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Tambah jabatan satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): $n = $jumlahGuru[(int) $r['id']] ?? 0; ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih jabatan <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td class="min-w-48">
                            <div class="font-medium"><?= esc($r['nama']) ?></div>
                            <?php if (! empty($r['keterangan'])): ?><div class="sel-ket"><?= esc($r['keterangan']) ?></div><?php endif; ?>
                            <?php if (! empty($r['is_struktural']) || ! empty($r['hadir_harian'])): ?>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <?php if (! empty($r['is_struktural'])): ?>
                                        <span class="lencana lencana-kuning" title="Wajib hadir walau tidak ada jadwal mengajar — muncul otomatis di panel Kehadiran Kerja"><?= ikon('star', 'h-3 w-3', 'solid') ?>Struktural</span>
                                    <?php endif; ?>
                                    <?php if (! empty($r['hadir_harian'])): ?>
                                        <span class="lencana lencana-merek" title="Penyandangnya otomatis diisikan ke panel Kehadiran Kerja setiap hari">Hadir harian</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><span class="lencana lencana-abu"><?= esc($labelKategori[$r['kategori']] ?? $r['kategori']) ?></span></td>
                        <td class="sel-ket"><?= $r['induk_nama'] !== null ? esc($r['induk_nama']) : '—' ?></td>
                        <td class="sel-ket"><?= $r['jurusan_kode'] !== null ? esc($r['jurusan_kode']) : '—' ?></td>
                        <td class="text-center"><span class="<?= $n > 0 ? 'font-semibold' : 'sel-ket' ?>"><?= $n ?></span></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/jabatan/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus jabatan ' . $r['nama'] . '? Guru yang menyandangnya akan kehilangan jabatan ini.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'kategori', 'per'], 'satuan' => 'jabatan']) ?>

    <?= ui_crud_form_buka(['benda' => 'Jabatan', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="jbt-kode">Kode <span class="text-red-600">*</span></label>
                <input type="text" id="jbt-kode" name="kode" x-model="form.kode" maxlength="30" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="jbt-kategori">Kategori</label>
                <select id="jbt-kategori" name="kategori" x-model="form.kategori" class="inp">
                    <?php foreach ($opsiKategori as $val => $lbl): ?>
                        <option value="<?= esc($val, 'attr') ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="jbt-nama">Nama jabatan <span class="text-red-600">*</span></label>
                <input type="text" id="jbt-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="jbt-induk">Induk jabatan</label>
                <select id="jbt-induk" name="parent_id" x-model="form.parent_id" class="inp">
                    <option value="">— Tanpa induk —</option>
                    <?php foreach ($indukOpts as $id => $nama): ?>
                        <?php // jabatan tak boleh menjadi induk bagi dirinya sendiri ?>
                        <option value="<?= (int) $id ?>" x-show="bukanDiriSendiri(<?= (int) $id ?>)"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint mt-1">Membentuk hierarki jabatan.</p>
            </div>
            <div>
                <label class="lbl" for="jbt-jurusan">Jurusan</label>
                <select id="jbt-jurusan" name="jurusan_id" x-model="form.jurusan_id" class="inp">
                    <option value="">— Tidak terkait jurusan —</option>
                    <?php foreach ($jurusanOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint mt-1">Mis. Ketua Program TKJ.</p>
            </div>
            <div>
                <label class="lbl" for="jbt-level">Level</label>
                <input type="number" id="jbt-level" name="level" x-model="form.level" min="1" max="99" class="inp">
                <p class="hint mt-1">1 = paling tinggi, untuk urutan tampil.</p>
            </div>
            <div class="sm:col-span-2">
                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800">
                    <input type="hidden" name="is_struktural" value="0">
                    <input type="checkbox" name="is_struktural" value="1" x-model="form.is_struktural" class="cek mt-0.5">
                    <span class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Jabatan struktural</span>
                        <span class="hint block">Wajib hadir walau tidak punya jadwal mengajar. Penyandangnya otomatis muncul di panel <b>Kehadiran Kerja</b> pada halaman Absensi, termasuk Sabtu/Minggu.</span>
                    </span>
                </label>
            </div>
            <div class="sm:col-span-2">
                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800">
                    <input type="hidden" name="hadir_harian" value="0">
                    <input type="checkbox" name="hadir_harian" value="1" x-model="form.hadir_harian" class="cek mt-0.5">
                    <span class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Wajib hadir harian (non-struktural)</span>
                        <span class="hint block">Untuk staf seperti <b>Tata Usaha</b>: penyandangnya otomatis diisikan ke panel <b>Kehadiran Kerja</b> tiap hari dan tampil di bagian "Staf TU &amp; lainnya" pada pesan WhatsApp.</span>
                    </span>
                </label>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="jbt-ket">Keterangan</label>
                <input type="text" id="jbt-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Jabatan',
        'aksi'        => site_url('admin/master/jabatan/import-preview'),
        'templateUrl' => site_url('admin/master/jabatan/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sudah ada akan diperbarui. Kolom <b>Kode Induk</b> &amp; <b>Kode Jurusan</b> diisi kode yang sudah terdaftar.',
    ]) ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script defer src="<?= base_url('assets/js/admin/master/jabatan.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/master/jabatan.js') ?>" <?= csp_script_nonce() ?>></script>
<?= $this->endSection() ?>
