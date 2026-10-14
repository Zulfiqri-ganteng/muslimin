<?php
/**
 * Data Siswa Prakerin — rekap tempat PKL per kelas dari data sistem. Isinya sama dengan unduhan Excel format sekolah
 * (PklPrakerin::excel): satu tabel = satu lembar.
 *
 * @var list<array{id: int, nama_kelas: string, tingkat: string, jumlah: int}> $semuaKelas
 * @var list<array>  $rekap         PklPrakerin::data()
 * @var string       $tingkatPilih
 * @var int          $kelasId       0 = semua kelas di tingkat terpilih
 * @var string       $sekolah
 * @var string       $tahun
 * @var bool         $bolehPengaturan
 */

use App\Libraries\IsianBantu;

$perTingkat = [];
foreach ($semuaKelas as $k) {
    $perTingkat[$k['tingkat']][] = $k;
}
$tot = ['siswa' => 0, 'mitra' => 0, 'tanggal' => 0];
foreach ($rekap as $r) {
    foreach ($tot as $kunci => $n) {
        $tot[$kunci] = $n + $r['ringkas'][$kunci];
    }
}
$urlHal   = static fn (array $q): string => site_url('admin/pkl/prakerin') . '?' . http_build_query($q);
$urlExcel = static fn (array $q): string => site_url('admin/pkl/prakerin/excel') . '?' . http_build_query($q);
$saringan = $kelasId > 0 ? ['kelas_id' => $kelasId] : ['tingkat' => $tingkatPilih];
$pil      = static fn (bool $aktif): string => $aktif
    ? 'border-brand-600 bg-brand-50 text-brand-700'
    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_prakerin_v1',
    'helpTitle' => 'Data Siswa Prakerin',
    'helpBody'  => '<p>Rekap tempat PKL siswa per kelas, <b>diambil otomatis dari sistem</b>. Tombol <b>Unduh Excel</b> menghasilkan berkas format sekolah "DATA SISWA PRAKERIN" (satu lembar per kelas) — isinya sama dengan tabel di halaman ini.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Mitra DI/DU</b> terisi bila ajuan PKL siswa sudah <b>Disetujui</b> (termasuk hasil impor). Ajuan yang masih menunggu ACC belum ditulis.</li>'
        . '<li><b>Mulai &amp; Selesai</b> diambil dari periode di Surat Balasan / hasil impor, atau dari laporan "Lapor Diterima" siswa. <b>Lamanya PKL</b> dihitung dalam bulan.</li>'
        . ($bolehPengaturan ? '<li>Punya data di Excel format ini? Pakai <b>Impor dari Excel</b> — pratinjau tampil dulu sebelum ada yang disimpan.</li>' : '')
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => 'prakerin', 'hitungTab' => $hitungTab]) ?>

<div class="space-y-5">
    <section class="rise rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Data Siswa Prakerin</p>
                <h2 class="mt-0.5 font-bold text-slate-800"><?= esc(mb_strtoupper($sekolah)) ?><?= $tahun !== '' ? ' · Tahun Pelajaran ' . esc($tahun) : '' ?></h2>
                <p class="mt-1 text-sm text-slate-500"><?= count($rekap) ?> kelas · <?= (int) $tot['siswa'] ?> siswa · <b class="text-slate-700"><?= (int) $tot['mitra'] ?></b> sudah ada mitra · <?= (int) $tot['tanggal'] ?> bertanggal</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <?php if ($rekap !== []): ?>
                    <a href="<?= esc($urlExcel($saringan), 'attr') ?>" class="inline-flex justify-center rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">⬇ Unduh Excel<?= count($rekap) > 1 ? ' (' . count($rekap) . ' lembar)' : '' ?></a>
                <?php endif; ?>
                <?php if ($bolehPengaturan): ?>
                    <a href="<?= site_url('admin/pkl/impor') ?>" class="inline-flex justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Impor dari Excel →</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-14 text-xs font-semibold text-slate-400">Tingkat</span>
                <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                    <a href="<?= esc($urlHal(['tingkat' => $t]), 'attr') ?>" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition <?= $pil($t === $tingkatPilih) ?>">Kelas <?= $t ?> <span class="text-[11px] font-normal text-slate-400">(<?= count($perTingkat[$t] ?? []) ?>)</span></a>
                <?php endforeach; ?>
            </div>
            <?php if (($perTingkat[$tingkatPilih] ?? []) !== []): ?>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-14 text-xs font-semibold text-slate-400">Kelas</span>
                    <a href="<?= esc($urlHal(['tingkat' => $tingkatPilih]), 'attr') ?>" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition <?= $pil($kelasId === 0) ?>">Semua kelas <?= esc($tingkatPilih) ?></a>
                    <?php foreach ($perTingkat[$tingkatPilih] as $k): ?>
                        <a href="<?= esc($urlHal(['kelas_id' => $k['id']]), 'attr') ?>" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition <?= $pil($kelasId === $k['id']) ?>"><?= esc($k['nama_kelas']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($rekap === []): ?>
        <p class="rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center font-semibold text-slate-600 shadow-sm">Belum ada kelas di tingkat <?= esc($tingkatPilih) ?>.</p>
    <?php endif; ?>

    <?php foreach ($rekap as $r): $k = $r['kelas']; ?>
        <section id="kelas-<?= (int) $k['id'] ?>" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
                <div>
                    <h3 class="font-bold text-slate-800"><?= esc($k['nama_kelas']) ?></h3>
                    <p class="text-xs text-slate-400"><?= (int) $r['ringkas']['siswa'] ?> siswa · <?= (int) $r['ringkas']['mitra'] ?> ada mitra · <?= (int) $r['ringkas']['tanggal'] ?> bertanggal</p>
                </div>
                <?php if (count($rekap) > 1): ?>
                    <a href="<?= esc($urlExcel(['kelas_id' => $k['id']]), 'attr') ?>" class="text-xs font-bold text-brand-700 hover:underline">⬇ Unduh kelas ini</a>
                <?php endif; ?>
            </div>
            <?php if ($r['siswa'] === []): ?>
                <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada siswa aktif di kelas ini.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="w-12 px-4 py-2.5 text-center font-semibold">No</th>
                                <th class="px-3 py-2.5 font-semibold">Nama</th>
                                <th class="px-3 py-2.5 font-semibold">Mitra DI/DU</th>
                                <th class="px-3 py-2.5 font-semibold">Mulai</th>
                                <th class="px-3 py-2.5 font-semibold">Selesai</th>
                                <th class="px-4 py-2.5 font-semibold">Lamanya PKL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($r['siswa'] as $s): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 text-center text-slate-400"><?= (int) $s['no'] ?></td>
                                    <td class="px-3 py-2 font-medium text-slate-800"><?= esc($s['nama']) ?></td>
                                    <td class="px-3 py-2 text-slate-700">
                                        <?php if ($s['mitra'] !== ''): ?><?= esc($s['mitra']) ?>
                                        <?php elseif ($s['catatan'] !== null): ?><span class="text-xs font-semibold text-amber-600"><?= esc($s['catatan']) ?></span>
                                        <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600"><?= $s['mulai'] !== null ? esc(IsianBantu::tanggalIndo($s['mulai'])) : '' ?></td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600"><?= $s['selesai'] !== null ? esc(IsianBantu::tanggalIndo($s['selesai'])) : '' ?></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-slate-600"><?= $s['lama'] !== null ? (int) $s['lama'] . ' bulan' : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
