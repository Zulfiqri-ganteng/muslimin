<?php
/**
 * Rekap Hasil UKK.
 *
 * @var int                $tahunId
 * @var array<int,string>  $tahunOpts
 * @var int                $totalPeserta
 * @var array              $perStatus
 * @var float|null         $rataNilai
 * @var array              $perPaket
 * @var array              $perJurusan
 */
$base = site_url('admin/laporan-ukk');
$num  = static fn ($v) => $v !== null ? number_format((float) $v, 1) : '—';
$statusLabel = [
    'terdaftar' => 'Terdaftar', 'hadir' => 'Hadir', 'tidak_hadir' => 'Tidak Hadir',
    'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus',
];
$statusTextColor = [
    'terdaftar' => 'text-slate-600', 'hadir' => 'text-sky-700',
    'tidak_hadir' => 'text-amber-700', 'lulus' => 'text-emerald-700',
    'tidak_lulus' => 'text-red-700',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'laporan_ukk',
    'helpTitle' => 'Rekap Hasil UKK',
    'helpBody'  => '<p>Rekap seluruh peserta UKK: total per status, per paket soal, dan per jurusan. Filter opsional
        per tahun ajaran. Bisa diunduh sebagai PDF (siap cetak dengan kop resmi) atau Excel.</p>',
]) ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="get" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2">
        <select name="tahun_ajaran_id" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand-500 outline-none shrink-0">
            <option value="">Semua tahun ajaran</option>
            <?php foreach ($tahunOpts as $id => $lbl): ?>
                <option value="<?= (int) $id ?>" <?= $tahunId === (int) $id ? 'selected' : '' ?>><?= esc($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex-1 hidden sm:block"></div>
        <a href="<?= $base ?>/pdf?tahun_ajaran_id=<?= (int) $tahunId ?>" target="_blank"
           class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold px-3.5 py-2.5 transition shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-8 4h4v-6H8v6z"/></svg>
            PDF
        </a>
        <a href="<?= $base ?>/excel?tahun_ajaran_id=<?= (int) $tahunId ?>"
           class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-3.5 py-2.5 transition shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4V4zm4 4h8m-8 4h8m-8 4h5"/></svg>
            Excel
        </a>
    </form>
</div>

<!-- Stat tiles -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
        <div class="text-xs text-slate-500">Total Peserta</div>
        <div class="text-2xl font-bold text-slate-800 mt-1"><?= (int) $totalPeserta ?></div>
    </div>
    <?php foreach ($perStatus as $k => $v): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
            <div class="text-xs text-slate-500"><?= esc($statusLabel[$k] ?? ucfirst($k)) ?></div>
            <div class="text-2xl font-bold mt-1 <?= $statusTextColor[$k] ?? 'text-slate-800' ?>"><?= (int) $v ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="text-sm text-slate-500">Rata-rata Nilai Akhir (seluruh peserta yang sudah dinilai)</div>
    <div class="text-3xl font-bold text-brand-700 mt-1"><?= $num($rataNilai) ?></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Per paket soal -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Rekap per Paket Soal</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Paket Soal</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Total</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Lulus</th>
                        <th class="px-3 py-2.5 font-semibold text-right">T. Lulus</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Rata²</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($perPaket)): ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada data.</td></tr>
                    <?php else: foreach ($perPaket as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5">
                                <div class="font-medium text-slate-700"><?= esc($r['paket_kode']) ?></div>
                                <div class="text-xs text-slate-400"><?= esc($r['paket_nama']) ?></div>
                            </td>
                            <td class="px-3 py-2.5 text-right"><?= (int) $r['total'] ?></td>
                            <td class="px-3 py-2.5 text-right text-emerald-700 font-semibold"><?= (int) $r['lulus'] ?></td>
                            <td class="px-3 py-2.5 text-right text-red-700 font-semibold"><?= (int) $r['tidak_lulus'] ?></td>
                            <td class="px-3 py-2.5 text-right"><?= $num($r['rata_nilai']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Per jurusan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">Rekap per Jurusan</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Jurusan</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Total</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Lulus</th>
                        <th class="px-3 py-2.5 font-semibold text-right">T. Lulus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($perJurusan)): ?>
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Belum ada data.</td></tr>
                    <?php else: foreach ($perJurusan as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 text-slate-700"><?= esc($r['jurusan_nama'] ?? '(tanpa jurusan)') ?></td>
                            <td class="px-3 py-2.5 text-right"><?= (int) $r['total'] ?></td>
                            <td class="px-3 py-2.5 text-right text-emerald-700 font-semibold"><?= (int) $r['lulus'] ?></td>
                            <td class="px-3 py-2.5 text-right text-red-700 font-semibold"><?= (int) $r['tidak_lulus'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
