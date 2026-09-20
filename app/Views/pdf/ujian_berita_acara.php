<?php
/**
 * Berita Acara Pelaksanaan satu sesi ujian — cetak (Dompdf, A4 portrait).
 *
 * Nomor berita acara DITURUNKAN dari data periode + id sesi, tidak disimpan
 * di tabel mana pun — stabil tiap kali dicetak tanpa menambah penomoran.
 *
 * @var array            $periode
 * @var string           $label
 * @var string           $panjang        kepanjangan jenis ujian
 * @var array            $jadwal
 * @var array<int,array> $sasaran        kelas sasaran sesi ini
 * @var int              $jumlahPeserta
 * @var array<int,array> $takHadir       ujian_susulan::withRelations()
 * @var array<int,array> $pengawas
 * @var string           $nomor
 * @var array            $setting
 */
$hariIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
];
$bulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$tglPanjang = static function ($d) use ($bulanIndo) {
    if (! $d) {
        return '—';
    }
    $t = strtotime((string) $d);

    return date('j', $t) . ' ' . ($bulanIndo[(int) date('n', $t)] ?? '') . ' ' . date('Y', $t);
};

$ts   = strtotime((string) $jadwal['tanggal']);
$hari = $hariIndo[date('l', $ts)] ?? '';
$jam  = $jadwal['jam_mulai']
    ? substr((string) $jadwal['jam_mulai'], 0, 5) . ($jadwal['jam_selesai'] ? ' s.d. ' . substr((string) $jadwal['jam_selesai'], 0, 5) : '')
    : 'sehari penuh';

$namaKelas = implode(', ', array_column($sasaran, 'nama_kelas'));
$hadir     = $jumlahPeserta - count($takHadir);
$labelAlasan = ['sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya'];
?>
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11px; color: #1f2937; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
    th { background: #eef2f7; }
    .center { text-align: center; }
    .judul { text-align: center; font-weight: bold; font-size: 14px; margin: 10px 0 2px; text-transform: uppercase; }
    .nomor { text-align: center; font-size: 11px; margin-bottom: 14px; }
    p.isi { text-align: justify; line-height: 1.6; margin: 6px 0; }
    .info { border: none; }
    .info td { border: none; padding: 1px 0; }
    .info td.k { width: 150px; }
    .ttd-wrap { width: 100%; margin-top: 26px; }
    .ttd-box { width: 30%; float: left; text-align: center; margin-right: 3%; margin-bottom: 36px; }
    .ttd-line { margin-top: 52px; border-top: 1px solid #1f2937; padding-top: 3px; }
    .clear { clear: both; }
</style>

<?= kop_pdf() ?>

<div class="judul">Berita Acara Pelaksanaan<br><?= esc($panjang ?: $label) ?></div>
<div class="nomor">Nomor: <?= esc($nomor) ?></div>

<p class="isi">
    Pada hari ini <b><?= esc($hari) ?></b>, tanggal <b><?= esc($tglPanjang($jadwal['tanggal'])) ?></b>,
    pukul <b><?= esc($jam) ?></b>, telah dilaksanakan <?= esc($panjang ?: $label) ?>
    Tahun Pelajaran <b><?= esc($periode['tahun_ajaran']) ?></b> Semester <b><?= esc($periode['semester']) ?></b>
    untuk mata pelajaran <b><?= esc($jadwal['nama_mapel'] ?? '—') ?></b>
    pada tingkat <b><?= esc($jadwal['tingkat']) ?></b><?= $jadwal['jurusan_nama'] ? ' program keahlian <b>' . esc($jadwal['jurusan_nama']) . '</b>' : '' ?>
    <?= $jadwal['ruang'] ? ', bertempat di ruang <b>' . esc($jadwal['ruang']) . '</b>' : '' ?>.
</p>

<table class="info">
    <tr><td class="k">Kelas peserta</td><td>: <?= esc($namaKelas ?: '—') ?></td></tr>
    <tr><td class="k">Jumlah peserta</td><td>: <?= (int) $jumlahPeserta ?> siswa</td></tr>
    <tr><td class="k">Hadir</td><td>: <?= (int) $hadir ?> siswa</td></tr>
    <tr><td class="k">Tidak hadir</td><td>: <?= count($takHadir) ?> siswa</td></tr>
</table>

<p class="isi"><b>Daftar siswa yang tidak hadir:</b></p>
<table>
    <thead>
        <tr>
            <th class="center" style="width:32px">No</th>
            <th style="width:90px">NIS</th>
            <th>Nama Siswa</th>
            <th style="width:110px">Kelas</th>
            <th style="width:80px">Alasan</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($takHadir === []): ?>
            <tr><td colspan="6" class="center">Nihil — seluruh peserta hadir.</td></tr>
        <?php else: foreach ($takHadir as $n => $r): ?>
            <tr>
                <td class="center"><?= $n + 1 ?></td>
                <td><?= esc($r['nis'] ?? '—') ?></td>
                <td><?= esc($r['siswa_nama'] ?? '—') ?></td>
                <td><?= esc($r['nama_kelas'] ?? '—') ?></td>
                <td><?= esc($labelAlasan[$r['alasan']] ?? $r['alasan']) ?></td>
                <td><?= esc($r['keterangan'] ?: '—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<p class="isi">
    Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.
    Siswa yang tidak hadir sebagaimana tercantum di atas akan mengikuti ujian susulan sesuai jadwal
    yang ditetapkan panitia.
</p>

<div class="ttd-wrap">
    <?php if ($pengawas === []): ?>
        <div class="ttd-box">
            <div>Pengawas Ujian</div>
            <div class="ttd-line">(&nbsp;..............................&nbsp;)</div>
        </div>
    <?php else: foreach ($pengawas as $p): ?>
        <div class="ttd-box">
            <div><?= $p['peran'] === 'cadangan' ? 'Pengawas Cadangan' : 'Pengawas Ujian' ?></div>
            <div class="ttd-line"><?= esc($p['guru_nama'] ?? '—') ?></div>
        </div>
    <?php endforeach; endif; ?>
    <div class="ttd-box">
        <div><?= esc($setting['city'] ?? '') ?>, <?= esc($tglPanjang(date('Y-m-d'))) ?><br>Kepala Sekolah</div>
        <div class="ttd-line"><?= esc($setting['headmaster_name'] ?? '—') ?></div>
    </div>
    <div class="clear"></div>
</div>
