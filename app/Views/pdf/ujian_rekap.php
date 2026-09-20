<?php
/**
 * Rekap satu periode ujian — cetak (Dompdf, A4 portrait).
 *
 * Angkanya berasal dari App\Libraries\UjianReport::hitung(), sama persis
 * dengan yang tampil di tab Rekap.
 *
 * @var array            $periode
 * @var string           $label
 * @var string           $panjang
 * @var array            $setting
 * @var int              $jadwalTotal
 * @var array            $jadwalPerTingkat
 * @var int              $pengawasTotal
 * @var int              $takHadirTotal
 * @var array            $perStatus
 * @var array            $perAlasan
 * @var array<int,array> $perKelas
 * @var array<int,array> $perMapel
 * @var array<int,array> $perTanggal
 * @var array<int,array> $siswaTerbanyak
 */
$tgl = static fn ($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '—';
?>
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10.5px; color: #1f2937; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
    th { background: #eef2f7; }
    .center { text-align: center; }
    .judul { text-align: center; font-weight: bold; font-size: 14px; margin: 10px 0 2px; text-transform: uppercase; }
    .sub { text-align: center; font-size: 11px; margin-bottom: 14px; }
    h3 { font-size: 11.5px; margin: 14px 0 5px; border-left: 3px solid #1a3a6b; padding-left: 6px; }
    .kartu { width: 100%; border: none; margin-bottom: 4px; }
    .kartu td { border: 1px solid #cbd5e1; text-align: center; padding: 6px; width: 25%; }
    .angka { font-size: 16px; font-weight: bold; }
    .kecil { font-size: 9.5px; color: #64748b; }
</style>

<?= kop_pdf() ?>

<div class="judul">Rekap <?= esc($panjang ?: $label) ?></div>
<div class="sub">
    <?= esc($label) ?> &mdash; Semester <?= esc($periode['semester']) ?><br>
    Pelaksanaan <?= $tgl($periode['tanggal_mulai']) ?> s.d. <?= $tgl($periode['tanggal_selesai']) ?>
    &nbsp;·&nbsp; Susulan <?= $tgl($periode['susulan_mulai']) ?> s.d. <?= $tgl($periode['susulan_selesai']) ?>
</div>

<table class="kartu">
    <tr>
        <td><div class="angka"><?= (int) $jadwalTotal ?></div><div class="kecil">Sesi Terjadwal</div></td>
        <td><div class="angka"><?= (int) $pengawasTotal ?></div><div class="kecil">Penugasan Pengawas</div></td>
        <td><div class="angka"><?= (int) $takHadirTotal ?></div><div class="kecil">Siswa Tidak Hadir</div></td>
        <td><div class="angka"><?= (int) $perStatus['selesai'] ?></div><div class="kecil">Susulan Selesai</div></td>
    </tr>
</table>

<h3>Status Ujian Susulan</h3>
<table>
    <tr>
        <th>Belum dijadwalkan</th><th>Sudah dijadwalkan</th><th>Selesai</th><th>Batal</th>
    </tr>
    <tr class="center">
        <td><?= (int) $perStatus['belum'] ?></td>
        <td><?= (int) $perStatus['dijadwalkan'] ?></td>
        <td><?= (int) $perStatus['selesai'] ?></td>
        <td><?= (int) $perStatus['batal'] ?></td>
    </tr>
</table>

<h3>Alasan Ketidakhadiran</h3>
<table>
    <tr><th>Sakit</th><th>Izin</th><th>Alpa</th><th>Lainnya</th></tr>
    <tr class="center">
        <td><?= (int) $perAlasan['sakit'] ?></td>
        <td><?= (int) $perAlasan['izin'] ?></td>
        <td><?= (int) $perAlasan['alpa'] ?></td>
        <td><?= (int) $perAlasan['lainnya'] ?></td>
    </tr>
</table>

<h3>Sesi Terjadwal per Tingkat</h3>
<table>
    <tr><th>Tingkat X</th><th>Tingkat XI</th><th>Tingkat XII</th></tr>
    <tr class="center">
        <td><?= (int) $jadwalPerTingkat['X'] ?></td>
        <td><?= (int) $jadwalPerTingkat['XI'] ?></td>
        <td><?= (int) $jadwalPerTingkat['XII'] ?></td>
    </tr>
</table>

<h3>Rekap per Kelas</h3>
<table>
    <thead>
        <tr>
            <th class="center" style="width:30px">No</th>
            <th>Kelas</th><th class="center" style="width:52px">Tingkat</th>
            <th class="center" style="width:62px">Tidak Hadir</th>
            <th class="center" style="width:50px">Belum</th>
            <th class="center" style="width:70px">Dijadwalkan</th>
            <th class="center" style="width:52px">Selesai</th>
            <th class="center" style="width:45px">Batal</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($perKelas === []): ?>
            <tr><td colspan="8" class="center">Belum ada catatan ketidakhadiran.</td></tr>
        <?php else: foreach ($perKelas as $n => $r): ?>
            <tr>
                <td class="center"><?= $n + 1 ?></td>
                <td><?= esc($r['nama_kelas'] ?? '—') ?></td>
                <td class="center"><?= esc($r['tingkat'] ?? '—') ?></td>
                <td class="center"><b><?= (int) $r['total'] ?></b></td>
                <td class="center"><?= (int) $r['belum'] ?></td>
                <td class="center"><?= (int) $r['dijadwalkan'] ?></td>
                <td class="center"><?= (int) $r['selesai'] ?></td>
                <td class="center"><?= (int) $r['batal'] ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<h3>Rekap per Mata Pelajaran</h3>
<table>
    <thead>
        <tr>
            <th class="center" style="width:30px">No</th>
            <th style="width:70px">Kode</th><th>Mata Pelajaran</th>
            <th class="center" style="width:62px">Tidak Hadir</th>
            <th class="center" style="width:50px">Belum</th>
            <th class="center" style="width:52px">Selesai</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($perMapel === []): ?>
            <tr><td colspan="6" class="center">Belum ada catatan ketidakhadiran.</td></tr>
        <?php else: foreach ($perMapel as $n => $r): ?>
            <tr>
                <td class="center"><?= $n + 1 ?></td>
                <td><?= esc($r['kode_mapel'] ?? '—') ?></td>
                <td><?= esc($r['nama_mapel'] ?? '—') ?></td>
                <td class="center"><b><?= (int) $r['total'] ?></b></td>
                <td class="center"><?= (int) $r['belum'] ?></td>
                <td class="center"><?= (int) $r['selesai'] ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if ($siswaTerbanyak !== []): ?>
    <h3>Siswa dengan Ketidakhadiran Terbanyak (lebih dari satu mapel)</h3>
    <table>
        <thead>
            <tr>
                <th class="center" style="width:30px">No</th>
                <th style="width:90px">NIS</th><th>Nama Siswa</th>
                <th style="width:110px">Kelas</th>
                <th class="center" style="width:62px">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($siswaTerbanyak as $n => $r): ?>
                <tr>
                    <td class="center"><?= $n + 1 ?></td>
                    <td><?= esc($r['nis'] ?? '—') ?></td>
                    <td><?= esc($r['nama'] ?? '—') ?></td>
                    <td><?= esc($r['nama_kelas'] ?? '—') ?></td>
                    <td class="center"><b><?= (int) $r['total'] ?></b></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p class="kecil">Dicetak <?= date('d/m/Y H:i') ?> dari <?= esc($setting['school_name'] ?? '') ?>.</p>
