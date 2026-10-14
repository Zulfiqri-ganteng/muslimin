<?php
/**
 * Halaman Master Paket Soal UKK — pola halaman data template Flowbite (rombak tampilan F5). Kolom Dokumen: buka Kisi-kisi/Jobsheet
 * (tab baru) + hapus berkas (POST + konfirmasi merah); formulir mengunggah PDF (multipart) dan bobot 5 komponen penilaian.
 *
 * @var string                        $q           Kata kunci pencarian
 * @var int                           $per         Baris per halaman
 * @var array                         $rows        Baris paket soal halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var int                           $total       Total seluruh data
 * @var array<int,string>             $jurusanOpts Opsi jurusan
 * @var array<int,string>             $tahunOpts   Opsi tahun ajaran
 */
helper('ukkdoc');

// Satu baris dokumen di kolom Dokumen: tautan buka + tombol hapus berkas.
$dokumen = static function (array $r, string $kolom, string $label, string $slug): string {
    if (empty($r[$kolom])) {
        return '<div class="text-gray-400 dark:text-gray-500">' . esc($label) . ': —</div>';
    }

    return '<div class="flex items-center gap-2">'
        . '<a href="' . esc(ukkdoc_url($r[$kolom]), 'attr') . '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:underline dark:text-brand-400">' . ikon('file-lines', 'h-3.5 w-3.5') . esc($label) . '</a>'
        . '<a href="' . site_url('admin/master/paket-soal-ukk/hapus-berkas/' . (int) $r['id'] . '/' . $slug) . '" data-method="post" data-confirm="Hapus berkas ' . esc(mb_strtolower($label), 'attr') . ' paket ' . esc($r['kode'], 'attr') . '?" data-confirm-judul="Hapus berkas?" data-confirm-ya="Ya, hapus" data-confirm-jenis="bahaya" class="font-medium text-red-600 hover:underline dark:text-red-500">hapus</a>'
        . '</div>';
};
$bobot = [['bobot_persiapan', 'Persiapan'], ['bobot_proses', 'Proses'], ['bobot_hasil', 'Hasil'], ['bobot_sikap', 'Sikap'], ['bobot_waktu', 'Waktu']];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'paket_soal_ukk',
    'helpTitle' => 'Master Paket Soal UKK',
    'helpKelas' => '',
    'helpBody'  => '<p>Paket soal Uji Kompetensi Keahlian (UKK), lengkap dengan berkas <b>Kisi-kisi</b> &amp; <b>Jobsheet</b> (PDF, maks 10 MB).</p>
        <p class="mt-1"><b>Bobot 5 komponen penilaian</b> (Persiapan/Proses/Hasil/Sikap/Waktu) harus berjumlah <b>100%</b> — dipakai otomatis saat menghitung <b>Nilai Akhir</b> di menu Penilaian. <b>KKM</b> menentukan batas kelulusan peserta.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/paket-soal-ukk') ?>" data-entity="paket soal"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'jurusan_id' => '', 'tahun_ajaran_id' => '',
         'deskripsi' => '', 'bobot_persiapan' => 10, 'bobot_proses' => 30, 'bobot_hasil' => 40,
         'bobot_sikap' => 10, 'bobot_waktu' => 10, 'kkm' => 70, 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/paket-soal-ukk'),
        'cari'        => 'Cari kode atau nama paket soal…',
        'q'           => $q,
        'per'         => $per,
        'eksporUrl'   => site_url('admin/master/paket-soal-ukk/export'),
        'eksporTitle' => 'Keluarkan semua paket soal ke file Excel',
        'massalUrl'   => site_url('admin/master/paket-soal-ukk/bulk-delete'),
        'massalLabel' => 'paket soal',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua paket soal di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Jurusan</th>
                    <th scope="col" class="text-center">KKM</th>
                    <th scope="col">Dokumen</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7">
                            <?= ui_kosong(['ikon' => 'file-lines', 'judul' => $q !== '' ? 'Paket soal tidak ditemukan' : 'Belum ada data paket soal',
                                'teks' => $q !== '' ? 'Tidak ada paket soal yang cocok dengan pencarian ini. Tekan Reset.' : 'Tambah paket soal satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih paket <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td class="min-w-48">
                            <div class="font-medium"><?= esc($r['nama']) ?></div>
                            <div class="sel-ket">Bobot: <?= (float) $r['bobot_persiapan'] ?>/<?= (float) $r['bobot_proses'] ?>/<?= (float) $r['bobot_hasil'] ?>/<?= (float) $r['bobot_sikap'] ?>/<?= (float) $r['bobot_waktu'] ?></div>
                        </td>
                        <td class="sel-ket"><?= $r['jurusan_nama'] !== null ? esc($r['jurusan_nama']) : '—' ?></td>
                        <td class="text-center font-semibold"><?= (float) $r['kkm'] ?></td>
                        <td class="space-y-1 text-xs whitespace-nowrap">
                            <?= $dokumen($r, 'kisi_kisi_file', 'Kisi-kisi', 'kisi-kisi') ?>
                            <?= $dokumen($r, 'jobsheet_file', 'Jobsheet', 'jobsheet') ?>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/paket-soal-ukk/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus paket soal ' . $r['kode'] . '? Berkas kisi-kisi/jobsheet ikut terhapus.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'satuan' => 'paket soal']) ?>

    <?= ui_crud_form_buka(['benda' => 'Paket Soal', 'ukuran' => 'lg', 'enctype' => 'multipart/form-data']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="ps-kode">Kode <span class="text-red-600">*</span></label>
                <input type="text" id="ps-kode" name="kode" x-model="form.kode" maxlength="30" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="ps-kkm">KKM</label>
                <input type="number" id="ps-kkm" name="kkm" x-model="form.kkm" min="0" max="100" step="0.5" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="ps-nama">Nama <span class="text-red-600">*</span></label>
                <input type="text" id="ps-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="ps-jurusan">Jurusan</label>
                <select id="ps-jurusan" name="jurusan_id" x-model="form.jurusan_id" class="inp">
                    <option value="">— Semua jurusan —</option>
                    <?php foreach ($jurusanOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="ps-tahun">Tahun ajaran</label>
                <select id="ps-tahun" name="tahun_ajaran_id" x-model="form.tahun_ajaran_id" class="inp">
                    <option value="">— Belum ditentukan —</option>
                    <?php foreach ($tahunOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="ps-deskripsi">Deskripsi</label>
                <input type="text" id="ps-deskripsi" name="deskripsi" x-model="form.deskripsi" maxlength="255" class="inp">
            </div>
        </div>

        <fieldset class="border-t border-gray-200 pt-4 dark:border-gray-600">
            <legend class="lbl">Bobot komponen penilaian (%) <span class="font-normal text-gray-500 dark:text-gray-400">— total harus 100</span></legend>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <?php foreach ($bobot as [$nama, $judul]): ?>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400" for="ps-<?= $nama ?>"><?= $judul ?></label>
                        <input type="number" id="ps-<?= $nama ?>" name="<?= $nama ?>" x-model="form.<?= $nama ?>" min="0" max="100" step="0.5" class="inp">
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="border-t border-gray-200 pt-4 dark:border-gray-600">
            <legend class="lbl">Dokumen (PDF, maks 10 MB)</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400" for="ps-kisi">Kisi-kisi</label>
                    <input type="file" id="ps-kisi" name="kisi_kisi" accept="application/pdf" class="inp-berkas">
                    <p class="hint mt-1">Kosongkan bila tidak ingin mengganti berkas yang sudah ada.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400" for="ps-jobsheet">Jobsheet</label>
                    <input type="file" id="ps-jobsheet" name="jobsheet" accept="application/pdf" class="inp-berkas">
                    <p class="hint mt-1">Kosongkan bila tidak ingin mengganti berkas yang sudah ada.</p>
                </div>
            </div>
        </fieldset>

        <div>
            <label class="lbl" for="ps-ket">Keterangan</label>
            <input type="text" id="ps-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Paket Soal UKK',
        'aksi'        => site_url('admin/master/paket-soal-ukk/import-preview'),
        'templateUrl' => site_url('admin/master/paket-soal-ukk/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Bobot komponen &amp; KKM diisi default (10/30/40/10/10, KKM 70) hanya untuk paket BARU — kode yang sudah ada tidak diubah bobotnya. Dokumen kisi-kisi/jobsheet tetap diunggah manual lewat formulir.',
    ]) ?>
</div>
<?= $this->endSection() ?>
