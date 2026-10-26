<?php
/**
 * Laporan Pembayaran PKL — siapa, kelas, jurusan, sudah bayar berapa (rumus & status: Libraries\PklLaporanBiaya).
 * Rombak tampilan F9: tab kit (_nav), Unduh Excel ber-ikon di kepala halaman, kartu angka (stat-*), kartu saringan berlabel,
 * tabel kit (layar sangat lebar) + kartu (HP/laptop), lencana status kit, ikon pengganti emoji, ui_kosong, paginasi tombol kit.
 * Rapi tampilan U6 (2026-10-26): tujuh kotak angka → satu kartu ringkasan; saringan Kelas/Jurusan/Tanggal/Beasiswa dilipat di
 * "Saringan lain" (terbuka bila dipakai). Nama isian saringan, angka, paginasi, dan Excel TETAP sama.
 *
 * @var array       $f          saringan (kelas_id, jurusan, status, q, dari, sampai, beasiswa)
 * @var array       $ringkas    siswa, lunas, sebagian, belum, beasiswa, uang_masuk, total_kekurangan, per_jenis
 * @var list<array> $jenis      jenis biaya aktif
 * @var list<array> $baris      baris halaman ini
 * @var int         $total      jumlah baris seluruh halaman
 * @var list<array> $kelas
 * @var string      $queryUnduh query saringan untuk tombol Excel & paginasi
 * @var int         $page
 * @var int         $jmlHal
 * Bersama dari PklDasar::dasar(): $tab, $hitungTab.
 */

use App\Libraries\PklBiaya;
use App\Libraries\PklLaporanBiaya;

$rp = static fn (int $n): string => PklBiaya::rupiah($n);
$urlHal = static function (int $hal) use ($f): string {
    $q = array_filter($f, static fn ($v) => $v !== '' && $v !== 0 && $v !== false && $v !== null);
    if ($hal > 1) {
        $q['page'] = $hal;
    }

    return site_url('admin/pkl/laporan') . ($q ? '?' . http_build_query($q) : '');
};
$warnaStatus = ['lunas' => 'lencana-hijau', 'sebagian' => 'lencana-kuning', 'belum' => 'lencana-merah'];
$adaSaringan = $f['kelas_id'] > 0 || $f['jurusan'] !== '' || $f['status'] !== '' || $f['q'] !== '' || $f['dari'] !== '' || $f['sampai'] !== '' || $f['beasiswa'];
$tandaBeasiswa = static fn (string $teks): string => '<span class="inline-flex items-center gap-1 font-semibold text-brand-700 dark:text-brand-400">' . ikon('award', 'h-3.5 w-3.5 shrink-0') . esc($teks) . '</span>';
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/pkl/laporan/excel') . ($queryUnduh !== '' ? '?' . esc($queryUnduh, 'attr') : '') ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel<?= $adaSaringan ? ' (sesuai saringan)' : '' ?></a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_laporan_v2',
    'helpTitle' => 'Laporan Pembayaran PKL',
    'helpBody'  => '<p>Daftar siswa yang suratnya sudah diterbitkan beserta <b>biaya yang sudah dicatat</b> (dicatat saat surat diunduh &mdash; tidak ada input terpisah).</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Lunas</b> = Biaya PKL (tahun ajaran ini) + semua biaya bulanan (SPP, Tabungan, OSIS) untuk <u>bulan surat</u> sudah tercatat. Siswa penerima beasiswa tidak diwajibkan SPP.</li>'
        . '<li><b>Sebagian</b> = ada yang tercatat tetapi belum semuanya &middot; <b>Belum</b> = belum ada biaya tercatat. <b>Kekurangan</b> = jumlah biaya yang seharusnya sudah ada.</li>'
        . '<li>Angka ringkasan ada di satu kartu paling atas. Saringan <b>Kelas, Jurusan, Tanggal, Beasiswa</b> ada di <b>Saringan lain</b> (terbuka sendiri bila sedang dipakai).</li>'
        . '<li>Filter <b>tanggal</b> memilih siswa yang punya catatan pada rentang itu dan menghitung uang yang masuk di rentang itu; status tetap dihitung dari seluruh catatan siswa.</li>'
        . '<li>Tombol <b>Unduh Excel</b> membuat berkas dengan lembar Rincian, Rekap Kelas, dan Rekap Jurusan sesuai saringan yang sedang dipakai. Jurusan sejenis (TKJ/TJKT/TKJT) digabung.</li>'
        . '<li>Salah catat? Buka ajuannya &rarr; kartu <b>Pembayaran siswa</b> &rarr; hapus catatan (wajib beralasan).</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <!-- Ringkasan (U6: satu kartu; angka sama dengan dulu tujuh kotak) -->
    <section class="kartu kartu-isi" aria-label="Ringkasan pembayaran">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4 lg:grid-cols-7">
            <?php foreach ([
                ['Siswa', (string) (int) $ringkas['siswa'], 'text-gray-900 dark:text-white'],
                ['Lunas', (string) (int) $ringkas['lunas'], 'text-green-700 dark:text-green-400'],
                ['Sebagian', (string) (int) $ringkas['sebagian'], 'text-yellow-700 dark:text-yellow-400'],
                ['Belum', (string) (int) $ringkas['belum'], 'text-red-700 dark:text-red-400'],
                ['Penerima beasiswa', (string) (int) $ringkas['beasiswa'], 'text-brand-700 dark:text-brand-400'],
                ['Uang tercatat' . (($f['dari'] !== '' || $f['sampai'] !== '') ? ' (rentang tanggal)' : ''), $rp((int) $ringkas['uang_masuk']), 'text-brand-700 dark:text-brand-400'],
                ['Kekurangan', $rp((int) $ringkas['total_kekurangan']), 'text-red-700 dark:text-red-400'],
            ] as [$lbl, $nilai, $warna]): ?>
                <div class="min-w-0"><dt class="stat-label text-xs"><?= esc($lbl) ?></dt><dd class="mt-1 truncate text-xl font-bold tabular-nums <?= $warna ?>"><?= esc($nilai) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </section>

    <!-- Saringan -->
    <form method="get" class="kartu kartu-isi grid gap-4 sm:grid-cols-2 lg:grid-cols-4" role="search">
        <div class="sm:col-span-2">
            <label class="lbl" for="lp-q">Cari</label>
            <input type="search" id="lp-q" name="q" value="<?= esc($f['q'], 'attr') ?>" placeholder="Cari nama, NIS, perusahaan, no. surat…" class="inp">
        </div>
        <div>
            <label class="lbl" for="lp-status">Status</label>
            <select id="lp-status" name="status" class="inp">
                <option value="">Semua status</option>
                <?php foreach (PklLaporanBiaya::STATUS as $kode => $label): ?><option value="<?= $kode ?>"<?= $f['status'] === $kode ? ' selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <button type="submit" class="btn btn-utama"><?= ikon('search', 'h-4 w-4') ?>Terapkan</button>
            <?php if ($adaSaringan): ?><a href="<?= site_url('admin/pkl/laporan') ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a><?php endif; ?>
        </div>
        <details class="sm:col-span-2 lg:col-span-4"<?= ($f['kelas_id'] > 0 || $f['jurusan'] !== '' || $f['dari'] !== '' || $f['sampai'] !== '' || $f['beasiswa']) ? ' open' : '' ?>>
            <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-brand-700 hover:underline dark:text-brand-400"><?= ikon('adjustments-horizontal', 'h-4 w-4') ?>Saringan lain (kelas, jurusan, tanggal, beasiswa)</summary>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="lbl" for="lp-kelas">Kelas</label>
                <select id="lp-kelas" name="kelas_id" class="inp">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>"<?= (int) $k['id'] === (int) $f['kelas_id'] ? ' selected' : '' ?>><?= esc($k['nama_kelas']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="lp-jurusan">Jurusan</label>
                <select id="lp-jurusan" name="jurusan" class="inp">
                    <option value="">Semua jurusan</option>
                    <?php foreach (['TKJ', 'AKL', 'MP'] as $j): ?><option value="<?= $j ?>"<?= $f['jurusan'] === $j ? ' selected' : '' ?>><?= esc(PklLaporanBiaya::JURUSAN[$j]) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl" for="lp-dari">Dari tanggal</label>
                <input type="date" id="lp-dari" name="dari" value="<?= esc($f['dari'], 'attr') ?>" class="inp">
            </div>
            <div>
                <label class="lbl" for="lp-sampai">Sampai tanggal</label>
                <input type="date" id="lp-sampai" name="sampai" value="<?= esc($f['sampai'], 'attr') ?>" class="inp">
            </div>
            <label class="flex items-center gap-2 self-end pb-2.5 text-sm font-medium text-gray-900 dark:text-white"><input type="checkbox" name="beasiswa" value="1"<?= $f['beasiswa'] ? ' checked' : '' ?> class="cek"> Hanya penerima beasiswa</label>
            </div>
        </details>
    </form>

    <section class="kartu overflow-hidden" aria-labelledby="judul-laporan-bayar">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h2 id="judul-laporan-bayar" class="kartu-judul flex items-center gap-2">Laporan Pembayaran<span class="lencana lencana-abu"><?= (int) $total ?></span></h2>
                <p class="kartu-ket max-w-2xl">Siswa dengan surat PKL yang sudah disetujui, dan biaya yang tercatat saat suratnya diunduh.</p>
            </div>
        </div>

        <?php if ($baris === []): ?>
            <?= ui_kosong(['ikon' => 'receipt', 'judul' => $adaSaringan ? 'Tidak ada siswa yang cocok dengan saringan' : 'Belum ada data pembayaran',
                'teks' => $adaSaringan ? 'Ubah saringan, atau tekan Reset.' : 'Data muncul setelah surat PKL diunduh dan biayanya dicatat.']) ?>
        <?php else: ?>
            <!-- Tabel (layar sangat lebar; di bawah itu memakai kartu agar tak perlu geser samping) -->
            <div class="hidden 2xl:block">
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col">Siswa</th>
                                <th scope="col">Kelas · Jurusan</th>
                                <th scope="col">Perusahaan · Surat</th>
                                <?php foreach ($jenis as $j): ?><th scope="col" class="text-right"><?= esc($j['nama']) ?></th><?php endforeach; ?>
                                <th scope="col" class="text-right">Total</th>
                                <th scope="col" class="text-right">Kurang</th>
                                <th scope="col" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b): ?>
                                <tr class="align-top">
                                    <td>
                                        <p class="font-semibold text-gray-900 dark:text-white"><?= $b['ajuan_id'] ? '<a href="' . esc(site_url('admin/pkl/' . $b['ajuan_id']), 'attr') . '" class="hover:text-brand-700 dark:hover:text-brand-400">' . esc($b['nama']) . '</a>' : esc($b['nama']) ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">NIS <?= esc($b['nis'] ?: '—') ?><?= $b['beasiswa'] ? ' · ' . $tandaBeasiswa((string) $b['beasiswa']) : '' ?></p>
                                        <?php if ($b['keringanan']): ?><p class="mt-0.5 max-w-[16rem] text-xs text-yellow-700 dark:text-yellow-400">Keringanan: <?= esc($b['keringanan']) ?></p><?php endif; ?>
                                    </td>
                                    <td class="text-xs"><b class="text-gray-900 dark:text-white"><?= esc($b['kelas'] ?: '—') ?></b><br><?= esc($b['jurusan']) ?></td>
                                    <td class="text-xs"><?= esc($b['perusahaan'] ?: '—') ?><br><span class="font-mono text-gray-500 dark:text-gray-400"><?= esc($b['nomor_surat'] ?: 'belum bernomor') ?></span></td>
                                    <?php foreach ($jenis as $j): $v = $b['bayar'][$j['kode']] ?? ['total' => 0, 'periode' => []]; ?>
                                        <td class="text-right text-xs whitespace-nowrap">
                                            <?php if ($v['total'] > 0): ?><b class="text-gray-900 dark:text-white"><?= esc($rp($v['total'])) ?></b><?php if ($j['siklus'] === 'bulanan'): ?><br><span class="text-gray-500 dark:text-gray-400"><?= esc(implode(', ', $v['periode'])) ?></span><?php endif; ?>
                                            <?php elseif ($j['kode'] === 'spp' && $b['beasiswa']): ?><span class="text-brand-700 dark:text-brand-400">dibebaskan</span>
                                            <?php else: ?><span class="text-gray-500 dark:text-gray-400">—</span><?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-right font-bold whitespace-nowrap text-gray-900 tabular-nums dark:text-white"><?= esc($rp((int) $b['total_bayar'])) ?></td>
                                    <td class="text-right text-xs font-semibold whitespace-nowrap <?= $b['kekurangan'] > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' ?>"><?= $b['kekurangan'] > 0 ? esc($rp((int) $b['kekurangan'])) : '—' ?></td>
                                    <td class="text-center"><span class="lencana <?= $warnaStatus[$b['status']] ?? 'lencana-abu' ?>"><?= esc($b['status_label']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kartu (HP, tablet, laptop) -->
            <ul class="divide-y divide-gray-200 2xl:hidden dark:divide-gray-700">
                <?php foreach ($baris as $b): ?>
                    <li class="px-4 py-3.5 sm:px-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white"><?= $b['ajuan_id'] ? '<a href="' . esc(site_url('admin/pkl/' . $b['ajuan_id']), 'attr') . '" class="hover:text-brand-700 dark:hover:text-brand-400">' . esc($b['nama']) . '</a>' : esc($b['nama']) ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc($b['kelas'] ?: '—') ?> · <?= esc($b['jurusan']) ?> · NIS <?= esc($b['nis'] ?: '—') ?></p>
                            </div>
                            <span class="lencana <?= $warnaStatus[$b['status']] ?? 'lencana-abu' ?> shrink-0"><?= esc($b['status_label']) ?></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc($b['perusahaan'] ?: '—') ?> · <span class="font-mono"><?= esc($b['nomor_surat'] ?: 'belum bernomor') ?></span></p>
                        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                            <?php foreach ($jenis as $j): $v = $b['bayar'][$j['kode']] ?? ['total' => 0, 'periode' => []]; ?>
                                <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400"><?= esc($j['nama']) ?></dt><dd class="font-semibold <?= $v['total'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' ?>"><?= $v['total'] > 0 ? esc($rp($v['total'])) : ($j['kode'] === 'spp' && $b['beasiswa'] ? 'bebas' : '—') ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                        <p class="mt-2 flex items-center justify-between border-t border-gray-200 pt-2 text-xs dark:border-gray-700"><span class="text-gray-500 dark:text-gray-400">Total <b class="text-gray-900 dark:text-white"><?= esc($rp((int) $b['total_bayar'])) ?></b></span><?php if ($b['kekurangan'] > 0): ?><span class="font-semibold text-red-700 dark:text-red-400">Kurang <?= esc($rp((int) $b['kekurangan'])) ?></span><?php endif; ?></p>
                        <?php if ($b['beasiswa']): ?><p class="mt-1 text-xs"><?= $tandaBeasiswa('Beasiswa ' . $b['beasiswa']) ?></p><?php endif; ?>
                        <?php if ($b['keringanan']): ?><p class="mt-1 text-xs text-yellow-700 dark:text-yellow-400">Keringanan: <?= esc($b['keringanan']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($jmlHal > 1): ?>
                <div class="kartu-kaki flex items-center justify-between gap-3 text-sm">
                    <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('chevron-left', 'h-4 w-4') ?>Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                    <span class="text-gray-500 dark:text-gray-400">Halaman <b class="text-gray-900 dark:text-white"><?= $page ?></b> dari <b class="text-gray-900 dark:text-white"><?= $jmlHal ?></b></span>
                    <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="btn btn-netral btn-sm">Berikutnya<?= ikon('chevron-right', 'h-4 w-4') ?></a><?php else: ?><span></span><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>
