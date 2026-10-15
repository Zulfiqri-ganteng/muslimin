<?php

/**
 * Halaman 400 — permintaan tidak valid (publik & admin). Rombak tampilan F10: kerangka bersama _galat.php + assets/css/galat.css
 * (dulu bawaan CodeIgniter berbahasa Inggris dengan blok gaya sebaris). Pesan teknis hanya tampil di luar production.
 *
 * @var string $message
 */
$galatKode  = '400';
$galatIkon  = 'exclamation-circle';
$galatJudul = 'Permintaan tidak dapat diproses';
$galatTeks  = 'Maaf, permintaan Anda tidak valid. Coba muat ulang halaman, lalu ulangi langkah tadi.';
$galatPesan = ENVIRONMENT !== 'production' ? (string) ($message ?? '') : null;

include __DIR__ . DIRECTORY_SEPARATOR . '_galat.php';
