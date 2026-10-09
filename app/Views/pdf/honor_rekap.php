<?php
/**
 * Rekap honor ujian — cetak PDF (Dompdf, F4 landscape). Bahan dari HonorCetak::bahan() (angka sama dengan layar & Excel).
 *
 * @var array<string,mixed> $b
 */
$rp   = static fn ($n): string => number_format((int) $n, 0, ',', '.');
$komp = $b['komponen'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title><?= esc($b['judul']) ?></title>
<style>
    @page { margin: 14mm 10mm 14mm 10mm; }
    .draf { position: fixed; top: -8mm; right: 0; color: #b91c1c; font-weight: bold; font-size: 9px; border: 1px solid #b91c1c; padding: 1px 6px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 8px; color: #111; }
    .judul { text-align: center; font-weight: bold; font-size: 12px; line-height: 1.5; }
    .judul2 { text-align: center; font-weight: bold; font-size: 10px; line-height: 1.5; }
    table.rekap { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.rekap th, table.rekap td { border: 0.6px solid #444; padding: 3px 4px; }
    table.rekap th { background: #e5eaf2; text-align: center; font-size: 7.5px; }
    table.rekap thead { display: table-header-group; }
    table.rekap tr { page-break-inside: avoid; }
    .no, .c { text-align: center; }
    .r { text-align: right; white-space: nowrap; }
    .tarif { font-weight: normal; font-size: 7px; }
    tr.jumlah td { font-weight: bold; background: #f1f5f9; }
    td.total { font-weight: bold; }
    table.ttd { width: 100%; margin-top: 16px; page-break-inside: avoid; border-collapse: collapse; }
    table.ttd td { text-align: center; vertical-align: top; width: 33%; font-size: 9px; padding: 0 6px; }
    .nama { font-weight: bold; text-decoration: underline; margin-top: 46px; }
    .tengah { margin-top: 12px; }
</style>
</head>
<body>
<?php if (($b['status'] ?? 'draf') === 'draf'): ?><div class="draf">DRAF &mdash; belum final</div><?php endif; ?>
<div class="judul"><?= esc($b['judul']) ?></div>
<div class="judul2"><?= esc($b['sekolah']) ?></div>
<div class="judul2"><?= esc($b['tahun']) ?></div>

<table class="rekap">
    <thead>
        <tr>
            <th rowspan="2" style="width:22px">NO</th>
            <th rowspan="2">NAMA</th>
            <th rowspan="2">JABATAN</th>
            <?php foreach ($komp as $k): ?>
                <?php if ($k['tipe'] === 'tetap'): ?>
                    <th rowspan="2"><?= esc(mb_strtoupper($k['nama'])) ?></th>
                <?php else: ?>
                    <th colspan="2"><?= esc(mb_strtoupper($k['nama'])) ?></th>
                <?php endif; ?>
            <?php endforeach; ?>
            <th rowspan="2">TOTAL</th>
            <th rowspan="2" style="width:30px">TTD</th>
        </tr>
        <tr>
            <?php foreach ($komp as $k): ?>
                <?php if ($k['tipe'] === 'satuan'): ?><th colspan="2" class="tarif">Rp. <?= $rp($k['tarif']) ?></th><?php endif; ?>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($b['baris'] as $br): ?>
            <tr>
                <td class="no"><?= (int) $br['no'] ?></td>
                <td><?= esc($br['nama']) ?></td>
                <td><?= esc($br['jabatan']) ?></td>
                <?php foreach ($komp as $k): $s = $br['sel'][(int) $k['id']]; ?>
                    <?php if ($k['tipe'] === 'tetap'): ?>
                        <td class="r"><?= $rp($s['rp']) ?></td>
                    <?php else: ?>
                        <td class="c"><?= (int) $s['n'] ?></td>
                        <td class="r"><?= $rp($s['rp']) ?></td>
                    <?php endif; ?>
                <?php endforeach; ?>
                <td class="r total"><?= $rp($br['total']) ?></td>
                <td style="text-align: <?= $br['no'] % 2 === 1 ? 'left' : 'center' ?>"><?= (int) $br['no'] ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="jumlah">
            <td colspan="3" class="c">JUMLAH</td>
            <?php foreach ($komp as $k): $kid = (int) $k['id']; ?>
                <?php if ($k['tipe'] === 'tetap'): ?>
                    <td class="r"><?= $rp($b['total_komponen'][$kid] ?? 0) ?></td>
                <?php else: ?>
                    <td class="c"><?= $rp($b['total_jumlah'][$kid] ?? 0) ?></td>
                    <td class="r"><?= $rp($b['total_komponen'][$kid] ?? 0) ?></td>
                <?php endif; ?>
            <?php endforeach; ?>
            <td class="r"><?= $rp($b['total']) ?></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="ttd">
    <tr>
        <td>
            Mengetahui,<br>Ketua
            <div class="nama"><?= esc($b['ketua'] !== '' ? $b['ketua'] : '(........................)') ?></div>
        </td>
        <td></td>
        <td>
            <?= esc(trim($b['tempat'] . ($b['tanggal'] !== '' ? ', ' . $b['tanggal'] : ', ..................'))) ?><br>Bendahara
            <div class="nama"><?= esc($b['bendahara'] !== '' ? $b['bendahara'] : '(........................)') ?></div>
        </td>
    </tr>
    <tr>
        <td></td>
        <td class="tengah">
            Menyetujui,<br>Kepala <?= esc($b['namaSekolah']) ?>
            <div class="nama"><?= esc($b['kepsek'] !== '' ? $b['kepsek'] : '(........................)') ?></div>
        </td>
        <td></td>
    </tr>
</table>
</body>
</html>
