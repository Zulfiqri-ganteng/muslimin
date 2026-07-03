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
$level  = trim((string) ($setting['school_level'] ?? ''));
?>
<div style="border-bottom: 3px double #1a3a6b; padding-bottom: 6px; margin-bottom: 10px;">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:76px; text-align:center; vertical-align:middle; border:0; padding:0; background:none;">
                <?php if ($logo): ?><img src="<?= $logo ?>" style="max-width:66px; max-height:66px;"><?php endif; ?>
            </td>
            <td style="text-align:center; vertical-align:middle; border:0; padding:0; background:none;">
                <?php if ($level !== ''): ?>
                    <div style="font-size:10px; letter-spacing:1px; color:#334155;"><?= esc(strtoupper($level)) ?></div>
                <?php endif; ?>
                <div style="font-size:17px; font-weight:bold; color:#1a3a6b; letter-spacing:.5px;"><?= esc(strtoupper((string) ($setting['school_name'] ?? ''))) ?></div>
                <?php if ($lokasi !== ''): ?>
                    <div style="font-size:9px; color:#475569; margin-top:2px;"><?= esc($lokasi) ?></div>
                <?php endif; ?>
                <?php if ($kontak !== ''): ?>
                    <div style="font-size:9px; color:#475569;"><?= esc($kontak) ?></div>
                <?php endif; ?>
            </td>
            <td style="width:76px; border:0; padding:0; background:none;"></td>
        </tr>
    </table>
</div>
