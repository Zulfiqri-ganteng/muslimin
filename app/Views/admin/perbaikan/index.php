<?php
/**
 * Perbaikan / Maintenance / Penggantian komponen — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD,
 * ui_crud_alat, tabel-crud, lencana, tombol aksi ber-ikon, ui_crud_kaki, dialog kit dibuka lewat data-buka-dialog).
 * Tanpa Alpine di halaman → siap CSP.
 *
 * @var array                         $rows          Baris perbaikan
 * @var \CodeIgniter\Pager\Pager|null $pager         Paginasi
 * @var string                        $q             Kata kunci
 * @var string                        $jenis         Filter jenis
 * @var int                           $per           Baris per halaman
 * @var array<int,string>             $asetOpts      Opsi aset
 * @var array<int,string>             $teknisiOpts   Opsi teknisi
 * @var array<int,string>             $sparepartOpts Opsi sparepart
 * @var array<int,string>             $kerusakanOpts Opsi kerusakan terbuka
 * @var array                         $jenisList     Daftar jenis
 * @var array                         $hasilList     Daftar hasil
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$base = site_url('admin/perbaikan');
$warnaJenis = ['perbaikan' => 'lencana-merek', 'maintenance' => 'lencana-pink', 'penggantian' => 'lencana-kuning'];
$warnaHasil = ['berhasil' => 'text-green-700 dark:text-green-400', 'sebagian' => 'text-yellow-700 dark:text-yellow-400', 'gagal' => 'text-red-700 dark:text-red-400', 'ganti_unit' => 'text-gray-500 dark:text-gray-400'];
$opsiJenis = [];
foreach ($jenisList as $j) {
    $opsiJenis[$j] = ucfirst($j);
}
$total = $pager ? $pager->getTotal() : count($rows);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'perbaikan',
    'helpTitle' => 'Perbaikan & Maintenance',
    'helpKelas' => '',
    'helpBody'  => '<p>Catat tindakan atas aset: <b>perbaikan</b>, <b>maintenance</b> rutin, atau <b>penggantian komponen</b>. Bila mengisi bagian "Penggantian Komponen", stok sparepart otomatis <b>berkurang</b> (dan dipulihkan bila catatan ini dihapus).</p>
        <p class="mt-1">Kaitkan ke sebuah <b>kerusakan</b> lalu set status <b>Selesai</b> → kerusakan itu otomatis ditandai selesai & asetnya kembali <b>tersedia</b>.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= ui_crud_alat([
    'baseUrl'      => $base,
    'cari'         => 'Cari aset atau tindakan…',
    'q'            => $q,
    'filter'       => [['name' => 'jenis', 'value' => $jenis, 'all' => 'Semua jenis', 'options' => $opsiJenis]],
    'per'          => $per,
    'tambah'       => false,
    'impor'        => false,
    'tambahanHtml' => '<button type="button" class="btn btn-utama" data-buka-dialog="catat-perbaikan">' . ikon('plus', 'h-4 w-4') . 'Catat perbaikan</button>',
]) ?>

<div class="tabel-bungkus">
    <table class="tabel-crud">
        <thead>
            <tr>
                <th scope="col">Aset</th>
                <th scope="col">Jenis</th>
                <th scope="col">Tindakan</th>
                <th scope="col">Komponen</th>
                <th scope="col">Tanggal</th>
                <th scope="col" class="kolom-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6">
                        <?= ui_kosong(['ikon' => 'hammer', 'judul' => $q !== '' || $jenis !== '' ? 'Tidak ada perbaikan yang cocok' : 'Belum ada catatan perbaikan',
                            'teks' => $q !== '' || $jenis !== '' ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Catat tindakan perbaikan, maintenance, atau penggantian komponen lewat tombol Catat perbaikan.']) ?>
                    </td>
                </tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <p class="font-mono font-semibold text-brand-700 dark:text-brand-400"><?= esc($r['nomor_aset'] ?? '—') ?></p>
                        <p class="sel-ket"><?= esc($r['aset_nama'] ?? '—') ?></p>
                    </td>
                    <td><span class="lencana <?= $warnaJenis[$r['jenis']] ?? 'lencana-abu' ?>"><?= esc(ucfirst($r['jenis'])) ?></span></td>
                    <td>
                        <p class="text-gray-900 dark:text-white"><?= esc($r['tindakan']) ?></p>
                        <p class="mt-0.5 text-xs">
                            <span class="font-semibold <?= $warnaHasil[$r['hasil']] ?? 'text-gray-500' ?>"><?= esc(ucfirst(str_replace('_', ' ', $r['hasil']))) ?></span>
                            <?php if ($r['biaya'] !== null): ?><span class="text-gray-500 dark:text-gray-400"> · Rp<?= number_format((float) $r['biaya'], 0, ',', '.') ?></span><?php endif; ?>
                            <?php if ($r['status'] === 'proses'): ?> <span class="lencana lencana-kuning ms-1">proses</span><?php endif; ?>
                        </p>
                    </td>
                    <td>
                        <?php if (! empty($r['komponen_nama'])): ?>
                            <?= esc($r['komponen_nama']) ?> <span class="sel-ket">×<?= (int) $r['komponen_jumlah'] ?> <?= esc($r['komponen_satuan'] ?? '') ?></span>
                        <?php else: ?><span class="text-gray-300 dark:text-gray-600">—</span><?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap"><?= $tgl($r['tanggal']) ?></td>
                    <td class="kolom-aksi">
                        <?= ui_crud_aksi([
                            'ubah'         => false,
                            'hapusUrl'     => $base . '/delete/' . (int) $r['id'],
                            'konfirmasi'   => 'Hapus catatan perbaikan ini? Stok komponen (bila ada) akan dipulihkan.',
                            'tambahanHtml' => '<a href="' . site_url('admin/lab-gambar/perbaikan/' . (int) $r['id']) . '" class="btn btn-netral btn-sm">' . ikon('image', 'h-4 w-4') . 'Foto</a>',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'jenis', 'per'], 'satuan' => 'catatan']) ?>

<!-- Dialog Catat perbaikan -->
<?= ui_dialog_buka(['nama' => 'catat-perbaikan', 'judul' => 'Catat perbaikan', 'ukuran' => 'xl']) ?>
<form method="post" action="<?= $base ?>">
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="pb-aset">Aset <span class="text-red-600 dark:text-red-400">*</span></label>
                <select id="pb-aset" name="aset_id" required class="inp">
                    <option value="">— Pilih aset —</option>
                    <?php foreach ($asetOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="pb-kerusakan">Kaitkan kerusakan</label>
                <select id="pb-kerusakan" name="kerusakan_id" class="inp">
                    <option value="">— Tidak dikaitkan —</option>
                    <?php foreach ($kerusakanOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="pb-jenis">Jenis</label>
                <select id="pb-jenis" name="jenis" class="inp">
                    <?php foreach ($jenisList as $j): ?><option value="<?= esc($j, 'attr') ?>"><?= esc(ucfirst($j)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="pb-tanggal">Tanggal</label>
                <input type="date" id="pb-tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" class="inp">
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="pb-tindakan">Tindakan <span class="text-red-600 dark:text-red-400">*</span></label>
                <input type="text" id="pb-tindakan" name="tindakan" required maxlength="255" class="inp">
            </div>
            <div>
                <label class="lbl" for="pb-hasil">Hasil</label>
                <select id="pb-hasil" name="hasil" class="inp">
                    <?php foreach ($hasilList as $h): ?><option value="<?= esc($h, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $h))) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="pb-status">Status</label>
                <select id="pb-status" name="status" class="inp">
                    <option value="selesai">Selesai</option>
                    <option value="proses">Proses</option>
                </select>
            </div>
            <div>
                <label class="lbl" for="pb-biaya">Biaya (Rp)</label>
                <input type="number" id="pb-biaya" name="biaya" min="0" step="1" class="inp">
            </div>
            <div>
                <label class="lbl" for="pb-teknisi">Teknisi</label>
                <select id="pb-teknisi" name="teknisi_id" class="inp">
                    <option value="">— Tidak dipilih —</option>
                    <?php foreach ($teknisiOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Penggantian komponen (opsional) -->
        <fieldset class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
            <legend class="px-1 text-sm font-semibold text-yellow-800 dark:text-yellow-300">Penggantian komponen (opsional)</legend>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="lbl" for="pb-sparepart">Sparepart</label>
                    <select id="pb-sparepart" name="sparepart_id" class="inp">
                        <option value="">— Tidak ada penggantian —</option>
                        <?php foreach ($sparepartOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="pb-jumlah">Jumlah</label>
                    <input type="number" id="pb-jumlah" name="jumlah_komponen" min="0" value="0" class="inp">
                </div>
            </div>
            <p class="hint text-yellow-700 dark:text-yellow-400">Bila diisi, stok sparepart berkurang otomatis sesuai jumlah.</p>
        </fieldset>

        <div>
            <label class="lbl" for="pb-ket">Keterangan</label>
            <input type="text" id="pb-ket" name="keterangan" maxlength="255" class="inp">
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>
<?= $this->endSection() ?>
