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
                <th style="width:14%">Nama Lengkap</th>
                <th style="width:11%">NIP/NUPTK</th>
                <th style="width:9%">Mapel</th>
                <th style="width:5%">Status</th>
                <th style="width:9%">No. HP</th>
                <th style="width:21%">Mata Pelajaran Diampu</th>
                <th style="width:4%">Jam</th>
                <th style="width:14%">Tugas Tambahan</th>
                <th style="width:10%">Hari Bersedia</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="ctr">Belum ada data.</td></tr>
            <?php else: foreach ($rows as $i => $d):
                $mapel = implode('; ', array_map(fn ($m) => "{$m['mapel']} ({$m['kelas']}: {$m['jam']}j)", $d['mapel_diampu'] ?: []));
                $tugas = implode(', ', $d['tugas_tambahan'] ?: []);
                if (! empty($d['tugas_lainnya'])) $tugas .= ($tugas ? ', ' : '') . $d['tugas_lainnya'];
                $hari  = implode(', ', array_map(fn ($h) => substr($h, 0, 3), array_keys(array_filter($d['ketersediaan_hari'] ?: [], fn ($v) => $v === 'Ya'))));
            ?>
                <tr>
                    <td class="ctr"><?= $i+1 ?></td>
                    <td><?= esc($d['nama_lengkap']) ?></td>
                    <td><?= esc($d['nip_nuptk']) ?></td>
                    <td><?= esc($d['guru_mapel']) ?></td>
                    <td class="ctr"><?= esc($d['status_kepegawaian']) ?></td>
                    <td><?= esc($d['nomor_hp']) ?></td>
                    <td><?= esc($mapel ?: '-') ?></td>
                    <td class="ctr"><?= $d['total_jam'] ?></td>
                    <td><?= esc($tugas ?: '-') ?></td>
                    <td><?= esc($hari ?: '-') ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="foot">Dicetak: <?= date('d F Y, H:i') ?> WIB</div>
</body>
</html>
