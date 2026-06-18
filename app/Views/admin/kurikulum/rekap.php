<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'rekap',
    'helpTitle' => 'Rekap Beban Mengajar',
    'helpBody'  => '<p>Total jam mengajar (JP) tiap guru berdasarkan <b>Penugasan</b>. Badge menunjukkan apakah beban <b>kurang</b>, <b>pas</b>, atau <b>lebih</b> dari Maks Beban guru. Ekspor PDF/Excel tersedia pada tahap berikutnya.</p>',
]) ?>

<?php if (! empty($grouped)): ?>
    <div class="flex flex-wrap justify-end gap-2 mb-4">
        <a href="<?= site_url('admin/cetak/rekap/excel') ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Excel (formula hidup)
        </a>
        <a href="<?= site_url('admin/cetak/rekap/pdf') ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7h-3V4a2 2 0 00-2-2H10a2 2 0 00-2 2v3H5a2 2 0 00-2 2v6a2 2 0 002 2h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
            Export PDF
        </a>
    </div>
<?php endif; ?>

<?php if (empty($grouped)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">
        Belum ada penugasan. Atur di menu <a href="<?= site_url('admin/master/pengampu') ?>" class="text-brand-600 underline">Penugasan</a>.
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($grouped as $g):
            $total = (int) $g['total']; $max = (int) $g['max_beban'];
            if ($total > $max)      { $badge = 'bg-orange-100 text-orange-700'; $stat = 'Lebih'; }
            elseif ($total < $max)  { $badge = 'bg-amber-100 text-amber-700';   $stat = 'Kurang'; }
            else                    { $badge = 'bg-emerald-100 text-emerald-700'; $stat = 'Pas'; }
        ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold text-slate-800 truncate"><span class="text-slate-400 font-normal"><?= esc($g['kode_guru']) ?></span> · <?= esc($g['guru_nama']) ?></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-sm text-slate-500">Total <b class="text-slate-800"><?= $total ?></b> / <?= $max ?> JP</span>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $badge ?>"><?= $stat ?></span>
                        <span class="text-slate-300">|</span>
                        <a href="<?= site_url('admin/cetak/jadwal-guru/' . $g['guru_id'] . '/pdf') ?>" target="_blank" title="Jadwal guru (PDF)" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </a>
                        <a href="<?= site_url('admin/cetak/jadwal-guru/' . $g['guru_id'] . '/excel') ?>" title="Jadwal guru (Excel)" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-5 py-2 font-semibold">Mata Pelajaran</th>
                            <th class="px-5 py-2 font-semibold w-40">Kelas</th>
                            <th class="px-5 py-2 font-semibold w-24 text-right">JP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($g['items'] as $it): ?>
                            <tr>
                                <td class="px-5 py-2"><?= esc($it['mapel']) ?></td>
                                <td class="px-5 py-2 text-slate-500"><?= esc($it['kelas']) ?></td>
                                <td class="px-5 py-2 text-right font-semibold"><?= (int) $it['jp'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
