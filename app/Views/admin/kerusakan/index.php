<?php
/**
 * Kerusakan aset lab — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, ui_crud_alat, tabel-crud, lencana,
 * tombol aksi ber-ikon, ui_crud_kaki, dialog kit). Tanpa Alpine di halaman: dialog dibuka lewat data-buka-dialog dan dialog
 * "Ubah status" diisi dari tombolnya (data-dialog-aksi / data-dialog-isi, kit ui.js) → siap CSP.
 *
 * @var array                         $rows        Baris kerusakan
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var string                        $q           Kata kunci
 * @var string                        $status      Filter status
 * @var int                           $per         Baris per halaman
 * @var int                           $terbuka     Jumlah kerusakan terbuka
 * @var array<int,string>             $asetOpts    Opsi aset
 * @var array<int,string>             $teknisiOpts Opsi teknisi
 * @var array                         $tingkatList Daftar tingkat
 * @var array                         $statusList  Daftar status
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/kerusakan');
$warnaTingkat = ['ringan' => 'lencana-abu', 'sedang' => 'lencana-kuning', 'berat' => 'lencana-merah'];
$warnaStatus  = ['dilaporkan' => 'titik-status-kuning', 'diproses' => 'titik-status-biru', 'selesai' => 'titik-status-hijau', 'tak_teratasi' => ''];
$labelStatus  = static fn (string $s): string => ucfirst(str_replace('_', ' ', $s));
$opsiStatus   = [];
foreach ($statusList as $s) {
    $opsiStatus[$s] = $labelStatus($s);
}
$total = $pager ? $pager->getTotal() : count($rows);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'kerusakan',
    'helpTitle' => 'Kerusakan Aset',
    'helpKelas' => '',
    'helpBody'  => '<p>Catat laporan kerusakan aset. Saat dilaporkan, status aset otomatis menjadi <b>perbaikan</b>. Ubah status ke <b>Selesai</b> jika sudah beres — aset otomatis kembali <b>tersedia</b> (bila tak ada kerusakan lain).</p>
        <p class="mt-1">Untuk mencatat <b>tindakan perbaikan</b> (termasuk penggantian komponen yang mengurangi stok sparepart), buka menu <b>Perbaikan</b> lalu kaitkan ke kerusakan ini.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= ui_crud_alat([
    'baseUrl'      => $base,
    'cari'         => 'Cari aset atau deskripsi…',
    'q'            => $q,
    'filter'       => [['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => $opsiStatus]],
    'per'          => $per,
    'tambah'       => false,
    'impor'        => false,
    'tambahanHtml' => '<span class="lencana lencana-kuning py-1">Kerusakan terbuka: ' . (int) $terbuka . '</span>'
        . '<button type="button" class="btn btn-utama" data-buka-dialog="lapor-kerusakan">' . ikon('exclamation-circle', 'h-4 w-4') . 'Lapor kerusakan</button>',
]) ?>

<div class="tabel-bungkus">
    <table class="tabel-crud">
        <thead>
            <tr>
                <th scope="col">Aset</th>
                <th scope="col">Deskripsi</th>
                <th scope="col">Tingkat</th>
                <th scope="col">Tgl lapor</th>
                <th scope="col">Status</th>
                <th scope="col" class="kolom-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6">
                        <?= ui_kosong(['ikon' => 'tools', 'judul' => $q !== '' || $status !== '' ? 'Tidak ada kerusakan yang cocok' : 'Belum ada laporan kerusakan',
                            'teks' => $q !== '' || $status !== '' ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Laporkan kerusakan aset lewat tombol Lapor kerusakan.']) ?>
                    </td>
                </tr>
            <?php else: foreach ($rows as $r):
                $open  = in_array($r['status'], ['dilaporkan', 'diproses'], true);
                $label = ($r['nomor_aset'] ?? '-') . ' — ' . mb_substr((string) $r['deskripsi'], 0, 40); ?>
                <tr>
                    <td>
                        <p class="font-mono font-semibold text-brand-700 dark:text-brand-400"><?= esc($r['nomor_aset'] ?? '—') ?></p>
                        <p class="sel-ket"><?= esc($r['aset_nama'] ?? '—') ?></p>
                    </td>
                    <td>
                        <p class="text-gray-900 dark:text-white"><?= esc($r['deskripsi']) ?></p>
                        <?php if (! empty($r['pelapor'])): ?><p class="sel-ket">Pelapor: <?= esc($r['pelapor']) ?></p><?php endif; ?>
                    </td>
                    <td><span class="lencana <?= $warnaTingkat[$r['tingkat']] ?? 'lencana-abu' ?>"><?= esc(ucfirst($r['tingkat'])) ?></span></td>
                    <td class="whitespace-nowrap"><?= $tgl($r['tanggal_lapor']) ?></td>
                    <td><span class="titik-status <?= $warnaStatus[$r['status']] ?? '' ?> font-medium"><?= esc($labelStatus($r['status'])) ?></span></td>
                    <td class="kolom-aksi">
                        <?= ui_crud_aksi([
                            'ubah'         => false,
                            'hapusUrl'     => $base . '/delete/' . (int) $r['id'],
                            'konfirmasi'   => 'Hapus laporan kerusakan ini?',
                            'tambahanHtml' => '<a href="' . site_url('admin/lab-gambar/kerusakan/' . (int) $r['id']) . '" class="btn btn-netral btn-sm">' . ikon('image', 'h-4 w-4') . 'Foto</a>'
                                . ($open ? '<button type="button" class="btn btn-utama btn-sm" data-buka-dialog="status-kerusakan" data-dialog-aksi="' . esc($base . '/status/' . (int) $r['id'], 'attr') . '" data-dialog-isi="' . esc((string) json_encode(['label' => $label]), 'attr') . '">' . ikon('edit', 'h-4 w-4') . 'Ubah status</button>' : ''),
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'status', 'per'], 'satuan' => 'laporan']) ?>

<!-- Dialog Lapor kerusakan -->
<?= ui_dialog_buka(['nama' => 'lapor-kerusakan', 'judul' => 'Lapor kerusakan', 'ukuran' => 'lg']) ?>
<form method="post" action="<?= $base ?>">
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="lbl" for="kr-aset">Aset <span class="text-red-600">*</span></label>
                <select id="kr-aset" name="aset_id" required class="inp">
                    <option value="">— Pilih aset —</option>
                    <?php foreach ($asetOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="kr-deskripsi">Deskripsi kerusakan <span class="text-red-600">*</span></label>
                <input type="text" id="kr-deskripsi" name="deskripsi" required maxlength="255" class="inp">
            </div>
            <div>
                <label class="lbl" for="kr-tingkat">Tingkat</label>
                <select id="kr-tingkat" name="tingkat" class="inp">
                    <?php foreach ($tingkatList as $t): ?><option value="<?= esc($t, 'attr') ?>"><?= esc(ucfirst($t)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="kr-tanggal">Tanggal lapor</label>
                <input type="date" id="kr-tanggal" name="tanggal_lapor" value="<?= date('Y-m-d') ?>" class="inp">
            </div>
            <div>
                <label class="lbl" for="kr-pelapor">Pelapor</label>
                <input type="text" id="kr-pelapor" name="pelapor" maxlength="150" class="inp">
            </div>
            <div>
                <label class="lbl" for="kr-teknisi">Teknisi</label>
                <select id="kr-teknisi" name="teknisi_id" class="inp">
                    <option value="">— Tidak dipilih —</option>
                    <?php foreach ($teknisiOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="kr-ket">Keterangan</label>
                <input type="text" id="kr-ket" name="keterangan" maxlength="255" class="inp">
            </div>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<!-- Dialog Ubah status (diisi dari tombol baris) -->
<?= ui_dialog_buka(['nama' => 'status-kerusakan', 'judul' => 'Ubah status kerusakan']) ?>
<form method="post" action="<?= $base ?>" data-aksi-pemicu>
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <p class="text-sm font-medium text-gray-900 dark:text-white" data-isi-pemicu="label"></p>
        <div>
            <label class="lbl" for="kr-status">Status baru</label>
            <select id="kr-status" name="status" class="inp">
                <option value="diproses">Diproses</option>
                <option value="selesai">Selesai</option>
                <option value="tak_teratasi">Tak teratasi</option>
            </select>
            <p class="hint">Selesai / Tak teratasi akan membebaskan aset (jika tak ada kerusakan lain).</p>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>
<?= $this->endSection() ?>
