<?php
/**
 * Daftar Hadir peserta satu sesi ujian — versi cetak (Dompdf, A4 portrait).
 *
 * Kolom tanda tangan sengaja DIBIARKAN KOSONG untuk diisi manual di ruang
 * ujian; sistem hanya mencatat yang TIDAK hadir (lihat DESAIN-UJIAN.md).
 * Satu kelas = satu halaman agar mudah dibagikan ke tiap ruang.
 *
 * @var array            $periode
 * @var string           $label
 * @var array            $jadwal    ujian_jadwal + relasinya
 * @var array<int,array> $kelompok  [['kelas'=>…, 'siswa'=>[…]], …]
 * @var array<int,array> $pengawas
 */
$hariIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
];
$ts   = strtotime((string) $jadwal['tanggal']);
$hari = $hariIndo[date('l', $ts)] ?? '';
$jam  = $jadwal['jam_mulai']
    ? substr((string) $jadwal['jam_mulai'], 0, 5) . ($jadwal['jam_selesai'] ? ' – ' . substr((string) $jadwal['jam_selesai'], 0, 5) : '')
    : 'Sehari penuh';
?>
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11px; color: #1f2937; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
    th { background: #eef2f7; }
    .judul { text-align: center; font-weight: bold; font-size: 14px; margin: 10px 0 2px; text-transform: uppercase; }
    .sub { text-align: center; font-size: 11px; margin-bottom: 12px; }
    .info { width: 100%; border: none; margin-bottom: 10px; }
    .info td { border: none; padding: 1px 0; }
    .info td.k { width: 110px; }
    .center { text-align: center; }
    .ttd-wrap { width: 100%; margin-top: 24px; }
    .ttd-box { width: 30%; float: left; text-align: center; margin-right: 3%; }
    .ttd-line { margin-top: 50px; border-top: 1px solid #1f2937; padding-top: 3px; }
    .clear { clear: both; }
    .pecah { page-break-after: always; }
</style>

<?php if ($kelompok === []): ?>
    <?= kop_pdf() ?>
    <div class="judul">Daftar Hadir Peserta Ujian</div>
    <p class="center">Tidak ada kelas sasaran yang berisi siswa aktif untuk sesi ini.</p>
<?php endif; ?>

<?php foreach ($kelompok as $i => $grup): ?>
    <div class="<?= $i < count($kelompok) - 1 ? 'pecah' : '' ?>">
        <?= kop_pdf() ?>

        <div class="judul">Daftar Hadir Peserta Ujian</div>
        <div class="sub"><?= esc($label) ?> &mdash; Semester <?= esc($periode['semester']) ?></div>

        <table class="info">
            <tr>
                <td class="k">Mata Pelajaran</td><td>: <b><?= esc($jadwal['nama_mapel'] ?? '—') ?></b></td>
                <td class="k">Kelas</td><td>: <b><?= esc($grup['kelas']['nama_kelas']) ?></b></td>
            </tr>
            <tr>
                <td class="k">Hari / Tanggal</td><td>: <?= esc($hari) ?>, <?= date('d/m/Y', $ts) ?></td>
                <td class="k">Ruang</td><td>: <?= esc($jadwal['ruang'] ?: '—') ?></td>
            </tr>
            <tr>
                <td class="k">Waktu</td><td>: <?= esc($jam) ?></td>
                <td class="k">Jumlah Peserta</td><td>: <?= count($grup['siswa']) ?> siswa</td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th class="center" style="width:32px">No</th>
                    <th style="width:90px">NIS</th>
                    <th>Nama Siswa</th>
                    <th class="center" style="width:34px">L/P</th>
                    <th class="center" style="width:150px">Tanda Tangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grup['siswa'] as $n => $s): ?>
                    <tr>
                        <td class="center"><?= $n + 1 ?></td>
                        <td><?= esc($s['nis']) ?></td>
                        <td><?= esc($s['nama']) ?></td>
                        <td class="center"><?= esc($s['jenis_kelamin'] ?: '—') ?></td>
                        <td style="height:22px">&nbsp;</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="ttd-wrap">
            <?php if ($pengawas === []): ?>
                <div class="ttd-box">
                    <div>Pengawas Ujian</div>
                    <div class="ttd-line">(&nbsp;..............................&nbsp;)</div>
                </div>
            <?php else: ?>
                <?php foreach ($pengawas as $p): ?>
                    <div class="ttd-box">
                        <div><?= $p['peran'] === 'cadangan' ? 'Pengawas Cadangan' : 'Pengawas Ujian' ?></div>
                        <div class="ttd-line"><?= esc($p['guru_nama'] ?? '—') ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="clear"></div>
        </div>
    </div>
<?php endforeach; ?>
