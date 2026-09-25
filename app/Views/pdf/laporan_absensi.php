<?php

/**
 * Laporan absensi bulanan format sekolah (PDF, F4/Legal landscape).
 * Tiga bagian: ABSEN (matriks tanggal × P/S), REKAP HADIR, REKAP TIDAK HADIR.
 *
 * @var array  $m        Hasil AbsensiLaporan::matriks()
 * @var string $bulan    YYYY-MM
 * @var string $judulBln Mis. "SEPTEMBER 2026"
 * @var array  $setting  Pengaturan sekolah
 * @var array  $hari     1..7 => SEN..MIN
 * @var string $tanggal  Tanggal cetak (Indonesia)
 */
$tarifJp    = (int) ($setting['absensi_potongan_jp'] ?? 5000);
$tarifTrans = (int) ($setting['absensi_transport'] ?? 0);
$rp         = static fn (int $v): string => 'Rp ' . number_format($v, 0, ',', '.');
$sekolah    = strtoupper((string) ($setting['school_name'] ?? ''));
$ttd        = static function () use ($setting, $tanggal): string {
    return '<table class="ttd"><tr><td></td><td class="c">' . esc(($setting['city'] ?? '') . ', ' . $tanggal)
        . '<br>Mengetahui, Kepala Sekolah<br><br><br><b><u>' . esc((string) ($setting['headmaster_name'] ?? ''))
        . '</u></b></td></tr></table>';
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        @page { margin: 14px 16px; }
        body { margin: 0; color: #1e293b; font-size: 7px; }
        h1 { margin: 0; font-size: 11px; text-align: center; }
        .sub { text-align: center; font-size: 8px; margin: 1px 0; font-weight: bold; }
        .ket { font-size: 6.5px; margin: 4px 0; color: #475569; }
        table.m { width: 100%; border-collapse: collapse; }
        table.m th, table.m td { border: 0.5px solid #94a3b8; padding: 0.5px 0; text-align: center; line-height: 1.15; }
        table.m th { background: #7B2C8F; color: #fff; font-size: 5.5px; }
        table.m td { font-size: 6px; }
        table.m td.nama { text-align: left; padding: 0.5px 2px; white-space: nowrap; overflow: hidden; }
        table.m td.sum { font-weight: bold; background: #f8fafc; }
        /* Baris pengatur lebar kolom: semua kolom diberi lebar agar sisa ruang dibagi proporsional. */
        tr.lebar td { border: none !important; padding: 0 !important; height: 0; line-height: 0; font-size: 0; }
        .p0 { background: #F6ECF8; } .p1 { background: #EBD7F0; }
        th.p0, th.p1 { color: #3b0764 !important; }
        .th { color: #b91c1c; font-weight: bold; }
        .ht { color: #a16207; font-weight: bold; }
        table.r { width: 100%; border-collapse: collapse; font-size: 7px; }
        table.r th, table.r td { border: 0.6px solid #94a3b8; padding: 0.5px 4px; line-height: 1.1; }
        table.r th { background: #7B2C8F; color: #fff; }
        table.r td.c, .c { text-align: center; }
        table.r tr.total td { font-weight: bold; background: #EBD7F0; }
        table.ttd { width: 100%; margin-top: 4px; font-size: 7.5px; page-break-inside: avoid; }
        table.ttd td { width: 50%; }
        .brk { page-break-before: always; }
        .foot { font-size: 6.5px; color: #64748b; margin-top: 4px; }
    </style>
</head>
<body>
    <!-- ===== 1. ABSEN (matriks) ===== -->
    <h1>ABSEN GURU DAN STAF TATA USAHA</h1>
    <div class="sub"><?= esc($sekolah) ?></div>
    <div class="sub">TAHUN PELAJARAN <?= esc($setting['academic_year'] ?? '') ?> — BULAN <?= esc($judulBln) ?></div>
    <div class="ket">Keterangan: P = Pagi · S = Siang · H = Hadir · HT = Hadir terlambat · I = Izin · S = Sakit · TH = Tidak hadir · - = tidak ada tugas · kosong = tidak diabsen</div>

    <?php $n = count($m['tanggal']); ?>
    <table class="m">
        <thead>
            <tr class="lebar">
                <td style="width:16px"></td><td style="width:132px"></td>
                <?php for ($i = 0; $i < $n * 2; $i++): ?><td style="width:14px"></td><?php endfor; ?>
                <td style="width:14px"></td><td style="width:14px"></td><td style="width:14px"></td><td style="width:14px"></td><td style="width:24px"></td>
            </tr>
            <tr>
                <th rowspan="3" style="width:16px">NO</th>
                <th rowspan="3" style="width:128px">NAMA</th>
                <?php foreach ($m['tanggal'] as $i => $tgl): ?>
                    <th colspan="2" class="p<?= $i % 2 ?>"><?= (int) date('j', strtotime($tgl)) ?></th>
                <?php endforeach; ?>
                <th colspan="2" rowspan="2" style="width:28px">HADIR</th>
                <th colspan="2" rowspan="2" style="width:28px">I/S/TH</th>
                <th rowspan="3" style="width:24px">TOTAL<br>HADIR</th>
            </tr>
            <tr>
                <?php foreach ($m['tanggal'] as $i => $tgl): ?>
                    <th colspan="2" class="p<?= $i % 2 ?>"><?= esc($hari[(int) date('N', strtotime($tgl))]) ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($m['tanggal'] as $i => $tgl): ?>
                    <th class="d p<?= $i % 2 ?>">P</th><th class="d p<?= $i % 2 ?>">S</th>
                <?php endforeach; ?>
                <th class="sum">P</th><th class="sum">S</th><th class="sum">P</th><th class="sum">S</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $tot = ['H' => 0, 'T' => 0];
                foreach ($m['orang'] as $no => $o):
                    $tot['H'] += $o['hadir']['pagi'] + $o['hadir']['siang'];
                    $tot['T'] += $o['tidak']['pagi'] + $o['tidak']['siang'];
            ?>
                <tr>
                    <td><?= $no + 1 ?></td>
                    <td class="nama"><?= esc($o['nama']) ?></td>
                    <?php foreach ($m['tanggal'] as $i => $tgl): foreach (['pagi', 'siang'] as $sh): $k = $o['sel'][$tgl][$sh]; ?>
                        <td class="d p<?= $i % 2 ?><?= in_array($k, ['I', 'S', 'TH'], true) ? ' th' : ($k === 'HT' ? ' ht' : '') ?>"><?= esc($k) ?></td>
                    <?php endforeach; endforeach; ?>
                    <td class="sum"><?= $o['hadir']['pagi'] ?></td>
                    <td class="sum"><?= $o['hadir']['siang'] ?></td>
                    <td class="sum"><?= $o['tidak']['pagi'] ?></td>
                    <td class="sum"><?= $o['tidak']['siang'] ?></td>
                    <td class="sum"><?= $o['hadir']['pagi'] + $o['hadir']['siang'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php $semua = $tot['H'] + $tot['T']; ?>
    <div class="foot">Persentase: hadir <?= $semua > 0 ? number_format($tot['H'] / $semua * 100, 1, ',', '.') : '0' ?>% · tidak hadir <?= $semua > 0 ? number_format($tot['T'] / $semua * 100, 1, ',', '.') : '0' ?>%</div>
    <?= $ttd() ?>

    <!-- ===== 2. REKAP HADIR ===== -->
    <div class="brk"></div>
    <h1>REKAP KEHADIRAN GURU DAN STAF TATA USAHA</h1>
    <div class="sub"><?= esc($sekolah) ?></div>
    <div class="sub">PERIODE BULAN <?= esc($judulBln) ?><?= $tarifTrans > 0 ? ' — uang transport ' . $rp($tarifTrans) . '/hari' : '' ?></div>
    <br>
    <table class="r">
        <thead>
            <tr>
                <th style="width:5%">NO</th><th>NAMA</th><th style="width:8%">PAGI</th><th style="width:8%">SIANG</th>
                <th style="width:8%">TOTAL</th><th style="width:18%">KET</th><th style="width:10%">TOTAL HARI</th>
                <?php if ($tarifTrans > 0): ?><th style="width:14%">UANG TRANSPORT</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $s = ['p' => 0, 's' => 0, 'h' => 0, 'u' => 0];
            foreach ($m['orang'] as $no => $o):
                $s['p'] += $o['hadir']['pagi']; $s['s'] += $o['hadir']['siang']; $s['h'] += $o['hari_hadir'];
                $s['u'] += $o['hari_hadir'] * $tarifTrans; ?>
                <tr>
                    <td class="c"><?= $no + 1 ?></td>
                    <td><?= esc($o['nama']) ?></td>
                    <td class="c"><?= $o['hadir']['pagi'] ?></td>
                    <td class="c"><?= $o['hadir']['siang'] ?></td>
                    <td class="c"><?= $o['hadir']['pagi'] + $o['hadir']['siang'] ?></td>
                    <td><?= $o['kode_n']['HT'] > 0 ? 'Terlambat ' . $o['kode_n']['HT'] . 'x' : '' ?></td>
                    <td class="c"><?= $o['hari_hadir'] ?></td>
                    <?php if ($tarifTrans > 0): ?><td style="text-align:right"><?= $rp($o['hari_hadir'] * $tarifTrans) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td></td><td>TOTAL</td><td class="c"><?= $s['p'] ?></td><td class="c"><?= $s['s'] ?></td>
                <td class="c"><?= $s['p'] + $s['s'] ?></td><td></td><td class="c"><?= $s['h'] ?></td>
                <?php if ($tarifTrans > 0): ?><td style="text-align:right"><?= $rp($s['u']) ?></td><?php endif; ?>
            </tr>
        </tbody>
    </table>
    <?= $ttd() ?>

    <!-- ===== 3. REKAP TIDAK HADIR ===== -->
    <div class="brk"></div>
    <h1>REKAP TIDAK HADIR GURU DAN STAF TATA USAHA</h1>
    <div class="sub"><?= esc($sekolah) ?></div>
    <div class="sub">PERIODE BULAN <?= esc($judulBln) ?> — potongan <?= $rp($tarifJp) ?> per JP</div>
    <br>
    <table class="r">
        <thead>
            <tr>
                <th style="width:5%">NO</th><th>NAMA</th><th style="width:7%">PAGI</th><th style="width:7%">SIANG</th>
                <th style="width:7%">TOTAL</th><th style="width:26%">KET</th><th style="width:9%">TOTAL JAM</th><th style="width:13%">TOTAL POTONGAN</th>
            </tr>
        </thead>
        <tbody>
            <?php $s = ['p' => 0, 's' => 0, 'j' => 0];
            foreach ($m['orang'] as $no => $o):
                $s['p'] += $o['tidak']['pagi']; $s['s'] += $o['tidak']['siang']; $s['j'] += $o['jp_total'];
                $ket = [];
                foreach (['I' => 'Izin', 'S' => 'Sakit', 'TH' => 'Tidak hadir'] as $kode => $lbl) {
                    if ($o['kode_n'][$kode] > 0) {
                        $ket[] = $lbl . ' ' . $o['kode_n'][$kode];
                    }
                }
                if ($o['jp_telat'] > 0) {
                    $ket[] = 'Terlambat ' . $o['jp_telat'] . ' JP';
                } ?>
                <tr>
                    <td class="c"><?= $no + 1 ?></td>
                    <td><?= esc($o['nama']) ?></td>
                    <td class="c"><?= $o['tidak']['pagi'] ?></td>
                    <td class="c"><?= $o['tidak']['siang'] ?></td>
                    <td class="c"><?= $o['tidak']['pagi'] + $o['tidak']['siang'] ?></td>
                    <td><?= esc(implode(', ', $ket)) ?></td>
                    <td class="c"><?= $o['jp_total'] ?></td>
                    <td style="text-align:right"><?= $o['jp_total'] > 0 ? $rp($o['jp_total'] * $tarifJp) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td></td><td>TOTAL</td><td class="c"><?= $s['p'] ?></td><td class="c"><?= $s['s'] ?></td>
                <td class="c"><?= $s['p'] + $s['s'] ?></td><td></td><td class="c"><?= $s['j'] ?></td>
                <td style="text-align:right"><?= $rp($s['j'] * $tarifJp) ?></td>
            </tr>
        </tbody>
    </table>
    <div class="foot">Total jam = JP terlambat + JP izin/sakit/tidak hadir (per sesi mengajar). Potongan = total jam × <?= $rp($tarifJp) ?>.</div>
    <?= $ttd() ?>
</body>
</html>
