<?php
/**
 * Perbandingan SKBM dengan data Penugasan (pengampu) — KHUSUS ADMIN. Hanya MEMBANDINGKAN: tidak mengubah Penugasan,
 * Jadwal KBM, maupun honor. Pasangan yang dibandingkan: guru × kelas (hanya guru yang ada di SKBM).
 *
 * @var string $tahun  @var array $b  hasil Skbm::bandingkanDenganPengampu()  @var string $back
 */
$maks = 300;
$bagian = [
    ['hanya_skbm', 'Ada di SKBM, belum ada di Penugasan', 'SK menugaskan guru ini di kelas tersebut, tetapi menu Penugasan belum memuatnya. Periksa: apakah Penugasan perlu ditambah (Jadwal KBM & Rekap Beban memakai Penugasan)?', 'border-amber-300 bg-amber-50 text-amber-900', false],
    ['hanya_pengampu', 'Ada di Penugasan, tidak ada di SKBM', 'Penugasan memuat pasangan ini, tetapi SKBM tidak. Periksa: apakah SKBM belum lengkap, atau Penugasan yang kelebihan?', 'border-orange-300 bg-orange-50 text-orange-900', false],
    ['jp_beda', 'JP berbeda', 'Pasangan guru–kelas sama di keduanya, tetapi jumlah JP per minggu berbeda.', 'border-sky-300 bg-sky-50 text-sky-900', true],
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="mx-auto max-w-6xl space-y-4">
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">SKBM &middot; khusus Admin</p>
                <h2 class="mt-0.5 text-lg font-bold text-slate-800">SKBM <?= esc($tahun) ?> dibandingkan dengan Penugasan</h2>
                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-slate-500">Halaman ini hanya <b>membandingkan</b>: data Penugasan, Jadwal KBM, dan honor tidak diubah. Yang dicocokkan ialah pasangan <b>guru &times; kelas</b>, hanya untuk guru yang ada di SKBM.</p>
            </div>
            <a href="<?= esc($back, 'attr') ?>" class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">&larr; Kembali ke SKBM</a>
        </div>

        <?php if (! $b['ada']): ?>
            <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">SKBM <?= esc($tahun) ?> belum diisi, jadi belum ada yang dibandingkan.</p>
        <?php else: ?>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div class="rounded-xl border border-slate-200 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Guru di SKBM</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= (int) $b['guru_skbm'] ?></p></div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2"><p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Sama</p><p class="text-xl font-extrabold tabular-nums text-emerald-700"><?= (int) $b['sama'] ?></p></div>
                <div class="rounded-xl border px-3 py-2 <?= $b['hanya_skbm'] !== [] ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?>"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Hanya di SKBM</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= count($b['hanya_skbm']) ?></p></div>
                <div class="rounded-xl border px-3 py-2 <?= $b['hanya_pengampu'] !== [] ? 'border-orange-300 bg-orange-50' : 'border-slate-200' ?>"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Hanya di Penugasan</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= count($b['hanya_pengampu']) ?></p></div>
                <div class="rounded-xl border px-3 py-2 <?= $b['jp_beda'] !== [] ? 'border-sky-300 bg-sky-50' : 'border-slate-200' ?>"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">JP beda</p><p class="text-xl font-extrabold tabular-nums text-slate-800"><?= count($b['jp_beda']) ?></p></div>
            </div>
            <?php if ($b['hanya_skbm'] === [] && $b['hanya_pengampu'] === [] && $b['jp_beda'] === []): ?>
                <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">Semua sama: Penugasan sesuai dengan SKBM untuk guru-guru yang ada di SKBM.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($b['ada']): ?>
        <?php foreach ($bagian as [$kunci, $judul, $uraian, $warna, $adaJp]): $isi = $b[$kunci]; ?>
            <?php if ($isi === []) { continue; } ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-4 py-3 <?= $warna ?>">
                    <h3 class="text-sm font-bold"><?= esc($judul) ?> <span class="font-semibold">(<?= count($isi) ?>)</span></h3>
                    <p class="mt-0.5 text-xs leading-relaxed"><?= esc($uraian) ?></p>
                </div>
                <div class="max-h-[32rem] overflow-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                            <tr><th class="px-4 py-2">Guru</th><th class="px-3 py-2">Kelas</th><th class="px-3 py-2">Mapel di SKBM</th><?php if ($adaJp): ?><th class="px-3 py-2">Mapel di Penugasan</th><?php endif; ?><th class="px-3 py-2 text-right">JP SKBM</th><th class="px-3 py-2 text-right">JP Penugasan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach (array_slice($isi, 0, $maks) as $r): ?>
                                <tr>
                                    <td class="px-4 py-1.5 font-semibold text-slate-800"><?= esc($r['guru']) ?></td>
                                    <td class="px-3 py-1.5 text-slate-700"><?= esc($r['kelas']) ?></td>
                                    <td class="px-3 py-1.5 text-slate-600"><?= $kunci === 'hanya_pengampu' ? '<span class="text-slate-300">&mdash;</span>' : esc($r['mapel']) ?></td>
                                    <?php if ($adaJp): ?><td class="px-3 py-1.5 text-slate-600"><?= esc($r['mapel_pengampu'] ?? '') ?></td><?php endif; ?>
                                    <td class="px-3 py-1.5 text-right tabular-nums text-slate-700"><?= $r['jp_skbm'] === null ? '<span class="text-slate-300">&mdash;</span>' : (int) $r['jp_skbm'] ?></td>
                                    <td class="px-3 py-1.5 text-right tabular-nums text-slate-700"><?= $r['jp_pengampu'] === null ? '<span class="text-slate-300">&mdash;</span>' : (int) $r['jp_pengampu'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($isi) > $maks): ?><p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">Ditampilkan <?= $maks ?> baris pertama dari <?= count($isi) ?>.</p><?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($b['guru_tanpa_skbm'] !== []): ?>
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h3 class="text-sm font-bold text-slate-700">Punya Penugasan tetapi tidak ada di SKBM <span class="font-semibold text-slate-500">(<?= count($b['guru_tanpa_skbm']) ?> guru)</span></h3>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Tidak dibandingkan per kelas karena gurunya memang belum masuk SKBM <?= esc($tahun) ?>. Bila seharusnya ada, tambahkan di halaman SKBM.</p>
                <p class="mt-2 text-sm leading-relaxed text-slate-700"><?= esc(implode(' · ', $b['guru_tanpa_skbm'])) ?></p>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
