<?php
/**
 * Halaman Master Teknisi / Penanggung Jawab — pola halaman data template Flowbite (rombak tampilan F5).
 *
 * @var string                        $q         Kata kunci pencarian
 * @var string                        $peran     Filter peran aktif
 * @var int                           $per       Baris per halaman
 * @var array                         $rows      Baris teknisi halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager     Paginasi
 * @var int                           $total     Total seluruh data
 * @var array<int,string>             $guruOpts  Opsi guru (untuk tautan)
 * @var array                         $peranList Daftar peran sah
 */
$labelPeran = static fn ($p) => ucwords(str_replace('_', ' ', (string) $p));
$opsiPeran  = [];
foreach ($peranList as $p) {
    $opsiPeran[$p] = $labelPeran($p);
}
$lencanaPeran = ['kepala_lab' => 'lencana-merek', 'teknisi' => 'lencana-biru', 'laboran' => 'lencana-pink', 'lainnya' => 'lencana-abu'];
$adaSaring    = $q !== '' || $peran !== '';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'teknisi',
    'helpTitle' => 'Master Teknisi / Penanggung Jawab',
    'helpKelas' => '',
    'helpBody'  => '<p>Daftar teknisi & penanggung jawab laboratorium. Bisa berupa <b>staf non-guru</b>; kalau kebetulan seorang guru, boleh <b>ditautkan</b> ke datanya.</p>
        <p class="mt-1">Teknisi dipakai sebagai <b>penanggung jawab lab</b>, petugas <b>peminjaman</b>, dan penangan <b>kerusakan/perbaikan</b>.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= site_url('admin/master/teknisi') ?>" data-entity="teknisi"
     data-defaults="<?= esc(json_encode([
         'id' => '', 'kode' => '', 'nama' => '', 'peran' => 'teknisi',
         'no_hp' => '', 'guru_id' => '', 'keterangan' => '',
     ]), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'     => site_url('admin/master/teknisi'),
        'cari'        => 'Cari kode atau nama teknisi…',
        'q'           => $q,
        'per'         => $per,
        'filter'      => [['name' => 'peran', 'value' => $peran, 'all' => 'Semua peran', 'options' => $opsiPeran]],
        'eksporUrl'   => site_url('admin/master/teknisi/export'),
        'eksporTitle' => 'Keluarkan semua teknisi ke file Excel',
        'massalUrl'   => site_url('admin/master/teknisi/bulk-delete'),
        'massalLabel' => 'teknisi',
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col" class="kolom-cek"><input type="checkbox" class="cek js-check-all" aria-label="Pilih semua teknisi di halaman ini"></th>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Peran</th>
                    <th scope="col">No HP</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6">
                            <?= ui_kosong(['ikon' => 'user-headset', 'judul' => $adaSaring ? 'Teknisi tidak ditemukan' : 'Belum ada data teknisi',
                                'teks' => $adaSaring ? 'Tidak ada teknisi yang cocok dengan pencarian/filter ini. Tekan Reset.' : 'Tambah teknisi satu per satu, atau Import dari Excel.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="kolom-cek"><input type="checkbox" class="cek row-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih teknisi <?= esc($r['kode'], 'attr') ?>"></td>
                        <td class="sel-judul whitespace-nowrap"><?= esc($r['kode']) ?></td>
                        <td>
                            <div class="font-medium"><?= esc($r['nama']) ?></div>
                            <?php if (! empty($r['guru_nama'])): ?><div class="sel-ket">Guru: <?= esc($r['guru_nama']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="lencana <?= $lencanaPeran[$r['peran']] ?? 'lencana-abu' ?>"><?= esc($labelPeran($r['peran'])) ?></span></td>
                        <td class="sel-ket whitespace-nowrap"><?= $r['no_hp'] !== null && $r['no_hp'] !== '' ? esc($r['no_hp']) : '—' ?></td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'        => $r,
                                'hapusUrl'   => site_url('admin/master/teknisi/delete/' . $r['id']),
                                'konfirmasi' => 'Hapus data teknisi ' . $r['nama'] . '?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'peran', 'per'], 'satuan' => 'teknisi']) ?>

    <?= ui_crud_form_buka(['benda' => 'Teknisi', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="tek-kode">Kode <span class="text-red-600">*</span></label>
                <input type="text" id="tek-kode" name="kode" x-model="form.kode" maxlength="30" required class="inp uppercase">
            </div>
            <div>
                <label class="lbl" for="tek-peran">Peran</label>
                <select id="tek-peran" name="peran" x-model="form.peran" class="inp">
                    <?php foreach ($opsiPeran as $val => $label): ?>
                        <option value="<?= esc($val, 'attr') ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="tek-nama">Nama <span class="text-red-600">*</span></label>
                <input type="text" id="tek-nama" name="nama" x-model="form.nama" maxlength="150" required class="inp">
            </div>
            <div>
                <label class="lbl" for="tek-hp">No HP</label>
                <input type="text" id="tek-hp" name="no_hp" x-model="form.no_hp" maxlength="25" class="inp" inputmode="tel">
            </div>
            <div>
                <label class="lbl" for="tek-guru">Tautkan ke guru</label>
                <select id="tek-guru" name="guru_id" x-model="form.guru_id" class="inp">
                    <option value="">— Bukan guru / tak ditautkan —</option>
                    <?php foreach ($guruOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="tek-ket">Keterangan</label>
                <input type="text" id="tek-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>

    <?= ui_crud_impor([
        'judul'       => 'Import Teknisi',
        'aksi'        => site_url('admin/master/teknisi/import-preview'),
        'templateUrl' => site_url('admin/master/teknisi/template'),
        'catatan'     => '<b>Import = memasukkan data.</b> Unggah Excel sesuai template. Data tampil dalam <b>pratinjau yang bisa diedit</b> sebelum disimpan. Kode yang sudah ada akan diperbarui.',
    ]) ?>
</div>
<?= $this->endSection() ?>
