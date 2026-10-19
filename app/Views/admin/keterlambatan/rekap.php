<?php
/**
 * Rekap Keterlambatan (menu KURIKULUM → Guru Piket) — saringan rentang tanggal & kelas (GET), ringkasan, tab Per siswa / Per kelas,
 * unduh Excel rekap dan Excel buku piket rentang (format sekolah, satu lembar per hari). Tanpa skrip (formulir GET biasa).
 *
 * @var string  $mulai
 * @var string  $selesai
 * @var ?string $pesan    rentang disesuaikan (tanggal tidak sah / terlalu panjang)
 * @var list<array{id: int, nama: string}> $kelas
 * @var int     $kelasId  0 = semua kelas
 * @var string  $tampil   siswa|kelas
 * @var array{siswa: list<array>, kelas: list<array>, total: int, jumlah_siswa: int, jumlah_hari: int} $rekap
 */

use App\Libraries\IsianBantu;

$base   = site_url('admin/keterlambatan/rekap');
$saring = array_filter(['mulai' => $mulai, 'selesai' => $selesai, 'kelas' => $kelasId ?: null]);
$qs     = static fn (array $tambah = []): string => '?' . http_build_query(array_filter($saring + $tambah));
$periode = IsianBantu::tanggalIndo($mulai) . ' s.d. ' . IsianBantu::tanggalIndo($selesai);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/help', [
    'helpKey'   => 'rekap_keterlambatan_v1',
    'helpTitle' => 'Rekap keterlambatan',
    'helpBody'  => '<p>Pilih <b>rentang tanggal</b> (bawaan: awal bulan ini sampai hari ini) dan bila perlu satu <b>kelas</b>. Tab <b>Per siswa</b> mengurutkan siswa yang paling sering terlambat; tab <b>Per kelas</b> menjumlahkan per kelas. Kelas yang dipakai = kelas siswa <b>saat dicatat</b>.</p>'
        . '<p class="mt-2"><b>Unduh buku piket</b> = Excel format sekolah, satu lembar per hari yang ada catatannya (siap cetak). <b>Unduh rekap</b> = Excel tabel per siswa dan per kelas.</p>',
]) ?>

<div class="space-y-4">
    <form method="get" action="<?= $base ?>" class="kartu kartu-isi">
        <input type="hidden" name="tampil" value="<?= esc($tampil, 'attr') ?>">
        <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,12rem))_auto]">
            <div>
                <label class="lbl" for="rk-mulai">Dari tanggal</label>
                <input type="date" id="rk-mulai" name="mulai" value="<?= esc($mulai, 'attr') ?>" max="<?= date('Y-m-d') ?>" class="inp">
            </div>
            <div>
                <label class="lbl" for="rk-selesai">Sampai tanggal</label>
                <input type="date" id="rk-selesai" name="selesai" value="<?= esc($selesai, 'attr') ?>" max="<?= date('Y-m-d') ?>" class="inp">
            </div>
            <div>
                <label class="lbl" for="rk-kelas">Kelas</label>
                <select id="rk-kelas" name="kelas" class="inp">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelas as $k): ?>
                        <option value="<?= $k['id'] ?>"<?= $k['id'] === $kelasId ? ' selected' : '' ?>><?= esc($k['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-utama"><?= ikon('search', 'h-4 w-4') ?>Tampilkan</button>
            </div>
        </div>
        <?php if ($pesan !== null): ?>
            <div class="mt-4"><?= ui_peringatan('awas', $pesan) ?></div>
        <?php endif; ?>
    </form>

    <div class="grid gap-4 sm:grid-cols-3">
        <?php foreach ([['Catatan terlambat', $rekap['total']], ['Siswa', $rekap['jumlah_siswa']], ['Hari ada catatan', $rekap['jumlah_hari']]] as [$label, $n]): ?>
            <div class="kartu kartu-isi">
                <p class="text-sm text-gray-500 dark:text-gray-400"><?= esc($label) ?></p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= number_format((int) $n, 0, ',', '.') ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="kartu min-w-0">
        <div class="kartu-kepala">
            <div>
                <h2 class="kartu-judul">Rekap <?= $tampil === 'kelas' ? 'per kelas' : 'per siswa' ?></h2>
                <p class="kartu-ket"><?= esc($periode) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= $base ?>/buku<?= $qs() ?>" class="btn btn-netral btn-sm" title="Excel format sekolah, satu lembar per hari"><?= ikon('download', 'h-4 w-4') ?>Unduh buku piket</a>
                <a href="<?= $base ?>/excel<?= $qs() ?>" class="btn btn-netral btn-sm" title="Excel tabel per siswa & per kelas"><?= ikon('download', 'h-4 w-4') ?>Unduh rekap</a>
            </div>
        </div>
        <div class="px-4 pt-3 sm:px-6">
            <?= ui_tab([
                ['label' => 'Per siswa', 'url' => $base . $qs(['tampil' => null]), 'aktif' => $tampil === 'siswa', 'jumlah' => $rekap['jumlah_siswa'], 'ikon' => 'users'],
                ['label' => 'Per kelas', 'url' => $base . $qs(['tampil' => 'kelas']), 'aktif' => $tampil === 'kelas', 'jumlah' => count($rekap['kelas']), 'ikon' => 'chalkboard'],
            ], 'Jenis rekap') ?>
        </div>

        <?php if ($rekap['total'] === 0): ?>
            <?= ui_kosong(['ikon' => 'clock', 'judul' => 'Tidak ada catatan keterlambatan', 'teks' => 'Tidak ada siswa terlambat yang dicatat pada ' . $periode . '.']) ?>
        <?php elseif ($tampil === 'kelas'): ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col" class="w-12">No</th>
                            <th scope="col">Kelas</th>
                            <th scope="col" class="text-right">Catatan terlambat</th>
                            <th scope="col" class="text-right">Jumlah siswa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap['kelas'] as $i => $k): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="sel-judul"><?= esc($k['kelas']) ?></td>
                                <td class="text-right"><?= $k['jumlah'] ?></td>
                                <td class="text-right"><?= $k['siswa'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th scope="col" class="w-12">No</th>
                            <th scope="col">Nama</th>
                            <th scope="col">Kelas</th>
                            <th scope="col" class="text-right">Jumlah</th>
                            <th scope="col">Tanggal terlambat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap['siswa'] as $i => $s): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <p class="sel-judul"><?= esc($s['nama']) ?></p>
                                    <?php if ($s['nis'] !== ''): ?><p class="sel-ket">NIS <?= esc($s['nis']) ?></p><?php endif; ?>
                                    <?php if ($s['manual']): ?><p class="mt-0.5"><span class="lencana lencana-kuning" title="Dicatat manual; siswa belum ada di Data Siswa">Belum terdaftar</span></p><?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap"><?= esc($s['kelas']) ?></td>
                                <td class="text-right"><span class="lencana lencana-merek"><?= $s['jumlah'] ?>×</span></td>
                                <td class="text-sm"><?= esc(implode(', ', array_map(static fn (string $t): string => date('d/m', strtotime($t)), $s['tanggal']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
