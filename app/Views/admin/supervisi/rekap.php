<?php
/**
 * Rekap Supervisi (menu KURIKULUM → Supervisi Guru) — kit F5, mode CRUD tanpa dialog & tanpa skrip halaman. Saringan GET (tahun
 * ajaran, semester, predikat; terkirim otomatis), ringkasan periode (jumlah per predikat, rata-rata), tabel satu baris per guru
 * (nilai per instrumen, rata-rata, predikat, tindak lanjut, tautan lembar/PDF tiap kunjungan), lalu daftar guru yang belum
 * disupervisi. Unduh: Rekap (Excel) & Lembar Hasil gabungan (PDF) — keduanya ikut saringan.
 *
 * @var array{ta: string, semester: string, predikat: string} $saring
 * @var array<string, mixed> $rekap Libraries\SupervisiPenilaian::rekap()
 * @var list<string> $pilihanTa
 */

use App\Libraries\IsianBantu;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiCetak as Cetak;
use App\Libraries\SupervisiPenilaian as Penilaian;

$base    = site_url('admin/supervisi/rekap');
$periode = Supervisi::labelPeriode($saring['ta'], $saring['semester']);
$qsPer   = http_build_query(['ta' => $saring['ta'], 'semester' => $saring['semester']]);
$qsKini  = http_build_query(array_filter($saring));
$rk      = $rekap['ringkas'];
$ins     = $rekap['instrumen'];
$warnaPredikat = ['Amat Baik' => 'lencana-hijau', 'Baik' => 'lencana-biru', 'Cukup' => 'lencana-kuning', 'Kurang' => 'lencana-merah'];

$saringanHtml = '<form method="get" action="' . $base . '" class="flex min-w-0 flex-1 flex-wrap items-center gap-2" aria-label="Saring rekap">'
    . '<select name="ta" class="inp alat-crud-pilih" aria-label="Tahun ajaran" data-autosubmit>';
foreach ($pilihanTa as $ta) {
    $saringanHtml .= '<option value="' . esc($ta, 'attr') . '"' . ($ta === $saring['ta'] ? ' selected' : '') . '>TA ' . esc($ta) . '</option>';
}
$saringanHtml .= '</select><select name="semester" class="inp alat-crud-pilih" aria-label="Semester" data-autosubmit>';
foreach (Supervisi::SEMESTER as $k => $label) {
    $saringanHtml .= '<option value="' . $k . '"' . ($k === $saring['semester'] ? ' selected' : '') . '>Semester ' . esc($label) . '</option>';
}
$saringanHtml .= '</select><select name="predikat" class="inp alat-crud-pilih" aria-label="Predikat" data-autosubmit><option value="">Semua predikat</option>';
foreach (Penilaian::PREDIKAT as $p) {
    $saringanHtml .= '<option value="' . esc($p, 'attr') . '"' . ($p === $saring['predikat'] ? ' selected' : '') . '>' . esc($p) . '</option>';
}
$saringanHtml .= '</select><noscript><button type="submit" class="btn btn-netral">Tampilkan</button></noscript></form>';

$unduhHtml = '<a href="' . site_url('admin/supervisi/rekap/excel') . '?' . esc($qsKini, 'attr') . '" class="btn btn-netral" title="Rekap periode ini (ikut saringan) ke Excel">'
    . ikon('download', 'h-4 w-4') . 'Unduh Rekap (Excel)</a>';
if ($rk['kunjungan_selesai'] > 0) {
    $unduhHtml .= '<a href="' . site_url('admin/supervisi/lembar') . '?' . esc($qsPer, 'attr') . '&amp;dari=rekap" target="_blank" rel="noopener" class="btn btn-netral" title="Semua lembar hasil kunjungan yang selesai, satu PDF">'
        . ikon('printer', 'h-4 w-4') . 'Lembar Hasil (PDF)</a>';
}
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'supervisi_rekap_v1',
    'helpTitle' => 'Rekap supervisi',
    'helpKelas' => '',
    'helpBody'  => '<p>Rekap memuat guru yang kunjungan supervisinya sudah <b>selesai dinilai</b> pada semester terpilih. Bila satu guru disupervisi lebih dari sekali, nilainya dirata-rata.</p>'
        . '<p class="mt-2"><b>Unduh Rekap (Excel)</b> berisi lembar <b>Rekap</b> (nilai tiap guru) dan <b>Ringkasan</b> (jumlah per predikat + guru yang belum disupervisi), siap ditandatangani Kepala Sekolah. <b>Lembar Hasil (PDF)</b> mencetak semua lembar penilaian sekaligus.</p>'
        . '<p class="mt-2">Predikat: <b>91–100 Amat Baik</b> · <b>76–90,99 Baik</b> · <b>61–75,99 Cukup</b> · <b>di bawah 61 Kurang</b>.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div>
    <?= ui_crud_alat([
        'baseUrl'      => $base,
        'cari'         => null,
        'kiriHtml'     => $saringanHtml,
        'tambah'       => false,
        'impor'        => false,
        'tambahanHtml' => $unduhHtml,
    ]) ?>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
        <span class="font-semibold text-gray-900 dark:text-white"><?= esc($periode) ?></span>
        <span><?= $rk['guru_dinilai'] ?> guru dinilai (<?= $rk['kunjungan_selesai'] ?> kunjungan)</span>
        <?php if ($rk['rata_rata'] !== null): ?>
            <span>Rata-rata sekolah <b class="text-gray-900 dark:text-white"><?= esc(Penilaian::teksNilai($rk['rata_rata'])) ?></b> (<?= esc((string) $rk['rata_predikat']) ?>)</span>
        <?php endif; ?>
        <?php foreach ($rk['per_predikat'] as $p => $n): ?>
            <a href="<?= $base . '?' . esc(http_build_query(['ta' => $saring['ta'], 'semester' => $saring['semester'], 'predikat' => $saring['predikat'] === $p ? null : $p]), 'attr') ?>"
               class="lencana <?= $saring['predikat'] === $p ? $warnaPredikat[$p] : 'lencana-abu' ?>"<?= $saring['predikat'] === $p ? ' aria-current="true"' : '' ?>><?= esc($p) ?>: <?= $n ?></a>
        <?php endforeach; ?>
        <?php if ($rekap['belum'] !== []): ?>
            <a href="#belum-disupervisi" class="lencana lencana-kuning"><?= count($rekap['belum']) ?> guru belum disupervisi</a>
        <?php endif; ?>
    </div>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Guru</th>
                    <th scope="col">Mapel</th>
                    <?php foreach ($ins as $i): ?>
                        <th scope="col" class="text-right"><?= esc(Cetak::namaPendek($i['nama'])) ?></th>
                    <?php endforeach; ?>
                    <th scope="col" class="text-right">Rata-rata</th>
                    <th scope="col">Tindak lanjut</th>
                    <th scope="col" class="kolom-aksi">Lembar hasil</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rekap['baris'] === []): ?>
                    <tr>
                        <td colspan="<?= 5 + count($ins) ?>"><?= ui_kosong(['ikon' => 'chart-mixed', 'judul' => 'Belum ada hasil supervisi',
                            'teks' => $saring['predikat'] !== '' ? 'Tidak ada guru berpredikat ' . $saring['predikat'] . ' pada ' . $periode . '.'
                                : 'Belum ada kunjungan yang selesai dinilai pada ' . $periode . '. Isi lembarnya di Penilaian Supervisi.']) ?></td>
                    </tr>
                <?php else: foreach ($rekap['baris'] as $r): ?>
                    <tr>
                        <td>
                            <p class="sel-judul"><?= esc($r['nama_guru']) ?></p>
                            <?php if (($r['nip_guru'] ?? '') !== ''): ?><p class="sel-ket">NIP <?= esc((string) $r['nip_guru']) ?></p><?php endif; ?>
                        </td>
                        <td><?= esc($r['mapel'] !== [] ? implode(', ', $r['mapel']) : '—') ?></td>
                        <?php foreach ($ins as $i): ?>
                            <td class="text-right whitespace-nowrap"><?= esc(Penilaian::teksNilai($r['nilai'][$i['kunci']] ?? null)) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right whitespace-nowrap">
                            <p class="sel-judul"><?= esc(Penilaian::teksNilai($r['rata_rata'])) ?></p>
                            <span class="lencana <?= $warnaPredikat[$r['predikat']] ?? 'lencana-abu' ?>"><?= esc((string) $r['predikat']) ?></span>
                        </td>
                        <td class="text-sm"><?= esc($r['tindak_lanjut'] !== [] ? implode(', ', Penilaian::labelTindakLanjut($r['tindak_lanjut'])) : '—') ?></td>
                        <td class="kolom-aksi">
                            <div class="flex flex-col items-end gap-1">
                                <?php foreach ($r['kunjungan'] as $k): ?>
                                    <span class="flex items-center gap-1 whitespace-nowrap">
                                        <a href="<?= site_url('admin/supervisi/penilaian/' . $k['id']) ?>?kembali=<?= esc(rawurlencode($qsPer), 'attr') ?>" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-400"
                                           title="Buka lembar penilaian"><?= esc(date('d-m-Y', strtotime($k['tanggal']))) ?></a>
                                        <a href="<?= site_url('admin/supervisi/penilaian/' . $k['id'] . '/pdf') ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm"
                                           aria-label="PDF lembar hasil <?= esc($r['nama_guru'], 'attr') ?> <?= esc(date('d-m-Y', strtotime($k['tanggal'])), 'attr') ?>"><?= ikon('printer', 'h-4 w-4') ?>PDF</a>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($rekap['belum'] !== []): ?>
        <section id="belum-disupervisi" class="border-t border-gray-200 px-4 py-4 dark:border-gray-700" aria-labelledby="judul-belum">
            <h2 id="judul-belum" class="text-base font-semibold text-gray-900 dark:text-white">Belum disupervisi (<?= count($rekap['belum']) ?> guru)</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                <?= $rk['terjadwal'] ?> sudah terjadwal tetapi belum selesai dinilai, <?= $rk['belum_dijadwalkan'] ?> belum dijadwalkan.
                <a href="<?= site_url('admin/supervisi/jadwal') . '?' . esc($qsPer, 'attr') ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Buka Jadwal Supervisi</a>
            </p>
            <ul class="mt-3 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($rekap['belum'] as $g): ?>
                    <li class="flex items-baseline justify-between gap-2 border-b border-gray-100 pb-1.5 dark:border-gray-700">
                        <span class="min-w-0 truncate text-gray-900 dark:text-white"><?= esc($g['nama']) ?></span>
                        <?php if ($g['status'] === 'terjadwal'): ?>
                            <a href="<?= site_url('admin/supervisi/penilaian/' . $g['jadwal_id']) ?>?kembali=<?= esc(rawurlencode($qsPer), 'attr') ?>" class="shrink-0 text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Terjadwal <?= esc(IsianBantu::tanggalIndo((string) $g['tanggal'])) ?></a>
                        <?php else: ?>
                            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">Belum dijadwalkan</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rekap['baris']), 'satuan' => 'guru']) ?>
</div>
<?= $this->endSection() ?>
