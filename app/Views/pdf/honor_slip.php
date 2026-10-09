<?php
/**
 * Slip honor ujian — cetak PDF (Dompdf, A4 portrait, dua slip per halaman). Bahan dari HonorCetak::slipHtml().
 *
 * @var array<string,mixed> $b  (b['baris'][n]['item'] = komponen bernilai, ['terbilang'])
 */
$rp = static fn ($n): string => number_format((int) $n, 0, ',', '.');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Slip <?= esc($b['judul']) ?></title>
<style>
    @page { margin: 12mm 14mm; }
    .draf { position: fixed; top: -7mm; right: 0; color: #b91c1c; font-weight: bold; font-size: 8px; border: 1px solid #b91c1c; padding: 1px 6px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10px; color: #111; }
    .slip { border: 1px solid #555; padding: 10px 14px; margin-bottom: 14px; page-break-inside: avoid; }
    .ph { page-break-after: always; }
    .judul { text-align: center; font-weight: bold; font-size: 13px; letter-spacing: 1px; }
    .sub { text-align: center; font-size: 9px; line-height: 1.5; }
    .garis { border-top: 1px solid #555; margin: 6px 0 8px; }
    table.idn td { padding: 1px 0; font-size: 10px; }
    table.rinci { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.rinci th, table.rinci td { border: 0.6px solid #444; padding: 3px 5px; }
    table.rinci th { background: #e5eaf2; font-size: 9px; }
    .c { text-align: center; } .r { text-align: right; white-space: nowrap; }
    tr.tot td { font-weight: bold; background: #f1f5f9; }
    .terbilang { font-style: italic; margin-top: 6px; font-size: 9.5px; }
    table.ttd { width: 100%; margin-top: 8px; }
    table.ttd td { width: 50%; text-align: center; font-size: 9.5px; vertical-align: top; }
    .nama { margin-top: 34px; font-weight: bold; }
</style>
</head>
<body>
<?php if (($b['status'] ?? 'draf') === 'draf'): ?><div class="draf">DRAF &mdash; belum final</div><?php endif; ?>
<?php foreach ($b['baris'] as $i => $br): ?>
    <div class="slip">
        <div class="judul">SLIP HONOR</div>
        <div class="sub"><?= esc($b['judul']) ?><br><?= esc($b['sekolah']) ?> &mdash; <?= esc($b['tahun']) ?></div>
        <div class="garis"></div>
        <table class="idn">
            <tr><td style="width:62px">Nama</td><td>: <b><?= esc($br['nama']) ?></b></td></tr>
            <tr><td>Jabatan</td><td>: <?= esc($br['jabatan'] !== '' ? $br['jabatan'] : '-') ?></td></tr>
        </table>
        <table class="rinci">
            <thead><tr><th style="width:24px">No</th><th>Komponen</th><th style="width:70px">Jumlah</th><th style="width:70px">Tarif (Rp)</th><th style="width:90px">Rupiah</th></tr></thead>
            <tbody>
                <?php if ($br['item'] === []): ?>
                    <tr><td colspan="5" class="c">Tidak ada honor pada periode ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($br['item'] as $no => $it): ?>
                    <tr>
                        <td class="c"><?= $no + 1 ?></td>
                        <td><?= esc($it['nama']) ?></td>
                        <td class="r"><?= $it['jumlah'] !== null ? (int) $it['jumlah'] . ' ' . esc($it['satuan']) : '' ?></td>
                        <td class="r"><?= $it['tarif'] !== null ? $rp($it['tarif']) : '' ?></td>
                        <td class="r"><?= $rp($it['rp']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="tot"><td colspan="4" class="r">JUMLAH PENDAPATAN</td><td class="r"><?= $rp($br['total']) ?></td></tr>
            </tbody>
        </table>
        <div class="terbilang">Terbilang: <?= esc($br['terbilang']) ?></div>
        <table class="ttd">
            <tr>
                <td>Penerima,<div class="nama">(<?= esc($br['nama']) ?>)</div></td>
                <td><?= esc(trim($b['tempat'] . ($b['tanggal'] !== '' ? ', ' . $b['tanggal'] : ''))) ?><br>Bendahara,<div class="nama"><?= esc($b['bendahara'] !== '' ? $b['bendahara'] : '(........................)') ?></div></td>
            </tr>
        </table>
    </div>
    <?php if (($i + 1) % 2 === 0 && $i + 1 < count($b['baris'])): ?><div class="ph"></div><?php endif; ?>
<?php endforeach; ?>
</body>
</html>
