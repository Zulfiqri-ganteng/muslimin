<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { margin: 0; color: #1e293b; font-size: 10px; }
    .head { text-align: center; margin-bottom: 10px; }
    .head h1 { margin: 0; font-size: 14px; }
    .head p { margin: 2px 0; font-size: 10px; color: #475569; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 0.7px solid #94a3b8; padding: 4px 5px; text-align: center; vertical-align: middle; }
    th { background: #1A3A6B; color: #fff; }
    td.jam { text-align: left; white-space: nowrap; background: #f1f5f9; font-weight: bold; }
    tr.istirahat td { background: #fff3cd; color: #92400e; font-weight: bold; letter-spacing: 1px; }
    .mapel { font-weight: bold; color: #1A3A6B; }
    .sub { color: #64748b; font-size: 9px; }
    .foot { margin-top: 10px; font-size: 9px; color: #94a3b8; text-align: right; }
</style>
</head>
<body>
    <div class="head">
        <h1><?= esc($title) ?></h1>
        <p><?= esc(strtoupper($setting['school_name'])) ?> &mdash; Tahun Pelajaran <?= esc($setting['academic_year'] ?? '') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:90px">Jam</th>
                <?php foreach ($hari as $h): ?><th><?= esc($h['nama']) ?></th><?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jam as $j): ?>
                <?php if (! empty($j['is_istirahat'])): ?>
                    <tr class="istirahat"><td colspan="<?= count($hari) + 1 ?>">ISTIRAHAT (<?= esc(substr($j['waktu_mulai'], 0, 5)) ?>&ndash;<?= esc(substr($j['waktu_selesai'], 0, 5)) ?>)</td></tr>
                <?php else: ?>
                    <tr>
                        <td class="jam">
                            <?= ($mode === 'guru' ? ucfirst($j['shift']) . ' ' : '') ?>Jam <?= esc($j['jam_ke']) ?><br>
                            <span class="sub"><?= esc(substr($j['waktu_mulai'], 0, 5)) ?>&ndash;<?= esc(substr($j['waktu_selesai'], 0, 5)) ?></span>
                        </td>
                        <?php foreach ($hari as $h): $c = $grid[$h['id'] . '-' . $j['id']] ?? null; ?>
                            <td>
                                <?php if ($c): ?>
                                    <?php if ($mode === 'kelas'): ?>
                                        <span class="mapel"><?= esc($c['nama_mapel']) ?></span><br><span class="sub"><?= esc($c['guru_nama']) ?></span>
                                    <?php else: ?>
                                        <span class="mapel"><?= esc($c['nama_kelas']) ?></span><br><span class="sub"><?= esc($c['nama_mapel']) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="foot">Dicetak <?= date('d-m-Y H:i') ?></p>
</body>
</html>
