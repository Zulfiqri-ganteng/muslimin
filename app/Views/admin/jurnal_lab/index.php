<?php
/**
 * Jurnal realisasi pemakaian lab — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, bilah alat kit dengan
 * cari + saringan lab + rentang tanggal, tabel-crud, lencana, ui_crud_aksi, ui_crud_kaki, dialog kit). Isian "Kendala" muncul
 * saat Kondisi = "Ada kendala" lewat CSS :has() (dulu Alpine mengubah style.display + style= sebaris) → tanpa JS, siap CSP.
 *
 * @var array                         $rows        Baris jurnal
 * @var \CodeIgniter\Pager\Pager|null $pager       Paginasi
 * @var string                        $q           Kata kunci
 * @var int                           $labId       Filter lab
 * @var string                        $dari        Filter tanggal dari
 * @var string                        $sampai      Filter tanggal sampai
 * @var int                           $per         Baris per halaman
 * @var array<int,string>             $labOpts     Opsi lab
 * @var array<int,string>             $guruOpts    Opsi guru
 * @var array<int,string>             $kelasOpts   Opsi kelas
 * @var array<int,string>             $teknisiOpts Opsi teknisi
 * @var array                         $kondisiList Daftar kondisi setelah
 */
$tgl  = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
$jam  = static fn ($t) => $t ? substr((string) $t, 0, 5) : null;
$base = site_url('admin/jurnal-lab');
$adaFilter = $q !== '' || $labId || $dari !== '' || $sampai !== '';
$total = $pager ? $pager->getTotal() : count($rows);

$opsiLab = '<option value="">Semua lab</option>';
foreach ($labOpts as $id => $nama) {
    $opsiLab .= '<option value="' . (int) $id . '"' . ($labId === (int) $id ? ' selected' : '') . '>' . esc($nama) . '</option>';
}
$kiri = '<form method="get" class="flex min-w-0 flex-1 flex-wrap items-center gap-2" role="search">'
    . '<label class="alat-crud-kotak"><span class="sr-only">Cari</span>' . ikon('search', '')
    . '<input type="search" name="q" value="' . esc($q, 'attr') . '" placeholder="Kegiatan / guru / lab…" class="inp"></label>'
    . '<select name="lab_id" data-autosubmit class="inp alat-crud-pilih" aria-label="Saring lab">' . $opsiLab . '</select>'
    . '<label class="sr-only" for="jr-dari">Dari tanggal</label><input type="date" id="jr-dari" name="dari" value="' . esc($dari, 'attr') . '" class="inp alat-crud-pilih" title="Dari tanggal">'
    . '<label class="sr-only" for="jr-sampai">Sampai tanggal</label><input type="date" id="jr-sampai" name="sampai" value="' . esc($sampai, 'attr') . '" class="inp alat-crud-pilih" title="Sampai tanggal">'
    . '<button type="submit" class="btn btn-netral">' . ikon('search', 'h-4 w-4') . 'Filter</button>'
    . ($adaFilter ? '<a href="' . $base . '" class="btn btn-teks">' . ikon('close', 'h-4 w-4') . 'Reset</a>' : '')
    . '</form>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'jurnal_lab',
    'helpTitle' => 'Jurnal Pemakaian Lab',
    'helpKelas' => '',
    'helpBody'  => '<p>Catatan <b>realisasi</b> pemakaian lab tiap sesi: kapan, oleh siapa, kegiatan apa, berapa yang hadir, dan kondisi lab setelah dipakai. Berbeda dari <b>Jadwal Lab</b> (rencana); jurnal ini adalah kenyataannya.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= ui_crud_alat([
    'baseUrl'      => $base,
    'cari'         => null,
    'kiriHtml'     => $kiri,
    'tambah'       => false,
    'impor'        => false,
    'tambahanHtml' => '<button type="button" class="btn btn-utama" data-buka-dialog="tambah-jurnal">' . ikon('plus', 'h-4 w-4') . 'Tambah jurnal</button>',
]) ?>

<div class="tabel-bungkus">
    <table class="tabel-crud">
        <thead>
            <tr>
                <th scope="col">Tanggal</th>
                <th scope="col">Lab</th>
                <th scope="col">Guru / kelas</th>
                <th scope="col">Kegiatan</th>
                <th scope="col" class="text-center">Hadir</th>
                <th scope="col">Kondisi</th>
                <th scope="col" class="kolom-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="7">
                        <?= ui_kosong(['ikon' => 'book-open', 'judul' => $adaFilter ? 'Tidak ada jurnal yang cocok' : 'Belum ada jurnal pemakaian',
                            'teks' => $adaFilter ? 'Ubah pencarian/filter, atau tekan Reset.' : 'Catat realisasi pemakaian lab lewat tombol Tambah jurnal.']) ?>
                    </td>
                </tr>
            <?php else: foreach ($rows as $r):
                $jm = $jam($r['jam_mulai']); $js = $jam($r['jam_selesai']); ?>
                <tr>
                    <td class="whitespace-nowrap">
                        <p class="sel-judul"><?= $tgl($r['tanggal']) ?></p>
                        <?php if ($jm): ?><p class="sel-ket"><?= $jm ?><?= $js ? '–' . $js : '' ?></p><?php endif; ?>
                    </td>
                    <td><?= esc($r['lab_nama'] ?? '—') ?></td>
                    <td>
                        <?= $r['guru_nama'] !== null ? esc($r['guru_nama']) : '<span class="text-gray-300 dark:text-gray-600">—</span>' ?>
                        <?php if (! empty($r['nama_kelas'])): ?><p class="sel-ket"><?= esc($r['nama_kelas']) ?></p><?php endif; ?>
                    </td>
                    <td><?= $r['kegiatan'] !== null ? esc($r['kegiatan']) : '<span class="text-gray-300 dark:text-gray-600">—</span>' ?></td>
                    <td class="text-center tabular-nums"><?= $r['jumlah_hadir'] !== null ? (int) $r['jumlah_hadir'] : '<span class="text-gray-300 dark:text-gray-600">—</span>' ?></td>
                    <td>
                        <?php if ($r['kondisi_setelah'] === 'ada_kendala'): ?>
                            <span class="lencana lencana-kuning" title="<?= esc($r['kendala'] ?? '', 'attr') ?>">Ada kendala</span>
                            <?php if (! empty($r['kendala'])): ?><p class="sel-ket mt-0.5"><?= esc($r['kendala']) ?></p><?php endif; ?>
                        <?php else: ?>
                            <span class="lencana lencana-hijau">Baik</span>
                        <?php endif; ?>
                    </td>
                    <td class="kolom-aksi"><?= ui_crud_aksi(['ubah' => false, 'hapusUrl' => $base . '/delete/' . (int) $r['id'], 'konfirmasi' => 'Hapus jurnal ini?']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?= ui_crud_kaki(['pager' => $pager, 'total' => (int) $total, 'kunci' => ['q', 'lab_id', 'dari', 'sampai', 'per'], 'satuan' => 'jurnal']) ?>

<!-- Dialog Tambah jurnal -->
<?= ui_dialog_buka(['nama' => 'tambah-jurnal', 'judul' => 'Tambah jurnal pemakaian', 'ukuran' => 'xl']) ?>
<form method="post" action="<?= $base ?>">
    <?= csrf_field() ?>
    <div class="dialog-isi">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lbl" for="jr-lab">Lab <span class="text-red-600 dark:text-red-400">*</span></label>
                <select id="jr-lab" name="lab_id" required class="inp">
                    <option value="">— Pilih lab —</option>
                    <?php foreach ($labOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="jr-tanggal">Tanggal</label>
                <input type="date" id="jr-tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" class="inp">
            </div>
            <div>
                <label class="lbl" for="jr-mulai">Jam mulai</label>
                <input type="time" id="jr-mulai" name="jam_mulai" class="inp">
            </div>
            <div>
                <label class="lbl" for="jr-selesai">Jam selesai</label>
                <input type="time" id="jr-selesai" name="jam_selesai" class="inp">
            </div>
            <div>
                <label class="lbl" for="jr-guru">Guru</label>
                <select id="jr-guru" name="guru_id" class="inp">
                    <option value="">— Tidak ada —</option>
                    <?php foreach ($guruOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="jr-kelas">Kelas</label>
                <select id="jr-kelas" name="kelas_id" class="inp">
                    <option value="">— Tidak ada —</option>
                    <?php foreach ($kelasOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="lbl" for="jr-kegiatan">Kegiatan / materi</label>
                <input type="text" id="jr-kegiatan" name="kegiatan" maxlength="255" class="inp">
            </div>
            <div>
                <label class="lbl" for="jr-hadir">Jumlah hadir</label>
                <input type="number" id="jr-hadir" name="jumlah_hadir" min="0" class="inp">
            </div>
            <div>
                <label class="lbl" for="jr-kondisi">Kondisi setelah</label>
                <select id="jr-kondisi" name="kondisi_setelah" class="inp">
                    <option value="baik">Baik</option>
                    <option value="ada_kendala">Ada kendala</option>
                </select>
            </div>
            <?php // Muncul hanya saat "Ada kendala" dipilih (CSS :has(), kelas .muncul-saat-kendala di admin.css). ?>
            <div class="muncul-saat-kendala sm:col-span-2">
                <label class="lbl" for="jr-kendala">Kendala</label>
                <input type="text" id="jr-kendala" name="kendala" maxlength="255" class="inp">
            </div>
            <div>
                <label class="lbl" for="jr-teknisi">Teknisi</label>
                <select id="jr-teknisi" name="teknisi_id" class="inp">
                    <option value="">— Tidak dipilih —</option>
                    <?php foreach ($teknisiOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="jr-ket">Keterangan</label>
                <input type="text" id="jr-ket" name="keterangan" maxlength="255" class="inp">
            </div>
        </div>
    </div>
    <div class="dialog-kaki">
        <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
    </div>
</form>
<?= ui_dialog_tutup() ?>
<?= $this->endSection() ?>
