<?php
/**
 * Laporan Laboratorium — versi cetak (Dompdf, A4 portrait).
 *
 * @var string $dari
 * @var string $sampai
 * @var int    $asetTotal
 * @var array  $asetKondisi
 * @var array  $asetStatus
 * @var array  $asetPerLab
 * @var int    $pmTotal
 * @var array  $pmStatus
 * @var int    $pmSedang
 * @var int    $pmTerlambat
 * @var int    $krTotal
 * @var array  $krStatus
 * @var int    $pbTotal
 * @var array  $pbJenis
 * @var float  $pbBiaya
 * @var int    $spTotal
 * @var array  $spMenipis
 * @var int    $spMenipisCount
 * @var int    $jrTotal
 * @var array  $jrPerLab
 */
$rp  = static fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
$cap = static fn ($s) => ucfirst(str_replace('_', ' ', (string) $s));
?>
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11px; color: #1f2937; }
    h2 { font-size: 12px; background: #1A3A6B; color: #fff; padding: 4px 8px; margin: 12px 0 6px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
    th { background: #eef2f7; }
    td.num, th.num { text-align: right; }
    .periode { font-size: 11px; color: #475569; margin-bottom: 6px; }
    .half { width: 49%; float: left; }
    .half + .half { margin-left: 2%; }
    .clear { clear: both; }
</style>

<?= kop_pdf() ?>

<div style="text-align:center; font-weight:bold; font-size:14px; margin: 6px 0;">LAPORAN LABORATORIUM</div>
<div class="periode">Periode: <?= esc($dari) ?> s/d <?= esc($sampai) ?></div>

<h2>Aset / Inventaris (kondisi terkini)</h2>
<table>
    <tr><th>Total Aset</th><td class="num"><?= (int) $asetTotal ?></td>
        <th>Tersedia</th><td class="num"><?= (int) $asetStatus['tersedia'] ?></td>
        <th>Dipinjam</th><td class="num"><?= (int) $asetStatus['dipinjam'] ?></td></tr>
    <tr><th>Perbaikan</th><td class="num"><?= (int) $asetStatus['perbaikan'] ?></td>
        <th>Kondisi Baik</th><td class="num"><?= (int) $asetKondisi['baik'] ?></td>
        <th>Rusak Ringan/Berat</th><td class="num"><?= (int) $asetKondisi['rusak_ringan'] ?> / <?= (int) $asetKondisi['rusak_berat'] ?></td></tr>
</table>
<table>
    <thead><tr><th>Aset per Lab</th><th class="num">Jumlah</th></tr></thead>
    <tbody>
        <?php if (empty($asetPerLab)): ?>
            <tr><td colspan="2">Belum ada aset.</td></tr>
        <?php else: foreach ($asetPerLab as $row): ?>
            <tr><td><?= $row['lab_nama'] !== null ? esc($row['lab_nama']) : '(tanpa lab)' ?></td><td class="num"><?= (int) $row['c'] ?></td></tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<h2>Peminjaman (periode)</h2>
<table>
    <tr><th>Total periode</th><td class="num"><?= (int) $pmTotal ?></td>
        <th>Dikembalikan</th><td class="num"><?= (int) $pmStatus['dikembalikan'] ?></td>
        <th>Hilang</th><td class="num"><?= (int) $pmStatus['hilang'] ?></td></tr>
    <tr><th>Dipinjam (periode)</th><td class="num"><?= (int) $pmStatus['dipinjam'] ?></td>
        <th>Sedang dipinjam (kini)</th><td class="num"><?= (int) $pmSedang ?></td>
        <th>Terlambat (kini)</th><td class="num"><?= (int) $pmTerlambat ?></td></tr>
</table>

<h2>Kerusakan &amp; Perbaikan (periode)</h2>
<table>
    <tr><th>Total kerusakan</th><td class="num"><?= (int) $krTotal ?></td>
        <th>Selesai</th><td class="num"><?= (int) $krStatus['selesai'] ?></td>
        <th>Belum selesai</th><td class="num"><?= (int) ($krStatus['dilaporkan'] + $krStatus['diproses']) ?></td></tr>
    <tr><th>Total perbaikan</th><td class="num"><?= (int) $pbTotal ?></td>
        <th>Penggantian komponen</th><td class="num"><?= (int) $pbJenis['penggantian'] ?></td>
        <th>Total biaya</th><td class="num"><?= $rp($pbBiaya) ?></td></tr>
</table>

<div class="clear"></div>
<div class="half">
    <h2>Sparepart (stok menipis)</h2>
    <table>
        <thead><tr><th>Sparepart</th><th class="num">Stok / Min</th></tr></thead>
        <tbody>
            <?php if (empty($spMenipis)): ?>
                <tr><td colspan="2">Semua stok aman (dari <?= (int) $spTotal ?> jenis).</td></tr>
            <?php else: foreach ($spMenipis as $s): ?>
                <tr><td><?= esc($s['nama']) ?></td><td class="num"><?= (int) $s['stok'] ?> / <?= (int) $s['stok_minimum'] ?> <?= esc($s['satuan']) ?></td></tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<div class="half">
    <h2>Pemakaian Lab (jurnal)</h2>
    <table>
        <thead><tr><th>Lab</th><th class="num">Sesi</th></tr></thead>
        <tbody>
            <tr><th>Total sesi</th><td class="num"><?= (int) $jrTotal ?></td></tr>
            <?php foreach ($jrPerLab as $row): ?>
                <tr><td><?= $row['lab_nama'] !== null ? esc($row['lab_nama']) : '(tanpa lab)' ?></td><td class="num"><?= (int) $row['c'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="clear"></div>

<div style="margin-top:14px; font-size:10px; color:#64748b;">Dicetak: <?= date('d/m/Y H:i') ?></div>
