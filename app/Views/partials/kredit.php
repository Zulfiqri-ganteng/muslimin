<?php
/**
 * Kredit desainer di kaki semua halaman web (admin, publik, form biodata, login).
 * Sengaja TIDAK dipasang di dokumen cetak/PDF/Excel resmi sekolah.
 * Warna mengikuti teks pembungkusnya agar serasi di latar terang maupun gelap.
 *
 * @var string|null $kreditKelas kelas CSS baris ini (nama unik: data view CI ikut
 *                               terbawa ke partial, jangan pakai nama umum spt $kelas)
 */
?>
<p class="<?= esc(is_string($kreditKelas ?? null) ? $kreditKelas : 'mt-1') ?>">Design By <a href="https://www.instagram.com/zufieee/" target="_blank" rel="noopener noreferrer" class="font-semibold hover:underline">zufieee</a></p>
