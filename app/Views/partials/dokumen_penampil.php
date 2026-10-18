<?php

/**
 * Penampil isi dokumen untuk halaman penerima tautan berbagi (public/berbagi_dokumen.php). Panel admin memakai
 * admin/dokumen/_penampil.php; perilaku keduanya SATU: komponen Alpine `penampilDokumen` di assets/js/dokumen-penampil.js
 * (isi Word/Excel dipasang lewat JS, bukan x-html). Skrip ber-nonce.
 * (P5 rombak publik) Gaya kit publik.css: ikon Flowbite lewat ikon() (bukan SVG tulisan tangan), tombol kit, latar gelap
 * .penampil-gelap, gaya isi Word/Excel .dok-pratinjau; tab lembar Excel pakai aria-pressed. frame-src mengizinkan youtube-nocookie
 * (Config\ContentSecurityPolicy) — alamat video disusun di $urlYoutube (pola admin/dokumen/_penampil.php).
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
$tombolUnduh     = $bolehUnduh ? '<a href="' . esc($urlUnduh, 'attr') . '" class="tombol tombol-utama mt-5">' . ikon('download') . 'Unduh berkas</a>' : '';
?>

<?php if ($pakaiPustaka) { ?>
    <?php if ($kategori === 'spreadsheet' || $ext === 'csv') { ?>
        <script defer src="<?= base_url('assets/js/vendor/xlsx.full.min.js') ?>" <?= csp_script_nonce() ?>></script>
    <?php } ?>
    <?php if ($ext === 'docx') { ?>
        <script defer src="<?= base_url('assets/js/vendor/mammoth.browser.min.js') ?>" <?= csp_script_nonce() ?>></script>
    <?php } ?>
<?php } ?>

<?php if (! defined('DOK_PENAMPIL_JS')) { define('DOK_PENAMPIL_JS', true); ?>
    <script src="<?= base_url('assets/js/dokumen-penampil.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/dokumen-penampil.js') ?>" <?= csp_script_nonce() ?>></script>
<?php } ?>

<div x-data="penampilDokumen" data-kategori="<?= esc($kategori, 'attr') ?>" data-ext="<?= esc($ext, 'attr') ?>" data-url="<?= esc($urlIsi, 'attr') ?>">

    <?php if ($tautan && $ytId !== null) { ?>
        <div class="aspect-video bg-kaki">
            <iframe class="h-full w-full" src="<?= esc($urlYoutube, 'attr') ?>" title="<?= esc($judul, 'attr') ?>" allowfullscreen
                    allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"></iframe>
        </div>

    <?php } elseif ($tautan) { ?>
        <div class="penampil-pesan">
            <span class="kosong-ikon"><?= ikon('link') ?></span>
            <p class="kosong-judul">Dokumen ini berupa tautan ke <?= esc($penyedia) ?></p>
            <p class="kosong-teks break-all"><?= esc((string) ($urlEksternal ?? '')) ?></p>
            <a href="<?= esc((string) ($urlEksternal ?? ''), 'attr') ?>" target="_blank" rel="noopener" class="tombol tombol-utama mt-5"><?= ikon('arrow-up-right-from-square') ?>Buka tautan</a>
        </div>

    <?php } elseif ($kategori === 'pdf') { ?>
        <!-- Penampil ini mengisi lebar penuh halaman, jadi tingginya ikut
             dinaikkan supaya satu halaman A4 terbaca tanpa perlu digulir. -->
        <iframe src="<?= esc($urlIsi, 'attr') ?>#view=FitH" class="penampil-pdf" title="<?= esc($judul, 'attr') ?>"></iframe>

    <?php } elseif ($kategori === 'gambar' && ! in_array($ext, ['heic', 'heif', 'svg'], true)) { ?>
        <div class="penampil-gelap">
            <img src="<?= esc($urlIsi, 'attr') ?>" alt="<?= esc($judul, 'attr') ?>" class="max-h-[78vh] max-w-full object-contain">
        </div>

    <?php } elseif ($kategori === 'gambar') { ?>
        <div class="penampil-pesan">
            <span class="kosong-ikon"><?= ikon('file-image') ?></span>
            <p class="kosong-judul">Pratinjau <?= esc(strtoupper($ext)) ?> tidak tersedia di peramban</p>
            <p class="kosong-teks">
                <?= $ext === 'svg'
                    ? 'Berkas SVG sengaja tidak ditampilkan langsung karena bisa memuat skrip. Unduh untuk membukanya.'
                    : 'Foto iPhone (HEIC) belum didukung peramban. Berkasnya tetap utuh — unduh untuk membukanya.' ?>
            </p>
            <?= $tombolUnduh ?>
        </div>

    <?php } elseif ($kategori === 'audio') { ?>
        <div class="penampil-pesan gap-5">
            <span class="kosong-ikon"><?= ikon('file-music') ?></span>
            <audio controls preload="metadata" class="w-full max-w-md"><source src="<?= esc($urlIsi, 'attr') ?>" type="<?= esc($mime, 'attr') ?>"></audio>
        </div>

    <?php } elseif ($kategori === 'video') { ?>
        <div class="bg-kaki">
            <video controls preload="metadata" class="max-h-[78vh] w-full"><source src="<?= esc($urlIsi, 'attr') ?>" type="<?= esc($mime, 'attr') ?>"></video>
        </div>

    <?php } elseif ($pakaiPustaka) { ?>
        <div class="p-4 sm:p-5">
            <div x-show="status === 'muat'" class="py-16 text-center text-sm text-teks-2" role="status">
                <span class="mx-auto block h-6 w-6 animate-spin rounded-full border-2 border-brand-200 border-t-brand-700" aria-hidden="true"></span>
                <p class="mt-3">Membuka isi berkas…</p>
            </div>

            <div x-show="status === 'gagal'" x-cloak class="penampil-pesan py-10">
                <p class="kosong-judul mt-0">Isi berkas tidak bisa ditampilkan di sini</p>
                <p class="kosong-teks" x-text="pesan"></p>
                <?= $tombolUnduh ?>
            </div>

            <div x-show="status === 'siap' && lembar.length > 1" x-cloak class="penampil-tab">
                <template x-for="(nama, i) in lembar" :key="nama">
                    <button type="button" @click="pilihLembar(i)" :aria-pressed="i === lembarAktif" x-text="nama"></button>
                </template>
            </div>

            <div x-show="status === 'siap'" x-cloak class="dok-pratinjau" data-isi-dokumen></div>
        </div>

    <?php } else { ?>
        <div class="penampil-pesan">
            <span class="kosong-ikon"><?= ikon($kategori === 'presentasi' ? 'file-ppt' : 'file') ?></span>
            <p class="kosong-judul">
                <?= $kategori === 'presentasi'
                    ? 'PowerPoint tidak bisa ditampilkan langsung di peramban'
                    : 'Jenis berkas ini tidak punya pratinjau' ?>
            </p>
            <p class="kosong-teks">
                Berkasnya tersimpan utuh. Unduh untuk membukanya dengan aplikasi di komputer<?= $kategori === 'presentasi' ? ' (PowerPoint, WPS, LibreOffice)' : '' ?>.
            </p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                <?php if ($bolehUnduh) { ?>
                    <a href="<?= esc($urlUnduh, 'attr') ?>" class="tombol tombol-utama"><?= ikon('download') ?>Unduh berkas</a>
                <?php } ?>
                <?php if ($bolehViewerLuar && ! empty($urlViewerLuar)) { ?>
                    <a href="<?= esc($urlViewerLuar, 'attr') ?>" target="_blank" rel="noopener" class="tombol tombol-netral"><?= ikon('arrow-up-right-from-square') ?>Buka di Office Viewer</a>
                <?php } ?>
            </div>
            <?php if ($bolehViewerLuar && ! empty($urlViewerLuar)) { ?>
                <div class="peringatan peringatan-awas mt-4 max-w-md text-left"><?= ikon('info-circle') ?><p>Office Viewer membuka berkas lewat server Microsoft, jadi berkas ini dikirim ke sana. Tombol ini hanya muncul untuk berkas yang memang sudah boleh diakses dari luar.</p></div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
