<?php

/**
 * Grid jadwal untuk PDF (mode 'kelas' = satu shift; mode 'guru' = per shift per halaman).
 *
 * @var string $title   Judul dokumen (mis. "JADWAL PELAJARAN")
 * @var string $label   Nama kelas (mode kelas) → ditaruh di pojok kiri atas tabel
 * @var array  $setting Pengaturan sekolah
 * @var array  $hari    Hari aktif terurut
 * @var array  $jam     Slot jam (termasuk istirahat)
 * @var array  $grid    Peta sel (kunci "hariId-jamId")
 * @var string $mode    'kelas' | 'guru'
 */
$label = $label ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        /* Margin halaman lebih rapat dari default Dompdf (0.75in) agar lebih
           banyak jam muat dalam satu halaman. Bila tetap melebihi satu halaman,
           tabel lanjut normal ke halaman berikutnya (tanpa kolom yang pecah). */
        @page {
            margin: 1cm;
        }

        * {
            font-family: "DejaVu Sans", sans-serif;
        }

        body {
            margin: 0;
            color: #1e293b;
            font-size: 10px;
        }

        .doctitle {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1A3A6B;
            margin-bottom: 4px;
        }

        /* Nama kelas di pojok kiri atas, tepat sebelum tabel */
        .classbar {
            text-align: left;
            font-size: 13px;
            font-weight: bold;
            color: #1A3A6B;
            margin: 0 0 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 0.7px solid #94a3b8;
            padding: 2px 4px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background: #1A3A6B;
            color: #fff;
        }

        td.jam {
            text-align: left;
            white-space: nowrap;
            background: #f1f5f9;
            font-weight: bold;
        }

        tr.istirahat td {
            background: #fff3cd;
            color: #92400e;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .mapel {
            font-weight: bold;
            color: #1A3A6B;
            font-size: 10px;
        }

        .cell2 {
            color: #475569;
            font-size: 10px;
        }

        .sub {
            color: #64748b;
            font-size: 9px;
        }

        .foot {
            margin-top: 10px;
            font-size: 9px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>

<body>
    <?php
    // Mode guru: pisah per shift → tiap shift satu tabel di HALAMAN sendiri (page-break).
    // Mode kelas: satu tabel (satu shift).
    if ($mode === 'guru') {
        $sections = [];
        foreach ($jam as $j) {
            $sections[$j['shift']][] = $j;
        }
    } else {
        $sections = ['' => $jam];
    }
    $first = true;
    ?>
    <?php foreach ($sections as $shift => $jamSec): ?>
        <div <?= $first ? '' : 'style="page-break-before: always;"' ?>>
            <div class="doctitle"><?= esc($title) ?><?php if ($mode === 'guru' && $shift !== ''): ?> (SHIFT <?= esc(strtoupper($shift)) ?>)<?php endif; ?></div>
            <?= kop_pdf() ?>

            <?php if ($mode === 'kelas' && $label !== ''): ?>
                <div class="classbar">KELAS: <?= esc($label) ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th style="width:88px">Jam</th>
                        <?php foreach ($hari as $h): ?><th><?= esc($h['nama']) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jamSec as $j): ?>
                        <?php if (! empty($j['is_istirahat'])): ?>
                            <tr class="istirahat">
                                <td colspan="<?= count($hari) + 1 ?>">ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>&ndash;<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="jam">
                                    Jam ke <?= esc($j['jam_ke']) ?><br>
                                    <span class="sub"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>&ndash;<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                                </td>
                                <?php foreach ($hari as $h): $c = $grid[$h['id'] . '-' . $j['id']] ?? null; ?>
                                    <td>
                                        <?php if ($c): ?>
                                            <?php if ($mode === 'kelas'): ?>
                                                <span class="mapel"><?= esc($c['nama_mapel']) ?></span><br><span class="cell2"><?= esc($c['guru_nama']) ?></span>
                                            <?php else: ?>
                                                <span class="cell2"><?= esc($c['nama_kelas']) ?></span><br><span class="mapel"><?= esc($c['nama_mapel']) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $first = false; ?>
    <?php endforeach; ?>

    <p class="foot">Dicetak <?= date('d-m-Y H:i') ?></p>
</body>

</html>
