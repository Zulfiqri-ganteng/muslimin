<?php
/**
 * Kerangka halaman satu jenis ujian (ASTS 1 / ASAS / ASTS 2 / ASAT).
 *
 * Berkas ini hanya memuat bagian yang sama untuk semua tab: identitas
 * periode, pemilih tahun pelajaran, dan bilah tab. Isi tiap tab ada di
 * `tab_*.php` masing-masing supaya tidak menumpuk di satu berkas.
 *
 * @var string               $jenis    ASTS1|ASAS|ASTS2|ASAT
 * @var string               $slug     asts1|asas|asts2|asat
 * @var string               $tab      tab aktif
 * @var array<string,string> $tabs     daftar tab [kunci => label]
 * @var array<string,mixed>  $periode  baris periode terpilih
 * @var string               $label    label periode, mis. "ASTS 1 — TP 2026/2027"
 * @var string               $panjang  kepanjangan jenis ujian
 * @var array<int,array>     $riwayat  periode jenis ini di semua tahun pelajaran
 * @var string               $berjalan tahun pelajaran berjalan (Pengaturan Sekolah)
 * @var array<string,mixed>  $ringkas  angka ringkasan
 */
$base = site_url('admin/ujian/' . $slug);
$tp   = $periode['tahun_ajaran'];
$qtp  = '?tp=' . rawurlencode($tp);

/** URL satu tab dengan tahun pelajaran yang sedang dilihat tetap terbawa. */
$urlTab = static fn (string $k) => $base . ($k === 'periode' ? '' : '/' . $k) . $qtp;

$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';

$warnaStatus = [
    'draft'    => 'bg-slate-100 text-slate-600 border-slate-200',
    'berjalan' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'selesai'  => 'bg-sky-50 text-sky-700 border-sky-200',
];
$labelStatus = ['draft' => 'Draft', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai'];

// Panel yang sudah digarap; sisanya memakai panel "belum tersedia".
$panelSiap = ['periode', 'jadwal', 'ketidakhadiran', 'susulan', 'rekap'];
$panelView = in_array($tab, $panelSiap, true) ? 'admin/ujian/tab_' . $tab : 'admin/ujian/tab_belum';

$panelData = [
    'periode'     => $periode,
    'label'       => $label,
    'slug'        => $slug,
    'base'        => $base,
    'tp'          => $tp,
    'qtp'         => $qtp,
    'tab'         => $tab,
    'tabs'        => $tabs,
    'ringkas'     => $ringkas,
    'labelStatus' => $labelStatus,
];
if ($tab === 'rekap') {
    $panelData += [
        'jadwalTotal'      => $jadwalTotal,
        'jadwalPerTingkat' => $jadwalPerTingkat,
        'pengawasTotal'    => $pengawasTotal,
        'takHadirTotal'    => $takHadirTotal,
        'perStatus'        => $perStatus,
        'perAlasan'        => $perAlasan,
        'perKelas'         => $perKelas,
        'perMapel'         => $perMapel,
        'perTanggal'       => $perTanggal,
        'siswaTerbanyak'   => $siswaTerbanyak,
    ];
}
if ($tab === 'susulan') {
    $panelData += [
        'rows'          => $rows,
        'pager'         => $pager,
        'filter'        => $filter,
        'per'           => $per,
        'totalSusulan'  => $totalSusulan,
        'ringkasStatus' => $ringkasStatus,
        'ringkasAlasan' => $ringkasAlasan,
        'kelasOpts'     => $kelasOpts,
        'mapelOpts'     => $mapelOpts,
        'guruOpts'      => $guruOpts,
        'statusList'    => $statusList,
        'alasanList'    => $alasanList,
        'statusLabel'   => $statusLabel,
    ];
}
if ($tab === 'ketidakhadiran') {
    $panelData += [
        'jadwalId'        => $jadwalId,
        'kelasId'         => $kelasId,
        'jadwalPilih'     => $jadwalPilih,
        'kelasPilih'      => $kelasPilih,
        'kelasSasaran'    => $kelasSasaran,
        'siswa'           => $siswa,
        'tercatat'        => $tercatat,
        'jadwalOpts'      => $jadwalOpts,
        'alasanList'      => $alasanList,
        'jmlTakHadirSesi' => $jmlTakHadirSesi,
    ];
}
if ($tab === 'jadwal') {
    $panelData += [
        'rows'        => $rows,
        'pager'       => $pager,
        'filter'      => $filter,
        'per'         => $per,
        'totalJadwal' => $totalJadwal,
        'jmlPengawas' => $jmlPengawas,
        'jmlTakHadir' => $jmlTakHadir,
        'mapelOpts'   => $mapelOpts,
        'jurusanOpts' => $jurusanOpts,
        'tingkatList' => $tingkatList,
        'shiftList'   => $shiftList,
    ];
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'ujian_v1',
    'helpTitle' => 'Menu Ujian',
    'helpBody'  => '<p>Halaman pendataan pelaksanaan asesmen sumatif. Tiap jenis ujian punya satu periode
        per tahun pelajaran, dan periode tahun berjalan <b>dibuat otomatis</b> — tidak perlu ditambah manual.</p>
        <p class="mt-1">• <b>Periode</b> — atur tanggal pelaksanaan, tanggal ujian susulan, dan status.<br>
        • <b>Jadwal Ujian</b> — daftar mapel yang diujikan per tingkat.<br>
        • <b>Ketidakhadiran</b> — centang siswa yang tidak hadir; otomatis masuk daftar susulan.<br>
        • <b>Ujian Susulan</b> — jadwalkan dan tandai selesai.</p>
        <p class="mt-1">Tahun pelajaran mengikuti <b>Pengaturan Sekolah</b>. Ganti tahun di sana, periode baru
        akan dibuat otomatis dan data tahun lama tetap tersimpan.</p>',
]) ?>

<!-- ===== Kepala: identitas periode ===== -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-bold text-slate-800 text-lg"><?= esc($label) ?></h2>
                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $warnaStatus[$periode['status']] ?? $warnaStatus['draft'] ?>">
                    <?= esc($labelStatus[$periode['status']] ?? $periode['status']) ?>
                </span>
                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 text-xs font-semibold">
                    Semester <?= esc($periode['semester']) ?>
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1"><?= esc($panjang) ?></p>
            <p class="text-xs text-slate-500 mt-2">
                Pelaksanaan: <b class="text-slate-700"><?= $tgl($periode['tanggal_mulai']) ?> &ndash; <?= $tgl($periode['tanggal_selesai']) ?></b>
                &nbsp;·&nbsp;
                Susulan: <b class="text-slate-700"><?= $tgl($periode['susulan_mulai']) ?> &ndash; <?= $tgl($periode['susulan_selesai']) ?></b>
            </p>
        </div>

        <?php if (count($riwayat) > 1): ?>
            <form method="get" action="<?= $base . ($tab === 'periode' ? '' : '/' . $tab) ?>" class="shrink-0">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Tahun Pelajaran</label>
                <select name="tp" data-autosubmit class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 outline-none">
                    <?php foreach ($riwayat as $r): ?>
                        <option value="<?= esc($r['tahun_ajaran'], 'attr') ?>" <?= $r['tahun_ajaran'] === $tp ? 'selected' : '' ?>>
                            <?= esc($r['tahun_ajaran']) ?><?= $r['tahun_ajaran'] === $berjalan ? ' (berjalan)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($tp !== $berjalan): ?>
        <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            Kamu sedang melihat tahun pelajaran <b><?= esc($tp) ?></b>, bukan tahun berjalan (<?= esc($berjalan) ?>).
            <a href="<?= $base ?>" class="font-semibold underline">Kembali ke tahun berjalan</a>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Tab ===== -->
<div class="mb-5 border-b border-slate-200 overflow-x-auto">
    <nav class="flex gap-1 min-w-max">
        <?php foreach ($tabs as $k => $lbl): ?>
            <?php $aktif = $k === $tab; ?>
            <a href="<?= $urlTab($k) ?>"
               class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition whitespace-nowrap <?= $aktif
                   ? 'border-brand-600 text-brand-700'
                   : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
                <?= esc($lbl) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<?= view($panelView, $panelData) ?>

<?= $this->endSection() ?>
