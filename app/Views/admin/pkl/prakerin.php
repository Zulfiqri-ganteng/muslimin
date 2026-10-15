<?php
/**
 * Data Siswa Prakerin — rekap tempat PKL per kelas dari data sistem. Isinya sama dengan unduhan Excel format sekolah
 * (PklPrakerin::excel): satu tabel = satu lembar. Rombak tampilan F9: tab kit (_nav), Unduh Excel / Impor ber-ikon di kepala
 * halaman, kartu ringkasan + saringan Tingkat/Kelas/Isi = tautan .tombol-saring (aktif: aria-current), satu kartu + tabel kit
 * per kelas, ui_kosong.
 *
 * @var list<array{id: int, nama_kelas: string, tingkat: string, jumlah: int}> $semuaKelas
 * @var list<array>  $rekap         PklPrakerin::data()
 * @var string       $tingkatPilih
 * @var int          $kelasId       0 = semua kelas di tingkat terpilih
 * @var bool         $hanyaDiterima true = hanya siswa yang sudah pasti diterima perusahaan (?isi=diterima)
 * @var string       $sekolah
 * @var string       $tahun
 * @var bool         $bolehPengaturan
 * @var array<string,int> $hitungTab
 */

use App\Libraries\IsianBantu;

$perTingkat = [];
foreach ($semuaKelas as $k) {
    $perTingkat[$k['tingkat']][] = $k;
}
$tot = ['siswa' => 0, 'diterima' => 0, 'tanggal' => 0, 'tunggu' => 0, 'belum' => 0];
foreach ($rekap as $r) {
    foreach ($tot as $kunci => $n) {
        $tot[$kunci] = $n + $r['ringkas'][$kunci];
    }
}
$isiQ     = $hanyaDiterima ? ['isi' => 'diterima'] : [];
$urlHal   = static fn (array $q): string => site_url('admin/pkl/prakerin') . '?' . http_build_query($q + $isiQ);
$urlExcel = static fn (array $q): string => site_url('admin/pkl/prakerin/excel') . '?' . http_build_query($q + $isiQ);
$saringan = $kelasId > 0 ? ['kelas_id' => $kelasId] : ['tingkat' => $tingkatPilih];
$urlIsi   = static fn (bool $diterima): string => site_url('admin/pkl/prakerin') . '?' . http_build_query($saringan + ($diterima ? ['isi' => 'diterima'] : []));
$aktif    = static fn (bool $ya): string => $ya ? ' aria-current="true"' : '';
$warnaKet = static fn (string $tahap): string => match ($tahap) {
    'diterima' => 'text-green-700 dark:text-green-400',
    'proses'   => 'font-semibold text-yellow-700 dark:text-yellow-400',
    default    => 'text-gray-500 dark:text-gray-400',
};
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<?php if ($rekap !== []): ?>
    <a href="<?= esc($urlExcel($saringan), 'attr') ?>" class="btn btn-utama"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel<?= count($rekap) > 1 ? ' (' . count($rekap) . ' lembar)' : '' ?></a>
<?php endif; ?>
<?php if ($bolehPengaturan): ?>
    <a href="<?= site_url('admin/pkl/impor') ?>" class="btn btn-netral"><?= ikon('upload', 'h-4 w-4') ?>Impor dari Excel</a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_prakerin_v1',
    'helpTitle' => 'Data Siswa Prakerin',
    'helpBody'  => '<p>Rekap tempat PKL siswa per kelas, <b>diambil otomatis dari sistem</b>. Tombol <b>Unduh Excel</b> menghasilkan berkas format sekolah "DATA SISWA PRAKERIN" (satu lembar per kelas) — isinya sama dengan tabel di halaman ini.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Mitra DI/DU</b> = perusahaan yang dituju siswa (ajuan aktif). Kolom <b>Keterangan</b> menjelaskan posisinya: <b>Sudah diterima</b> (sudah Lapor Diterima, ada Surat Balasan, atau hasil impor Excel sekolah), belum diterima perusahaan / belum lapor, belum di-ACC Hubin, perlu perbaikan, ditolak, atau belum mengajukan.</li>'
        . '<li>ACC Hubin saja <b>belum berarti diterima</b> perusahaan — siswa harus mengisi <b>Lapor Diterima</b>. Keterangan ikut tercetak di Excel.</li>'
        . '<li><b>Mulai &amp; Selesai</b> diambil dari periode di Surat Balasan / hasil impor, atau dari laporan "Lapor Diterima" siswa. <b>Lamanya PKL</b> dihitung dalam bulan.</li>'
        . '<li>Pilih <b>Hanya yang sudah diterima</b> bila Excel cukup berisi siswa yang sudah pasti (nomor urut menyesuaikan).</li>'
        . ($bolehPengaturan ? '<li>Punya data di Excel format ini? Pakai <b>Impor dari Excel</b> — pratinjau tampil dulu sebelum ada yang disimpan.</li>' : '')
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => 'prakerin', 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <section class="kartu" aria-labelledby="judul-prakerin">
        <div class="kartu-isi">
            <h2 id="judul-prakerin" class="text-lg font-semibold text-gray-900 dark:text-white"><?= esc(mb_strtoupper($sekolah)) ?><?= $tahun !== '' ? ' · Tahun Pelajaran ' . esc($tahun) : '' ?></h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?= count($rekap) ?> kelas · <?= (int) $tot['siswa'] ?> siswa · <b class="text-gray-900 dark:text-white"><?= (int) $tot['diterima'] ?></b> sudah diterima · <?= (int) $tot['tunggu'] ?> mengajukan, belum diterima · <?= (int) $tot['belum'] ?> belum mengajukan</p>
        </div>

        <div class="kartu-kaki space-y-2.5">
            <nav class="flex flex-wrap items-center gap-2" aria-label="Tingkat">
                <span class="w-14 text-xs font-semibold text-gray-500 dark:text-gray-400">Tingkat</span>
                <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                    <a href="<?= esc($urlHal(['tingkat' => $t]), 'attr') ?>" class="tombol-saring"<?= $aktif($t === $tingkatPilih) ?>>Kelas <?= $t ?> <span class="tabular-nums opacity-90">(<?= count($perTingkat[$t] ?? []) ?>)</span></a>
                <?php endforeach; ?>
            </nav>
            <?php if (($perTingkat[$tingkatPilih] ?? []) !== []): ?>
                <nav class="flex flex-wrap items-center gap-2" aria-label="Kelas">
                    <span class="w-14 text-xs font-semibold text-gray-500 dark:text-gray-400">Kelas</span>
                    <a href="<?= esc($urlHal(['tingkat' => $tingkatPilih]), 'attr') ?>" class="tombol-saring"<?= $aktif($kelasId === 0) ?>>Semua kelas <?= esc($tingkatPilih) ?></a>
                    <?php foreach ($perTingkat[$tingkatPilih] as $k): ?>
                        <a href="<?= esc($urlHal(['kelas_id' => $k['id']]), 'attr') ?>" class="tombol-saring"<?= $aktif($kelasId === $k['id']) ?>><?= esc($k['nama_kelas']) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <nav class="flex flex-wrap items-center gap-2" aria-label="Isi">
                <span class="w-14 text-xs font-semibold text-gray-500 dark:text-gray-400">Isi</span>
                <a href="<?= esc($urlIsi(false), 'attr') ?>" class="tombol-saring"<?= $aktif(! $hanyaDiterima) ?>>Semua siswa (dengan keterangan)</a>
                <a href="<?= esc($urlIsi(true), 'attr') ?>" class="tombol-saring"<?= $aktif($hanyaDiterima) ?>>Hanya yang sudah diterima</a>
            </nav>
        </div>
    </section>

    <?php if ($rekap === []): ?>
        <div class="kartu"><?= ui_kosong(['ikon' => 'school', 'judul' => 'Belum ada kelas di tingkat ' . $tingkatPilih]) ?></div>
    <?php endif; ?>

    <?php foreach ($rekap as $r): $k = $r['kelas']; ?>
        <section id="kelas-<?= (int) $k['id'] ?>" class="kartu overflow-hidden" aria-labelledby="judul-kelas-<?= (int) $k['id'] ?>">
            <div class="kartu-kepala">
                <div class="min-w-0">
                    <h3 id="judul-kelas-<?= (int) $k['id'] ?>" class="kartu-judul text-base"><?= esc($k['nama_kelas']) ?></h3>
                    <p class="kartu-ket"><?= (int) $r['ringkas']['siswa'] ?> siswa · <?= (int) $r['ringkas']['diterima'] ?> sudah diterima · <?= (int) $r['ringkas']['tunggu'] ?> belum diterima · <?= (int) $r['ringkas']['belum'] ?> belum mengajukan</p>
                </div>
                <?php if (count($rekap) > 1): ?>
                    <a href="<?= esc($urlExcel(['kelas_id' => $k['id']]), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh kelas ini</a>
                <?php endif; ?>
            </div>
            <?php if ($r['siswa'] === []): ?>
                <?= ui_kosong(['ikon' => 'users', 'judul' => $hanyaDiterima ? 'Belum ada siswa yang sudah diterima perusahaan di kelas ini' : 'Belum ada siswa aktif di kelas ini']) ?>
            <?php else: ?>
                <div class="tabel-bungkus">
                    <table class="tabel min-w-[44rem]">
                        <thead>
                            <tr>
                                <th scope="col" class="w-12 text-center">No</th>
                                <th scope="col">Nama</th>
                                <th scope="col">Mitra DI/DU</th>
                                <th scope="col">Mulai</th>
                                <th scope="col">Selesai</th>
                                <th scope="col">Lamanya PKL</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($r['siswa'] as $s): ?>
                                <tr>
                                    <td class="text-center tabular-nums text-gray-500 dark:text-gray-400"><?= (int) $s['no'] ?></td>
                                    <td class="font-medium text-gray-900 dark:text-white"><?= esc($s['nama']) ?></td>
                                    <td><?= $s['mitra'] !== '' ? esc($s['mitra']) : '<span class="text-gray-500 dark:text-gray-400">—</span>' ?></td>
                                    <td class="whitespace-nowrap"><?= $s['mulai'] !== null ? esc(IsianBantu::tanggalIndo($s['mulai'])) : '' ?></td>
                                    <td class="whitespace-nowrap"><?= $s['selesai'] !== null ? esc(IsianBantu::tanggalIndo($s['selesai'])) : '' ?></td>
                                    <td class="whitespace-nowrap"><?= $s['lama'] !== null ? (int) $s['lama'] . ' bulan' : '' ?></td>
                                    <td class="text-xs <?= $warnaKet((string) $s['tahap']) ?>"><?= esc((string) $s['catatan']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
