<?php
/**
 * Pratinjau impor Master Siswa (2026-11-23, keluhan klien: Export → isi No HP orang tua → Import macet). Hanya menampilkan siswa yang
 * BERUBAH (nilai lama → baru) dan siswa BARU; siswa yang datanya sama dilewati. Dirender server tanpa Alpine sehingga ribuan baris
 * tetap ringan; siap CSP. Rencana simpan ada di berkas sementara server (writable/impor/siswa_<token>.json) — tombol Simpan cukup
 * mengirim token (tidak kena batas max_input_vars). Logika: Admin\Master\Siswa::importPreview / importCommit, App\Libraries\SiswaImpor.
 *
 * @var list<array<string, mixed>> $item      perubahan: jenis ubah|baru, nis, nama, data [kolom => nilai baru], lama [kolom => nilai lama], pulih
 * @var int                        $sama      jumlah siswa yang datanya sama (dilewati)
 * @var list<string>               $galat     catatan / baris yang dilewati
 * @var array                      $baca      lembar, baris, siswa, kolom (kunci kolom yang dikenali)
 * @var array<string, string>      $label     kunci kolom → label
 * @var array<int, string>         $kelasNama id kelas → nama kelas
 * @var string                     $token
 * @var string                     $namaFile
 * @var string                     $commitUrl
 * @var string                     $backUrl
 */
$ubah   = array_values(array_filter($item, static fn (array $it): bool => $it['jenis'] === 'ubah'));
$baru   = array_values(array_filter($item, static fn (array $it): bool => $it['jenis'] === 'baru'));
$tampil = static function (string $kolom, mixed $v) use ($kelasNama): string {
    if ($v === null || $v === '') {
        return '<span class="italic text-gray-500 dark:text-gray-400">(kosong)</span>';
    }

    return esc($kolom === 'kelas_id' ? ($kelasNama[(int) $v] ?? '#' . $v) : (string) $v);
};
$maksGalat = 50;
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="space-y-4">
    <div class="kartu">
        <div class="kartu-kepala">
            <div class="min-w-0">
                <h2 class="kartu-judul">Pratinjau Impor Siswa</h2>
                <p class="kartu-ket">Berkas <b><?= esc($namaFile) ?></b>: <?= (int) $baca['lembar'] ?> lembar dibaca, <?= (int) $baca['siswa'] ?> siswa. Yang disimpan <b>hanya data yang berubah</b> di bawah ini — sel kosong di Excel tidak mengubah data lama. Periksa dulu, lalu tekan <b>Simpan</b>.</p>
            </div>
        </div>
        <div class="kartu-isi flex flex-wrap gap-2">
            <span class="lencana lencana-kuning"><?= count($ubah) ?> siswa berubah</span>
            <span class="lencana lencana-hijau"><?= count($baru) ?> siswa baru</span>
            <span class="lencana lencana-abu"><?= (int) $sama ?> sama (dilewati)</span>
            <?php if ($galat !== []): ?><span class="lencana lencana-merah"><?= count($galat) ?> catatan</span><?php endif; ?>
        </div>
    </div>

    <?php if ($galat !== []): ?>
        <?= ui_peringatan('awas', '', ['html' => '<b>Catatan — baris/kolom berikut tidak ikut disimpan:</b><ul class="mt-1 list-disc space-y-1 ps-5">'
            . implode('', array_map(static fn (string $g): string => '<li>' . esc($g) . '</li>', array_slice($galat, 0, $maksGalat)))
            . (count($galat) > $maksGalat ? '<li>… dan ' . (count($galat) - $maksGalat) . ' catatan lainnya.</li>' : '') . '</ul>']) ?>
    <?php endif; ?>

    <?php if ($item === []): ?>
        <div class="kartu">
            <?= ui_kosong(['ikon' => 'check-circle', 'judul' => 'Tidak ada perubahan',
                'teks' => 'Semua data di berkas sama dengan data sekarang. Isi atau ubah kolom yang perlu (mis. No. Telepon Orang Tua) di Excel, simpan berkasnya, lalu Import lagi.',
                'aksi' => '<a href="' . esc($backUrl, 'attr') . '" class="btn btn-netral">Kembali ke Master Siswa</a>']) ?>
        </div>
    <?php else: ?>

        <?php if ($ubah !== []): ?>
            <section class="kartu" aria-labelledby="judul-ubah">
                <div class="kartu-kepala">
                    <div>
                        <h2 id="judul-ubah" class="kartu-judul">Siswa yang berubah (<?= count($ubah) ?>)</h2>
                        <p class="kartu-ket">Kolom yang berubah saja, ditulis <b>lama → baru</b>.</p>
                    </div>
                </div>
                <div class="tabel-bungkus">
                    <table class="tabel-crud">
                        <thead>
                            <tr>
                                <th scope="col" class="w-12 text-center">#</th>
                                <th scope="col">NIS</th>
                                <th scope="col">Nama</th>
                                <th scope="col">Perubahan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ubah as $i => $it): ?>
                                <tr class="align-top">
                                    <td class="sel-ket text-center"><?= $i + 1 ?></td>
                                    <td class="whitespace-nowrap font-mono"><?= esc($it['nis']) ?></td>
                                    <td class="sel-judul"><?= esc($it['nama']) ?></td>
                                    <td>
                                        <?php if (! empty($it['pulih'])): ?><span class="lencana lencana-biru mb-1">dipulihkan dari data terhapus</span><?php endif; ?>
                                        <ul class="space-y-1">
                                            <?php foreach ($it['data'] as $kolom => $nilai): ?>
                                                <li><span class="text-gray-500 dark:text-gray-400"><?= esc($label[$kolom] ?? $kolom) ?>:</span>
                                                    <?= $tampil($kolom, $it['lama'][$kolom] ?? null) ?> → <b class="text-gray-900 dark:text-white"><?= $tampil($kolom, $nilai) ?></b></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($baru !== []): ?>
            <section class="kartu" aria-labelledby="judul-baru">
                <div class="kartu-kepala">
                    <div>
                        <h2 id="judul-baru" class="kartu-judul">Siswa baru (<?= count($baru) ?>)</h2>
                        <p class="kartu-ket">NIS ini belum ada di Master Siswa dan akan <b>ditambahkan</b>. Bila seharusnya siswa lama, periksa NIS-nya di Excel.</p>
                    </div>
                </div>
                <div class="tabel-bungkus">
                    <table class="tabel-crud">
                        <thead>
                            <tr>
                                <th scope="col" class="w-12 text-center">#</th>
                                <th scope="col">NIS</th>
                                <th scope="col">Nama</th>
                                <th scope="col">JK</th>
                                <th scope="col">Kelas</th>
                                <th scope="col">Kolom terisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baru as $i => $it): $d = $it['data']; ?>
                                <tr>
                                    <td class="sel-ket text-center"><?= $i + 1 ?></td>
                                    <td class="whitespace-nowrap font-mono"><?= esc($it['nis']) ?></td>
                                    <td class="sel-judul"><?= esc($it['nama']) ?></td>
                                    <td><?= esc($d['jenis_kelamin'] ?? '—') ?></td>
                                    <td class="whitespace-nowrap"><?= isset($d['kelas_id']) ? esc($kelasNama[(int) $d['kelas_id']] ?? '—') : '—' ?></td>
                                    <td class="sel-ket"><?= count($d) ?> kolom</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <form method="post" action="<?= esc($commitUrl, 'attr') ?>" class="flex flex-wrap items-center justify-end gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
            <p class="basis-full text-sm text-gray-500 sm:basis-auto sm:me-auto dark:text-gray-400">Pratinjau berlaku 2 jam. Bila Excel diubah lagi, Import ulang berkasnya.</p>
            <a href="<?= esc($backUrl, 'attr') ?>" class="btn btn-netral">Batal</a>
            <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?>Simpan <?= count($item) ?> perubahan</button>
        </form>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
