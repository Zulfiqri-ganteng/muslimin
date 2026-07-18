<?php

/**
 * Rekap absensi guru (PDF).
 *
 * @var array  $setting Pengaturan sekolah
 * @var array  $rows    Baris rekap per guru
 * @var string $dari    Tanggal awal periode
 * @var string $sampai  Tanggal akhir periode
 */
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            font-family: "DejaVu Sans", sans-serif;
        }

        body {
            margin: 0;
            color: #1e293b;
            font-size: 10px;
        }

        .head {
            text-align: center;
            margin-bottom: 12px;
        }

        .head h1 {
            margin: 0;
            font-size: 15px;
        }

        .head p {
            margin: 2px 0;
            font-size: 10px;
            color: #475569;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 0.7px solid #94a3b8;
            padding: 4px 6px;
        }

        th {
            background: #1A3A6B;
            color: #fff;
        }

        .c {
            text-align: center;
        }

        .total td {
            font-weight: bold;
            background: #eef2ff;
        }

        .foot {
            margin-top: 8px;
            font-size: 9px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>

<body>
    <?= kop_pdf() ?>
    <div class="head">
        <h1>REKAP ABSENSI GURU</h1>
        <p>Periode <?= esc($dari) ?> s/d <?= esc($sampai) ?></p>
    </div>

    <?php if (empty($rows)): ?>
        <p style="text-align:center;color:#94a3b8">Belum ada data pada periode ini.</p>
    <?php else:
        $sum = ['total' => 0, 'hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
    ?>
        <table>
            <thead>
                <tr>
                    <th style="width:4%" class="c">No</th>
                    <th style="width:10%">Kode</th>
                    <th>Nama Guru</th>
                    <th style="width:20%">Jabatan</th>
                    <th style="width:9%" class="c">Total Hari</th>
                    <th style="width:8%" class="c">Hadir</th>
                    <th style="width:8%" class="c">Telat</th>
                    <th style="width:8%" class="c">Izin</th>
                    <th style="width:8%" class="c">Sakit</th>
                    <th style="width:8%" class="c">Alpa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): foreach ($sum as $k => $_) {
                        $sum[$k] += (int) $r[$k];
                    } ?>
                    <tr>
                        <td class="c"><?= $i + 1 ?></td>
                        <td><?= esc($r['kode']) ?></td>
                        <td><?= esc($r['nama']) ?></td>
                        <td><?= esc($r['jabatan_all'] ?? '') ?></td>
                        <td class="c"><?= (int) $r['total'] ?></td>
                        <td class="c"><?= (int) $r['hadir'] ?></td>
                        <td class="c"><?= (int) $r['telat'] ?></td>
                        <td class="c"><?= (int) $r['izin'] ?></td>
                        <td class="c"><?= (int) $r['sakit'] ?></td>
                        <td class="c"><?= (int) $r['alpa'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td colspan="4">TOTAL</td>
                    <td class="c"><?= $sum['total'] ?></td>
                    <td class="c"><?= $sum['hadir'] ?></td>
                    <td class="c"><?= $sum['telat'] ?></td>
                    <td class="c"><?= $sum['izin'] ?></td>
                    <td class="c"><?= $sum['sakit'] ?></td>
                    <td class="c"><?= $sum['alpa'] ?></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="foot">Dicetak <?= date('d-m-Y H:i') ?></p>
</body>

</html>