<?php
/**
 * Sistem → Log Error Hosting (docs/DESAIN-ALAT-SISTEM.md F1). Atas: isi satu berkas log — galat yang sama digabung, dilipat
 * (<details> kit, tanpa JS), penjelasan bahasa awam bila dikenal. Bawah: daftar semua berkas + jumlah per tingkat.
 *
 * @var array<string, array>             $berkas    LogGalat::berkas()
 * @var array<string, array<string,int>> $hitung    kunci => tingkat => jumlah
 * @var string                           $kunci     berkas terpilih ('' = belum ada log)
 * @var string                           $tingkat
 * @var string                           $q
 * @var list<array>                      $kelompok  LogGalat::kelompok()
 * @var int                              $jumlah    kejadian sesudah disaring
 * @var bool                             $terpotong hanya ujung berkas yang dibaca
 */
use App\Libraries\IsianBantu;
use App\Libraries\LogGalat;

$pilih     = $berkas[$kunci] ?? null;
$adaSaring = $tingkat !== '' || $q !== '';
$ikonTk    = ['berat' => 'close-circle', 'galat' => 'exclamation-circle', 'peringatan' => 'exclamation-circle', 'info' => 'info-circle'];
$labelAsal = ['web' => 'halaman web', 'perintah' => 'perintah terjadwal (cron)'];
$ukuran    = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) ceil($b / 1024)) . ' KB';
// Log aplikasi = satu hari (cukup jam); error_log PHP bisa berhari-hari (tanggal + jam).
$waktu = static fn (string $w): string => $w === '' ? '—' : (($pilih['jenis'] ?? '') === 'php' ? date('d-m-Y H:i:s', strtotime($w)) : substr($w, 11));
?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('aksi') ?>
<a href="<?= site_url('admin/sistem/log-error/bersihkan') ?>" class="btn btn-bahaya" data-method="post"
   data-confirm="Hapus log aplikasi yang lebih lama dari <?= LogGalat::SIMPAN_HARI ?> hari? Log yang lebih baru dan error_log PHP tidak dihapus." data-confirm-judul="Bersihkan log lama?" data-confirm-ya="Ya, bersihkan" data-confirm-jenis="bahaya"><?= ikon('trash-bin', 'h-4 w-4') ?>Bersihkan &gt;<?= LogGalat::SIMPAN_HARI ?> hari</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'log_error',
    'helpTitle' => 'Log Error Hosting',
    'helpBody'  => '<p>Catatan galat <b>asli dari server hosting</b>, tanpa perlu membuka cPanel. Galat yang sama digabung menjadi satu baris beserta berapa kali terjadi dan jamnya. Untuk galat yang sudah dikenal ada penjelasan singkat (mis. database hosting sesaat mati = bukan kesalahan aplikasi). Tombol <b>Unduh</b> menyimpan berkas aslinya untuk dikirim ke pengembang.</p>',
]) ?>

<?php if ($pilih === null): ?>
    <section class="kartu">
        <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Belum ada log galat', 'teks' => 'Server belum mencatat galat apa pun.']) ?>
    </section>
<?php else: ?>

<section class="kartu overflow-hidden" aria-labelledby="judul-isi-log">
    <div class="kartu-kepala">
        <div class="min-w-0">
            <h2 id="judul-isi-log" class="kartu-judul"><?= esc($pilih['label']) ?></h2>
            <p class="kartu-ket"><?= (int) $jumlah ?> kejadian · <?= count($kelompok) ?> jenis galat · <?= esc($ukuran($pilih['ukuran'])) ?> · diperbarui <?= esc(date('d-m-Y H:i', $pilih['diubah'])) ?></p>
        </div>
        <a href="<?= site_url('admin/sistem/log-error/unduh') . '?berkas=' . rawurlencode($kunci) ?>" class="btn btn-netral btn-sm"><?= ikon('download', 'h-4 w-4') ?>Unduh</a>
    </div>

    <div class="alat-crud">
        <form method="get" class="alat-crud-cari" role="search">
            <select name="berkas" data-autosubmit class="inp alat-crud-pilih" aria-label="Berkas log">
                <?php foreach ($berkas as $k => $b): ?>
                    <?php $n = $hitung[$k]['berat'] + $hitung[$k]['galat']; ?>
                    <option value="<?= esc($k, 'attr') ?>"<?= $k === $kunci ? ' selected' : '' ?>><?= esc($b['jenis'] === 'php' ? $b['label'] : IsianBantu::tanggalIndo($b['tanggal'])) ?> (<?= $n ?> galat)</option>
                <?php endforeach; ?>
            </select>
            <select name="tingkat" data-autosubmit class="inp alat-crud-pilih" aria-label="Tingkat">
                <option value="">Semua tingkat</option>
                <?php foreach (LogGalat::TINGKAT as $k => [$label]): ?>
                    <option value="<?= $k ?>"<?= $tingkat === $k ? ' selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="alat-crud-kotak">
                <span class="sr-only">Cari kata di galat</span>
                <?= ikon('search', '') ?>
                <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari kata…" class="inp">
            </label>
            <button type="submit" class="btn btn-netral"><?= ikon('search', 'h-4 w-4') ?>Cari</button>
            <?php if ($adaSaring): ?><a href="<?= site_url('admin/sistem/log-error') . '?berkas=' . rawurlencode($kunci) ?>" class="btn btn-teks"><?= ikon('close', 'h-4 w-4') ?>Reset</a><?php endif; ?>
        </form>
    </div>

    <div class="kartu-isi space-y-3">
        <?php if ($terpotong): ?>
            <?= ui_peringatan('awas', 'Berkas ini besar, jadi hanya bagian akhirnya (± ' . $ukuran(LogGalat::MAKS_BACA) . ' terakhir) yang ditampilkan. Unduh untuk melihat seluruh isinya.') ?>
        <?php endif; ?>

        <?php if ($kelompok === []): ?>
            <?= ui_kosong(['ikon' => 'check-circle', 'judul' => $adaSaring ? 'Tidak ada galat yang cocok' : 'Berkas ini tidak berisi galat',
                'teks' => $adaSaring ? 'Ubah saringan atau tekan Reset.' : '']) ?>
        <?php endif; ?>

        <?php foreach ($kelompok as $i => $g): ?>
            <?php
            [$labelTk, $lencanaTk] = LogGalat::TINGKAT[$g['tingkat']];
            $ringkas = $labelTk . ' · ' . $g['jumlah'] . '× · terakhir ' . $waktu($g['terakhir']);
            ?>
            <?= ui_lipat_buka(['judul' => mb_strimwidth($g['pesan'], 0, 160, '…'), 'ringkas' => $ringkas, 'ikon' => $ikonTk[$g['tingkat']], 'id' => 'galat-' . ($i + 1)]) ?>
                <?php if ($g['penjelasan'] !== null): ?>
                    <?= ui_peringatan('info', $g['penjelasan'], ['judul' => 'Artinya']) ?>
                <?php endif; ?>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500 dark:text-gray-400">Tingkat</dt><dd><span class="lencana <?= $lencanaTk ?>"><?= esc($labelTk) ?></span></dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Terjadi</dt><dd class="font-medium text-gray-900 dark:text-white"><?= (int) $g['jumlah'] ?> kali</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Pertama · terakhir</dt><dd class="tabular-nums"><?= esc($waktu($g['pertama'])) ?> · <?= esc($waktu($g['terakhir'])) ?></dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Dari</dt><dd><?= esc(implode(', ', array_map(static fn (string $a): string => $labelAsal[$a] ?? $a, $g['asal']))) ?></dd></div>
                </dl>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pesan lengkap</p>
                    <p class="mt-1 font-mono text-xs break-words text-gray-900 dark:text-white"><?= esc($g['pesan']) ?></p>
                </div>
                <?php if ($g['jumlah'] > 1): ?>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Jam kejadian<?= $g['jumlah'] > LogGalat::MAKS_JAM ? ' (' . LogGalat::MAKS_JAM . ' terbaru)' : '' ?></p>
                        <p class="mt-1 text-xs tabular-nums text-gray-700 dark:text-gray-300"><?= esc(implode(' · ', array_map($waktu, $g['jam']))) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($g['jejak'] !== ''): ?>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Jejak (kejadian terakhir) — untuk pengembang</p>
                        <pre class="mt-1 max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs leading-relaxed text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"><?= esc($g['jejak']) ?></pre>
                    </div>
                <?php endif; ?>
            <?= ui_lipat_tutup() ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="kartu overflow-hidden" aria-labelledby="judul-berkas-log">
    <div class="kartu-kepala">
        <h2 id="judul-berkas-log" class="kartu-judul flex items-center gap-2">Semua berkas log<span class="lencana lencana-abu"><?= count($berkas) ?></span></h2>
    </div>
    <div class="tabel-bungkus">
        <table class="tabel">
            <thead>
                <tr>
                    <th scope="col">Berkas</th>
                    <th scope="col" class="text-end">Galat berat</th>
                    <th scope="col" class="text-end">Galat</th>
                    <th scope="col" class="text-end">Peringatan</th>
                    <th scope="col" class="text-end">Ukuran</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($berkas as $k => $b): ?>
                    <tr<?= $k === $kunci ? ' aria-current="true" class="bg-gray-50 dark:bg-gray-800"' : '' ?>>
                        <td class="font-medium text-gray-900 dark:text-white"><?= esc($b['label']) ?></td>
                        <?php foreach (['berat', 'galat', 'peringatan'] as $t): ?>
                            <td class="text-end tabular-nums"><?= $hitung[$k][$t] > 0 ? '<span class="lencana ' . LogGalat::TINGKAT[$t][1] . '">' . $hitung[$k][$t] . '</span>' : '<span class="text-gray-400">0</span>' ?></td>
                        <?php endforeach; ?>
                        <td class="text-end text-xs whitespace-nowrap tabular-nums"><?= esc($ukuran($b['ukuran'])) ?></td>
                        <td class="whitespace-nowrap text-end">
                            <a href="<?= site_url('admin/sistem/log-error') . '?berkas=' . rawurlencode($k) ?>" class="btn btn-teks btn-sm"><?= ikon('eye', 'h-4 w-4') ?>Lihat</a>
                            <a href="<?= site_url('admin/sistem/log-error/unduh') . '?berkas=' . rawurlencode($k) ?>" class="btn btn-teks btn-sm" aria-label="Unduh <?= esc($b['label'], 'attr') ?>"><?= ikon('download', 'h-4 w-4') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php endif; ?>

<?= $this->endSection() ?>
