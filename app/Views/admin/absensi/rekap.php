<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'rekap_absensi',
    'helpTitle' => 'Rekap Absensi Guru',
    'helpBody'  => '<p>Ringkasan kehadiran tiap guru pada rentang tanggal yang dipilih. <b>Total Sesi</b> dihitung dari jadwal KBM (jumlah sesi guru pada hari aktif dalam rentang). <b>Hadir</b> = Total − (Telat + Izin + Sakit + Alpa). Ubah <b>Dari</b>/<b>Sampai</b> lalu klik Tampilkan. Gunakan tombol <b>Export</b> untuk PDF atau Excel sebagai lampiran perhitungan gaji.</p>',
]) ?>

<?php
    $qs   = '?dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai);
    $cols = [
        'hadir' => ['Hadir', 'text-emerald-700'],
        'telat' => ['Telat', 'text-amber-700'],
        'izin'  => ['Izin', 'text-sky-700'],
        'sakit' => ['Sakit', 'text-violet-700'],
        'alpa'  => ['Alpa', 'text-red-700'],
    ];
?>

<!-- Filter rentang + export -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Dari</label>
                <input type="date" name="dari" value="<?= esc($dari) ?>" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Sampai</label>
                <input type="date" name="sampai" value="<?= esc($sampai) ?>" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-brand-500 outline-none">
            </div>
            <button class="rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-bold px-5 py-2.5 text-sm transition">Tampilkan</button>
        </form>
        <?php if (! empty($rows)): ?>
            <div class="flex flex-wrap gap-2">
                <a href="<?= site_url('admin/absensi/rekap/excel') . $qs ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Excel
                </a>
                <a href="<?= site_url('admin/absensi/rekap/pdf') . $qs ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7h-3V4a2 2 0 00-2-2H10a2 2 0 00-2 2v3H5a2 2 0 00-2 2v6a2 2 0 002 2h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
                    Export PDF
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
        Belum ada data pada periode ini. Pastikan jadwal KBM sudah tersusun.
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-3">
            <p class="font-bold text-slate-800">Periode <?= esc($dari) ?> s/d <?= esc($sampai) ?></p>
            <p class="text-sm text-slate-400"><?= count($rows) ?> guru</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold w-10 text-center">#</th>
                        <th class="px-4 py-2.5 font-semibold">Guru</th>
                        <th class="px-4 py-2.5 font-semibold w-24 text-center">Total Sesi</th>
                        <?php foreach ($cols as [$lbl, $tc]): ?>
                            <th class="px-4 py-2.5 font-semibold w-20 text-center <?= $tc ?>"><?= $lbl ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $i => $r): ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-2.5 text-center text-slate-400"><?= $i + 1 ?></td>
                            <td class="px-4 py-2.5"><span class="text-slate-400"><?= esc($r['kode']) ?></span> · <span class="font-semibold text-slate-700"><?= esc($r['nama']) ?></span></td>
                            <td class="px-4 py-2.5 text-center font-bold text-slate-700"><?= (int) $r['total'] ?></td>
                            <?php foreach (array_keys($cols) as $k): $v = (int) $r[$k]; ?>
                                <td class="px-4 py-2.5 text-center <?= $v > 0 ? $cols[$k][1] . ' font-bold' : 'text-slate-300' ?>"><?= $v ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-bold text-slate-700">
                        <td class="px-4 py-2.5"></td>
                        <td class="px-4 py-2.5">TOTAL</td>
                        <td class="px-4 py-2.5 text-center"><?= (int) $sum['total'] ?></td>
                        <?php foreach (array_keys($cols) as $k): ?>
                            <td class="px-4 py-2.5 text-center"><?= (int) $sum[$k] ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
