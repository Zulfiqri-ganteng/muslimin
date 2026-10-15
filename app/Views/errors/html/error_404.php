<?php

/**
 * Halaman 404 — halaman tidak ditemukan (publik & admin). Rombak tampilan F10: kerangka bersama _galat.php + assets/css/galat.css
 * (dulu bawaan CodeIgniter berbahasa Inggris dengan blok gaya sebaris). Pesan teknis hanya tampil di luar production.
 *
 * @var string $message
 */
$galatKode  = '404';
$galatIkon  = 'file-search';
$galatJudul = 'Halaman tidak ditemukan';
$galatTeks  = 'Maaf, halaman yang Anda cari tidak ada, sudah dipindahkan, atau alamatnya salah ketik.';
$galatPesan = ENVIRONMENT !== 'production' ? (string) ($message ?? '') : null;

include __DIR__ . DIRECTORY_SEPARATOR . '_galat.php';
