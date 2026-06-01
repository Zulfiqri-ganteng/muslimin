<?php
    $hariList = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $logoPath = (! empty($setting['logo']) && is_file(FCPATH . 'uploads/' . $setting['logo']))
        ? FCPATH . 'uploads/' . $setting['logo'] : null;
    $ttl = $row['tempat_lahir'];
    if ($row['tanggal_lahir']) $ttl .= ($ttl ? ', ' : '') . date('d F Y', strtotime($row['tanggal_lahir']));
    $komitmen = [
        'Melaksanakan proses pembelajaran sesuai ketentuan yang berlaku.',
        'Menyusun perangkat ajar secara lengkap dan tepat waktu.',
        'Melaksanakan asesmen dan evaluasi pembelajaran secara objektif.',
        'Menjaga disiplin, etika profesi, dan nama baik sekolah.',
        'Mendukung seluruh program sekolah.',
        'Mengikuti rapat, pelatihan, workshop, dan kegiatan sekolah yang ditugaskan.',
        'Melaksanakan tugas tambahan dengan penuh tanggung jawab.',
        'Mencapai target kinerja yang ditetapkan sekolah.',
    ];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: 'DejaVu Sans', sans-serif; }
    @page { margin: 1.6cm 1.8cm; }
    body { font-size: 11px; color: #1a1a1a; line-height: 1.45; }
    .kop { border-bottom: 3px double #1a3a6b; padding-bottom: 8px; margin-bottom: 4px; }
    .kop table { width: 100%; }
    .kop .logo { width: 70px; text-align: center; vertical-align: middle; }
    .kop .logo img { max-width: 64px; max-height: 64px; }
    .kop .name { text-align: center; vertical-align: middle; }
    .kop .name h1 { margin: 0; font-size: 16px; color: #1a3a6b; letter-spacing: .5px; }
    .kop .name h2 { margin: 0; font-size: 18px; color: #1a3a6b; }
    .kop .name p { margin: 2px 0 0; font-size: 9.5px; color: #333; }
    .title { text-align: center; margin: 14px 0 2px; }
    .title h3 { margin: 0; font-size: 13px; text-decoration: underline; text-transform: uppercase; }
    .title p { margin: 1px 0 0; font-size: 10.5px; }
    .intro { margin: 10px 0; text-align: justify; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data td { padding: 1.5px 0; vertical-align: top; }
    .lbl { width: 38%; }
    .sep { width: 3%; }
    table.grid { width: 100%; border-collapse: collapse; margin: 5px 0; }
    table.grid th, table.grid td { border: 1px solid #555; padding: 4px 6px; font-size: 10px; }
    table.grid th { background: #e8eef7; text-align: center; }
    .sec-title { font-weight: bold; margin: 10px 0 3px; color: #1a3a6b; }
    ol.komit { margin: 3px 0 3px 16px; padding: 0; }
    ol.komit li { margin-bottom: 1px; text-align: justify; }
    .ttd { width: 100%; margin-top: 24px; }
    .ttd td { vertical-align: top; text-align: center; width: 50%; font-size: 10.5px; }
    .ttd .space { height: 64px; }
    .materai { border: 1px dashed #999; padding: 6px 4px; font-size: 8px; color: #888; display: inline-block; margin: 4px 0; }
    .tag { display: inline-block; border: 1px solid #1a3a6b; border-radius: 3px; padding: 1px 6px; margin: 1px; font-size: 9.5px; }
    .muted { color: #777; }
</style>
</head>
<body>

    <!-- KOP SURAT -->
    <div class="kop">
        <table>
            <tr>
                <?php if ($logoPath): ?><td class="logo"><img src="<?= $logoPath ?>"></td><?php endif; ?>
                <td class="name">
                    <?php if (! empty($setting['school_level'])): ?><h1>PEMERINTAH / YAYASAN — <?= esc(strtoupper($setting['school_level'])) ?></h1><?php endif; ?>
                    <h2><?= esc(strtoupper($setting['school_name'])) ?></h2>
                    <p>
                        <?= esc($setting['address']) ?><?= $setting['city'] ? ', ' . esc($setting['city']) : '' ?>
                        <?php if ($setting['phone'] || $setting['email']): ?><br>
                            <?= $setting['phone'] ? 'Telp: ' . esc($setting['phone']) : '' ?>
                            <?= $setting['email'] ? ' &middot; Email: ' . esc($setting['email']) : '' ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- JUDUL -->
    <div class="title">
        <h3>Surat Pernyataan Kesediaan Guru Mengajar</h3>
        <p>Tahun Pelajaran <?= esc($setting['academic_year']) ?></p>
    </div>

    <p class="intro">Yang bertanda tangan di bawah ini menyatakan kesediaan untuk melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan dari Kepala Sekolah:</p>

    <!-- IDENTITAS -->
    <table class="data">
        <tr><td class="lbl">Nama Lengkap</td><td class="sep">:</td><td><b><?= esc($row['nama_lengkap']) ?></b></td></tr>
        <tr><td class="lbl">NIP / NUPTK</td><td class="sep">:</td><td><?= esc($row['nip_nuptk']) ?></td></tr>
        <tr><td class="lbl">Tempat, Tanggal Lahir</td><td class="sep">:</td><td><?= esc($ttl ?: '-') ?></td></tr>
        <tr><td class="lbl">Pendidikan Terakhir</td><td class="sep">:</td><td><?= esc($row['pendidikan_terakhir'] ?: '-') ?></td></tr>
        <tr><td class="lbl">Jabatan</td><td class="sep">:</td><td>Guru Mata Pelajaran <?= esc($row['guru_mapel'] ?: '-') ?></td></tr>
        <tr><td class="lbl">Status Kepegawaian</td><td class="sep">:</td><td><?= esc($row['status_kepegawaian']) ?></td></tr>
        <tr><td class="lbl">Nomor HP</td><td class="sep">:</td><td><?= esc($row['nomor_hp']) ?></td></tr>
    </table>

    <!-- MATA PELAJARAN -->
    <div class="sec-title">A. Mata Pelajaran yang Diampu</div>
    <table class="grid">
        <thead><tr><th style="width:8%">No</th><th>Mata Pelajaran</th><th style="width:20%">Kelas</th><th style="width:18%">Jam/Minggu</th></tr></thead>
        <tbody>
            <?php if (! empty($row['mapel_diampu'])): foreach ($row['mapel_diampu'] as $i => $m): ?>
                <tr><td style="text-align:center"><?= $i+1 ?></td><td><?= esc($m['mapel']) ?></td><td style="text-align:center"><?= esc($m['kelas']) ?></td><td style="text-align:center"><?= esc($m['jam']) ?></td></tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" style="text-align:center" class="muted">—</td></tr>
            <?php endif; ?>
            <tr><td colspan="3" style="text-align:right"><b>TOTAL JAM / MINGGU</b></td><td style="text-align:center"><b><?= $row['total_jam'] ?></b></td></tr>
        </tbody>
    </table>

    <!-- TUGAS TAMBAHAN -->
    <div class="sec-title">B. Kesediaan Tugas Tambahan</div>
    <div>
        <?php if (! empty($row['tugas_tambahan'])): foreach ($row['tugas_tambahan'] as $t): ?>
            <span class="tag"><?= esc($t) ?></span>
        <?php endforeach; endif; ?>
        <?php if (! empty($row['tugas_lainnya'])): ?><span class="tag"><?= esc($row['tugas_lainnya']) ?></span><?php endif; ?>
        <?php if (empty($row['tugas_tambahan']) && empty($row['tugas_lainnya'])): ?><span class="muted">Tidak ada tugas tambahan dipilih.</span><?php endif; ?>
    </div>

    <!-- LAMPIRAN -->
    <table style="width:100%; margin-top:8px"><tr>
        <td style="width:50%; vertical-align:top">
            <div class="sec-title">C. Preferensi Mata Pelajaran</div>
            <?php if (! empty($row['preferensi'])): ?>
                <ol class="komit"><?php foreach ($row['preferensi'] as $p): ?><li><?= esc($p['mapel']) ?></li><?php endforeach; ?></ol>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td style="width:50%; vertical-align:top">
            <div class="sec-title">D. Ketersediaan Hari Mengajar</div>
            <table class="grid"><tr>
                <?php foreach ($hariList as $h): ?><th><?= substr($h,0,3) ?></th><?php endforeach; ?>
            </tr><tr>
                <?php foreach ($hariList as $h): $v = $row['ketersediaan_hari'][$h] ?? '-'; ?>
                    <td style="text-align:center"><?= esc($v === 'Ya' ? '✓' : ($v === 'Tidak' ? '✗' : '-')) ?></td>
                <?php endforeach; ?>
            </tr></table>
        </td>
    </tr></table>

    <!-- KOMITMEN -->
    <div class="sec-title">E. Komitmen Pelaksanaan Tugas</div>
    <p style="margin:2px 0">Saya berkomitmen untuk:</p>
    <ol class="komit"><?php foreach ($komitmen as $k): ?><li><?= esc($k) ?></li><?php endforeach; ?></ol>

    <?php if (! empty($row['keterangan_tambahan'])): ?>
        <div class="sec-title">F. Keterangan Tambahan</div>
        <p style="text-align:justify"><?= nl2br(esc($row['keterangan_tambahan'])) ?></p>
    <?php endif; ?>

    <p class="intro" style="margin-top:10px">Demikian surat pernyataan ini saya buat dengan sebenarnya untuk digunakan sebagaimana mestinya. Apabila di kemudian hari saya tidak melaksanakan tugas sesuai komitmen yang telah disepakati, saya bersedia menerima pembinaan dan ketentuan yang berlaku di sekolah.</p>

    <!-- TANDA TANGAN -->
    <table class="ttd">
        <tr>
            <td>Mengetahui,<br>Kepala Sekolah</td>
            <td><?= esc($setting['city']) ?>, <?= date('d F Y', strtotime($row['created_at'])) ?><br>Guru yang Menyatakan</td>
        </tr>
        <tr>
            <td><div class="space"></div></td>
            <td><div class="materai">Materai<br>Rp10.000</div></td>
        </tr>
        <tr>
            <td><b><u><?= esc($setting['headmaster_name'] ?: '..............................') ?></u></b><br>NIP. <?= esc($setting['headmaster_nip'] ?: '..............................') ?></td>
            <td><b><u><?= esc($row['nama_lengkap']) ?></u></b><br>NIP. <?= esc($row['nip_nuptk']) ?></td>
        </tr>
    </table>

</body>
</html>
