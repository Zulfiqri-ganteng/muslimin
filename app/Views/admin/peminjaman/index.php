<?php
/**
 * Peminjaman & Pengembalian aset lab — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, ui_crud_alat,
 * tabel-crud, lencana & status bertitik, tombol aksi ber-ikon, ui_crud_kaki, dialog kit). Tanpa Alpine di halaman: dialog
 * "Proses pengembalian" diisi dari tombol barisnya (data-dialog-aksi / data-dialog-isi, kit ui.js) → siap CSP.
 *
 * @var array                         $rows        Baris peminjaman halaman ini
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var string                        $q           Kata kunci pencarian
 * @var string                        $status      Filter status aktif
 * @var int                           $per         Baris per halaman
 * @var string                        $today       Tanggal hari ini (Y-m-d)
 * @var int                           $sedang      Jumlah sedang dipinjam
 * @var int                           $terlambat   Jumlah terlambat
 * @var array<int,string>             $asetOpts    Opsi aset tersedia
 * @var array<int,string>             $petugasOpts Opsi petugas (teknisi)
 * @var array                         $statusList  Daftar status sah
 * @var array                         $kondisiList Daftar kondisi sah
 */
$tgl       = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$warnaTipe = ['guru' => 'lencana-merek', 'siswa' => 'lencana-biru', 'umum' => 'lencana-abu'];
$base      = site_url('admin/peminjaman');
$opsiStatus = [];
foreach ($statusList as $s) {
    $opsiStatus[$s] = ucfirst($s);
}
$total = $pager ? $pager->getTotal() : count($rows);
$ringkas = '<span class="lencana lencana-merek py-1">Sedang dipinjam: ' . (int) $sedang . '</span>'
    . ($terlambat > 0 ? '<span class="lencana lencana-merah py-1">Terlambat: ' . (int) $terlambat . '</span>' : '');
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'peminjaman',
    'helpTitle' => 'Peminjaman & Pengembalian',
    'helpKelas' => '',
    'helpBody'  => '<p>Catat peminjaman barang lab. Saat dipinjam, status aset otomatis menjadi <b>dipinjam</b> (tak bisa dipinjam ganda). Peminjam boleh siapa saja (guru/siswa/umum) — cukup ketik namanya.</p>
        <p class="mt-1">• Klik <b>Kembalikan</b> pada baris untuk memproses pengembalian: isi tanggal & kondisi barang, status aset otomatis kembali <b>tersedia</b>.<br>
        • Barang yang <b>hilang</b> bisa ditandai saat pengembalian (aset ditarik dari daftar; bisa dipulihkan lewat Master Aset).<br>
        • Baris <b>Terlambat</b> = masih dipinjam melewati tanggal rencana kembali.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= ui_crud_alat([
    'baseUrl'      => $base,
    'cari'         => 'Cari aset atau nama peminjam…',
    'q'            => $q,
    'filter'       => [['name' => 'status', 'value' => $status, 'all' => 'Semua status', 'options' => $opsiStatus]],
    'per'          => $per,
    'tambah'       => false,
    'impor'        => false,
    'tambahanHtml' => $ringkas . '<button type="button" class="btn btn-utama" data-buka-dialog="pinjam-barang">' . ikon('plus', 'h-4 w-4') . 'Pinjamkan barang</button>',
]) ?>

<div class="tabel-bungkus">
    <table class="tabel-crud">
        <thead>
            <tr>
                <th scope="col">Aset</th>
                <th scope="col">Peminjam</th>
                <th scope="col">Tgl pinjam</th>
                <th scope="col">Rencana kembali</th>
                <th scope="col">Status</th>
                <th scope="col" class="kolom-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6">
                        <?= ui_kosong(['ikon' => 'briefcase', 'judul' => $q !== '' || $status !== '' ? 'Tidak ada peminjaman yang cocok' : 'Belum ada peminjaman',
                            'teks' => $q !== '' || $status !== '' ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Klik Pinjamkan barang untuk mencatat peminjaman.']) ?>
                    </td>
                </tr>
            <?php else: foreach ($rows as $r):
                $late  = $r['status'] === 'dipinjam' && $r['tanggal_kembali_rencana'] && $r['tanggal_kembali_rencana'] < $today;
                $label = ($r['nomor_aset'] ?? '-') . ' — ' . ($r['aset_nama'] ?? '-');
                if ($late) {
                    [$titik, $txt] = ['titik-status-merah', 'Terlambat'];
                } elseif ($r['status'] === 'dipinjam') {
                    [$titik, $txt] = ['titik-status-biru', 'Dipinjam'];
                } elseif ($r['status'] === 'dikembalikan') {
                    [$titik, $txt] = ['titik-status-hijau', 'Dikembalikan'];
                } else {
                    [$titik, $txt] = ['', ucfirst($r['status'])];
                }
                ?>
                <tr class="<?= $late ? 'bg-red-50 dark:bg-red-900/20' : '' ?>">
                    <td>
                        <p class="font-mono font-semibold text-brand-700 dark:text-brand-400"><?= esc($r['nomor_aset'] ?? '—') ?></p>
                        <p class="sel-ket"><?= esc($r['aset_nama'] ?? '—') ?></p>
                    </td>
                    <td>
                        <p class="sel-judul"><?= esc($r['peminjam_nama']) ?></p>
                        <span class="lencana <?= $warnaTipe[$r['peminjam_tipe']] ?? 'lencana-abu' ?> mt-0.5"><?= esc(ucfirst($r['peminjam_tipe'])) ?></span>
                        <?php if (! empty($r['tujuan'])): ?><p class="sel-ket mt-0.5"><?= esc($r['tujuan']) ?></p><?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap"><?= $tgl($r['tanggal_pinjam']) ?></td>
                    <td class="whitespace-nowrap">
                        <?= $tgl($r['tanggal_kembali_rencana']) ?>
                        <?php if ($r['status'] !== 'dipinjam' && $r['tanggal_kembali_aktual']): ?>
                            <p class="sel-ket">kembali: <?= $tgl($r['tanggal_kembali_aktual']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td><span class="titik-status <?= $titik ?> font-medium"><?= esc($txt) ?></span></td>
                    <td class="kolom-aksi">
                        <?= ui_crud_aksi([
                            'ubah'         => false,
                            'hapusUrl'     => $base . '/delete/' . (int) $r['id'],
                            'konfirmasi'   => 'Hapus catatan peminjaman ini?',
                            'tambahanHtml' => '<a href="' . site_url('admin/lab-gambar/peminjaman/' . (int) $r['id']) . '" class="btn btn-netral btn-sm">' . ikon('image', 'h-4 w-4') . 'Foto</a>'
                                . ($r['status'] === 'dipinjam' ? '<button type="button" class="btn btn-sukses btn-sm" data-buka-dialog="kembalikan-barang" data-dialog-aksi="' . esc($base . '/kembalikan/' . (int) $r['id'], 'attr') . '" data-dialog-isi="' . esc((string) json_encode(['label' => $label]), 'attr') . '">' . ikon('check', 'h-4 w-4') . 'Kembalikan</button>' : ''),
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'status', 'per'], 'satuan' => 'peminjaman']) ?>

<!-- Dialog Pinjamkan barang -->
<?= ui_dialog_buka(['nama' => 'pinjam-barang', 'judul' => 'Pinjamkan barang', 'ukuran' => 'lg']) ?>
<form method="post" action="<?= $base ?>">
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="lbl" for="pj-aset">Aset (yang tersedia) <span class="text-red-600">*</span></label>
                <select id="pj-aset" name="aset_id" required class="inp">
                    <option value="">— Pilih aset —</option>
                    <?php foreach ($asetOpts as $id => $lbl): ?>
                        <option value="<?= (int) $id ?>"><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($asetOpts)): ?>
                    <p class="hint text-yellow-700 dark:text-yellow-400">Tidak ada aset berstatus "tersedia". Tambahkan/ubah di Master Aset.</p>
                <?php endif; ?>
            </div>
            <div>
                <label class="lbl" for="pj-nama">Nama peminjam <span class="text-red-600">*</span></label>
                <input type="text" id="pj-nama" name="peminjam_nama" required maxlength="150" class="inp">
            </div>
            <div>
                <label class="lbl" for="pj-tipe">Tipe peminjam</label>
                <select id="pj-tipe" name="peminjam_tipe" class="inp">
                    <option value="umum">Umum</option>
                    <option value="guru">Guru</option>
                    <option value="siswa">Siswa</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="pj-tujuan">Tujuan / keperluan</label>
                <input type="text" id="pj-tujuan" name="tujuan" maxlength="255" class="inp">
            </div>
            <div>
                <label class="lbl" for="pj-tgl">Tanggal pinjam</label>
                <input type="date" id="pj-tgl" name="tanggal_pinjam" value="<?= esc($today, 'attr') ?>" class="inp">
            </div>
            <div>
                <label class="lbl" for="pj-rencana">Rencana kembali</label>
                <input type="date" id="pj-rencana" name="tanggal_kembali_rencana" class="inp">
            </div>
            <div>
                <label class="lbl" for="pj-kondisi">Kondisi saat pinjam</label>
                <select id="pj-kondisi" name="kondisi_pinjam" class="inp">
                    <?php foreach ($kondisiList as $k): ?>
                        <option value="<?= esc($k, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $k))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="pj-petugas">Petugas</label>
                <select id="pj-petugas" name="petugas_id" class="inp">
                    <option value="">— Tidak dipilih —</option>
                    <?php foreach ($petugasOpts as $id => $nama): ?>
                        <option value="<?= (int) $id ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="pj-ket">Keterangan</label>
                <input type="text" id="pj-ket" name="keterangan" maxlength="255" class="inp">
            </div>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>

<!-- Dialog Proses pengembalian (diisi dari tombol baris) -->
<?= ui_dialog_buka(['nama' => 'kembalikan-barang', 'judul' => 'Proses pengembalian']) ?>
<form method="post" action="<?= $base ?>" data-aksi-pemicu>
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <p class="text-sm font-medium text-gray-900 dark:text-white" data-isi-pemicu="label"></p>
        <div>
            <label class="lbl" for="kb-status">Status</label>
            <select id="kb-status" name="status" class="inp">
                <option value="dikembalikan">Dikembalikan</option>
                <option value="hilang">Hilang</option>
            </select>
        </div>
        <div>
            <label class="lbl" for="kb-tgl">Tanggal kembali</label>
            <input type="date" id="kb-tgl" name="tanggal_kembali_aktual" value="<?= esc($today, 'attr') ?>" class="inp">
        </div>
        <div>
            <label class="lbl" for="kb-kondisi">Kondisi saat kembali</label>
            <select id="kb-kondisi" name="kondisi_kembali" class="inp">
                <option value="">— Tidak diubah —</option>
                <?php foreach ($kondisiList as $k): ?>
                    <option value="<?= esc($k, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $k))) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Bila diisi, kondisi aset ikut diperbarui.</p>
        </div>
        <div>
            <label class="lbl" for="kb-ket">Keterangan</label>
            <input type="text" id="kb-ket" name="keterangan" maxlength="255" class="inp">
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-sukses"><?= ikon('check', 'h-4 w-4') ?>Proses</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>
<?= $this->endSection() ?>
