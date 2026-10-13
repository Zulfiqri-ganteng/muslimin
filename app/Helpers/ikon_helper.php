<?php

/**
 * Ikon Flowbite (MIT) sebagai SVG sebaris — aman CSP (SVG bukan skrip/gaya), warna ikut `currentColor` (atur lewat kelas text-*).
 * Berkas: app/ThirdParty/flowbite-icons/{outline,solid}/<nama>.svg, disalin oleh `npm run vendor` HANYA untuk nama yang terdaftar
 * di resources/ikon.json (ikon baru: tambah namanya di sana lalu jalankan ulang). Nama: https://flowbite.com/icons/
 *
 *   <?= ikon('eye') ?>                              outline, w-5 h-5
 *   <?= ikon('bars', 'w-6 h-6 text-gray-500') ?>
 *   <?= ikon('check', 'w-4 h-4', 'solid') ?>
 *
 * Nama yang tidak ada → string kosong + catatan di log (dev:cek-ui juga memeriksa semua pemanggilan ikon()).
 */
if (! function_exists('ikon')) {
    function ikon(string $nama, string $kelas = 'w-5 h-5', string $varian = 'outline'): string
    {
        static $simpan = [];

        $kunci = $varian . '/' . $nama;
        if (! isset($simpan[$kunci])) {
            $berkas = APPPATH . 'ThirdParty/flowbite-icons/' . $varian . '/' . $nama . '.svg';
            if (preg_match('/^[a-z0-9-]+$/', $nama) !== 1 || ! in_array($varian, ['outline', 'solid'], true) || ! is_file($berkas)) {
                log_message('error', 'ikon() tidak ditemukan: {kunci}', ['kunci' => $kunci]);

                return '';
            }
            // Ukuran diatur lewat kelas: buang width/height di tag <svg> pembuka saja (bukan di <rect>/<path> di dalamnya).
            $simpan[$kunci] = (string) preg_replace_callback(
                '/^<svg\b[^>]*>/',
                static fn (array $m): string => (string) preg_replace('/\s(?:width|height)="[^"]*"/', '', $m[0]),
                trim((string) file_get_contents($berkas)),
            );
        }

        return (string) preg_replace('/^<svg\b/', '<svg class="' . esc($kelas, 'attr') . '" aria-hidden="true" focusable="false"', $simpan[$kunci], 1);
    }
}
