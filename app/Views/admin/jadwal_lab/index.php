<?php
/**
 * Jadwal pemakaian lab + jadwal praktik guru — pola halaman data template Flowbite (rombak tampilan F8: mode CRUD, bilah alat
 * kit berisi dua pemilih [data-autosubmit], tabel-crud, ui_crud_aksi, ui_kosong, dialog kit Tambah jadwal). Tanpa Alpine di
 * halaman → siap CSP.
 *
 * @var array             $rows      Baris jadwal (mode lab atau guru)
 * @var int               $labId     Lab terpilih
 * @var int               $guruId    Guru terpilih (mode praktik guru)
 * @var array<int,string> $labOpts   Opsi lab
 * @var array<int,string> $guruOpts  Opsi guru
 * @var array<int,string> $kelasOpts Opsi kelas
 * @var array<int,string> $mapelOpts Opsi mapel
 * @var array<int,string> $hariOpts  Opsi hari
 * @var array<int,string> $jamOpts   Opsi jam
 */
$base     = site_url('admin/jadwal-lab');
$modeLab  = $labId > 0;
$modeGuru = ! $modeLab && $guruId > 0;

$opsi = static function (array $daftar, int $pilih, string $kosong): string {
    $h = '<option value="">' . esc($kosong) . '</option>';
    foreach ($daftar as $id => $nama) {
        $h .= '<option value="' . (int) $id . '"' . ($pilih === (int) $id ? ' selected' : '') . '>' . esc($nama) . '</option>';
    }

    return $h;
};
$kiri = '<form method="get" class="flex flex-wrap items-center gap-2">'
    . '<label class="sr-only" for="jl-lab">Kelola jadwal lab</label>'
    . '<select id="jl-lab" name="lab_id" data-autosubmit class="inp alat-crud-pilih min-w-48">' . $opsi($labOpts, $labId, '— Kelola jadwal lab —') . '</select>'
    . '<button type="submit" class="btn btn-netral">' . ikon('eye', 'h-4 w-4') . 'Tampilkan</button></form>'
    . '<span class="hidden h-8 w-px bg-gray-200 lg:block dark:bg-gray-700" aria-hidden="true"></span>'
    . '<form method="get" class="flex flex-wrap items-center gap-2">'
    . '<label class="sr-only" for="jl-guru">Jadwal praktik guru</label>'
    . '<select id="jl-guru" name="guru_id" data-autosubmit class="inp alat-crud-pilih min-w-48">' . $opsi($guruOpts, $guruId, '— Jadwal praktik guru —') . '</select>'
    . '<button type="submit" class="btn btn-netral">' . ikon('users', 'h-4 w-4') . 'Lihat</button></form>';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'jadwal_lab',
    'helpTitle' => 'Jadwal Lab',
    'helpKelas' => '',
    'helpBody'  => '<p>Atur jadwal pemakaian tiap laboratorium. Pilih <b>lab</b> untuk mengelola slotnya (satu lab tak bisa dipakai dua kegiatan pada jam yang sama; guru pun tak bisa dijadwalkan di dua lab bersamaan).</p>
        <p class="mt-1">Pilih <b>guru</b> untuk melihat <b>jadwal praktik guru</b> tersebut lintas lab (tampilan baca saja).</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= ui_crud_alat([
    'baseUrl'      => $base,
    'cari'         => null,
    'kiriHtml'     => $kiri,
    'tambah'       => false,
    'impor'        => false,
    'tambahanHtml' => $modeLab ? '<button type="button" class="btn btn-utama" data-buka-dialog="tambah-jadwal-lab">' . ikon('plus', 'h-4 w-4') . 'Tambah jadwal</button>' : '',
]) ?>

<?php if ($modeLab || $modeGuru): ?>
    <p class="flex items-center gap-2 border-b border-gray-200 px-4 py-2.5 text-sm text-gray-600 md:px-6 dark:border-gray-700 dark:text-gray-300">
        <?php if ($modeLab): ?>
            <?= ikon('desktop-pc', 'h-4 w-4 shrink-0 text-gray-500') ?>Jadwal lab: <b class="text-gray-900 dark:text-white"><?= esc($labOpts[$labId] ?? '-') ?></b>
        <?php else: ?>
            <?= ikon('user', 'h-4 w-4 shrink-0 text-gray-500') ?>Jadwal praktik guru: <b class="text-gray-900 dark:text-white"><?= esc($guruOpts[$guruId] ?? '-') ?></b> <span class="lencana lencana-abu">baca saja</span>
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if (! $modeLab && ! $modeGuru): ?>
    <?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Pilih lab atau guru', 'teks' => 'Pilih lab untuk mengelola jadwal, atau pilih guru untuk melihat jadwal praktiknya.']) ?>
<?php else: ?>
    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Hari</th>
                    <th scope="col">Jam</th>
                    <th scope="col"><?= $modeGuru ? 'Lab' : 'Guru' ?></th>
                    <th scope="col">Kelas / kegiatan</th>
                    <?php if ($modeLab): ?><th scope="col" class="kolom-aksi">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5"><?= ui_kosong(['ikon' => 'calendar-week', 'judul' => 'Belum ada jadwal', 'teks' => $modeLab ? 'Tambahkan slot lewat tombol Tambah jadwal.' : 'Guru ini belum punya jadwal praktik di lab.']) ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td class="sel-judul"><?= esc(ucfirst(strtolower((string) $r['hari_nama']))) ?></td>
                        <td class="whitespace-nowrap">Jam <?= (int) $r['jam_ke'] ?> <span class="sel-ket">(<?= substr((string) $r['waktu_mulai'], 0, 5) ?>–<?= substr((string) $r['waktu_selesai'], 0, 5) ?>)</span></td>
                        <?php if ($modeGuru): ?>
                            <td><?= esc($r['lab_nama'] ?? '—') ?></td>
                        <?php else: ?>
                            <td><?= $r['guru_nama'] !== null ? esc($r['guru_nama']) : '<span class="text-gray-300 dark:text-gray-600">—</span>' ?></td>
                        <?php endif; ?>
                        <td>
                            <?php
                            $bits = array_filter([
                                $r['nama_kelas'] ?? null,
                                $r['nama_mapel'] ?? null,
                                $r['kegiatan'] ?? null,
                            ]);
                            echo $bits ? esc(implode(' · ', $bits)) : '<span class="text-gray-300 dark:text-gray-600">—</span>';
                            ?>
                        </td>
                        <?php if ($modeLab): ?>
                            <td class="kolom-aksi"><?= ui_crud_aksi(['ubah' => false, 'hapusUrl' => $base . '/delete/' . (int) $r['id'], 'konfirmasi' => 'Hapus slot jadwal ini?']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= ui_crud_kaki(['total' => count($rows), 'satuan' => 'slot jadwal']) ?>
<?php endif; ?>

<!-- Dialog Tambah jadwal (mode lab) -->
<?php if ($modeLab): ?>
    <?= ui_dialog_buka(['nama' => 'tambah-jadwal-lab', 'judul' => 'Tambah jadwal — ' . ($labOpts[$labId] ?? ''), 'ukuran' => 'lg']) ?>
    <form method="post" action="<?= $base ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="lab_id" value="<?= (int) $labId ?>">
        <div class="dialog-isi">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl" for="jl-hari">Hari <span class="text-red-600 dark:text-red-400">*</span></label>
                    <select id="jl-hari" name="hari_id" required class="inp">
                        <option value="">— Pilih —</option>
                        <?php foreach ($hariOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc(ucfirst(strtolower($nama))) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="jl-jam">Jam <span class="text-red-600 dark:text-red-400">*</span></label>
                    <select id="jl-jam" name="jam_id" required class="inp">
                        <option value="">— Pilih —</option>
                        <?php foreach ($jamOpts as $id => $lbl): ?><option value="<?= (int) $id ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="jl-guru-isi">Guru</label>
                    <select id="jl-guru-isi" name="guru_id" class="inp">
                        <option value="">— Tidak ada —</option>
                        <?php foreach ($guruOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="jl-kelas">Kelas</label>
                    <select id="jl-kelas" name="kelas_id" class="inp">
                        <option value="">— Tidak ada —</option>
                        <?php foreach ($kelasOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="jl-mapel">Mapel</label>
                    <select id="jl-mapel" name="mapel_id" class="inp">
                        <option value="">— Tidak ada —</option>
                        <?php foreach ($mapelOpts as $id => $nama): ?><option value="<?= (int) $id ?>"><?= esc($nama) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lbl" for="jl-kegiatan">Kegiatan</label>
                    <input type="text" id="jl-kegiatan" name="kegiatan" maxlength="150" placeholder="mis. Praktik Jaringan" class="inp">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl" for="jl-ket">Keterangan</label>
                    <input type="text" id="jl-ket" name="keterangan" maxlength="255" class="inp">
                </div>
            </div>
        </div>
        <div class="dialog-kaki">
            <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan</button>
        </div>
    </form>
    <?= ui_dialog_tutup() ?>
<?php endif; ?>
<?= $this->endSection() ?>
