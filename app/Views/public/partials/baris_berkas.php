<?php
/**
 * Satu baris berkas (Dokumen sekolah publik, folder tautan berbagi): ikon jenis berkas satu warna (bukan label pelangi), judul,
 * deskripsi, ukuran/penyedia + tanggal, tombol Buka (tautan) atau Lihat + Unduh (berkas).
 * Panggil: view('public/partials/baris_berkas', ['r' => $r, 'urlLihat' => …, 'urlUnduh' => …|null, 'tanggal' => true], ['saveData' => false]).
 *
 * @var array       $r        baris dokumen (judul, deskripsi, kategori, ekstensi, tipe, ukuran, penyedia, url_eksternal, created_at)
 * @var string      $urlLihat URL penyaji berkas (tab baru)
 * @var string|null $urlUnduh null = unduhan dimatikan
 * @var bool|null   $tanggal  tampilkan tanggal unggah (bawaan: ya)
 */
helper('dokumen');

$tautan = $r['tipe'] === 'tautan';
$ikonJenis = [
    'pdf' => 'file-pdf', 'dokumen' => 'file-word', 'spreadsheet' => 'file-csv', 'presentasi' => 'file-ppt', 'gambar' => 'file-image',
    'audio' => 'file-music', 'video' => 'file-video', 'arsip' => 'file-zip',
];
$ikonBerkas = $tautan ? 'link' : ($ikonJenis[(string) $r['kategori']] ?? 'file');
$jenis      = strtoupper((string) ($r['ekstensi'] ?: ($tautan ? 'tautan' : $r['kategori'])));
$info       = array_filter([
    $jenis,
    $tautan ? 'Tautan ' . (string) $r['penyedia'] : dokumen_ukuran_manusia((int) $r['ukuran']),
    ($tanggal ?? true) ? \App\Libraries\LayananPublik::tanggal((string) $r['created_at'], false) : '',
]);
?>
<li class="baris">
    <div class="flex min-w-0 flex-1 items-start gap-3 sm:items-center">
        <span class="berkas-ikon"><?= ikon($ikonBerkas) ?></span>
        <div class="baris-isi">
            <p class="baris-judul"><?= esc($r['judul']) ?></p>
            <?php if (trim((string) ($r['deskripsi'] ?? '')) !== ''): ?>
                <p class="baris-ket"><?= esc($r['deskripsi']) ?></p>
            <?php endif; ?>
            <p class="baris-info"><?= esc(implode(' · ', $info)) ?></p>
        </div>
    </div>
    <div class="baris-aksi pl-14 sm:pl-0">
        <?php if ($tautan): ?>
            <a href="<?= esc((string) $r['url_eksternal'], 'attr') ?>" target="_blank" rel="noopener" class="tombol tombol-netral tombol-kecil"><?= ikon('arrow-up-right-from-square') ?>Buka</a>
        <?php else: ?>
            <a href="<?= esc($urlLihat, 'attr') ?>" target="_blank" rel="noopener" class="tombol tombol-netral tombol-kecil"><?= ikon('eye') ?>Lihat</a>
            <?php if ($urlUnduh !== null): ?>
                <a href="<?= esc($urlUnduh, 'attr') ?>" class="tombol tombol-utama tombol-kecil"><?= ikon('download') ?>Unduh</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</li>
