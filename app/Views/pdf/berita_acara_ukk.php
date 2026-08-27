<?php
/**
 * Berita Acara Pelaksanaan UKK — versi cetak (Dompdf, A4 portrait).
 *
 * @var array $ba      berita_acara_ukk::withRelations() satu baris
 * @var array $peserta peserta_ukk::withRelations() milik jadwal terkait
 * @var array $penguji JadwalUkkPengujiModel::forJadwal()
 */
$tgl = static fn ($d) => $d ? date('d F Y', strtotime((string) $d)) : '—';
$hariIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
];
$hari = $hariIndo[date('l', strtotime((string) $ba['tanggal']))] ?? '';
$warnaStatus = [
    'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus', 'hadir' => 'Hadir',
    'tidak_hadir' => 'Tidak Hadir', 'terdaftar' => 'Terdaftar',
];
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
    .ttd-wrap { width: 100%; margin-top: 30px; }
    .ttd-box { width: 30%; float: left; text-align: center; margin-right: 3%; margin-bottom: 40px; }
    .ttd-line { margin-top: 55px; border-top: 1px solid #1f2937; padding-top: 3px; }
    .clear { clear: both; }
</style>

<?= kop_pdf() ?>

<div class="judul">Berita Acara Pelaksanaan<br>Uji Kompetensi Keahlian (UKK)</div>
<div class="nomor">Nomor: <?= esc($ba['nomor_ba']) ?></div>

<p class="isi">
    Pada hari ini <?= esc($hari) ?>, tanggal <?= esc($tgl($ba['tanggal'])) ?>, telah dilaksanakan Uji Kompetensi
    Keahlian (UKK) untuk paket soal <b><?= esc($ba['paket_nama'] ?? '—') ?></b>
    <?= ! empty($ba['tempat_nama']) ? 'bertempat di <b>' . esc($ba['tempat_nama']) . '</b>' : '' ?>
    <?= ! empty($ba['jadwal_tanggal']) ? ', sesuai jadwal tanggal ' . esc($tgl($ba['jadwal_tanggal'])) : '' ?>.
    Pelaksanaan diikuti oleh peserta dan diuji oleh penguji sebagaimana tercantum pada daftar berikut.
</p>

<?php if (! empty($ba['catatan'])): ?>
    <p class="isi"><b>Catatan pelaksanaan:</b> <?= nl2br(esc($ba['catatan'])) ?></p>
<?php endif; ?>

<table>
    <thead>
        <tr><th class="center" style="width:5%">No</th><th>No. Peserta</th><th>Nama Peserta</th><th style="width:18%">Status/Hasil</th></tr>
    </thead>
    <tbody>
        <?php if (empty($peserta)): ?>
            <tr><td colspan="4" class="center">Belum ada peserta terdaftar.</td></tr>
        <?php else: foreach ($peserta as $i => $p): ?>
            <tr>
                <td class="center"><?= $i + 1 ?></td>
                <td><?= esc($p['no_peserta'] ?? '—') ?></td>
                <td><?= esc($p['siswa_nama'] ?? '—') ?></td>
                <td><?= esc($warnaStatus[$p['status']] ?? ucfirst($p['status'])) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<table>
    <thead>
        <tr><th class="center" style="width:5%">No</th><th>Nama Penguji</th><th style="width:20%">Tipe</th><th style="width:20%">Peran</th></tr>
    </thead>
    <tbody>
        <?php if (empty($penguji)): ?>
            <tr><td colspan="4" class="center">Belum ada penguji ditugaskan.</td></tr>
        <?php else: foreach ($penguji as $i => $pg):
            $internal = $pg['tipe'] === 'internal';
            $nama     = $internal ? ($pg['guru_nama'] ?? '—') : ($pg['eksternal_nama'] ?? '—'); ?>
            <tr>
                <td class="center"><?= $i + 1 ?></td>
                <td><?= esc($nama) ?></td>
                <td><?= $internal ? 'Internal' : 'Eksternal' ?></td>
                <td><?= esc(ucfirst($pg['peran'])) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<p class="isi">Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

<div class="ttd-wrap">
    <?php if (empty($penguji)): ?>
        <div class="ttd-box">
            <div>Ketua Penguji</div>
            <div class="ttd-line">(....................................)</div>
        </div>
    <?php else: foreach ($penguji as $pg):
        $internal = $pg['tipe'] === 'internal';
        $nama     = $internal ? ($pg['guru_nama'] ?? '') : ($pg['eksternal_nama'] ?? ''); ?>
        <div class="ttd-box">
            <div><?= esc(ucfirst($pg['peran'])) ?> Penguji<?= $internal ? '' : ' (Eksternal)' ?></div>
            <div class="ttd-line"><?= esc($nama) ?></div>
        </div>
    <?php endforeach; endif; ?>
    <div class="clear"></div>
</div>
