<?php
/**
 * Jadwal pelaksanaan UKK — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, komponen kit "masterList",
 * ui_crud_alat, tabel-crud, lencana jumlah penguji, ui_crud_aksi Penguji · Nilai · Ubah · Hapus, ui_crud_kaki, dialog formulir
 * kit). Dulu x-data objek bermetode sebaris + @click berisi JSON → siap CSP.
 *
 * @var array                          $rows          Baris jadwal halaman ini
 * @var array<int,int>                 $jumlahPenguji jadwal_ukk_id => jumlah penguji
 * @var \CodeIgniter\Pager\Pager|null $pager
 * @var string                        $q
 * @var int                           $paketId
 * @var int                           $per
 * @var array<int,string>             $paketOpts
 * @var array<int,string>             $tempatOpts
 * @var array<int,string>             $tahunOpts
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/jadwal-ukk');
$defaults = [
    'id' => '', 'paket_soal_id' => '', 'tempat_uji_id' => '', 'tahun_ajaran_id' => '',
    'tanggal_mulai' => date('Y-m-d'), 'tanggal_selesai' => '', 'sesi' => '', 'keterangan' => '',
];
$opsiPaket = [];
foreach ($paketOpts as $id => $lbl) {
    $opsiPaket[(string) $id] = $lbl;
}
$adaFilter = $q !== '' || $paketId > 0;
$total = $pager ? $pager->getTotal() : count($rows);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_ukk',
    'helpTitle' => 'Jadwal UKK',
    'helpKelas' => '',
    'helpBody'  => '<p>Buat jadwal pelaksanaan UKK per paket soal (tanggal, sesi, dan tempat uji). Setelah jadwal
        dibuat, klik <b>Penguji</b> untuk menugaskan penguji internal (guru sekolah) dan/atau eksternal
        (DUDI/industri) pada jadwal tersebut, lalu <b>Nilai</b> untuk mengisi nilai peserta.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div x-data="masterList" data-base="<?= $base ?>" data-entity="jadwal UKK" data-defaults="<?= esc((string) json_encode($defaults), 'attr') ?>">

    <?= ui_crud_alat([
        'baseUrl'        => $base,
        'cari'           => 'Cari paket soal atau tempat uji…',
        'q'              => $q,
        'filter'         => [['name' => 'paket_soal_id', 'value' => $paketId > 0 ? (string) $paketId : '', 'all' => 'Semua paket soal', 'options' => $opsiPaket]],
        'per'            => $per,
        'filterLangsung' => true,
        'labelTambah'    => 'Tambah jadwal',
        'impor'          => false,
    ]) ?>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Paket soal</th>
                    <th scope="col">Tempat uji</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Sesi</th>
                    <th scope="col">Penguji</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6">
                            <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => $adaFilter ? 'Tidak ada jadwal yang cocok' : 'Belum ada jadwal UKK',
                                'teks' => $adaFilter ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Klik Tambah jadwal untuk memulai.']) ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r):
                    $nPenguji = (int) ($jumlahPenguji[$r['id']] ?? 0); ?>
                    <tr>
                        <td>
                            <p class="sel-judul"><?= esc($r['paket_kode'] ?? '—') ?></p>
                            <p class="sel-ket"><?= esc($r['paket_nama'] ?? '—') ?></p>
                        </td>
                        <td><?= esc($r['tempat_nama'] ?? '—') ?></td>
                        <td class="whitespace-nowrap">
                            <?= $tgl($r['tanggal_mulai']) ?>
                            <?php if (! empty($r['tanggal_selesai']) && $r['tanggal_selesai'] !== $r['tanggal_mulai']): ?>
                                <p class="sel-ket">s/d <?= $tgl($r['tanggal_selesai']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($r['sesi'] ?? '—') ?></td>
                        <td>
                            <?php if ($nPenguji > 0): ?>
                                <span class="lencana lencana-merek"><?= $nPenguji ?> penguji</span>
                            <?php else: ?>
                                <span class="lencana lencana-kuning">Belum ada</span>
                            <?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?= ui_crud_aksi([
                                'row'          => [
                                    'id' => $r['id'], 'paket_soal_id' => $r['paket_soal_id'],
                                    'tempat_uji_id' => $r['tempat_uji_id'], 'tahun_ajaran_id' => $r['tahun_ajaran_id'],
                                    'tanggal_mulai' => $r['tanggal_mulai'], 'tanggal_selesai' => $r['tanggal_selesai'],
                                    'sesi' => $r['sesi'], 'keterangan' => $r['keterangan'],
                                ],
                                'hapusUrl'     => $base . '/delete/' . (int) $r['id'],
                                'konfirmasi'   => 'Hapus jadwal UKK ini? Penugasan penguji ikut terhapus; peserta yang sudah terdaftar TIDAK terhapus (hanya lepas dari jadwal ini).',
                                'tambahanHtml' => '<a href="' . $base . '/penguji/' . (int) $r['id'] . '" class="btn btn-netral btn-sm">' . ikon('users', 'h-4 w-4') . 'Penguji</a>'
                                    . '<a href="' . site_url('admin/penilaian-ukk/jadwal/' . (int) $r['id']) . '" class="btn btn-sukses btn-sm">' . ikon('pen-nib', 'h-4 w-4') . 'Nilai</a>',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'paket_soal_id', 'per'], 'satuan' => 'jadwal']) ?>

    <?= ui_crud_form_buka(['benda' => 'Jadwal UKK', 'ukuran' => 'lg']) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="lbl" for="ju-paket">Paket soal <span class="text-red-600 dark:text-red-400">*</span></label>
                <select id="ju-paket" name="paket_soal_id" x-model="form.paket_soal_id" required class="inp">
                    <option value="">— Pilih paket soal —</option>
                    <?php foreach ($paketOpts as $id => $lbl): ?>
                        <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="ju-tempat">Tempat uji</label>
                <select id="ju-tempat" name="tempat_uji_id" x-model="form.tempat_uji_id" class="inp">
                    <option value="">— Tidak ditentukan —</option>
                    <?php foreach ($tempatOpts as $id => $lbl): ?>
                        <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="ju-tahun">Tahun ajaran</label>
                <select id="ju-tahun" name="tahun_ajaran_id" x-model="form.tahun_ajaran_id" class="inp">
                    <option value="">— Tidak ditentukan —</option>
                    <?php foreach ($tahunOpts as $id => $lbl): ?>
                        <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="ju-mulai">Tanggal mulai <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="date" id="ju-mulai" name="tanggal_mulai" x-model="form.tanggal_mulai" required class="inp">
            </div>
            <div>
                <label class="lbl" for="ju-selesai">Tanggal selesai</label>
                <input type="date" id="ju-selesai" name="tanggal_selesai" x-model="form.tanggal_selesai" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="ju-sesi">Sesi</label>
                <input type="text" id="ju-sesi" name="sesi" x-model="form.sesi" maxlength="50" placeholder="mis. Sesi 1 (08.00-12.00)" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="ju-ket">Keterangan</label>
                <input type="text" id="ju-ket" name="keterangan" x-model="form.keterangan" maxlength="255" class="inp">
            </div>
        </div>
    <?= ui_crud_form_tutup() ?>
</div>
<?= $this->endSection() ?>
