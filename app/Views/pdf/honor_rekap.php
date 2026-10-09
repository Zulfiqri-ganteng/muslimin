<?php
/**
 * Rekap honor ujian — cetak PDF (Dompdf, F4 landscape). Bahan dari HonorCetak::bahan(): angka, urutan baris, dan
 * judul SAMA dengan lembar REKAP HONOR di Excel (meniru rekap sekolah: judul diulang di tiap halaman, header bernomor,
 * JABATAN & jumlah Koreksi kuning, format "Rp" akuntansi, nama bergaya Times).
 *
 * @var array<string,mixed> $b
 */
use App\Libraries\HonorCetak;

$rp    = static fn ($n): string => number_format((int) $n, 0, ',', '.');
// Format akuntansi Excel: "Rp" rata kiri, angka rata kanan, nol = "-". Tabel kecil dalam sel (float: dompdf menambah tinggi baris).
$akt   = static fn ($n): string => '<table class="ak"><tr><td class="rp">Rp</td><td class="v">' . ((int) $n === 0 ? '-' : number_format((int) $n, 0, ',', '.')) . '</td></tr></table>';
$komp  = $b['komponen'];
$lebar = HonorCetak::lebarKolomPdf($komp);
$jml   = array_sum($lebar);
$nKol  = count($lebar);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title><?= esc($b['judul']) ?></title>
<style>
    @page { margin: 12mm 8mm 12mm 8mm; }
    .draf { position: fixed; top: -7mm; right: 0; color: #b91c1c; font-weight: bold; font-size: 9px; border: 1px solid #b91c1c; padding: 1px 6px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 8px; color: #000; }
    table.rekap { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.rekap thead { display: table-header-group; }
    table.rekap tr { page-break-inside: avoid; }
    table.rekap th, table.rekap td { border: 0.6px solid #222; padding: 4px 3px; vertical-align: middle; }
    table.rekap td { white-space: nowrap; }
    table.rekap td.nama, table.rekap td.jab { white-space: normal; }
    table.rekap th { text-align: center; font-weight: bold; font-size: 8px; }
    table.rekap th.tj { border: 0; text-align: center; font-size: 11px; padding: 1px; }
    table.rekap th.tj3 { border: 0; border-bottom: 0.6px solid #222; }
    table.rekap th.n4 { font-size: 7px; padding: 1px; }
    table.rekap tr.h6 th { border-bottom: 3px double #222; }
    .tarif { font-weight: bold; }
    .kuning { background: #ffff00; }
    .c { text-align: center; }
    .nama { font-family: "Times", "Times New Roman", serif; font-size: 9px; }
    table.rekap td.akt { padding: 4px 3px; }
    table.ak { width: 100%; border-collapse: collapse; border: 0; }
    table.ak td { border: 0; padding: 0; white-space: nowrap; }
    table.ak td.rp { text-align: left; width: 12px; }
    table.ak td.v { text-align: right; }
    tr.jumlah td { text-align: center; font-weight: normal; background: #fff; }
    tr.jumlah td.lbl { font-weight: bold; }
    table.ttd { width: 100%; margin-top: 14px; border-collapse: collapse; page-break-inside: avoid; }
    table.ttd td { vertical-align: top; font-size: 9px; padding: 0; }
    .nm { font-weight: bold; margin-top: 40px; }
</style>
</head>
<body>
<?php if (($b['status'] ?? 'draf') === 'draf'): ?><div class="draf">DRAF &mdash; belum final</div><?php endif; ?>

<table class="rekap">
    <colgroup>
        <?php foreach ($lebar as $w): ?><col style="width: <?= round($w / $jml * 100, 3) ?>%"><?php endforeach; ?>
    </colgroup>
    <thead>
        <tr><th colspan="<?= $nKol ?>" class="tj"><?= esc($b['judul']) ?></th></tr>
        <tr><th colspan="<?= $nKol ?>" class="tj"><?= esc($b['sekolah']) ?></th></tr>
        <tr><th colspan="<?= $nKol ?>" class="tj tj3"><?= esc($b['tahun']) ?></th></tr>
        <tr>
            <?php for ($i = 1; $i <= $nKol; $i++): ?><th class="n4" style="width: <?= round($lebar[$i - 1] / $jml * 100, 3) ?>%"><?= $i ?></th><?php endfor; ?>
        </tr>
        <tr>
            <th rowspan="2">NO</th>
            <th rowspan="2" style="text-align:left">NAMA</th>
            <th rowspan="2" class="kuning" style="border-bottom: 3px double #222">JABATAN</th>
            <?php foreach ($komp as $k): ?>
                <?php if ($k['tipe'] === 'tetap'): ?>
                    <th rowspan="2" style="border-bottom: 3px double #222"><?= esc(HonorCetak::judulKolom($k)) ?></th>
                <?php else: ?>
                    <th colspan="2"><?= esc(HonorCetak::judulKolom($k)) ?></th>
                <?php endif; ?>
            <?php endforeach; ?>
            <th rowspan="2" style="border-bottom: 3px double #222">TOTAL</th>
            <th rowspan="2" style="font-weight:normal; border-bottom: 3px double #222">TTD</th>
        </tr>
        <tr class="h6">
            <?php foreach ($komp as $k): ?>
                <?php if ($k['tipe'] === 'satuan'): ?><th colspan="2" class="tarif">Rp. <?= $rp($k['tarif']) ?></th><?php endif; ?>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($b['baris'] as $br): ?>
            <tr>
                <td class="c"><?= (int) $br['no'] ?></td>
                <td class="nama"><?= esc($br['nama']) ?></td>
                <td class="jab"><?= esc($br['jabatan']) ?></td>
                <?php foreach ($komp as $k): $s = $br['sel'][(int) $k['id']]; ?>
                    <?php if ($k['tipe'] === 'tetap'): ?>
                        <td class="akt"><?= $akt($s['rp']) ?></td>
                    <?php else: ?>
                        <td class="c<?= ($k['sumber'] ?? '') === 'koreksi' ? ' kuning' : '' ?>"><?= (int) $s['n'] ?></td>
                        <td class="akt"><?= $akt($s['rp']) ?></td>
                    <?php endif; ?>
                <?php endforeach; ?>
                <td class="akt"><?= $akt($br['total']) ?></td>
                <td style="text-align: <?= $br['no'] % 2 === 1 ? 'left' : 'center' ?>"><?= (int) $br['no'] ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="jumlah">
            <td colspan="3" class="lbl">Jumlah</td>
            <?php foreach ($komp as $k): $kid = (int) $k['id']; ?>
                <?php if ($k['tipe'] === 'tetap'): ?>
                    <td class="akt"><?= $akt($b['total_komponen'][$kid] ?? 0) ?></td>
                <?php else: ?>
                    <td class="c"><?= $rp($b['total_jumlah'][$kid] ?? 0) ?></td>
                    <td class="akt"><?= $akt($b['total_komponen'][$kid] ?? 0) ?></td>
                <?php endif; ?>
            <?php endforeach; ?>
            <td class="akt"><?= $akt($b['total']) ?></td>
            <td></td>
        </tr>
    </tbody>
</table>

<table class="ttd">
    <tr>
        <td style="width: 22%"></td>
        <td style="width: 26%">
            Mengetahui,<br>Ketua
            <div class="nm"><?= esc($b['ketua'] !== '' ? $b['ketua'] : '(........................)') ?></div>
        </td>
        <td style="width: 24%"></td>
        <td style="width: 28%">
            <?= esc($b['tanggal_ttd']) ?><br>Bendahara
            <div class="nm"><?= esc($b['bendahara'] !== '' ? $b['bendahara'] : '(........................)') ?></div>
        </td>
    </tr>
    <tr>
        <td></td>
        <td colspan="2" style="text-align: center; padding-top: 12px;">
            Menyetujui,<br>Kepala <?= esc($b['namaSekolahJudul']) ?>
            <div class="nm"><?= esc($b['kepsek'] !== '' ? $b['kepsek'] : '(........................)') ?></div>
        </td>
        <td></td>
    </tr>
</table>
</body>
</html>
