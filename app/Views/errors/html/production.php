<?php

/**
 * Halaman gangguan sistem di production (pengganti rincian galat). Rombak tampilan F10: kerangka bersama _galat.php +
 * assets/css/galat.css (dulu bawaan CodeIgniter berbahasa Inggris dengan debug.css disisipkan sebagai blok gaya sebaris).
 * Sengaja tanpa rincian teknis.
 */
$galatKode  = '';
$galatIkon  = 'tools';
$galatJudul = 'Sedang ada gangguan';
$galatTeks  = 'Maaf, sistem sedang mengalami kendala. Silakan coba lagi beberapa saat lagi. Bila masalah berlanjut, hubungi admin sekolah.';
$galatPesan = null;

include __DIR__ . DIRECTORY_SEPARATOR . '_galat.php';
