<?php
/**
 * Status Siswa — siapa sudah/belum mengisi, siapa sudah/belum PKL.
 *
 * @var array<string,int|float> $ringkas
 * @var list<array>             $rows
 * @var int                     $total
 * @var string                  $q
 * @var int                     $kelasId
 * @var string                  $fase
 * @var list<array>             $kelas
 * @var list<string>            $tingkat
 * @var int                     $page
 * @var int                     $jmlHal
 */

use App\Libraries\IsianBantu;
use App\Models\PklPengajuanModel;

$urlSaring = static fn (array $tambah): string => site_url('admin/pkl/siswa') . '?' . http_build_query(array_filter(
    ['q' => $q, 'kelas_id' => $kelasId ?: null] + $tambah,
    static fn ($v) => $v !== null && $v !== ''
));
$urlHal = static fn (int $hal): string => $urlSaring(['fase' => $fase, 'page' => $hal > 1 ? $hal : null]);

$opsiFase = [
    ''              => 'Semua status',
    'belum_mengisi' => 'Belum mengisi (belum + ditolak)',
    'sudah_mengisi' => 'Sudah mengisi',
    'sudah_pkl'     => 'Sudah dapat tempat PKL',
    'belum'         => '— Belum pernah mengisi',
    'ditolak'       => '— Ditolak, perlu ajukan ulang',
    'menunggu'      => '— Menunggu ACC',
    'perbaikan'     => '— Perlu perbaikan',
    'belum_mulai'   => '— Disetujui, belum mulai',
    'sedang'        => '— Sedang PKL',
    'selesai'       => '— Selesai PKL',
];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_siswa_v1',
    'helpTitle' => 'Status Siswa PKL',
    'helpBody'  => '<p>Status dihitung <b>otomatis</b> dari ajuan dan tanggal hari ini; tidak ada yang diketik manual.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Sudah mengisi</b> = punya ajuan aktif (menunggu, perlu perbaikan, atau disetujui). <b>Belum mengisi</b> = belum pernah, atau ajuannya ditolak.</li>'
        . '<li><b>Sudah PKL</b> = ajuannya sudah <b>disetujui</b> (sudah dapat tempat), lalu dirinci: belum mulai, sedang PKL, atau selesai menurut tanggalnya.</li>'
        . '<li>Yang dihitung hanya siswa aktif di tingkat yang diizinkan di <b>Pengaturan PKL</b>.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<?php if ($tingkat === []): ?>
    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
        <b>Tingkat yang boleh mengajukan belum diatur</b>, jadi belum ada siswa yang dihitung.
        <?php if ($bolehPengaturan): ?><a href="<?= site_url('admin/pkl/pengaturan') ?>" class="font-bold underline">Atur di Pengaturan PKL</a>.<?php endif; ?>
    </div>
<?php endif; ?>

<!-- Empat angka utama -->
<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <?php foreach ([
        ['Sudah mengisi', $ringkas['sudah_isi'], 'text-blue-700', 'sudah_mengisi', $ringkas['persen_isi'] . '% dari ' . $ringkas['total']],
        ['Belum mengisi', $ringkas['belum_isi'], 'text-slate-700', 'belum_mengisi', 'belum + ditolak'],
        ['Sudah PKL', $ringkas['sudah_pkl'], 'text-green-600', 'sudah_pkl', $ringkas['persen_pkl'] . '% (sudah disetujui)'],
        ['Belum PKL', $ringkas['belum_pkl'], 'text-amber-600', '', 'belum dapat tempat'],
    ] as [$label, $nilai, $warna, $saring, $ket]): ?>
        <a href="<?= $saring !== '' ? esc($urlSaring(['fase' => $saring]), 'attr') : esc($urlSaring([]), 'attr') ?>" class="rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm transition hover:border-brand-300 hover:shadow">
            <p class="text-xs font-semibold text-slate-400"><?= esc($label) ?></p>
            <p class="mt-1 text-3xl font-extrabold <?= $warna ?>"><?= (int) $nilai ?></p>
            <p class="mt-0.5 text-[11px] text-slate-400"><?= esc($ket) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<!-- Rincian status -->
<div class="mb-5 flex flex-wrap gap-2">
    <?php foreach (['belum' => 'Belum mengisi', 'ditolak' => 'Ditolak', 'menunggu' => 'Menunggu ACC', 'perbaikan' => 'Perlu perbaikan', 'belum_mulai' => 'Belum mulai', 'sedang' => 'Sedang PKL', 'selesai' => 'Selesai PKL'] as $kode => $label): ?>
        <a href="<?= esc($urlSaring(['fase' => $kode]), 'attr') ?>" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition <?= $fase === $kode ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>">
            <?= esc($label) ?> <span class="rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-500"><?= (int) $ringkas[$kode] ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <form method="get" class="grid grid-cols-1 gap-2 border-b border-slate-100 p-4 sm:grid-cols-[1fr_12rem_15rem_auto]">
        <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama siswa…" class="inp" aria-label="Cari nama">
        <select name="kelas_id" class="inp" aria-label="Kelas">
            <option value="">Semua kelas</option>
            <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>" <?= (int) $k['id'] === $kelasId ? 'selected' : '' ?>><?= esc($k['nama_kelas']) ?></option><?php endforeach; ?>
        </select>
        <select name="fase" class="inp" aria-label="Status">
            <?php foreach ($opsiFase as $v => $l): ?><option value="<?= esc($v, 'attr') ?>" <?= $v === $fase ? 'selected' : '' ?>><?= esc($l) ?></option><?php endforeach; ?>
        </select>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Terapkan</button>
            <?php if ($q !== '' || $kelasId > 0 || $fase !== ''): ?><a href="<?= site_url('admin/pkl/siswa') ?>" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a><?php endif; ?>
        </div>
    </form>

    <p class="border-b border-slate-100 px-5 py-2.5 text-xs text-slate-400"><?= (int) $total ?> siswa ditampilkan</p>

    <?php if ($rows === []): ?>
        <p class="px-5 py-12 text-center font-semibold text-slate-600">Tidak ada siswa yang cocok.</p>
    <?php else: ?>
        <div class="hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-3 py-3 font-semibold">Kelas</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-3 py-3 font-semibold">Perusahaan</th>
                        <th class="px-3 py-3 font-semibold">Periode</th>
                        <th class="px-5 py-3 text-right font-semibold">Ajuan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-2.5 font-medium text-slate-800"><?= esc($r['nama']) ?></td>
                            <td class="px-3 py-2.5 text-slate-500"><?= esc($r['nama_kelas']) ?></td>
                            <td class="px-3 py-2.5"><?= view('admin/pkl/_lencana', ['kode' => $r['fase'], 'siswa' => true]) ?></td>
                            <td class="px-3 py-2.5 text-slate-700"><?= esc($r['perusahaan_nama'] ?? '') ?: '<span class="text-slate-300">—</span>' ?></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-xs text-slate-500"><?= ! empty($r['tanggal_mulai']) ? esc(IsianBantu::tanggalIndo($r['tanggal_mulai'])) . ' – ' . esc(IsianBantu::tanggalIndo($r['tanggal_selesai'])) : '—' ?></td>
                            <td class="px-5 py-2.5 text-right"><?php if (! empty($r['ajuan_id'])): ?><a href="<?= site_url('admin/pkl/' . $r['ajuan_id']) ?>" class="font-mono text-xs font-semibold text-brand-700 hover:underline"><?= esc(PklPengajuanModel::kode((int) $r['ajuan_id'])) ?></a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($rows as $r): ?>
                <li class="px-4 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-slate-800"><?= esc($r['nama']) ?> <span class="text-xs font-normal text-slate-400">· <?= esc($r['nama_kelas']) ?></span></p>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <?= view('admin/pkl/_lencana', ['kode' => $r['fase'], 'siswa' => true]) ?>
                        <?php if (! empty($r['ajuan_id'])): ?><a href="<?= site_url('admin/pkl/' . $r['ajuan_id']) ?>" class="font-mono text-[11px] font-semibold text-brand-700"><?= esc(PklPengajuanModel::kode((int) $r['ajuan_id'])) ?></a><?php endif; ?>
                    </div>
                    <?php if (! empty($r['perusahaan_nama'])): ?><p class="mt-1 text-xs text-slate-500"><?= esc($r['perusahaan_nama']) ?></p><?php endif; ?>
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
