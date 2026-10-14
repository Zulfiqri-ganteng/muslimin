<?php
/**
 * Kerangka halaman satu jenis ujian (ASTS 1 / ASAS / ASTS 2 / ASAT). Rombak tampilan F7: kartu identitas periode + lencana status,
 * pemilih tahun pelajaran kit, bilah tab kit (.tab-daftar/.tab) dengan lencana kelengkapan.
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

$warnaStatus = ['draft' => 'lencana-abu', 'berjalan' => 'lencana-hijau', 'selesai' => 'lencana-merek'];
$labelStatus = ['draft' => 'Draft', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai'];

// Lencana kelengkapan di baris tab: [teks, nada] (teks null = ikon centang). Nada: ok (hijau) | kosong (abu) | perlu (kuning).
$periodeTerisi = ! empty($periode['tanggal_mulai']) && ! empty($periode['tanggal_selesai']);
$susulanBelum  = max(0, (int) ($ringkas['tidakHadir'] ?? 0) - (int) (($ringkas['statusList'] ?? [])['selesai'] ?? 0));
$honorLabel    = ['draf' => 'Draf', 'final' => 'Final', 'dikunci' => 'Dikunci'];
$lencana = [
    'periode'        => $periodeTerisi ? [null, 'ok'] : ['atur', 'perlu'],
    'jadwal'         => (int) $ringkas['jadwal'] > 0 ? [(string) (int) $ringkas['jadwal'], 'ok'] : ['kosong', 'perlu'],
    'ketidakhadiran' => (int) $ringkas['tidakHadir'] > 0 ? [(string) (int) $ringkas['tidakHadir'], 'ok'] : ['0', 'kosong'],
    'susulan'        => $susulanBelum > 0 ? [$susulanBelum . ' belum', 'perlu'] : ((int) $ringkas['tidakHadir'] > 0 ? [null, 'ok'] : ['0', 'kosong']),
    'honor'          => ($ringkas['honorStatus'] ?? null) !== null ? [$honorLabel[$ringkas['honorStatus']] ?? $ringkas['honorStatus'], $ringkas['honorStatus'] === 'draf' ? 'perlu' : 'ok'] : ['belum', 'kosong'],
];
$warnaLencana = ['ok' => 'lencana-hijau', 'kosong' => 'lencana-abu', 'perlu' => 'lencana-kuning'];

// Panel yang sudah digarap; sisanya memakai panel "belum tersedia".
$panelSiap = ['periode', 'jadwal', 'ketidakhadiran', 'susulan', 'rekap', 'honor'];
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
if ($tab === 'honor') {
    $panelData += [
        'honor'          => $honor,
        'honorCalon'     => $honorCalon,
        'honorLain'      => $honorLain,
        'honorPratinjau' => $honorPratinjau,
        'honorPeriksa'   => $honorPeriksa ?? [],
        'honorUtuh'      => $honorUtuh ?? null,
        'honorGambaran'  => $honorGambaran ?? [],
        'honorPetunjuk'  => $honorPetunjuk ?? [],
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
        'jmlPembuat'  => $jmlPembuat ?? [],
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

<div class="mb-4 space-y-4">
    <!-- ===== Kepala: identitas periode ===== -->
    <section class="kartu kartu-isi" aria-labelledby="judul-periode-ujian">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="judul-periode-ujian" class="text-lg font-semibold text-gray-900 dark:text-white"><?= esc($label) ?></h2>
                    <span class="lencana <?= $warnaStatus[$periode['status']] ?? $warnaStatus['draft'] ?>"><?= esc($labelStatus[$periode['status']] ?? $periode['status']) ?></span>
                    <span class="lencana lencana-abu">Semester <?= esc($periode['semester']) ?></span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?= esc($panjang) ?></p>
                <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1.5"><?= ikon('calendar-month', 'h-4 w-4 shrink-0') ?>Pelaksanaan: <b class="font-semibold text-gray-900 dark:text-white"><?= $tgl($periode['tanggal_mulai']) ?> &ndash; <?= $tgl($periode['tanggal_selesai']) ?></b></span>
                    <span class="inline-flex items-center gap-1.5"><?= ikon('calendar-plus', 'h-4 w-4 shrink-0') ?>Susulan: <b class="font-semibold text-gray-900 dark:text-white"><?= $tgl($periode['susulan_mulai']) ?> &ndash; <?= $tgl($periode['susulan_selesai']) ?></b></span>
                </p>
            </div>

            <?php if (count($riwayat) > 1): ?>
                <form method="get" action="<?= $base . ($tab === 'periode' ? '' : '/' . $tab) ?>" class="shrink-0">
                    <label class="lbl" for="pilihTp">Tahun pelajaran</label>
                    <select id="pilihTp" name="tp" data-autosubmit class="inp">
                        <?php foreach ($riwayat as $r): ?>
                            <option value="<?= esc($r['tahun_ajaran'], 'attr') ?>"<?= $r['tahun_ajaran'] === $tp ? ' selected' : '' ?>>
                                <?= esc($r['tahun_ajaran']) ?><?= $r['tahun_ajaran'] === $berjalan ? ' (berjalan)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($tp !== $berjalan): ?>
            <div class="mt-4">
                <?= ui_peringatan('awas', '', ['html' => 'Kamu sedang melihat tahun pelajaran <b>' . esc($tp) . '</b>, bukan tahun berjalan (' . esc($berjalan) . '). <a href="' . $base . '" class="font-semibold underline">Kembali ke tahun berjalan</a>']) ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ===== Tab ===== -->
    <div class="overflow-x-auto">
        <nav class="tab-daftar min-w-max flex-nowrap" aria-label="Bagian ujian">
            <?php foreach ($tabs as $k => $lbl): $aktif = $k === $tab; ?>
                <a href="<?= $urlTab($k) ?>" class="tab whitespace-nowrap"<?= $aktif ? ' aria-current="page"' : '' ?>>
                    <?= esc($lbl) ?>
                    <?php if (isset($lencana[$k])): [$teksL, $nadaL] = $lencana[$k]; ?>
                        <span class="lencana <?= $warnaLencana[$nadaL] ?> px-1.5 py-0 text-[11px]"><?= $teksL === null ? ikon('check', 'h-3 w-3') . '<span class="sr-only">lengkap</span>' : esc($teksL) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<?= view($panelView, $panelData) ?>

<?= $this->endSection() ?>
