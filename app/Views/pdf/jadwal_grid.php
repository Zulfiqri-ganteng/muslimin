<?php

/**
 * Grid jadwal untuk PDF (mode 'kelas' = satu shift; mode 'guru' = per shift per halaman).
 *
 * @var string $title   Judul dokumen (mis. "JADWAL PELAJARAN")
 * @var string $label   Nama kelas (mode kelas) → jadi kolom vertikal di kiri tabel
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

        .head {
            text-align: center;
            margin-bottom: 10px;
        }

        .head p {
            margin: 2px 0;
            font-size: 10px;
            color: #475569;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 0.7px solid #94a3b8;
            padding: 4px 5px;
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

        /* Kolom kelas vertikal (kiri, memanjang ke bawah, tanpa judul) */
        td.kelascol {
            background: #eef2ff;
            color: #1A3A6B;
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 1px;
            width: 26px;
            padding: 0;
        }

        td.kelascol div {
            line-height: 1.05;
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
    // Mode kelas: satu tabel (satu shift), kelas ditaruh sebagai kolom vertikal.
    if ($mode === 'guru') {
        $sections = [];
        foreach ($jam as $j) {
            $sections[$j['shift']][] = $j;
        }
    } else {
        $sections = ['' => $jam];
    }
    $first = true;
    // Susun teks kelas menjadi tumpukan vertikal (per karakter) — aman di Dompdf.
    $kelasVert = '';
    if ($mode === 'kelas' && $label !== '') {
        foreach (preg_split('//u', $label, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $kelasVert .= ($ch === ' ')
                ? '<div style="height:6px"></div>'
                : '<div>' . esc($ch) . '</div>';
        }
    }
    ?>
    <?php foreach ($sections as $shift => $jamSec): ?>
        <div <?= $first ? '' : 'style="page-break-before: always;"' ?>>
            <div class="doctitle"><?= esc($title) ?><?php if ($mode === 'guru' && $shift !== ''): ?> (SHIFT <?= esc(strtoupper($shift)) ?>)<?php endif; ?></div>
            <?= kop_pdf() ?>

            <table>
                <tr>
                    <?php if ($mode === 'kelas' && $label !== ''): ?>
                        <td class="kelascol" rowspan="<?= count($jamSec) + 1 ?>"><?= $kelasVert ?></td>
                    <?php endif; ?>
                    <th style="width:88px">Jam</th>
                    <?php foreach ($hari as $h): ?><th><?= esc($h['nama']) ?></th><?php endforeach; ?>
                </tr>
                <?php foreach ($jamSec as $j): ?>
                    <?php if (! empty($j['is_istirahat'])): ?>
                        <tr class="istirahat">
                            <td colspan="<?= count($hari) + 1 ?>">ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>&ndash;<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="jam">
                                Jam <?= esc($j['jam_ke']) ?><br>
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
            </table>
        </div>
        <?php $first = false; ?>
    <?php endforeach; ?>

    <p class="foot">Dicetak <?= date('d-m-Y H:i') ?></p>
</body>

</html>
