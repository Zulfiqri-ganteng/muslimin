<?php
/**
 * Penilaian Supervisi (menu KURIKULUM → Supervisi Guru) — kit F5, mode CRUD tanpa dialog. Daftar kunjungan satu semester (saringan
 * bersama admin/supervisi/saringan) dengan kemajuan penilaian (belum / draf / selesai), nilai & predikat; tombol ke lembar penilaian.
 * Tanpa skrip halaman.
 *
 * @var array{ta: string, semester: string, status: string, guru_id: int} $saring
 * @var list<array<string, mixed>> $rows     Libraries\Supervisi::daftarJadwal
 * @var array{dijadwalkan: int, selesai: int, batal: int, total: int, guru_belum: list<array>, guru_sudah: list<int>} $ringkas
 * @var ?float $rataRata  rata-rata nilai akhir kunjungan selesai di daftar ini
 * @var list<string> $pilihanTa
 * @var list<array{id: int, nama: string}> $guru
 */

use App\Libraries\Keterlambatan;
use App\Libraries\Supervisi;
use App\Libraries\SupervisiPenilaian as Penilaian;

$base    = site_url('admin/supervisi/penilaian');
$periode = Supervisi::labelPeriode($saring['ta'], $saring['semester']);
$qsKini  = http_build_query(array_filter(['ta' => $saring['ta'], 'semester' => $saring['semester'], 'status' => $saring['status'], 'guru_id' => $saring['guru_id'] ?: null]));
$aktif   = $ringkas['dijadwalkan'] + $ringkas['selesai'];
$warnaPredikat = ['Amat Baik' => 'lencana-hijau', 'Baik' => 'lencana-biru', 'Cukup' => 'lencana-kuning', 'Kurang' => 'lencana-merah'];
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('tata') ?>crud<?= $this->endSection() ?>

<?= $this->section('bantuan') ?>
<?= view('admin/partials/help', [
    'helpKey'   => 'supervisi_penilaian_v1',
    'helpTitle' => 'Penilaian supervisi',
    'helpKelas' => '',
    'helpBody'  => '<p>Tekan <b>Nilai</b> pada kunjungan untuk membuka <b>lembar penilaian</b>. Tiap butir diberi skor <b>1 = Kurang, 2 = Cukup, 3 = Baik, 4 = Sangat Baik</b>, atau <b>TD</b> (tidak dinilai, tidak ikut dihitung). Nilai dan predikat dihitung otomatis.</p>'
        . '<p class="mt-2">Belum selesai? Tekan <b>Simpan draf</b> dan lanjutkan nanti. Bila semua butir sudah diisi, tekan <b>Simpan &amp; selesai</b> — status kunjungan menjadi <b>Selesai</b> dengan nilai akhir = rata-rata nilai instrumennya.</p>'
        . '<p class="mt-2">Predikat: <b>91–100 Amat Baik</b> · <b>76–90,99 Baik</b> · <b>61–75,99 Cukup</b> · <b>di bawah 61 Kurang</b>.</p>',
]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div>
    <?= ui_crud_alat([
        'baseUrl'  => $base,
        'cari'     => null,
        'kiriHtml' => view('admin/supervisi/saringan', ['base' => $base, 'saring' => $saring, 'pilihanTa' => $pilihanTa, 'guru' => $guru], ['saveData' => false]),
        'tambah'   => false,
        'impor'    => false,
        'tambahanHtml' => '<a href="' . site_url('admin/supervisi/jadwal') . ($qsKini !== '' ? '?' . esc($qsKini, 'attr') : '') . '" class="btn btn-netral">' . ikon('calendar-week', 'h-4 w-4') . 'Jadwal Supervisi</a>'
            . ($ringkas['selesai'] > 0 ? '<a href="' . site_url('admin/supervisi/lembar') . '?' . esc(http_build_query(array_filter(['ta' => $saring['ta'], 'semester' => $saring['semester'], 'guru_id' => $saring['guru_id'] ?: null])), 'attr')
                . '" target="_blank" rel="noopener" class="btn btn-netral" title="Semua lembar hasil kunjungan yang selesai (ikut saringan guru), satu PDF">' . ikon('printer', 'h-4 w-4') . 'Lembar Hasil (PDF)</a>' : '')
            . '<a href="' . site_url('admin/supervisi/rekap') . '?' . esc(http_build_query(['ta' => $saring['ta'], 'semester' => $saring['semester']]), 'attr') . '" class="btn btn-netral">' . ikon('chart-mixed', 'h-4 w-4') . 'Rekap</a>',
    ]) ?>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
        <span class="font-semibold text-gray-900 dark:text-white"><?= esc($periode) ?></span>
        <span><span class="titik-status titik-status-hijau"><?= $ringkas['selesai'] ?> dari <?= $aktif ?> kunjungan selesai dinilai</span></span>
        <?php if ($rataRata !== null): ?>
            <span>Rata-rata nilai <b class="text-gray-900 dark:text-white"><?= esc(Penilaian::teksNilai($rataRata)) ?></b> (<?= esc((string) Penilaian::predikat($rataRata)) ?>)</span>
        <?php endif; ?>
    </div>

    <div class="tabel-bungkus">
        <table class="tabel-crud">
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Guru</th>
                    <th scope="col">Mapel &amp; kelas</th>
                    <th scope="col">Supervisor</th>
                    <th scope="col">Penilaian</th>
                    <th scope="col" class="text-right">Nilai</th>
                    <th scope="col" class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="7"><?= ui_kosong(['ikon' => 'file-pen', 'judul' => 'Belum ada kunjungan supervisi',
                            'teks' => 'Belum ada jadwal ' . $periode . ($saring['status'] !== '' || $saring['guru_id'] > 0 ? ' dengan saringan ini' : '') . '. Buat jadwalnya dulu di Jadwal Supervisi.']) ?></td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <?php
                    $nIns    = count($r['instrumen_id']);
                    $nDraf   = count(array_intersect($r['dinilai'], $r['instrumen_id']));
                    $lembar  = $base . '/' . (int) $r['id'] . '?kembali=' . rawurlencode($qsKini);
                    ?>
                    <tr<?= $r['status'] === 'batal' ? ' class="opacity-60"' : '' ?>>
                        <td class="whitespace-nowrap">
                            <p class="sel-judul"><?= esc(Keterlambatan::hariTanggal((string) $r['tanggal'])) ?></p>
                            <?php if ($r['jam'] !== null): ?><p class="sel-ket">Jam <?= esc(substr((string) $r['jam'], 0, 5)) ?></p><?php endif; ?>
                        </td>
                        <td>
                            <p class="sel-judul"><?= esc($r['nama_guru']) ?></p>
                            <?php if (($r['nip_guru'] ?? '') !== ''): ?><p class="sel-ket">NIP <?= esc($r['nip_guru']) ?></p><?php endif; ?>
                        </td>
                        <td>
                            <p><?= esc($r['nama_mapel'] ?? '—') ?></p>
                            <p class="sel-ket"><?= esc($r['nama_kelas'] ?? 'Tanpa kelas') ?></p>
                        </td>
                        <td><?= esc($r['nama_supervisor']) ?></td>
                        <td class="whitespace-nowrap">
                            <?php if ($r['status'] === 'batal'): ?>
                                <span class="titik-status titik-status-merah font-medium">Batal</span>
                            <?php elseif ($r['status'] === 'selesai'): ?>
                                <span class="titik-status titik-status-hijau font-medium">Selesai</span>
                            <?php elseif ($r['ada_nilai']): ?>
                                <span class="titik-status titik-status-kuning font-medium">Draf</span>
                                <p class="sel-ket"><?= $nDraf ?> dari <?= $nIns ?> instrumen mulai diisi</p>
                            <?php else: ?>
                                <span class="titik-status font-medium">Belum dinilai</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <?php if ($r['status'] === 'selesai' && $r['nilai_akhir'] !== null): ?>
                                <p class="sel-judul"><?= esc(Penilaian::teksNilai($r['nilai_akhir'])) ?></p>
                                <span class="lencana <?= $warnaPredikat[$r['predikat']] ?? 'lencana-abu' ?>"><?= esc((string) $r['predikat']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400 dark:text-gray-500">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="kolom-aksi">
                            <?php if ($r['status'] !== 'batal'): ?>
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <a href="<?= esc($lembar, 'attr') ?>" class="btn <?= $r['status'] === 'selesai' ? 'btn-netral' : 'btn-sukses' ?> btn-sm"><?= ikon($r['status'] === 'selesai' ? 'eye' : 'file-pen', 'h-4 w-4') ?><?= $r['status'] === 'selesai' ? 'Lihat hasil' : ($r['ada_nilai'] ? 'Lanjutkan' : 'Nilai') ?></a>
                                    <a href="<?= $base . '/' . (int) $r['id'] . '/pdf' ?>" target="_blank" rel="noopener" class="btn btn-netral btn-sm"
                                       title="<?= $r['status'] === 'selesai' ? 'Lembar hasil (PDF)' : 'Lembar penilaian (PDF) — bisa dicetak kosong untuk diisi tangan' ?>"
                                       aria-label="PDF lembar <?= esc($r['nama_guru'], 'attr') ?>"><?= ikon('printer', 'h-4 w-4') ?>PDF</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?= ui_crud_kaki(['pager' => null, 'total' => count($rows), 'satuan' => 'kunjungan']) ?>
</div>
<?= $this->endSection() ?>
