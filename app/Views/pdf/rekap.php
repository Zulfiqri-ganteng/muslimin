<?php
    $logoPath = (! empty($setting['logo']) && is_file(FCPATH . 'uploads/' . $setting['logo']))
        ? FCPATH . 'uploads/' . $setting['logo'] : null;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: 'DejaVu Sans', sans-serif; }
    @page { margin: 1cm 1.2cm; }
    body { font-size: 9px; color: #1a1a1a; }
    .kop { border-bottom: 2px solid #1a3a6b; padding-bottom: 6px; margin-bottom: 8px; }
    .kop table { width: 100%; }
    .kop .logo { width: 50px; text-align: center; }
    .kop .logo img { max-width: 46px; max-height: 46px; }
    .kop h2 { margin: 0; font-size: 14px; color: #1a3a6b; }
    .kop p { margin: 1px 0 0; font-size: 8px; color: #444; }
    .title { text-align: center; margin: 4px 0 8px; }
    .title h3 { margin: 0; font-size: 12px; text-transform: uppercase; }
    .title p { margin: 1px 0 0; font-size: 9px; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #666; padding: 3px 4px; vertical-align: top; }
    table.grid th { background: #1a3a6b; color: #fff; text-align: center; font-size: 8.5px; }
    .ctr { text-align: center; }
    .foot { margin-top: 12px; font-size: 8px; text-align: right; color: #555; }
</style>
</head>
<body>
    <div class="kop">
        <table><tr>
            <?php if ($logoPath): ?><td class="logo"><img src="<?= $logoPath ?>"></td><?php endif; ?>
            <td style="text-align:center">
                <h2><?= esc(strtoupper($setting['school_name'])) ?></h2>
                <p><?= esc($setting['address']) ?><?= $setting['city'] ? ', ' . esc($setting['city']) : '' ?></p>
            </td>
        </tr></table>
    </div>

    <div class="title">
        <h3>Rekapitulasi Kesediaan Guru Mengajar</h3>
        <p>Tahun Pelajaran <?= esc($setting['academic_year']) ?><?= $status ? ' &middot; Status: ' . esc($status) : '' ?> &middot; Total: <?= count($rows) ?> guru</p>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:16%">Nama Lengkap</th>
                <th style="width:10%">Guru Mapel</th>
                <th style="width:5%">Status</th>
                <th style="width:10%">No. HP</th>
                <th style="width:24%">Mata Pelajaran Diampu</th>
                <th style="width:7%">Tugas Tmbh.</th>
                <th style="width:15%">Hari (Pagi/Siang)</th>
                <th style="width:10%">Kesediaan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="ctr">Belum ada data.</td></tr>
            <?php else: foreach ($rows as $i => $d):
                $mapel = implode('; ', array_map(fn ($m) => $m['mapel'] . ($m['kelas'] ? " ({$m['kelas']})" : ''), $d['mapel_diampu'] ?: []));
                $tugas = ! empty($d['tugas_tambahan']) ? 'Bersedia' : '-';
                $hari  = [];
                foreach (($d['ketersediaan_hari'] ?: []) as $h => $s) {
                    if ($s) $hari[] = substr($h, 0, 3) . ': ' . implode('/', (array) $s);
                }
                $hariStr = $hari ? implode(', ', $hari) : '-';
            ?>
                <tr>
                    <td class="ctr"><?= $i+1 ?></td>
                    <td><?= esc($d['nama_lengkap']) ?></td>
                    <td><?= esc($d['guru_mapel'] ?: '-') ?></td>
                    <td class="ctr"><?= esc($d['status_kepegawaian'] ?: '-') ?></td>
                    <td><?= esc($d['nomor_hp'] ?: '-') ?></td>
                    <td><?= esc($mapel ?: '-') ?></td>
                    <td class="ctr"><?= $tugas ?></td>
                    <td><?= esc($hariStr) ?></td>
                    <td class="ctr"><?= empty($d['bersedia_mengajar']) ? 'TIDAK' : 'Bersedia' ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="foot">Dicetak: <?= date('d F Y, H:i') ?> WIB</div>
</body>
</html>
