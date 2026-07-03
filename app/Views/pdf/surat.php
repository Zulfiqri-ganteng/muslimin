<?php
    $hariList = ['Senin','Selasa','Rabu','Kamis','Jumat'];
    // Logo di-embed sebagai data URI (base64) agar pasti tampil di Dompdf
    $logoData = null;
    if (! empty($setting['logo'])) {
        $lp = FCPATH . 'uploads/' . $setting['logo'];
        if (is_file($lp)) {
            $mime = function_exists('mime_content_type') ? (mime_content_type($lp) ?: 'image/png') : 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode((string) @file_get_contents($lp));
        }
    }
    // Lokasi: hindari "BEKASI, Bekasi" (kota sudah ada di alamat)
    $alamat = trim((string) ($setting['address'] ?? ''));
    $kota   = trim((string) ($setting['city'] ?? ''));
    $lokasi = ($kota && stripos($alamat, $kota) === false) ? ($alamat ? $alamat . ', ' . $kota : $kota) : $alamat;
    $bersedia = ! empty($row['bersedia_mengajar']);
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
    .kop .logo { width: 92px; text-align: center; vertical-align: middle; }
    .kop .logo img { max-width: 84px; max-height: 84px; }
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
    ul.list { margin: 3px 0 3px 16px; padding: 0; }
    .ttd { width: 100%; margin-top: 24px; }
    .ttd td { vertical-align: top; text-align: center; width: 50%; font-size: 10.5px; }
    .ttd .space { height: 64px; }
    .materai { border: 1px dashed #999; padding: 6px 4px; font-size: 8px; color: #888; display: inline-block; margin: 4px 0; }
    .muted { color: #777; }
    .warn { border: 1px solid #c0392b; background: #fdecea; color: #922; padding: 6px 10px; margin: 8px 0; }
</style>
</head>
<body>

    <?= kop_pdf() ?>

    <div class="title">
        <h3>Surat Pernyataan Kesediaan Guru Mengajar</h3>
        <p>Tahun Pelajaran <?= esc($setting['academic_year']) ?></p>
    </div>

    <p class="intro">Yang bertanda tangan di bawah ini:</p>

    <table class="data">
        <tr><td class="lbl">Nama Lengkap</td><td class="sep">:</td><td><b><?= esc($row['nama_lengkap']) ?></b></td></tr>
        <tr><td class="lbl">Pendidikan Terakhir</td><td class="sep">:</td><td><?= esc($row['pendidikan_terakhir'] ?: '-') ?></td></tr>
        <tr><td class="lbl">Guru Mapel</td><td class="sep">:</td><td><?= esc($row['guru_mapel'] ?: '-') ?></td></tr>
        <tr><td class="lbl">Status Kepegawaian</td><td class="sep">:</td><td><?= esc($row['status_kepegawaian'] ?: '-') ?></td></tr>
        <tr><td class="lbl">Nomor HP</td><td class="sep">:</td><td><?= esc($row['nomor_hp'] ?: '-') ?></td></tr>
        <tr><td class="lbl">Kesediaan Mengajar</td><td class="sep">:</td><td><b><?= $bersedia ? 'BERSEDIA' : 'TIDAK BERSEDIA' ?></b></td></tr>
    </table>

    <?php if (! $bersedia): ?>
        <div class="warn">Guru yang bersangkutan menyatakan <b>TIDAK BERSEDIA</b> mengisi/melaksanakan tugas mengajar. Mohon dikonfirmasi kepada pihak sekolah.</div>
    <?php else: ?>
        <p class="intro">menyatakan <b>bersedia</b> melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan dari Kepala Sekolah, dengan rincian:</p>

        <div class="sec-title">A. Mata Pelajaran yang Diampu</div>
        <table class="grid">
            <thead><tr><th style="width:8%">No</th><th>Mata Pelajaran</th><th style="width:28%">Kelas</th></tr></thead>
            <tbody>
                <?php if (! empty($row['mapel_diampu'])): foreach ($row['mapel_diampu'] as $i => $m): ?>
                    <tr><td style="text-align:center"><?= $i+1 ?></td><td><?= esc($m['mapel']) ?></td><td style="text-align:center"><?= esc($m['kelas']) ?></td></tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3" style="text-align:center" class="muted">—</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="sec-title">B. Kesediaan Tugas Tambahan</div>
        <p style="margin:2px 0"><?= ! empty($row['tugas_tambahan']) ? '✓ Bersedia menerima tugas tambahan sesuai kebutuhan sekolah.' : '<span class="muted">Tidak menyatakan bersedia menerima tugas tambahan.</span>' ?></p>

        <div class="sec-title">C. Kesediaan Jam Mengajar</div>
        <?php if (! empty($row['kesediaan_jam'])): ?>
            <ul class="list"><?php foreach ($row['kesediaan_jam'] as $j): ?><li><?= esc($j) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p class="muted" style="margin:2px 0">—</p><?php endif; ?>

        <div class="sec-title">D. Ketersediaan Hari Mengajar</div>
        <table class="grid">
            <tr><?php foreach ($hariList as $h): ?><th><?= $h ?></th><?php endforeach; ?></tr>
            <tr><?php foreach ($hariList as $h): $s = $row['ketersediaan_hari'][$h] ?? []; ?>
                <td style="text-align:center"><?= $s ? esc(implode(' & ', (array) $s)) : '-' ?></td>
            <?php endforeach; ?></tr>
        </table>

        <div class="sec-title">E. Komitmen Pelaksanaan Tugas</div>
        <p style="margin:2px 0">Saya berkomitmen untuk:</p>
        <ol class="komit"><?php foreach ($komitmen as $k): ?><li><?= esc($k) ?></li><?php endforeach; ?></ol>

        <?php if (! empty($row['keterangan_tambahan'])): ?>
            <div class="sec-title">F. Keterangan Tambahan</div>
            <p style="text-align:justify"><?= nl2br(esc($row['keterangan_tambahan'])) ?></p>
        <?php endif; ?>

        <p class="intro" style="margin-top:10px">Demikian surat pernyataan ini saya buat dengan sebenarnya untuk digunakan sebagaimana mestinya. Apabila di kemudian hari saya tidak melaksanakan tugas sesuai komitmen yang telah disepakati, saya bersedia menerima pembinaan dan ketentuan yang berlaku di sekolah.</p>
    <?php endif; ?>

    <table class="ttd">
        <tr>
            <td>Mengetahui,<br>Kepala Sekolah</td>
            <td><?= esc($setting['city']) ?>, <?= date('d F Y', strtotime($row['created_at'])) ?><br>Guru yang Menyatakan</td>
        </tr>
        <tr>
            <td><div class="space"></div></td>
            <td><?php if ($bersedia): ?><div class="materai">Materai<br>Rp10.000</div><?php else: ?><div class="space"></div><?php endif; ?></td>
        </tr>
        <tr>
            <td><b><u><?= esc($setting['headmaster_name'] ?: '..............................') ?></u></b><br>NIP. <?= esc($setting['headmaster_nip'] ?: '..............................') ?></td>
            <td><b><u><?= esc($row['nama_lengkap']) ?></u></b></td>
        </tr>
    </table>

</body>
</html>
