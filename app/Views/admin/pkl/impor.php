<?php
/**
 * Impor riwayat PKL lama dari Excel (Operator/Admin): format contoh, atau format sekolah "DATA SISWA PRAKERIN"
 * (satu lembar per kelas — sama dengan unduhan menu Data Prakerin).
 * Rombak tampilan F9: tab kit (_nav), kartu kit per langkah, area unggah = komponen kit "pilihBerkas", kartu angka, tabel kit,
 * ui_peringatan untuk baris bermasalah / beda mitra / nama mirip, rincian <details> berbingkai kit, tombol kit ber-ikon;
 * label "Menyimpan…" lewat x-show (teks ditulis server).
 *
 * @var ?array  $pratinjau  hasil PklImpor::baca (null = belum unggah)
 * @var string  $token
 * @var array<string,int> $hitungTab
 */

use App\Libraries\IsianBantu;

$sekolah    = $pratinjau !== null && ($pratinjau['format'] ?? '') === 'sekolah';
$labelBaris = static fn (array $g): string => isset($g['lembar']) ? $g['lembar'] . ' · baris ' . (int) $g['baris'] : 'Baris ' . (int) $g['baris'];
$periode    = static fn (array $k): string => $k['mulai'] !== null || $k['selesai'] !== null
    ? IsianBantu::tanggalIndo($k['mulai']) . ' – ' . IsianBantu::tanggalIndo($k['selesai'])
    : 'tanpa tanggal';
$netral = 'text-gray-900 dark:text-white';
$redup  = 'text-gray-400 dark:text-gray-500';
// Kartu angka pratinjau.
$tile = static fn (string $label, int $angka, string $warna, string $ket = ''): string => '<div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">'
    . '<p class="text-xs font-medium text-gray-500 dark:text-gray-400">' . esc($label) . '</p>'
    . '<p class="stat-angka mt-1 tabular-nums ' . $warna . '">' . $angka . '</p>'
    . ($ket !== '' ? '<p class="text-xs text-gray-500 dark:text-gray-400">' . esc($ket) . '</p>' : '') . '</div>';
// Daftar baris yang tidak diimpor.
$daftarGalat = static fn (array $galat): string => ui_peringatan('galat', '', ['judul' => 'Baris ini TIDAK akan diimpor — perbaiki di Excel lalu unggah lagi bila perlu:',
    'html' => '<ul class="max-h-56 space-y-1 overflow-y-auto">' . implode('', array_map(static fn (array $g): string => '<li><b>' . esc($labelBaris($g)) . ':</b> ' . esc($g['pesan']) . '</li>', $galat)) . '</ul>']);
$tombolSimpan = static fn (string $label): string => '<button type="submit" class="btn btn-sukses" :disabled="proses">' . ikon('check', 'h-4 w-4')
    . '<span x-show="!proses">' . esc($label) . '</span><span x-show="proses" x-cloak>Menyimpan…</span></button>';
$kotakDetail = 'rounded-lg border border-gray-200 dark:border-gray-700';
$ringkasDetail = 'cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white';
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'pkl_impor_v3',
    'helpTitle' => 'Impor Riwayat PKL',
    'helpBody'  => '<p>Untuk memasukkan data siswa yang <b>sudah dapat tempat PKL di luar sistem</b>, supaya tercatat sudah PKL, tampil di Data Prakerin, dan tidak bisa mengajukan lagi.</p>'
        . '<ul class="mt-2 list-disc pl-5 space-y-1">'
        . '<li><b>Format sekolah "DATA SISWA PRAKERIN"</b> (satu lembar per kelas, kolom NO, NAMA, Mitra DI/DU, Mulai, Selesai, Lamanya PKL) bisa langsung diunggah. Nama lembar = nama kelas; siswa dikenali dari <b>nama</b> di kelas itu.</li>'
        . '<li>Atau pakai <b>contoh berkas</b>: satu baris per siswa, siswa dikenali dari <b>NIS</b> (atau Nama + Kelas).</li>'
        . '<li>Anda melihat <b>pratinjau dulu</b>; belum ada yang tersimpan sampai menekan Simpan. Hasilnya berstatus Disetujui dan ditandai <b>"diimpor"</b> — bukan persetujuan (ACC) lewat sistem.</li>'
        . '<li>Siswa yang sudah punya ajuan PKL di sistem <b>tidak diubah</b>. Mengunggah berkas yang sama dua kali tidak menggandakan data.</li>'
        . '</ul>',
]) ?>

<?= view('admin/pkl/_nav', ['tab' => 'impor', 'hitungTab' => $hitungTab]) ?>

<div class="mx-auto max-w-4xl space-y-4">
    <section class="kartu" aria-labelledby="judul-impor-1">
        <div class="kartu-kepala"><h2 id="judul-impor-1" class="kartu-judul">1. Siapkan &amp; unggah berkas</h2></div>
        <div class="kartu-isi space-y-4">
            <ul class="space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                <li><b class="text-gray-900 dark:text-white">Format sekolah "DATA SISWA PRAKERIN"</b> — satu lembar per kelas (nama lembar = nama kelas), kolom <b>NO, NAMA, Mitra DI/DU, Mulai, Selesai, Lamanya PKL</b>. Baris tanpa Mitra dilewati. <a href="<?= site_url('admin/pkl/prakerin') ?>" class="font-semibold text-brand-700 hover:underline dark:text-brand-400">Unduh format ini dari Data Prakerin</a></li>
                <li><b class="text-gray-900 dark:text-white">Format contoh</b> — kolom <b>NIS, Nama, Kelas, Perusahaan, Alamat, Kota, Telepon, Kontak, Jabatan, Mulai, Selesai</b>. Wajib: Perusahaan, dan NIS (atau Nama + Kelas).</li>
            </ul>
            <form method="post" action="<?= site_url('admin/pkl/impor/pratinjau') ?>" enctype="multipart/form-data" class="space-y-3">
                <?= csrf_field() ?>
                <label x-data="pilihBerkas" data-kosong="Belum ada berkas dipilih"
                       class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-5 text-center transition-colors focus-within:ring-4 focus-within:ring-brand-300 hover:border-brand-400 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700/50 dark:hover:border-brand-500 dark:hover:bg-gray-700">
                    <?= ikon('upload', 'h-7 w-7 text-gray-500 dark:text-gray-400') ?>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Pilih berkas Excel (.xlsx, .xls, .csv)</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="namaBerkasTerpilih()">Belum ada berkas dipilih</span>
                    <input type="file" name="berkas" accept=".xlsx,.xls,.csv" required class="sr-only" @change="catatBerkas($event)">
                </label>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                    <a href="<?= site_url('admin/pkl/impor/contoh') ?>" class="btn btn-netral"><?= ikon('download', 'h-4 w-4') ?>Unduh contoh berkas</a>
                    <button type="submit" class="btn btn-utama"><?= ikon('eye', 'h-4 w-4') ?>Lihat pratinjau</button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($pratinjau !== null && ! $sekolah): $r = $pratinjau['ringkas']; ?>
        <section class="kartu" aria-labelledby="judul-impor-2">
            <div class="kartu-kepala"><h2 id="judul-impor-2" class="kartu-judul">2. Pratinjau</h2></div>
            <div class="kartu-isi space-y-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <?= $tile('Baris dibaca', (int) $r['baris'], $netral) ?>
                    <?= $tile('Ajuan akan dibuat', (int) $r['kelompok'], 'text-green-700 dark:text-green-400') ?>
                    <?= $tile('Siswa tercatat PKL', (int) $r['siswa'], 'text-brand-700 dark:text-brand-400') ?>
                    <?= $tile('Baris bermasalah', (int) $r['galat'], $r['galat'] > 0 ? 'text-red-700 dark:text-red-400' : $redup) ?>
                </div>

                <?php if ($pratinjau['galat'] !== []): ?><?= $daftarGalat($pratinjau['galat']) ?><?php endif; ?>

                <?php if ($pratinjau['kelompok'] !== []): ?>
                    <div class="max-h-96 divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                        <?php foreach ($pratinjau['kelompok'] as $k): ?>
                            <div class="px-4 py-3">
                                <p class="font-semibold text-gray-900 dark:text-white"><?= esc($k['perusahaan']) ?> <span class="text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc($k['kota']) ?></span></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?= esc(IsianBantu::tanggalIndo($k['mulai'])) ?> – <?= esc(IsianBantu::tanggalIndo($k['selesai'])) ?></p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300"><?= esc(implode(', ', array_map(static fn ($s) => $s['nama'] . ' (' . $s['kelas'] . ')', $k['siswa']))) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" action="<?= site_url('admin/pkl/impor/simpan') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                          x-data="{ proses: false }" @submit="proses = true">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
                        <p class="text-sm text-gray-500 sm:me-auto dark:text-gray-400">Hasilnya berstatus <b>Disetujui</b>; siswanya langsung terkunci dan tak bisa mengajukan lagi.</p>
                        <?= $tombolSimpan('Simpan ' . (int) $r['kelompok'] . ' ajuan') ?>
                    </form>
                <?php else: ?>
                    <?= ui_kosong(['ikon' => 'file-search', 'judul' => 'Tidak ada baris yang bisa diimpor']) ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($sekolah):
        $r       = $pratinjau['ringkas'];
        $beda    = array_values(array_filter($pratinjau['sudah'], static fn (array $s): bool => $s['beda']));
        $samaMit = array_values(array_filter($pratinjau['sudah'], static fn (array $s): bool => ! $s['beda']));
        $perKls  = [];
        foreach ($pratinjau['kelompok'] as $k) {
            $perKls[$k['kelas']][] = $k;
        }
        ?>
        <section class="kartu" aria-labelledby="judul-impor-2s">
            <div class="kartu-kepala">
                <div class="min-w-0">
                    <h2 id="judul-impor-2s" class="kartu-judul">2. Pratinjau — format DATA SISWA PRAKERIN</h2>
                    <p class="kartu-ket">
                        <?= count($pratinjau['lembar']) ?> lembar (kelas)<?= ! empty($pratinjau['tahun_ajaran']) ? ' · Tahun Pelajaran <b>' . esc($pratinjau['tahun_ajaran']) . '</b>' : '' ?>
                        · satu ajuan berisi siswa sekelas dengan mitra &amp; tanggal yang sama, paling banyak <?= (int) $pratinjau['maks_siswa'] ?> siswa.
                    </p>
                </div>
            </div>
            <div class="kartu-isi space-y-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <?= $tile('Nama dibaca', (int) $r['baris'], $netral, $r['mitra'] . ' ada mitra') ?>
                    <?= $tile('Siap diimpor', (int) $r['siswa'], 'text-green-700 dark:text-green-400', 'nama cocok dengan Data Siswa') ?>
                    <?= $tile('Nama mirip — cek dulu', (int) $r['saran'], $r['saran'] > 0 ? 'text-yellow-700 dark:text-yellow-400' : $redup, 'centang di bawah bila benar') ?>
                    <?= $tile('Sudah ada di sistem', (int) $r['sudah'], 'text-brand-700 dark:text-brand-400', count($beda) > 0 ? count($beda) . ' beda mitra — tidak diubah' : 'tidak diubah') ?>
                    <?= $tile('Belum ada mitra', (int) ($r['tanpa_mitra'] + $r['lewati']), 'text-gray-500 dark:text-gray-400', $r['lewati'] > 0 ? 'termasuk ' . $r['lewati'] . ' bertulis "PKL"/"-"' : 'kolom Mitra kosong') ?>
                    <?= $tile('Bermasalah', (int) $r['galat'], $r['galat'] > 0 ? 'text-red-700 dark:text-red-400' : $redup, 'tidak diimpor') ?>
                </div>

                <div class="tabel-bungkus rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="tabel min-w-[40rem]">
                        <thead>
                            <tr>
                                <th scope="col">Lembar</th>
                                <th scope="col">Kelas di sistem</th>
                                <th scope="col" class="text-right">Nama</th>
                                <th scope="col" class="text-right">Ada mitra</th>
                                <th scope="col" class="text-right">Siap</th>
                                <th scope="col" class="text-right">Mirip</th>
                                <th scope="col" class="text-right">Sudah ada</th>
                                <th scope="col" class="text-right">"PKL"/"-"</th>
                                <th scope="col" class="text-right">Masalah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pratinjau['lembar'] as $l): ?>
                                <tr>
                                    <td class="font-medium text-gray-900 dark:text-white"><?= esc($l['judul']) ?></td>
                                    <td><?= $l['kelas'] !== null ? esc($l['kelas']) : '<span class="font-semibold text-red-700 dark:text-red-400">tidak dikenali</span>' ?></td>
                                    <td class="text-right tabular-nums"><?= (int) $l['nama'] ?></td>
                                    <td class="text-right tabular-nums"><?= (int) $l['mitra'] ?></td>
                                    <td class="text-right font-semibold tabular-nums text-green-700 dark:text-green-400"><?= (int) $l['siap'] ?></td>
                                    <td class="text-right tabular-nums <?= $l['saran'] > 0 ? 'font-semibold text-yellow-700 dark:text-yellow-400' : $redup ?>"><?= (int) $l['saran'] ?></td>
                                    <td class="text-right tabular-nums"><?= (int) $l['sudah'] ?></td>
                                    <td class="text-right tabular-nums"><?= (int) $l['lewati'] ?></td>
                                    <td class="text-right tabular-nums <?= $l['galat'] > 0 ? 'font-semibold text-red-700 dark:text-red-400' : $redup ?>"><?= (int) $l['galat'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pratinjau['galat'] !== []): ?><?= $daftarGalat($pratinjau['galat']) ?><?php endif; ?>

                <?php if ($beda !== []): ?>
                    <?= ui_peringatan('awas', '', ['judul' => 'Sudah punya ajuan di sistem dengan mitra BERBEDA — tidak diubah (' . count($beda) . '):',
                        'html' => '<p class="text-xs">Bila Excel yang benar, ubah ajuannya lewat Kotak Masuk (buka nomor ajuannya).</p><ul class="mt-2 max-h-56 space-y-1 overflow-y-auto">'
                            . implode('', array_map(static fn (array $s): string => '<li><b>' . esc($labelBaris($s)) . ': ' . esc($s['nama']) . '</b> — di sistem <b>' . esc($s['sistem']) . '</b> (' . esc($s['kode']) . ', ' . esc($s['status']) . '), di Excel <b>' . esc($s['mitra']) . '</b></li>', $beda))
                            . '</ul>']) ?>
                <?php endif; ?>

                <?php if ($pratinjau['kelompok'] !== []): ?>
                    <form method="post" action="<?= site_url('admin/pkl/impor/simpan') ?>" class="space-y-4" x-data="{ proses: false }" @submit="proses = true">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">

                        <?php if ($pratinjau['saran'] !== []): ?>
                            <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-gray-800">
                                <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">Nama mirip — centang bila orang yang sama (<?= count($pratinjau['saran']) ?>)</p>
                                <p class="text-xs text-yellow-800 dark:text-yellow-300">Ejaan di Excel sedikit beda dengan Data Siswa. Yang <b>tidak dicentang</b> tidak diimpor.</p>
                                <ul class="mt-2 max-h-72 space-y-1.5 overflow-y-auto">
                                    <?php foreach ($pratinjau['saran'] as $s): ?>
                                        <li>
                                            <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-900 dark:text-white">
                                                <input type="checkbox" name="pakai[]" value="<?= esc($s['kunci'], 'attr') ?>"<?= $s['centang'] ? ' checked' : '' ?> class="cek mt-0.5">
                                                <span><b><?= esc($labelBaris($s)) ?>:</b> Excel "<?= esc($s['excel']) ?>" → Data Siswa "<b><?= esc($s['sistem']) ?></b>"<?= $s['kelas'] !== '' ? ' (' . esc($s['kelas']) . ')' : '' ?> · mirip <?= (int) $s['skor'] ?>% · <?= esc($s['mitra']) ?></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <details class="<?= $kotakDetail ?>">
                            <summary class="<?= $ringkasDetail ?>">Lihat ajuan yang akan dibuat (<?= count($pratinjau['kelompok']) ?>)</summary>
                            <div class="max-h-96 divide-y divide-gray-200 overflow-y-auto border-t border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                                <?php foreach ($perKls as $kls => $daftar): ?>
                                    <p class="bg-gray-50 px-4 py-1.5 text-xs font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-400"><?= esc((string) $kls) ?></p>
                                    <?php foreach ($daftar as $k): ?>
                                        <div class="px-4 py-2.5">
                                            <p class="font-semibold text-gray-900 dark:text-white"><?= esc($k['perusahaan']) ?> <span class="text-xs font-normal text-gray-500 dark:text-gray-400"><?= esc($periode($k)) ?></span></p>
                                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300"><?= esc(implode(', ', array_map(static fn ($s) => $s['nama'] . (! empty($s['saran']) ? ' (nama mirip)' : '') . (isset($s['catatan']) ? ' (' . $s['catatan'] . ')' : ''), $k['siswa']))) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </details>

                        <?php if ($pratinjau['lewati'] !== [] || $samaMit !== []): ?>
                            <details class="<?= $kotakDetail ?>">
                                <summary class="<?= $ringkasDetail ?>">Lihat yang dilewati (<?= count($pratinjau['lewati']) + count($samaMit) ?>)</summary>
                                <ul class="max-h-72 space-y-1 overflow-y-auto border-t border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                    <?php foreach ($pratinjau['lewati'] as $s): ?><li><b><?= esc($labelBaris($s)) ?>:</b> <?= esc($s['nama']) ?> — Mitra "<?= esc($s['mitra']) ?>" belum jelas</li><?php endforeach; ?>
                                    <?php foreach ($samaMit as $s): ?><li><b><?= esc($labelBaris($s)) ?>:</b> <?= esc($s['nama']) ?> — sudah ada di sistem (<?= esc($s['kode']) ?>, <?= esc($s['status']) ?>)</li><?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <p class="text-sm text-gray-500 sm:me-auto dark:text-gray-400">Hasilnya berstatus <b>Disetujui</b> (ditandai "diimpor"); siswanya langsung tercatat sudah PKL dan tak bisa mengajukan lagi.</p>
                            <?= $tombolSimpan('Simpan ke sistem') ?>
                        </div>
                    </form>
                <?php else: ?>
                    <?= ui_kosong(['ikon' => 'file-search', 'judul' => 'Tidak ada siswa baru yang bisa diimpor', 'teks' => $r['sudah'] > 0 ? 'Yang bermitra sudah tercatat di sistem.' : '']) ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
