<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
    $statusOpts = ['telat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];
    $badge = [
        'telat' => 'bg-amber-50 text-amber-700 border-amber-200',
        'izin'  => 'bg-sky-50 text-sky-700 border-sky-200',
        'sakit' => 'bg-violet-50 text-violet-700 border-violet-200',
        'alpa'  => 'bg-red-50 text-red-700 border-red-200',
    ];
    $chip = [
        'hadir' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'telat' => 'bg-amber-50 text-amber-700 border-amber-200',
        'izin'  => 'bg-sky-50 text-sky-700 border-sky-200',
        'sakit' => 'bg-violet-50 text-violet-700 border-violet-200',
        'alpa'  => 'bg-red-50 text-red-700 border-red-200',
    ];
    $bulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
    $namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $qs = '?dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai);
?>

<a href="<?= site_url('admin/absensi/rekap') . $qs ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-700 mb-4">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Kembali ke Rekap
</a>

<!-- Header guru + ringkasan -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="font-bold text-lg text-slate-800"><?= esc($guru['nama']) ?></h2>
            <p class="text-sm text-slate-400"><?= esc($guru['kode_guru']) ?> &middot; Periode <?= esc($dari) ?> s/d <?= esc($sampai) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-bold text-slate-600">Total sesi: <?= (int) $ringkas['total'] ?></span>
            <span class="inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-bold <?= $chip['hadir'] ?>">Hadir: <?= (int) $ringkas['hadir'] ?></span>
            <?php foreach ($statusOpts as $k => $lbl): ?>
                <span class="inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-bold <?= $chip[$k] ?>"><?= $lbl ?>: <?= (int) $ringkas[$k] ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (empty($detail)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
        Tidak ada catatan ketidakhadiran pada periode ini — guru <b>hadir penuh</b>. 🎉
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100">
            <p class="font-bold text-slate-800">Rincian Ketidakhadiran <span class="text-slate-400 font-normal">(<?= count($detail) ?> catatan)</span></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold w-36">Tanggal</th>
                        <th class="px-4 py-2.5 font-semibold w-40">Jam</th>
                        <th class="px-4 py-2.5 font-semibold">Kelas &amp; Mapel</th>
                        <th class="px-4 py-2.5 font-semibold w-24">Status</th>
                        <th class="px-4 py-2.5 font-semibold w-24">Jam Masuk</th>
                        <th class="px-4 py-2.5 font-semibold">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($detail as $d): $ts = strtotime($d['tanggal']); ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-2.5 whitespace-nowrap"><span class="font-semibold text-slate-700"><?= (int) date('j', $ts) ?> <?= $bulan[(int) date('n', $ts)] ?> <?= date('Y', $ts) ?></span><br><span class="text-xs text-slate-400"><?= $namaHari[(int) date('w', $ts)] ?></span></td>
                            <td class="px-4 py-2.5 whitespace-nowrap"><?php if ($d['jam_ke'] !== null): ?>Jam <?= esc($d['jam_ke']) ?> <span class="text-xs text-slate-400">(<?= esc(substr((string) $d['waktu_mulai'], 0, 5)) ?>–<?= esc(substr((string) $d['waktu_selesai'], 0, 5)) ?>)</span><?php else: ?><span class="text-slate-300">—</span><?php endif; ?></td>
                            <td class="px-4 py-2.5"><?= esc($d['nama_kelas'] ?? '—') ?> <span class="text-slate-400">·</span> <span class="font-medium"><?= esc($d['nama_mapel'] ?? '—') ?></span></td>
                            <td class="px-4 py-2.5"><span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-bold <?= $badge[$d['status']] ?? $badge['izin'] ?>"><?= esc($statusOpts[$d['status']] ?? $d['status']) ?></span></td>
                            <td class="px-4 py-2.5"><?= $d['jam_masuk'] ? esc(substr($d['jam_masuk'], 0, 5)) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-2.5 text-slate-500"><?= $d['keterangan'] ? esc($d['keterangan']) : '<span class="text-slate-300">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
