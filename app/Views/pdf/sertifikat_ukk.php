<?php
/**
 * Sertifikat Kelulusan UKK — versi cetak (Dompdf, A4 landscape).
 *
 * @var array $srt     sertifikat_ukk::withRelations() satu baris
 * @var array $setting Pengaturan sekolah (SettingModel::get())
 */
$tgl = static fn ($d) => $d ? date('d F Y', strtotime((string) $d)) : '—';
$logo = kop_logo_base64($setting);
?>
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { color: #1f2937; }
    .frame { border: 3px double #1A3A6B; padding: 28px 40px; text-align: center; }
    .logo { height: 60px; margin-bottom: 6px; }
    .school { font-size: 16px; font-weight: bold; color: #1A3A6B; text-transform: uppercase; letter-spacing: 0.5px; }
    .addr { font-size: 10px; color: #64748b; margin-top: 2px; }
    .hr { border-top: 2px solid #1A3A6B; margin: 10px auto 18px; width: 60%; }
    .title { font-size: 26px; font-weight: bold; color: #1A3A6B; letter-spacing: 3px; text-transform: uppercase; }
    .subtitle { font-size: 12px; color: #475569; margin-bottom: 18px; }
    .nomor { font-size: 11px; color: #64748b; margin-bottom: 22px; }
    .diberikan { font-size: 11px; color: #475569; }
    .nama { font-size: 24px; font-weight: bold; color: #111827; margin: 8px 0 2px; }
    .nis { font-size: 11px; color: #64748b; margin-bottom: 16px; }
    .isi { font-size: 12px; color: #334155; line-height: 1.7; width: 80%; margin: 0 auto 16px; text-align: center; }
    .nilai-box { display: inline-block; margin: 10px 0 22px; }
    .nilai-box table { border-collapse: collapse; }
    .nilai-box td { padding: 4px 18px; font-size: 12px; border: 1px solid #cbd5e1; }
    .nilai-box .label { background: #eef2f7; font-weight: bold; }
    .ttd { margin-top: 20px; width: 100%; }
    .ttd-box { display: inline-block; width: 260px; text-align: center; }
    .ttd-place { font-size: 11px; margin-bottom: 55px; }
    .ttd-name { font-size: 12px; font-weight: bold; text-decoration: underline; }
    .ttd-nip { font-size: 10px; color: #64748b; }
</style>

<div class="frame">
    <?php if ($logo): ?><img src="<?= $logo ?>" class="logo"><?php endif; ?>
    <div class="school"><?= esc($setting['school_name'] ?? 'SEKOLAH') ?></div>
    <div class="addr"><?= esc(kop_lokasi($setting)) ?><?= kop_kontak($setting) !== '' ? ' — ' . esc(kop_kontak($setting)) : '' ?></div>
    <div class="hr"></div>

    <div class="title">Sertifikat</div>
    <div class="subtitle">Uji Kompetensi Keahlian (UKK) Tahun Pelajaran <?= esc($setting['academic_year'] ?? '') ?></div>
    <div class="nomor">Nomor: <?= esc($srt['nomor_sertifikat']) ?></div>

    <div class="diberikan">Diberikan kepada:</div>
    <div class="nama"><?= esc($srt['siswa_nama'] ?? '—') ?></div>
    <div class="nis">NIS: <?= esc($srt['nis'] ?? '—') ?></div>

    <div class="isi">
        Atas keberhasilannya dinyatakan <b>KOMPETEN</b> dalam pelaksanaan Uji Kompetensi Keahlian
        untuk paket soal <b><?= esc($srt['paket_nama'] ?? '—') ?></b>.
    </div>

    <div class="nilai-box">
        <table>
            <tr><td class="label">Nilai Akhir</td><td><?= $srt['nilai_akhir'] !== null ? number_format((float) $srt['nilai_akhir'], 1) : '—' ?></td>
                <td class="label">Predikat</td><td><?= esc($srt['predikat'] ?? '—') ?></td></tr>
        </table>
    </div>

    <table style="width:100%; border:none; margin-top: 10px;">
        <tr>
            <td style="width:50%; border:none;"></td>
            <td style="width:50%; border:none; text-align:center;">
                <div class="ttd-box">
                    <div class="ttd-place"><?= esc($setting['city'] ?? '') ?>, <?= esc($tgl($srt['tanggal_terbit'])) ?></div>
                    <div>Kepala Sekolah</div>
                    <div class="ttd-name" style="margin-top:50px;"><?= esc($setting['headmaster_name'] ?? '....................................') ?></div>
                    <?php if (! empty($setting['headmaster_nip'])): ?>
                        <div class="ttd-nip">NIP. <?= esc($setting['headmaster_nip']) ?></div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
</div>
