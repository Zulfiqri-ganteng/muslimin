<?php
/**
 * Laporan Pembayaran PKL — siapa, kelas, jurusan, sudah bayar berapa (rumus & status: Libraries\PklLaporanBiaya).
 *
 * @var array       $f          saringan (kelas_id, jurusan, status, q, dari, sampai, beasiswa)
 * @var array       $ringkas    siswa, lunas, sebagian, belum, beasiswa, uang_masuk, total_kekurangan, per_jenis
 * @var list<array> $jenis      jenis biaya aktif
 * @var list<array> $baris      baris halaman ini
 * @var int         $total      jumlah baris seluruh halaman
 * @var list<array> $kelas
 * @var string      $queryUnduh query saringan untuk tombol Excel & paginasi
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
$warnaStatus = ['lunas' => 'bg-green-100 text-green-700', 'sebagian' => 'bg-amber-100 text-amber-800', 'belum' => 'bg-red-100 text-red-700'];
$adaSaringan = $f['kelas_id'] > 0 || $f['jurusan'] !== '' || $f['status'] !== '' || $f['q'] !== '' || $f['dari'] !== '' || $f['sampai'] !== '' || $f['beasiswa'];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_laporan_v1',
    'helpTitle' => 'Laporan Pembayaran PKL',
    'helpBody'  => '<p>Daftar siswa yang suratnya sudah diterbitkan beserta <b>biaya yang sudah dicatat</b> (dicatat saat surat diunduh &mdash; tidak ada input terpisah).</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Lunas</b> = Biaya PKL (tahun ajaran ini) + semua biaya bulanan (SPP, Tabungan, OSIS) untuk <u>bulan surat</u> sudah tercatat. Siswa penerima beasiswa tidak diwajibkan SPP.</li>'
        . '<li><b>Sebagian</b> = ada yang tercatat tetapi belum semuanya &middot; <b>Belum</b> = belum ada biaya tercatat. <b>Kekurangan</b> = jumlah biaya yang seharusnya sudah ada.</li>'
        . '<li>Filter <b>tanggal</b> memilih siswa yang punya catatan pada rentang itu dan menghitung uang yang masuk di rentang itu; status tetap dihitung dari seluruh catatan siswa.</li>'
        . '<li>Tombol <b>Unduh Excel</b> membuat berkas dengan lembar Rincian, Rekap Kelas, dan Rekap Jurusan sesuai saringan yang sedang dipakai. Jurusan sejenis (TKJ/TJKT/TKJT) digabung.</li>'
        . '<li>Salah catat? Buka ajuannya &rarr; kartu <b>Pembayaran siswa</b> &rarr; hapus catatan (wajib beralasan).</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<!-- Ringkasan: jumlah siswa per status, lalu uang -->
<div class="rise mb-5 space-y-3">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <?php foreach ([
            ['Siswa', (int) $ringkas['siswa'], 'text-slate-800'],
            ['Lunas', (int) $ringkas['lunas'], 'text-green-700'],
            ['Sebagian', (int) $ringkas['sebagian'], 'text-amber-700'],
            ['Belum', (int) $ringkas['belum'], 'text-red-600'],
            ['Penerima beasiswa', (int) $ringkas['beasiswa'], 'text-indigo-700'],
        ] as [$label, $nilai, $warna]): ?>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400"><?= esc($label) ?></p>
                <p class="mt-1 text-2xl font-extrabold <?= $warna ?>"><?= esc((string) $nilai) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-brand-200 bg-brand-50/60 px-5 py-3.5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-brand-700/70">Uang tercatat<?= ($f['dari'] !== '' || $f['sampai'] !== '') ? ' (pada rentang tanggal)' : '' ?></p>
            <p class="mt-1 text-2xl font-extrabold text-brand-700"><?= esc($rp((int) $ringkas['uang_masuk'])) ?></p>
        </div>
        <div class="rounded-2xl border border-red-200 bg-red-50/60 px-5 py-3.5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-red-700/70">Kekurangan (yang seharusnya sudah ada)</p>
            <p class="mt-1 text-2xl font-extrabold text-red-600"><?= esc($rp((int) $ringkas['total_kekurangan'])) ?></p>
        </div>
    </div>
</div>

<div class="rise rise-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="flex items-center gap-2 font-bold text-slate-800">Laporan Pembayaran <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500"><?= (int) $total ?></span></h2>
                <p class="mt-0.5 max-w-2xl text-xs text-slate-400">Siswa dengan surat PKL yang sudah disetujui, dan biaya yang tercatat saat suratnya diunduh.</p>
            </div>
            <a href="<?= site_url('admin/pkl/laporan/excel') . ($queryUnduh !== '' ? '?' . esc($queryUnduh, 'attr') : '') ?>" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-green-700 active:scale-95">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
                Unduh Excel<?= $adaSaringan ? ' (sesuai saringan)' : '' ?>
            </a>
        </div>

        <form method="get" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <input type="search" name="q" value="<?= esc($f['q'], 'attr') ?>" placeholder="Cari nama, NIS, perusahaan, no. surat…" class="inp sm:col-span-2" aria-label="Cari">
            <select name="kelas_id" class="inp" aria-label="Kelas">
                <option value="">Semua kelas</option>
                <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>" <?= (int) $k['id'] === (int) $f['kelas_id'] ? 'selected' : '' ?>><?= esc($k['nama_kelas']) ?></option><?php endforeach; ?>
            </select>
            <select name="jurusan" class="inp" aria-label="Jurusan">
                <option value="">Semua jurusan</option>
                <?php foreach (['TKJ', 'AKL', 'MP'] as $j): ?><option value="<?= $j ?>" <?= $f['jurusan'] === $j ? 'selected' : '' ?>><?= esc(PklLaporanBiaya::JURUSAN[$j]) ?></option><?php endforeach; ?>
            </select>
            <select name="status" class="inp" aria-label="Status">
                <option value="">Semua status</option>
                <?php foreach (PklLaporanBiaya::STATUS as $kode => $label): ?><option value="<?= $kode ?>" <?= $f['status'] === $kode ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
            </select>
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-500">Dari <input type="date" name="dari" value="<?= esc($f['dari'], 'attr') ?>" class="inp"></label>
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-500">Sampai <input type="date" name="sampai" value="<?= esc($f['sampai'], 'attr') ?>" class="inp"></label>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-600"><input type="checkbox" name="beasiswa" value="1" <?= $f['beasiswa'] ? 'checked' : '' ?> class="h-4 w-4"> Hanya penerima beasiswa</label>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                <button type="submit" class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Terapkan</button>
                <?php if ($adaSaringan): ?><a href="<?= site_url('admin/pkl/laporan') ?>" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($baris === []): ?>
        <div class="px-5 py-12 text-center">
            <p class="font-semibold text-slate-700"><?= $adaSaringan ? 'Tidak ada siswa yang cocok dengan saringan.' : 'Belum ada data pembayaran.' ?></p>
            <p class="mt-1 text-sm text-slate-400"><?= $adaSaringan ? '' : 'Data muncul setelah surat PKL diunduh dan biayanya dicatat.' ?></p>
        </div>
    <?php else: ?>
        <!-- Tabel (layar sangat lebar; di bawah itu memakai kartu agar tak perlu geser samping) -->
        <div class="hidden overflow-x-auto 2xl:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Siswa</th>
                        <th class="px-3 py-3 font-semibold">Kelas · Jurusan</th>
                        <th class="px-3 py-3 font-semibold">Perusahaan · Surat</th>
                        <?php foreach ($jenis as $j): ?><th class="whitespace-nowrap px-3 py-3 text-right font-semibold"><?= esc($j['nama']) ?></th><?php endforeach; ?>
                        <th class="px-3 py-3 text-right font-semibold">Total</th>
                        <th class="px-3 py-3 text-right font-semibold">Kurang</th>
                        <th class="px-5 py-3 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($baris as $b): ?>
                        <tr class="align-top hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <p class="font-semibold text-slate-800"><?= $b['ajuan_id'] ? '<a href="' . esc(site_url('admin/pkl/' . $b['ajuan_id']), 'attr') . '" class="hover:text-brand-700">' . esc($b['nama']) . '</a>' : esc($b['nama']) ?></p>
                                <p class="text-xs text-slate-400">NIS <?= esc($b['nis'] ?: '—') ?><?= $b['beasiswa'] ? ' · <span class="font-semibold text-indigo-600">🎓 ' . esc($b['beasiswa']) . '</span>' : '' ?></p>
                                <?php if ($b['keringanan']): ?><p class="mt-0.5 max-w-[16rem] text-xs text-amber-700">Keringanan: <?= esc($b['keringanan']) ?></p><?php endif; ?>
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-600"><b class="text-slate-700"><?= esc($b['kelas'] ?: '—') ?></b><br><?= esc($b['jurusan']) ?></td>
                            <td class="px-3 py-3 text-xs text-slate-600"><?= esc($b['perusahaan'] ?: '—') ?><br><span class="font-mono text-slate-400"><?= esc($b['nomor_surat'] ?: 'belum bernomor') ?></span></td>
                            <?php foreach ($jenis as $j): $v = $b['bayar'][$j['kode']] ?? ['total' => 0, 'periode' => []]; ?>
                                <td class="whitespace-nowrap px-3 py-3 text-right text-xs">
                                    <?php if ($v['total'] > 0): ?><b class="text-slate-800"><?= esc($rp($v['total'])) ?></b><?php if ($j['siklus'] === 'bulanan'): ?><br><span class="text-slate-400"><?= esc(implode(', ', $v['periode'])) ?></span><?php endif; ?>
                                    <?php elseif ($j['kode'] === 'spp' && $b['beasiswa']): ?><span class="text-indigo-500">dibebaskan</span>
                                    <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm font-bold text-slate-800"><?= esc($rp((int) $b['total_bayar'])) ?></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-xs font-semibold <?= $b['kekurangan'] > 0 ? 'text-red-600' : 'text-slate-300' ?>"><?= $b['kekurangan'] > 0 ? esc($rp((int) $b['kekurangan'])) : '—' ?></td>
                            <td class="px-5 py-3 text-center"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $warnaStatus[$b['status']] ?>"><?= esc($b['status_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Kartu (HP, tablet, laptop) -->
        <ul class="divide-y divide-slate-100 2xl:hidden">
            <?php foreach ($baris as $b): ?>
                <li class="px-4 py-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800"><?= $b['ajuan_id'] ? '<a href="' . esc(site_url('admin/pkl/' . $b['ajuan_id']), 'attr') . '">' . esc($b['nama']) . '</a>' : esc($b['nama']) ?></p>
                            <p class="text-xs text-slate-500"><?= esc($b['kelas'] ?: '—') ?> · <?= esc($b['jurusan']) ?> · NIS <?= esc($b['nis'] ?: '—') ?></p>
                        </div>
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $warnaStatus[$b['status']] ?>"><?= esc($b['status_label']) ?></span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400"><?= esc($b['perusahaan'] ?: '—') ?> · <span class="font-mono"><?= esc($b['nomor_surat'] ?: 'belum bernomor') ?></span></p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                        <?php foreach ($jenis as $j): $v = $b['bayar'][$j['kode']] ?? ['total' => 0, 'periode' => []]; ?>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500"><?= esc($j['nama']) ?></dt><dd class="font-semibold <?= $v['total'] > 0 ? 'text-slate-800' : 'text-slate-300' ?>"><?= $v['total'] > 0 ? esc($rp($v['total'])) : ($j['kode'] === 'spp' && $b['beasiswa'] ? 'bebas' : '—') ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                    <p class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 text-xs"><span class="text-slate-500">Total <b class="text-slate-800"><?= esc($rp((int) $b['total_bayar'])) ?></b></span><?php if ($b['kekurangan'] > 0): ?><span class="font-semibold text-red-600">Kurang <?= esc($rp((int) $b['kekurangan'])) ?></span><?php endif; ?></p>
                    <?php if ($b['beasiswa']): ?><p class="mt-1 text-xs font-semibold text-indigo-600">🎓 Beasiswa <?= esc($b['beasiswa']) ?></p><?php endif; ?>
                    <?php if ($b['keringanan']): ?><p class="mt-1 text-xs text-amber-700">Keringanan: <?= esc($b['keringanan']) ?></p><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($jmlHal > 1): ?>
            <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
                <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">← Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                <span class="text-xs text-slate-400">Halaman <?= $page ?> dari <?= $jmlHal ?></span>
                <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="font-semibold text-brand-700 hover:underline">Berikutnya →</a><?php else: ?><span></span><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
