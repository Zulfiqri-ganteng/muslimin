<?php

/**
 * Kepala surat (KOP) resmi sekolah untuk semua PDF — dipakai lewat
 * helper kop_pdf(). Style inline agar aman di Dompdf dan tidak
 * terpengaruh CSS masing-masing dokumen.
 *
 * @var array $setting Pengaturan sekolah
 */
$logo   = kop_logo_base64($setting);
$lokasi = kop_lokasi($setting);
$kontak = kop_kontak($setting);
// Jenjang + nama digabung jadi SATU baris besar: "SMK BINA NUSA (BINUS)".
$level  = trim((string) ($setting['school_level'] ?? ''));
$nama   = trim((string) ($setting['school_name'] ?? ''));
$namaLengkap = trim($level . ' ' . $nama);
$tahun  = trim((string) ($setting['academic_year'] ?? ''));
?>
<div style="border-bottom: 3px double #1a3a6b; padding-bottom: 6px; margin-bottom: 12px;">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:110px; text-align:center; vertical-align:middle; border:0; padding:0; background:none;">
                <?php if ($logo): ?><img src="<?= $logo ?>" style="max-width:96px; max-height:96px;"><?php endif; ?>
            </td>
            <td style="text-align:center; vertical-align:middle; border:0; padding:0; background:none;">
                <div style="font-size:21px; font-weight:bold; color:#1a3a6b; letter-spacing:.5px;"><?= esc(strtoupper($namaLengkap)) ?></div>
                <?php if ($lokasi !== ''): ?>
                    <div style="font-size:9.5px; color:#475569; margin-top:3px;"><?= esc($lokasi) ?></div>
                <?php endif; ?>
                <?php if ($kontak !== ''): ?>
                    <div style="font-size:9.5px; color:#475569;"><?= esc($kontak) ?></div>
                <?php endif; ?>

            </td>
            <td style="width:110px; border:0; padding:0; background:none;"></td>
        </tr>
    </table>
</div>