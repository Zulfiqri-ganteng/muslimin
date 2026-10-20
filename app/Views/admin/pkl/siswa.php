<?php
/**
 * Status Siswa — siapa sudah/belum mengisi, siapa sudah/belum PKL. Rombak tampilan F9: tab kit (_nav), kartu angka kit
 * (kartu-tautan), rincian status = tautan .tombol-saring (aktif: aria-current), kartu kit dengan bilah cari & saringan langsung,
 * Unduh Excel ber-ikon, tabel kit (layar lebar) + kartu (HP), lencana status kit (_lencana), ui_peringatan / ui_kosong.
 *
 * @var array<string,int|float> $ringkas
 * @var list<array>             $rows
 * @var int                     $total
 * @var string                  $q
 * @var int                     $kelasId
 * @var string                  $fase
 * @var list<array>             $kelas
 * @var list<string>            $tingkat
 * @var int                     $page
 * @var int                     $jmlHal
 * Bersama dari PklDasar::dasar(): $tab, $hitungTab, $bolehPengaturan, …
 */

use App\Libraries\IsianBantu;
use App\Models\PklPengajuanModel;

$urlSaring = static fn (array $tambah): string => site_url('admin/pkl/siswa') . '?' . http_build_query(array_filter(
    ['q' => $q, 'kelas_id' => $kelasId ?: null] + $tambah,
    static fn ($v) => $v !== null && $v !== ''
));
$urlHal = static fn (int $hal): string => $urlSaring(['fase' => $fase, 'page' => $hal > 1 ? $hal : null]);

$opsiFase = [
    ''              => 'Semua status',
    'belum_mengisi' => 'Belum mengisi (belum + ditolak)',
    'sudah_mengisi' => 'Sudah mengisi',
    'sudah_pkl'     => 'Sudah dapat tempat PKL',
    'belum'         => '— Belum pernah mengisi',
    'ditolak'       => '— Ditolak, perlu ajukan ulang',
    'menunggu'      => '— Menunggu ACC',
    'perbaikan'     => '— Perlu perbaikan',
    'belum_mulai'   => '— Disetujui, belum mulai',
    'sedang'        => '— Sedang PKL',
    'selesai'       => '— Selesai PKL',
    'pernah_ditolak' => 'Pernah ditolak / dikeluarkan (status apa pun)',
];
$adaSaring = $q !== '' || $kelasId > 0 || $fase !== '';
$urlExcel  = site_url('admin/pkl/siswa/excel') . '?' . http_build_query(array_filter(['q' => $q, 'kelas_id' => $kelasId ?: null, 'fase' => $fase]));
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_siswa_v2',
    'helpTitle' => 'Status Siswa PKL',
    'helpBody'  => '<p>Status dihitung <b>otomatis</b> dari ajuan dan tanggal hari ini; tidak ada yang diketik manual.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Sudah mengisi</b> = punya ajuan aktif (menunggu, perlu perbaikan, atau disetujui). <b>Belum mengisi</b> = belum pernah, atau ajuannya ditolak.</li>'
        . '<li><b>Sudah PKL</b> = ajuannya sudah <b>disetujui</b> (sudah dapat tempat), lalu dirinci: belum mulai, sedang PKL, atau selesai menurut tanggalnya.</li>'
        . '<li><b>Pernah ditolak</b> = pernah punya ajuan yang ditolak (sekolah/perusahaan) atau pernah <b>dikeluarkan</b> dari kelompok — tetap terlihat walau sekarang sudah mengajukan lagi. Klik nama siswa untuk melihat riwayatnya.</li>'
        . '<li>Yang dihitung hanya siswa aktif di tingkat yang diizinkan di <b>Pengaturan PKL</b>.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => $tab, 'hitungTab' => $hitungTab]) ?>

<div class="space-y-4">
    <?php if ($tingkat === []): ?>
        <?= ui_peringatan('awas', '', ['html' => '<b>Tingkat yang boleh mengajukan belum diatur</b>, jadi belum ada siswa yang dihitung.'
            . ($bolehPengaturan ? ' <a href="' . site_url('admin/pkl/pengaturan') . '" class="font-semibold underline">Atur di Pengaturan PKL</a>.' : '')]) ?>
    <?php endif; ?>

    <!-- Empat angka utama -->
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Ringkasan siswa">
        <?php foreach ([
            ['Sudah mengisi', $ringkas['sudah_isi'], 'text-brand-700 dark:text-brand-400', 'sudah_mengisi', $ringkas['persen_isi'] . '% dari ' . $ringkas['total']],
            ['Belum mengisi', $ringkas['belum_isi'], 'text-gray-900 dark:text-white', 'belum_mengisi', 'belum + ditolak'],
            ['Sudah PKL', $ringkas['sudah_pkl'], 'text-green-700 dark:text-green-400', 'sudah_pkl', $ringkas['persen_pkl'] . '% (sudah disetujui)'],
            ['Belum PKL', $ringkas['belum_pkl'], 'text-yellow-700 dark:text-yellow-400', '', 'belum dapat tempat'],
        ] as [$label, $nilai, $warna, $saring, $ket]): ?>
            <a href="<?= esc($urlSaring($saring !== '' ? ['fase' => $saring] : []), 'attr') ?>" class="kartu kartu-isi kartu-tautan">
                <p class="stat-label text-sm"><?= esc($label) ?></p>
                <p class="stat-angka mt-2 tabular-nums <?= $warna ?>"><?= (int) $nilai ?></p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc($ket) ?></p>
            </a>
        <?php endforeach; ?>
    </section>

    <!-- Rincian status -->
    <nav class="flex flex-wrap gap-2" aria-label="Rincian status siswa">
        <?php foreach (['belum' => 'Belum mengisi', 'ditolak' => 'Ditolak', 'menunggu' => 'Menunggu ACC', 'perbaikan' => 'Perlu perbaikan', 'belum_mulai' => 'Belum mulai', 'sedang' => 'Sedang PKL', 'selesai' => 'Selesai PKL', 'pernah_ditolak' => 'Pernah ditolak'] as $kode => $label): ?>
            <a href="<?= esc($urlSaring(['fase' => $kode]), 'attr') ?>" class="tombol-saring inline-flex items-center gap-1.5"<?= $fase === $kode ? ' aria-current="true"' : '' ?>>
                <?= esc($label) ?><span class="tabular-nums opacity-90"><?= (int) ($ringkas[$kode] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="kartu overflow-hidden" aria-label="Daftar siswa">
        <div class="alat-crud">
            <form method="get" class="alat-crud-cari" role="search">
                <label class="alat-crud-kotak">
                    <span class="sr-only">Cari nama</span>
                    <?= ikon('search', '') ?>
                    <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama siswa…" class="inp">
                </label>
                <select name="kelas_id" data-autosubmit class="inp alat-crud-pilih" aria-label="Kelas">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelas as $k): ?><option value="<?= (int) $k['id'] ?>"<?= (int) $k['id'] === $kelasId ? ' selected' : '' ?>><?= esc($k['nama_kelas']) ?></option><?php endforeach; ?>
                </select>
                <select name="fase" data-autosubmit class="inp alat-crud-pilih" aria-label="Status">
                    <?php foreach ($opsiFase as $v => $l): ?><option value="<?= esc($v, 'attr') ?>"<?= $v === $fase ? ' selected' : '' ?>><?= esc($l) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Terapkan</button>
                <?php if ($adaSaring): ?><a href="<?= site_url('admin/pkl/siswa') ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a><?php endif; ?>
            </form>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-2.5 sm:px-6 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400"><b class="text-gray-900 tabular-nums dark:text-white"><?= (int) $total ?></b> siswa ditampilkan</p>
            <a href="<?= esc($urlExcel, 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh Excel (sesuai saringan)</a>
        </div>

        <?php if ($rows === []): ?>
            <?= ui_kosong(['ikon' => 'users', 'judul' => 'Tidak ada siswa yang cocok', 'teks' => $adaSaring ? 'Ubah pencarian/saringan, atau tekan Reset.' : '']) ?>
        <?php else: ?>
            <div class="hidden md:block">
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">Kelas</th>
                                <th scope="col">Status</th>
                                <th scope="col">Perusahaan</th>
                                <th scope="col">Periode</th>
                                <th scope="col" class="text-right">Ajuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="font-medium text-gray-900 dark:text-white"><a href="<?= site_url('admin/pkl/riwayat/' . (int) $r['id']) ?>" class="hover:underline"><?= esc($r['nama']) ?></a>
                                        <?php if ((int) ($r['jml_tolak'] ?? 0) > 0): ?><span class="lencana lencana-kuning ms-1">Pernah ditolak <?= (int) $r['jml_tolak'] ?>×</span><?php endif; ?></td>
                                    <td class="whitespace-nowrap"><?= esc($r['nama_kelas']) ?></td>
                                    <td><?= view('admin/pkl/_lencana', ['kode' => $r['fase'], 'siswa' => true]) ?></td>
                                    <td><?= esc($r['perusahaan_nama'] ?? '') ?: '<span class="text-gray-500 dark:text-gray-400">—</span>' ?></td>
                                    <td class="text-xs whitespace-nowrap"><?= ! empty($r['tanggal_mulai']) ? esc(IsianBantu::tanggalIndo($r['tanggal_mulai'])) . ' – ' . esc(IsianBantu::tanggalIndo($r['tanggal_selesai'])) : '—' ?></td>
                                    <td class="text-right"><?php if (! empty($r['ajuan_id'])): ?><a href="<?= site_url('admin/pkl/' . $r['ajuan_id']) ?>" class="font-mono text-xs font-semibold text-brand-700 hover:underline dark:text-brand-400"><?= esc(PklPengajuanModel::kode((int) $r['ajuan_id'])) ?></a><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <ul class="divide-y divide-gray-200 md:hidden dark:divide-gray-700">
                <?php foreach ($rows as $r): ?>
                    <li class="px-4 py-3">
                        <p class="font-semibold text-gray-900 dark:text-white"><a href="<?= site_url('admin/pkl/riwayat/' . (int) $r['id']) ?>" class="hover:underline"><?= esc($r['nama']) ?></a> <span class="text-xs font-normal text-gray-500 dark:text-gray-400">· <?= esc($r['nama_kelas']) ?></span></p>
                        <?php if ((int) ($r['jml_tolak'] ?? 0) > 0): ?><p class="mt-1"><span class="lencana lencana-kuning">Pernah ditolak <?= (int) $r['jml_tolak'] ?>×</span></p><?php endif; ?>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <?= view('admin/pkl/_lencana', ['kode' => $r['fase'], 'siswa' => true]) ?>
                            <?php if (! empty($r['ajuan_id'])): ?><a href="<?= site_url('admin/pkl/' . $r['ajuan_id']) ?>" class="font-mono text-xs font-semibold text-brand-700 dark:text-brand-400"><?= esc(PklPengajuanModel::kode((int) $r['ajuan_id'])) ?></a><?php endif; ?>
                        </div>
                        <?php if (! empty($r['perusahaan_nama'])): ?><p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?= esc($r['perusahaan_nama']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($jmlHal > 1): ?>
                <div class="kartu-kaki flex items-center justify-between gap-3 text-sm">
                    <?php if ($page > 1): ?><a href="<?= esc($urlHal($page - 1), 'attr') ?>" class="btn btn-netral btn-sm"><?= ikon('chevron-left', 'h-4 w-4') ?>Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
                    <span class="text-gray-500 dark:text-gray-400">Halaman <b class="text-gray-900 dark:text-white"><?= $page ?></b> dari <b class="text-gray-900 dark:text-white"><?= $jmlHal ?></b></span>
                    <?php if ($page < $jmlHal): ?><a href="<?= esc($urlHal($page + 1), 'attr') ?>" class="btn btn-netral btn-sm">Berikutnya<?= ikon('chevron-right', 'h-4 w-4') ?></a><?php else: ?><span></span><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?= $this->endSection() ?>
