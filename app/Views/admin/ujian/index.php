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
    'jadwal-pengawas' => (int) ($ringkas['pengawasRuang'] ?? 0) > 0 ? [(string) (int) $ringkas['pengawasRuang'], 'ok'] : ['kosong', 'kosong'],
    'ketidakhadiran' => (int) $ringkas['tidakHadir'] > 0 ? [(string) (int) $ringkas['tidakHadir'], 'ok'] : ['0', 'kosong'],
    'susulan'        => $susulanBelum > 0 ? [$susulanBelum . ' belum', 'perlu'] : ((int) $ringkas['tidakHadir'] > 0 ? [null, 'ok'] : ['0', 'kosong']),
    'honor'          => ($ringkas['honorStatus'] ?? null) !== null ? [$honorLabel[$ringkas['honorStatus']] ?? $ringkas['honorStatus'], $ringkas['honorStatus'] === 'draf' ? 'perlu' : 'ok'] : ['belum', 'kosong'],
];
$warnaLencana = ['ok' => 'lencana-hijau', 'kosong' => 'lencana-abu', 'perlu' => 'lencana-kuning'];

// Panel yang sudah digarap; sisanya memakai panel "belum tersedia".
$panelSiap = ['periode', 'jadwal', 'jadwal-pengawas', 'ketidakhadiran', 'susulan', 'rekap', 'honor'];
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
if ($tab === 'jadwal-pengawas') {
    // F3 cetak jadwal (2026-11-22): data dari App\Libraries\JadwalPengawas::dataTab() (kunci ber-awalan pg).
    foreach (['pgShifts', 'pgShift', 'pgSesi', 'pgRuang', 'pgIsi', 'pgTerisi', 'pgTotalSel', 'pgGuru', 'pgTerpilih', 'pgSaran', 'pgKode', 'pgBeban', 'pgMasalah', 'pgKelasShift', 'pgUbah'] as $kunciPg) {
        $panelData[$kunciPg] = $$kunciPg ?? null;
    }
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
        'istirahat'   => $istirahat ?? [],  // bagian Istirahat + baris sisipan di daftar (2026-11-24)
        'istSisipan'  => $istSisipan ?? [],
    ];
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
// Panduan halaman (permintaan user 2026-11-24: dijelaskan sederhana supaya sekolah paham cara pakainya). Jadwal Ujian & Jadwal Pengawas
// punya panduan sendiri berisi langkah 1-2-3; tab lain memakai ringkasan menu.
$daftarUrut  = static fn (array $butir): string => '<ol class="mt-1 list-decimal space-y-1 pl-5"><li>' . implode('</li><li>', $butir) . '</li></ol>';
$daftarTitik = static fn (array $butir): string => '<ul class="mt-1 list-disc space-y-1 pl-5"><li>' . implode('</li><li>', $butir) . '</li></ul>';
$subJudul    = static fn (string $t): string => '<p class="mt-3 font-semibold text-gray-900 dark:text-white">' . $t . '</p>';
$panduan = [
    'jadwal' => ['ujian_jadwal_v1', 'Cara pakai Jadwal Ujian',
        $daftarUrut([
            '<b>Isi jadwal.</b> Tekan <b>Tambah jadwal</b> → pilih mapel, tingkat, shift (Pagi/Siang), tanggal, jam mulai dan jam selesai → <b>Simpan</b>. Kalau banyak, isi lewat Excel dengan <b>Import</b>.',
            '<b>Cek istirahat.</b> Istirahat muncul sendiri bila ada jeda. Contoh: ujian 07.00–08.30 lalu 09.10–10.40, istirahatnya 08.30–09.10. Mau jam lain? Buka bagian <b>Istirahat</b> di atas daftar → <b>Atur istirahat</b>. Bisa untuk satu hari atau semua hari.',
            '<b>Cetak.</b> Tekan <b>Cetak &amp; lainnya</b> → <b>Cetak Jadwal Ujian</b> (Excel atau PDF).',
        ])
        . $subJudul('Arti tombol')
        . $daftarTitik([
            '<b>Pensil</b> = ubah, <b>tong sampah</b> = hapus.',
            '<b>Jam berpanah</b> (di bagian Istirahat) = kembali ke istirahat otomatis.',
            '<b>Baris kuning</b> di daftar = jam istirahat.',
        ])
        . $subJudul('Kalau ada masalah')
        . $daftarTitik([
            '<b>Istirahat tidak muncul</b> → baca tulisan kuning di bagian Istirahat, sebabnya ditulis di situ.',
            '<b>Muncul pesan "menabrak"</b> → geser dulu jam ujiannya, baru pasang istirahat (atau sebaliknya).',
            '<b>Jadwal tidak ikut tercetak</b> → cek di Master Kelas: tingkat, jurusan, dan shift kelasnya harus sama dengan jadwal.',
            '<b>Jam ujian diubah</b> → pengawas yang sudah terisi ikut pindah setelah <b>semua</b> jadwal di jam itu diubah.',
            '<b>Jadwal terakhir di suatu jam dihapus</b> → pengawas di jam itu ikut terhapus.',
        ])
        . $subJudul('Yang perlu diketahui')
        . $daftarTitik([
            'Satu jadwal = satu shift. Ujian yang sama untuk pagi dan siang dibuat dua kali.',
            'Isian <b>Ruang</b> hanya catatan, tidak ikut tercetak.',
            'Nama mapel di cetakan diatur di <b>Master Mapel</b> ("Nama di jadwal ujian"). Nama dan urutan kolom kelas diatur di <b>Master Jurusan</b>. Ujian susulan diatur di tab <b>Periode</b>.',
        ])],
    'jadwal-pengawas' => ['ujian_jadwal_pengawas_v1', 'Cara pakai Jadwal Pengawas',
        $daftarUrut([
            '<b>Isi Jadwal Ujian dulu.</b> Hari, jam, dan istirahat di sini mengikuti Jadwal Ujian.',
            '<b>Pilih guru.</b> Di kartu nomor 1, centang guru yang mengawasi → <b>Simpan daftar guru</b>.',
            '<b>Cek jumlah ruang</b> di kartu nomor 2. Kalau beda, ganti angkanya → <b>Ubah</b>. Pagi dan Siang diatur sendiri-sendiri.',
            '<b>Tekan Generate otomatis.</b> Semua ruang terisi merata, tidak ada guru yang dobel.',
            '<b>Periksa.</b> Mau ganti? Klik angkanya → pilih guru → <b>Simpan</b>.',
            '<b>Cetak.</b> Tekan <b>Cetak</b> → Excel atau PDF.',
        ])
        . $subJudul('Arti tampilan')
        . $daftarTitik([
            '<b>Angka</b> = kode guru pengawas. Arahkan kursor ke angka untuk melihat namanya.',
            '<b>Kotak kuning</b> = belum ada pengawas.',
            '<b>R1, R2, …</b> = ruang 1, ruang 2, dan seterusnya.',
        ])
        . $subJudul('Mengubah dan menghapus')
        . $daftarTitik([
            '<b>Klik angka</b> atau <b>pensil</b> = ubah pengawas satu jam ujian.',
            '<b>Ubah semua</b> = ketik semua angka sekaligus seperti di Excel.',
            '<b>Tong sampah</b> = hapus satu jam ujian. <b>Hapus hari ini</b> = satu hari. <b>Hapus semua pagi/siang</b> = satu shift.',
            'Menghapus pengawas <b>tidak</b> menghapus jadwal ujian dan daftar guru.',
        ])
        . $subJudul('Kalau ada masalah')
        . $daftarTitik([
            '<b>Masih ada kotak kuning setelah Generate</b> → guru yang dicentang kurang dari jumlah ruang. Tambah guru, lalu Generate lagi.',
            '<b>Tulisan "isi angka" atau "angka sama"</b> di samping nama guru → isi angka lain di kotaknya, lalu Simpan daftar guru.',
            '<b>Guru tercatat dua kali</b> (nama sama, kode beda) → centang salah satu saja.',
            '<b>Generate lagi</b> akan mengganti semua isian shift itu dengan susunan baru.',
            '<b>Jam atau istirahat salah</b> → betulkan di tab Jadwal Ujian. Setelah istirahat diubah, Generate lagi bila ingin pengawas pindah ruang sesudah istirahat yang baru.',
        ])
        . $subJudul('Yang perlu diketahui')
        . $daftarTitik([
            'Kode pengawas selalu angka: huruf di Kode Guru dibuang (13a jadi 13). Kode Guru untuk KBM tidak berubah.',
            'Nama Ketua Panitia diisi di tab <b>Periode</b>. Di Excel, tanda tangan ada di bawah tabel, di luar area cetak seperti berkas contoh sekolah.',
            'Di bawah tabel ada <b>beban mengawasi per guru</b> untuk mengecek pembagiannya.',
        ])],
];
[$kunciPanduan, $judulPanduan, $isiPanduan] = $panduan[$tab] ?? ['ujian_v1', 'Menu Ujian',
    '<p>Satu halaman untuk satu ujian. Periode tahun berjalan <b>dibuat otomatis</b>, tidak perlu ditambah. Isi dari kiri ke kanan:</p>'
    . $daftarTitik([
        '<b>Periode</b> — tanggal ujian, tanggal susulan, status, nama Ketua Panitia, jam susulan, dan jumlah ruang pagi/siang (kosong = jumlah kelas).',
        '<b>Jadwal Ujian</b> — mapel, hari, jam, dan istirahat. Panduan lengkapnya ada di tab itu.',
        '<b>Jadwal Pengawas</b> — guru pengawas tiap ruang. Panduan lengkapnya ada di tab itu.',
        '<b>Ketidakhadiran</b> — centang siswa yang tidak hadir; otomatis masuk daftar susulan.',
        '<b>Ujian Susulan</b> — jadwalkan, lalu tandai selesai.',
    ])
    . '<p class="mt-2">Tahun pelajaran mengikuti <b>Pengaturan Sekolah</b>. Data tahun lama tetap tersimpan.</p>'];
?>
<?= view('admin/partials/help', ['helpKey' => $kunciPanduan, 'helpTitle' => $judulPanduan, 'helpBody' => $isiPanduan]) ?>

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
