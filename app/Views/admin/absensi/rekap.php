<?php
/**
 * Rekap Absensi Guru (rombak tampilan F6: kartu + tabel kit). Saringan periode & jabatan; Export Excel/PDF di kepala halaman (ikut
 * saringan); Laporan Bulanan format sekolah (Excel/PDF, data-noload → layar "Memproses" tidak menunggu unduhan); tarif potongan &
 * transport; tabel rekap per guru + baris TOTAL. Warna status tetap: hadir hijau, telat kuning, izin biru, sakit pink, alpa merah.
 *
 * @var string $dari         Awal periode
 * @var string $sampai       Akhir periode
 * @var int    $jabatanId    Saringan jabatan
 * @var array  $jabatanOpts  Opsi jabatan
 * @var array  $rows         Rekap per guru
 * @var array  $sum          Total
 * @var int    $hariTercatat Jumlah hari diabsen
 * @var string $bulanLaporan Bulan bawaan laporan (Y-m)
 * @var int    $tarifJp      Potongan per JP
 * @var int    $tarifTrans   Transport per hari
 */
$jabatanId   = (int) ($jabatanId ?? 0);
$jabatanOpts = $jabatanOpts ?? [];
// Saringan jabatan ikut terbawa ke tautan rincian & tombol export.
$qs = '?dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai) . ($jabatanId > 0 ? '&jabatan_id=' . $jabatanId : '');
$cols = [
    'hadir' => ['Hadir', 'text-green-700 dark:text-green-400'],
    'telat' => ['Telat', 'text-yellow-700 dark:text-yellow-400'],
    'izin'  => ['Izin', 'text-blue-700 dark:text-blue-300'],
    'sakit' => ['Sakit', 'text-pink-700 dark:text-pink-300'],
    'alpa'  => ['Alpa', 'text-red-700 dark:text-red-300'],
];
?>
<?= $this->extend('layouts/admin') ?>

<?php if (! empty($rows)): ?>
    <?= $this->section('aksi') ?>
    <a href="<?= site_url('admin/absensi/rekap/excel') . $qs ?>" class="btn btn-netral" title="Rekap periode ini (ikut saringan) ke Excel"><?= ikon('download', 'h-4 w-4') ?>Export Excel</a>
    <a href="<?= site_url('admin/absensi/rekap/pdf') . $qs ?>" target="_blank" rel="noopener" class="btn btn-netral" title="Rekap periode ini (ikut saringan) dalam PDF"><?= ikon('printer', 'h-4 w-4') ?>Export PDF</a>
    <?= $this->endSection() ?>
<?php endif; ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'rekap_absensi_v3',
    'helpTitle' => 'Rekap Absensi Guru',
    'helpBody'  => '<p>Ringkasan kehadiran tiap guru pada rentang tanggal yang dipilih. Rekap <b>hanya menghitung hari yang sudah diabsen</b> (hari yang Anda buka lalu klik Simpan) — hari libur atau yang belum dikelola tidak dihitung. <b>Total hari</b> dihitung per hari (bukan per sesi) dan <b>menggabungkan dua sumber</b>: hari mengajar terjadwal + hari masuk kerja di luar jadwal. <b>Hadir</b> = Total − (Telat + Izin + Sakit + Alpa). Ubah <b>Dari</b>/<b>Sampai</b> lalu klik Tampilkan.</p>
        <p class="mt-2"><b>Kolom &amp; saringan Jabatan.</b> Kolom <b>Jabatan</b> menampilkan jabatan utama guru (kuning = jabatan struktural); arahkan kursor untuk melihat seluruh jabatannya bila lebih dari satu. Pakai saringan <b>Jabatan</b> untuk melihat kelompok tertentu saja, mis. hanya para <b>Wakil Kepala Sekolah</b>.</p>
        <p class="mt-2">Tombol <b>Export</b> (Excel / PDF) di kanan atas <b>mengikuti saringan yang sedang aktif</b> — bila Anda menyaring satu jabatan, berkas yang terunduh berisi kelompok itu saja dan judulnya mencantumkan nama jabatannya. Klik nama guru untuk melihat rincian tanggalnya.</p>',
]) ?>

<div class="space-y-4">
    <!-- Saringan periode & jabatan -->
    <form method="get" class="kartu kartu-isi flex flex-wrap items-end gap-3">
        <div>
            <label class="lbl" for="rk-dari">Dari</label>
            <input type="date" id="rk-dari" name="dari" value="<?= esc($dari, 'attr') ?>" class="inp">
        </div>
        <div>
            <label class="lbl" for="rk-sampai">Sampai</label>
            <input type="date" id="rk-sampai" name="sampai" value="<?= esc($sampai, 'attr') ?>" class="inp">
        </div>
        <div>
            <label class="lbl" for="rk-jabatan">Jabatan</label>
            <select id="rk-jabatan" name="jabatan_id" class="inp">
                <option value="">Semua jabatan</option>
                <?php foreach ($jabatanOpts as $id => $nama): ?>
                    <option value="<?= (int) $id ?>"<?= $jabatanId === (int) $id ? ' selected' : '' ?>><?= esc($nama) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-utama"><?= ikon('search', 'h-4 w-4') ?>Tampilkan</button>
        <?php if ($jabatanId > 0): ?>
            <a href="<?= site_url('admin/absensi/rekap') ?>?dari=<?= urlencode($dari) ?>&amp;sampai=<?= urlencode($sampai) ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a>
        <?php endif; ?>
    </form>

    <!-- Laporan bulanan format sekolah (matriks P/S + rekap hadir + rekap tidak hadir & potongan) + tarif -->
    <section class="kartu" aria-labelledby="judul-laporan">
        <div class="kartu-kepala">
            <div>
                <h2 id="judul-laporan" class="kartu-judul">Laporan bulanan <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(format sekolah)</span></h2>
                <p class="kartu-ket max-w-2xl">Tiga lembar seperti lembar manual sekolah: <b>ABSEN</b> (tanggal × Pagi/Siang, kode H/HT/I/S/TH), <b>REKAP HADIR</b>, dan <b>REKAP TIDAK HADIR</b> berikut total JP &amp; potongan.</p>
            </div>
        </div>
        <div class="kartu-isi flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <form method="get" class="flex flex-wrap items-end gap-2" data-noload>
                <div>
                    <label class="lbl" for="rk-bulan">Bulan</label>
                    <input type="month" id="rk-bulan" name="bulan" value="<?= esc($bulanLaporan ?? date('Y-m'), 'attr') ?>" required class="inp">
                </div>
                <button type="submit" formaction="<?= site_url('admin/absensi/laporan/excel') ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Excel</button>
                <button type="submit" formaction="<?= site_url('admin/absensi/laporan/pdf') ?>" formtarget="_blank" class="btn btn-netral"><?= ikon('printer', 'h-4 w-4') ?>PDF</button>
            </form>
            <form method="post" action="<?= site_url('admin/absensi/tarif') ?>" class="flex flex-wrap items-end gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700/50">
                <?= csrf_field() ?>
                <div>
                    <label class="lbl" for="rk-potongan">Potongan per JP (Rp)</label>
                    <input type="number" id="rk-potongan" name="absensi_potongan_jp" min="0" step="500" value="<?= (int) ($tarifJp ?? 5000) ?>" class="inp w-36">
                </div>
                <div>
                    <label class="lbl" for="rk-transport">Transport per hari (Rp)</label>
                    <input type="number" id="rk-transport" name="absensi_transport" min="0" step="500" value="<?= (int) ($tarifTrans ?? 0) ?>" class="inp w-36">
                </div>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan tarif</button>
                <p class="hint w-full">Potongan berlaku untuk telat, izin, sakit &amp; tidak hadir (per JP). Transport 0 = kolom uang transport tidak ditampilkan.</p>
            </form>
        </div>
    </section>

    <?php if (empty($rows)): ?>
        <div class="kartu">
            <?= ui_kosong(['ikon' => 'calendar-month', 'judul' => 'Belum ada hari yang diabsen pada periode ini',
                'teks' => 'Rekap akan terisi setelah tanggal dibuka di menu Absensi Guru lalu disimpan.',
                'aksi' => '<a href="' . site_url('admin/absensi') . '" class="btn btn-utama">' . ikon('clipboard-check', 'h-4 w-4') . 'Buka Absensi Guru</a>']) ?>
        </div>
    <?php else: ?>
        <section class="kartu" aria-labelledby="judul-rekap">
            <div class="kartu-kepala">
                <h2 id="judul-rekap" class="text-base font-semibold text-gray-900 dark:text-white">Periode <?= esc($dari) ?> s/d <?= esc($sampai) ?></h2>
                <span class="text-sm text-gray-500 dark:text-gray-400"><?= (int) ($hariTercatat ?? 0) ?> hari diabsen &middot; <?= count($rows) ?> guru</span>
            </div>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col" class="w-10 text-center">#</th>
                            <th scope="col">Guru</th>
                            <th scope="col">Jabatan</th>
                            <th scope="col" class="text-center">Total hari</th>
                            <?php foreach ($cols as [$lbl, $tc]): ?>
                                <th scope="col" class="text-center <?= $tc ?>"><?= $lbl ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): ?>
                            <tr>
                                <td class="text-center"><?= $i + 1 ?></td>
                                <td class="min-w-48">
                                    <a href="<?= site_url('admin/absensi/rekap/guru/' . (int) $r['id']) . $qs ?>" class="group inline-flex items-center gap-1.5">
                                        <span class="text-gray-500 dark:text-gray-400"><?= esc($r['kode']) ?></span> · <span class="font-semibold text-gray-900 group-hover:text-brand-700 group-hover:underline dark:text-white dark:group-hover:text-brand-400"><?= esc($r['nama']) ?></span>
                                        <span class="text-gray-400 group-hover:text-brand-600"><?= ikon('chevron-right', 'h-3.5 w-3.5') ?></span>
                                    </a>
                                </td>
                                <td>
                                    <?php if (($r['jabatan'] ?? '') !== ''): ?>
                                        <span class="lencana <?= ! empty($r['struktural']) ? 'lencana-kuning' : 'lencana-abu' ?>" title="<?= esc($r['jabatan_all'] ?? '', 'attr') ?>"><?= esc($r['jabatan']) ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="text-center font-semibold text-gray-900 dark:text-white"><?= (int) $r['total'] ?></td>
                                <?php foreach (array_keys($cols) as $k): $v = (int) $r[$k]; ?>
                                    <td class="text-center <?= $v > 0 ? $cols[$k][1] . ' font-semibold' : 'text-gray-500 dark:text-gray-400' ?>"><?= $v ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold text-gray-900 dark:bg-gray-700 dark:text-white">
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3">TOTAL</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-center"><?= (int) $sum['total'] ?></td>
                            <?php foreach (array_keys($cols) as $k): ?>
                                <td class="px-4 py-3 text-center"><?= (int) $sum[$k] ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
