<?php

/**
 * Rekap beban mengajar (PDF).
 *
 * @var array $setting Pengaturan sekolah
 * @var array $grouped Data beban per guru
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
            margin-bottom: 12px;
        }

        th,
        td {
            border: 0.7px solid #94a3b8;
            padding: 4px 6px;
        }

        th {
            background: #1A3A6B;
            color: #fff;
            text-align: left;
        }

        .gh {
            background: #eef2ff;
            font-weight: bold;
        }

        .total td {
            font-weight: bold;
            background: #f1f5f9;
        }

        .right {
            text-align: right;
        }

        .badge {
            font-size: 9px;
            padding: 1px 5px;
            border-radius: 8px;
        }

        .foot {
            margin-top: 6px;
            font-size: 9px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>

<body>
    <?= kop_pdf() ?>
    <div class="head">
        <h1>REKAP BEBAN MENGAJAR</h1>
    </div>

    <?php if (empty($grouped)): ?>
        <p style="text-align:center;color:#94a3b8">Belum ada data penugasan.</p>
        <?php else: foreach ($grouped as $g):
            $total = (int) $g['total'];
            $max = (int) $g['max_beban'];
            $stat = $total > $max ? 'Lebih' : ($total < $max ? 'Kurang' : 'Pas');
            $bg   = $total > $max ? '#ffedd5' : ($total < $max ? '#fef3c7' : '#d1fae5');
        ?>
            <table>
                <tr class="gh">
                    <td colspan="3"><?= esc($g['kode_guru']) ?> &middot; <?= esc($g['guru_nama']) ?></td>
                    <td class="right">Total <?= $total ?> / <?= $max ?> JP
                        <span class="badge" style="background:<?= $bg ?>"><?= $stat ?></span>
                    </td>
                </tr>
                <tr>
                    <th style="width:45%">Mata Pelajaran</th>
                    <th style="width:35%">Kelas</th>
                    <th colspan="2" class="right" style="text-align:right">JP</th>
                </tr>
                <?php foreach ($g['items'] as $it): ?>
                    <tr>
                        <td><?= esc($it['mapel']) ?></td>
                        <td><?= esc($it['kelas']) ?></td>
                        <td colspan="2" class="right"><?= (int) $it['jp'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td colspan="2">TOTAL</td>
                    <td colspan="2" class="right"><?= $total ?> JP</td>
                </tr>
            </table>
    <?php endforeach;
    endif; ?>

    <p class="foot">Dicetak <?= date('d-m-Y H:i') ?></p>
</body>

</html>