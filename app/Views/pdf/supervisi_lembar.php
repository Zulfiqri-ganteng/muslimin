<?php
/**
 * LEMBAR HASIL SUPERVISI AKADEMIK — cetak Dompdf A4 tegak (Libraries\SupervisiCetak::lembarHtml). Satu kunjungan per halaman (daftar
 * beberapa kunjungan = PDF gabungan, tiap kunjungan mulai halaman baru). Isi: kop sekolah, identitas, satu tabel per instrumen
 * (No | Butir | skor 1–4 dicentang | Catatan; butir "tidak dinilai" ditulis TD), jumlah skor/nilai/predikat, kekuatan, kelemahan,
 * rekomendasi, tindak lanjut, ringkasan nilai akhir, tanda tangan guru · supervisor · kepala sekolah. Kunjungan yang belum selesai
 * ditandai DRAF (lembar kosong bisa dipakai menilai dengan tangan).
 *
 * @var list<array{jadwal: array<string, mixed>, instrumen: list<array<string, mixed>>, ringkas: array{nilai: ?float, predikat: ?string, lengkap: bool}}> $daftar
 * @var array{kota: string, kepala: string, nip: string} $ttd
 */

use App\Libraries\IsianBantu;
use App\Libraries\Keterlambatan;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiCetak;
use App\Libraries\SupervisiPenilaian as Penilaian;

$huruf = range('A', 'Z');
$titik = '..........................................';
$teks  = static fn (?string $t): string => trim((string) $t) === '' ? '<span class="kosong">' . $titik . '</span>' : nl2br(esc((string) $t));
?>
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    @page { margin: 26px 34px 30px 34px; }
    body { font-size: 9.5px; color: #111827; }
    .halaman { page-break-after: always; }
    .halaman.akhir { page-break-after: auto; }
    .judul { text-align: center; font-weight: bold; font-size: 13px; margin: 4px 0 1px; }
    .sub { text-align: center; font-size: 10px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    .ident td { padding: 1.5px 0; vertical-align: top; }
    .ident td.k { width: 120px; }
    .ident td.t { width: 10px; }
    .draf { border: 1px solid #b45309; color: #92400e; padding: 4px 6px; margin: 6px 0 2px; font-weight: bold; text-align: center; }
    h3 { font-size: 10.5px; margin: 12px 0 4px; }
    .skor th, .skor td { border: 1px solid #6b7280; padding: 2.5px 4px; vertical-align: top; }
    .skor th { background: #ede9fe; text-align: center; font-weight: bold; }
    .skor td.c { text-align: center; vertical-align: middle; }
    .skor td.aspek { background: #f3f4f6; font-weight: bold; }
    .skor td.catat { width: 120px; font-size: 8.5px; }
    .skor tr.jumlah td { background: #f9fafb; font-weight: bold; }
    .temuan td { border: 1px solid #6b7280; padding: 3px 5px; vertical-align: top; }
    .temuan td.k { width: 110px; font-weight: bold; background: #f9fafb; }
    .kosong { color: #9ca3af; }
    .ttd td { text-align: center; vertical-align: top; width: 33%; padding: 0 4px; }
    .ttd .ruang { height: 46px; }
    .ttd .nama { font-weight: bold; text-decoration: underline; }
    .ket { font-size: 8px; color: #4b5563; margin-top: 6px; }
</style>

<?php foreach ($daftar as $ke => $l): ?>
    <?php
    $j        = $l['jadwal'];
    $selesai  = $j['status'] === 'selesai';
    $adaNilai = (bool) ($j['ada_nilai'] ?? false);
    $nilaiIns = array_values(array_filter($l['instrumen'], static fn (array $i): bool => $i['ringkas']['nilai'] !== null));
    $supSamaKepala = $ttd['kepala'] !== '' && mb_strtolower(trim((string) $j['nama_supervisor'])) === mb_strtolower($ttd['kepala']);
    ?>
    <div class="halaman<?= $ke === count($daftar) - 1 ? ' akhir' : '' ?>">
        <?= kop_pdf() ?>
        <div class="judul">LEMBAR HASIL SUPERVISI AKADEMIK</div>
        <div class="sub"><?= esc(Supervisi::labelPeriode((string) $j['tahun_ajaran'], (string) $j['semester'])) ?></div>

        <table class="ident">
            <tr><td class="k">Nama guru</td><td class="t">:</td><td><b><?= esc((string) $j['nama_guru']) ?></b></td></tr>
            <tr><td class="k">NIP</td><td class="t">:</td><td><?= esc(($j['nip_guru'] ?? '') !== '' ? (string) $j['nip_guru'] : '-') ?></td></tr>
            <tr><td class="k">Mata pelajaran</td><td class="t">:</td><td><?= esc((string) ($j['nama_mapel'] ?? '-')) ?></td></tr>
            <tr><td class="k">Kelas</td><td class="t">:</td><td><?= esc((string) ($j['nama_kelas'] ?? '-')) ?></td></tr>
            <tr><td class="k">Hari, tanggal</td><td class="t">:</td><td><?= esc(Keterlambatan::hariTanggal((string) $j['tanggal']) . ($j['jam'] !== null ? ', pukul ' . substr((string) $j['jam'], 0, 5) : '')) ?></td></tr>
            <tr><td class="k">Supervisor</td><td class="t">:</td><td><?= esc((string) $j['nama_supervisor'] . (($j['jabatan_supervisor'] ?? '') !== '' ? ' (' . $j['jabatan_supervisor'] . ')' : '')) ?></td></tr>
        </table>
        <?php if (! $selesai): ?>
            <div class="draf"><?= $adaNilai ? 'DRAF — penilaian belum selesai, nilai belum final.' : 'BELUM DINILAI — lembar ini dapat diisi dengan tangan saat kunjungan.' ?></div>
        <?php endif; ?>

        <?php foreach ($l['instrumen'] as $i => $ins): $no = 0; $aspekLalu = null; $rk = $ins['ringkas']; ?>
            <h3><?= $huruf[$i] ?? ($i + 1) ?>. <?= esc((string) $ins['nama']) ?></h3>
            <table class="skor">
                <thead>
                    <tr>
                        <th style="width:20px" rowspan="2">No</th>
                        <th rowspan="2">Butir yang diamati</th>
                        <th colspan="4">Skor</th>
                        <th rowspan="2">Catatan</th>
                    </tr>
                    <tr><th style="width:17px">1</th><th style="width:17px">2</th><th style="width:17px">3</th><th style="width:17px">4</th></tr>
                </thead>
                <tbody>
                    <?php if ($ins['butir'] === []): ?>
                        <tr><td colspan="7" class="c">Instrumen ini belum punya butir.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ins['butir'] as $b): $no++; ?>
                        <?php if ($b['aspek'] !== $aspekLalu): $aspekLalu = $b['aspek']; ?>
                            <tr><td class="aspek" colspan="7"><?= esc((string) $b['aspek']) ?></td></tr>
                        <?php endif; ?>
                        <tr>
                            <td class="c"><?= $no ?></td>
                            <td><?= esc((string) $b['uraian']) ?></td>
                            <?php if ($b['skor'] === 0): ?>
                                <td class="c" colspan="4">TD</td>
                            <?php else: foreach ([1, 2, 3, 4] as $s): ?>
                                <td class="c"><?= $b['skor'] === $s ? '&#10003;' : '' ?></td>
                            <?php endforeach; endif; ?>
                            <td class="catat"><?= esc((string) $b['catatan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="jumlah">
                        <td colspan="2">Jumlah skor: <?= $rk['dinilai'] > 0 ? $rk['total'] . ' dari ' . $rk['maks'] : '-' ?><?= $rk['td'] > 0 ? ' (' . $rk['td'] . ' butir tidak dinilai)' : '' ?></td>
                        <td colspan="5">Nilai: <?= esc(Penilaian::teksNilai($rk['nilai'])) ?> · Predikat: <?= esc((string) ($rk['predikat'] ?? '—')) ?></td>
                    </tr>
                </tbody>
            </table>
            <table class="temuan" style="margin-top:4px">
                <tr><td class="k">Kekuatan</td><td><?= $teks($ins['kekuatan']) ?></td></tr>
                <tr><td class="k">Kelemahan</td><td><?= $teks($ins['kelemahan']) ?></td></tr>
                <tr><td class="k">Rekomendasi</td><td><?= $teks($ins['rekomendasi']) ?></td></tr>
                <tr><td class="k">Tindak lanjut</td><td><?= $ins['tindak_lanjut'] !== [] ? esc(implode(', ', Penilaian::labelTindakLanjut($ins['tindak_lanjut']))) : '<span class="kosong">' . esc(implode(' / ', Penilaian::TINDAK_LANJUT)) . '</span>' ?></td></tr>
            </table>
        <?php endforeach; ?>

        <h3>Ringkasan nilai</h3>
        <table class="skor">
            <thead><tr><th style="width:20px">No</th><th>Instrumen</th><th style="width:70px">Nilai</th><th style="width:80px">Predikat</th></tr></thead>
            <tbody>
                <?php foreach ($l['instrumen'] as $i => $ins): ?>
                    <tr>
                        <td class="c"><?= $huruf[$i] ?? ($i + 1) ?></td>
                        <td><?= esc(SupervisiCetak::namaPendek((string) $ins['nama'])) ?></td>
                        <td class="c"><?= esc(Penilaian::teksNilai($ins['ringkas']['nilai'])) ?></td>
                        <td class="c"><?= esc((string) ($ins['ringkas']['predikat'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="jumlah">
                    <td colspan="2">Nilai akhir (rata-rata <?= count($nilaiIns) ?> instrumen)</td>
                    <td class="c"><?= esc(Penilaian::teksNilai($selesai ? $j['nilai_akhir'] : $l['ringkas']['nilai'])) ?></td>
                    <td class="c"><?= esc((string) (($selesai ? $j['predikat'] : $l['ringkas']['predikat']) ?? '—')) ?></td>
                </tr>
            </tbody>
        </table>
        <div class="ket">Skor: 1 = Kurang, 2 = Cukup, 3 = Baik, 4 = Sangat Baik, TD = tidak dinilai (tidak dihitung). Nilai = jumlah skor ÷ (butir dinilai × 4) × 100.
            Predikat: <?= esc(implode(' · ', array_map(static fn (int $b, string $p): string => SupervisiCetak::rentangPredikat($b) . ' ' . $p, array_keys(Penilaian::PREDIKAT), Penilaian::PREDIKAT))) ?>.</div>

        <table class="ttd" style="margin-top:14px">
            <tr>
                <td><br>Guru yang disupervisi</td>
                <td><?= esc(($ttd['kota'] !== '' ? $ttd['kota'] . ', ' : '') . IsianBantu::tanggalIndo((string) $j['tanggal'])) ?><br>Supervisor</td>
                <?php if (! $supSamaKepala): ?><td><br>Mengetahui,<br>Kepala Sekolah</td><?php endif; ?>
            </tr>
            <tr><td class="ruang"></td><td class="ruang"></td><?php if (! $supSamaKepala): ?><td class="ruang"></td><?php endif; ?></tr>
            <tr>
                <td><span class="nama"><?= esc((string) $j['nama_guru']) ?></span><br><?= ($j['nip_guru'] ?? '') !== '' ? 'NIP. ' . esc((string) $j['nip_guru']) : '&nbsp;' ?></td>
                <td><span class="nama"><?= esc((string) $j['nama_supervisor']) ?></span><br><?= $supSamaKepala && $ttd['nip'] !== '' ? 'NIP. ' . esc($ttd['nip']) : '&nbsp;' ?></td>
                <?php if (! $supSamaKepala): ?>
                    <td><span class="nama"><?= esc($ttd['kepala'] !== '' ? $ttd['kepala'] : '..............................') ?></span><br><?= $ttd['nip'] !== '' ? 'NIP. ' . esc($ttd['nip']) : '&nbsp;' ?></td>
                <?php endif; ?>
            </tr>
        </table>
    </div>
<?php endforeach; ?>
