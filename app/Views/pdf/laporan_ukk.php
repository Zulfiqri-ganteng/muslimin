<?php
/**
 * Rekap Hasil UKK — versi cetak (Dompdf, A4 portrait).
 *
 * @var int|null   $tahunId
 * @var array      $tahunOpts
 * @var int        $totalPeserta
 * @var array      $perStatus
 * @var float|null $rataNilai
 * @var array      $perPaket
 * @var array      $perJurusan
 */
$num = static fn ($v) => $v !== null ? number_format((float) $v, 1) : '—';
$statusLabel = [
    'terdaftar' => 'Terdaftar', 'hadir' => 'Hadir', 'tidak_hadir' => 'Tidak Hadir',
    'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus',
];
$tahunLabel = $tahunId > 0 ? ($tahunOpts[$tahunId] ?? '') : 'Semua Tahun Ajaran';
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
</style>

<?= kop_pdf() ?>

<div style="text-align:center; font-weight:bold; font-size:14px; margin: 6px 0;">REKAP HASIL UJI KOMPETENSI KEAHLIAN (UKK)</div>
<div class="periode">Periode: <?= esc($tahunLabel) ?></div>

<h2>Ringkasan Peserta</h2>
<table>
    <tr>
        <th>Total Peserta</th><td class="num"><?= (int) $totalPeserta ?></td>
        <th>Rata-rata Nilai Akhir</th><td class="num"><?= $num($rataNilai) ?></td>
    </tr>
    <tr>
        <?php foreach ($perStatus as $k => $v): ?>
            <th><?= esc($statusLabel[$k] ?? ucfirst($k)) ?></th><td class="num"><?= (int) $v ?></td>
        <?php endforeach; ?>
    </tr>
</table>

<h2>Rekap per Paket Soal</h2>
<table>
    <thead><tr><th>Paket Soal</th><th class="num">Total</th><th class="num">Lulus</th><th class="num">Tidak Lulus</th><th class="num">Rata-rata Nilai</th></tr></thead>
    <tbody>
        <?php if (empty($perPaket)): ?>
            <tr><td colspan="5">Belum ada data.</td></tr>
        <?php else: foreach ($perPaket as $r): ?>
            <tr>
                <td><?= esc($r['paket_kode']) ?> — <?= esc($r['paket_nama']) ?></td>
                <td class="num"><?= (int) $r['total'] ?></td>
                <td class="num"><?= (int) $r['lulus'] ?></td>
                <td class="num"><?= (int) $r['tidak_lulus'] ?></td>
                <td class="num"><?= $num($r['rata_nilai']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<h2>Rekap per Jurusan</h2>
<table>
    <thead><tr><th>Jurusan</th><th class="num">Total</th><th class="num">Lulus</th><th class="num">Tidak Lulus</th></tr></thead>
    <tbody>
        <?php if (empty($perJurusan)): ?>
            <tr><td colspan="4">Belum ada data.</td></tr>
        <?php else: foreach ($perJurusan as $r): ?>
            <tr>
                <td><?= esc($r['jurusan_nama'] ?? '(tanpa jurusan)') ?></td>
                <td class="num"><?= (int) $r['total'] ?></td>
                <td class="num"><?= (int) $r['lulus'] ?></td>
                <td class="num"><?= (int) $r['tidak_lulus'] ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
