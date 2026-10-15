<?php

/**
 * Penampil isi dokumen untuk PANEL ADMIN (F10, gaya kit). Perilakunya sama persis dengan penampil halaman publik berbagi
 * (partials/dokumen_penampil.php, masih gaya publik Tailwind v3) karena keduanya memakai komponen Alpine yang sama:
 * `penampilDokumen` di assets/js/dokumen-penampil.js (dulu fungsi global di skrip sebaris). Isi Excel/Word dipasang lewat JS
 * ke [data-isi-dokumen] (pengganti x-html). Skrip pustaka & komponen ber-nonce. Video YouTube: alamat dirakit di PHP;
 * frame-src mengizinkan youtube-nocookie (Config\ContentSecurityPolicy).
 *
 * @var string      $urlIsi          URL penyaji berkas (inline)
 * @var string      $urlUnduh        URL unduhan
 * @var string      $kategori        pdf|dokumen|spreadsheet|presentasi|gambar|audio|video|arsip|lainnya
 * @var string      $ext             ekstensi berkas (huruf kecil)
 * @var string      $judul
 * @var string      $mime
 * @var bool        $tautan          dokumen ini berupa tautan eksternal?
 * @var string|null $ytId            id video YouTube (bila tautan YouTube)
 * @var string|null $urlEksternal
 * @var string      $penyedia
 * @var bool        $bolehViewerLuar tampilkan tombol Office Viewer?
 * @var string|null $urlViewerLuar
 * @var bool        $bolehUnduh      tombol unduh ditampilkan?
 */
$bolehUnduh      = $bolehUnduh      ?? true;
$bolehViewerLuar = $bolehViewerLuar ?? false;
$ytId            = $ytId            ?? null;
$penyedia        = $penyedia        ?? 'lainnya';
$pakaiPustaka    = ! $tautan && in_array($kategori, ['spreadsheet', 'dokumen'], true);
$urlYoutube      = $ytId !== null ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($ytId) : '';
$tombolUnduh     = $bolehUnduh ? '<a href="' . esc($urlUnduh, 'attr') . '" class="btn btn-utama mt-3">' . ikon('download', 'h-4 w-4') . 'Unduh berkas</a>' : '';
$kosong = static fn (string $ikon, string $judul, string $teks, string $aksi = ''): string => ui_kosong(['ikon' => $ikon, 'judul' => $judul, 'teks' => $teks, 'aksi' => $aksi]);
?>

<?php if ($pakaiPustaka): ?>
    <?php if ($kategori === 'spreadsheet' || $ext === 'csv'): ?>
        <script defer src="<?= base_url('assets/js/vendor/xlsx.full.min.js') ?>" <?= csp_script_nonce() ?>></script>
    <?php endif; ?>
    <?php if ($ext === 'docx'): ?>
        <script defer src="<?= base_url('assets/js/vendor/mammoth.browser.min.js') ?>" <?= csp_script_nonce() ?>></script>
    <?php endif; ?>
<?php endif; ?>
<?php if (! defined('DOK_PENAMPIL_JS')) { define('DOK_PENAMPIL_JS', true); ?>
    <script src="<?= base_url('assets/js/dokumen-penampil.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/dokumen-penampil.js') ?>" <?= csp_script_nonce() ?>></script>
<?php } ?>

<div x-data="penampilDokumen" data-kategori="<?= esc($kategori, 'attr') ?>" data-ext="<?= esc($ext, 'attr') ?>" data-url="<?= esc($urlIsi, 'attr') ?>">

    <?php if ($tautan && $ytId !== null): ?>
        <div class="aspect-video bg-gray-900">
            <iframe class="h-full w-full" src="<?= esc($urlYoutube, 'attr') ?>" title="<?= esc($judul, 'attr') ?>" allowfullscreen
                    allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"></iframe>
        </div>

    <?php elseif ($tautan): ?>
        <?= $kosong('arrow-up-right-from-square', 'Dokumen ini berupa tautan ke ' . $penyedia, (string) ($urlEksternal ?? ''),
            '<a href="' . esc((string) ($urlEksternal ?? ''), 'attr') . '" target="_blank" rel="noopener" class="btn btn-utama">' . ikon('arrow-up-right-from-square', 'h-4 w-4') . 'Buka tautan</a>') ?>

    <?php elseif ($kategori === 'pdf'): ?>
        <!-- Penampil mengisi lebar penuh halaman, jadi tingginya ikut dinaikkan supaya satu halaman A4 terbaca tanpa perlu digulir. -->
        <iframe src="<?= esc($urlIsi, 'attr') ?>#view=FitH" class="h-[85vh] min-h-[520px] w-full bg-gray-100 dark:bg-gray-700" title="<?= esc($judul, 'attr') ?>"></iframe>

    <?php elseif ($kategori === 'gambar' && ! in_array($ext, ['heic', 'heif', 'svg'], true)): ?>
        <div class="flex min-h-[50vh] items-center justify-center bg-gray-900">
            <img src="<?= esc($urlIsi, 'attr') ?>" alt="<?= esc($judul, 'attr') ?>" class="max-h-[78vh] max-w-full object-contain">
        </div>

    <?php elseif ($kategori === 'gambar'): ?>
        <?= $kosong('image', 'Pratinjau ' . strtoupper($ext) . ' tidak tersedia di peramban', $ext === 'svg'
            ? 'Berkas SVG sengaja tidak ditampilkan langsung karena bisa memuat skrip. Unduh untuk membukanya.'
            : 'Foto iPhone (HEIC) belum didukung peramban. Berkasnya tetap utuh — unduh untuk membukanya.', $tombolUnduh) ?>

    <?php elseif ($kategori === 'audio'): ?>
        <div class="flex flex-col items-center gap-4 p-8">
            <?= ikon('bullhorn', 'h-12 w-12 text-brand-300 dark:text-brand-500') ?>
            <audio controls preload="metadata" class="w-full max-w-md"><source src="<?= esc($urlIsi, 'attr') ?>" type="<?= esc($mime, 'attr') ?>"></audio>
        </div>

    <?php elseif ($kategori === 'video'): ?>
        <div class="bg-gray-900">
            <video controls preload="metadata" class="max-h-[78vh] w-full"><source src="<?= esc($urlIsi, 'attr') ?>" type="<?= esc($mime, 'attr') ?>"></video>
        </div>

    <?php elseif ($pakaiPustaka): ?>
        <div class="p-4">
            <div x-show="status === 'muat'" class="py-16 text-center text-sm text-gray-500 dark:text-gray-400">
                <span class="mx-auto block h-6 w-6 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" aria-hidden="true"></span>
                <p class="mt-2">Membuka isi berkas…</p>
            </div>

            <div x-show="status === 'gagal'" x-cloak class="py-12 text-center">
                <p class="text-sm font-medium text-gray-900 dark:text-white">Isi berkas tidak bisa ditampilkan di sini</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="pesan"></p>
                <?= $tombolUnduh ?>
            </div>

            <div x-show="status === 'siap' && lembar.length > 1" x-cloak class="mb-3 flex flex-wrap gap-1.5 border-b border-gray-200 pb-2 dark:border-gray-700">
                <template x-for="(nama, i) in lembar" :key="nama">
                    <button type="button" class="tombol-saring" @click="pilihLembar(i)" :aria-pressed="i === lembarAktif" x-text="nama"></button>
                </template>
            </div>

            <div x-show="status === 'siap'" x-cloak class="dok-pratinjau max-h-[72vh] overflow-auto text-gray-700 dark:text-gray-200" data-isi-dokumen></div>
        </div>

    <?php else: ?>
        <?php
        $aksiLain = $tombolUnduh;
        if ($bolehViewerLuar && ! empty($urlViewerLuar)) {
            $aksiLain .= ' <a href="' . esc($urlViewerLuar, 'attr') . '" target="_blank" rel="noopener" class="btn btn-netral mt-3">' . ikon('arrow-up-right-from-square', 'h-4 w-4') . 'Buka di Office Viewer</a>'
                . '<p class="mx-auto mt-3 max-w-sm rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs text-yellow-800 dark:border-yellow-800 dark:bg-gray-700 dark:text-yellow-300">Office Viewer membuka berkas lewat server Microsoft, jadi berkas ini dikirim ke sana. Tombol ini hanya muncul untuk berkas yang memang sudah boleh diakses dari luar.</p>';
        }
        ?>
        <?= $kosong('file-lines', $kategori === 'presentasi' ? 'PowerPoint tidak bisa ditampilkan langsung di peramban' : 'Jenis berkas ini tidak punya pratinjau',
            'Berkasnya tersimpan utuh. Unduh untuk membukanya dengan aplikasi di komputer' . ($kategori === 'presentasi' ? ' (PowerPoint, WPS, LibreOffice)' : '') . '.', $aksiLain) ?>
    <?php endif; ?>
</div>
