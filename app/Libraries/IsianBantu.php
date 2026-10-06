<?php

namespace App\Libraries;

/**
 * Pembantu umum untuk merapikan & memeriksa isian form publik (nama, telepon,
 * tanggal). Dipakai modul PKL; dibuat TERPISAH dari BiodataForm (yang punya
 * salinan privat fungsi serupa) supaya modul biodata yang sudah live tidak
 * disentuh. Semua fungsi murni (tanpa DB/sesi) — mudah diuji.
 */
final class IsianBantu
{
    public const BULAN = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /** Buang spasi di tepi, spasi ganda, dan karakter kendali. */
    public static function rapikan(string $s): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    /**
     * Ketikan huruf kecil semua / BESAR semua → "Huruf Awal Besar".
     * Campuran dibiarkan apa adanya (pengisi sengaja menulis begitu).
     */
    public static function judul(string $s): string
    {
        if ($s !== '' && ($s === mb_strtolower($s) || $s === mb_strtoupper($s))) {
            return mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
        }

        return $s;
    }

    /** Nama orang: huruf, boleh titik, koma, petik, strip (gelar & nama ber-tanda). */
    public static function namaOrangSah(string $s): bool
    {
        return (bool) preg_match("/^\\p{L}[\\p{L} .,'`\\-]*$/u", $s);
    }

    /**
     * Isian telepon hanya boleh angka + pemisah lazim (spasi, strip, titik, kurung,
     * dan "+" di awal). Huruf DITOLAK — telepon() membuangnya diam-diam, padahal
     * huruf "O" yang tertukar dengan angka 0 ("0812345678OO") adalah salah ketik
     * paling umum dan hasilnya nomor yang keliru tanpa ada yang tahu.
     */
    public static function teleponMurni(string $s): bool
    {
        return (bool) preg_match('/^\+?[\d\s().\-]+$/', $s);
    }

    /** Hanya angka; awalan +62/62 diseragamkan menjadi 0. */
    public static function telepon(string $s): string
    {
        $s = preg_replace('/\D/', '', $s) ?? '';
        if (str_starts_with($s, '62') && strlen($s) >= 10) {
            $s = '0' . substr($s, 2);
        }

        return $s;
    }

    /**
     * Telepon rumah/kantor/HP: diawali 0, 8–15 angka, dan BUKAN satu angka yang
     * diulang seluruhnya (0000000000 / 0111111111) — isian asal-asalan penghindar
     * kolom wajib. Nomor "cantik" (mis. 0877 7777 7777) tetap lolos.
     */
    public static function teleponSah(string $s): bool
    {
        return (bool) preg_match('/^0\d{7,14}$/', $s) && ! preg_match('/^0(\d)\1+$/', $s);
    }

    /** HP/WhatsApp: diawali 08, total 10–14 angka, bukan satu angka berulang. */
    public static function hpSah(string $s): bool
    {
        return (bool) preg_match('/^08\d{8,12}$/', $s) && ! preg_match('/^0(\d)\1+$/', $s);
    }

    /** Y-m-d yang sah & tahunnya dalam rentang, selain itu null. */
    public static function tanggal(string $s, int $minTahun, int $maksTahun): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return null;
        }
        [, $y, $mo, $dd] = array_map('intval', $m);
        if (! checkdate($mo, $dd, $y) || $y < $minTahun || $y > $maksTahun) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $dd);
    }

    /** "2027-01-06" → "6 Januari 2027" (kosong bila bukan tanggal sah). */
    public static function tanggalIndo(?string $ymd): string
    {
        if ($ymd === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return '';
        }

        return (int) $m[3] . ' ' . self::BULAN[(int) $m[2] - 1] . ' ' . $m[1];
    }

    /** Selisih hari inklusif: 6 Jan s/d 6 Jan = 1 hari. Kedua argumen Y-m-d yang sah. */
    public static function hariInklusif(string $mulai, string $selesai): int
    {
        return (int) (new \DateTimeImmutable($mulai))->diff(new \DateTimeImmutable($selesai))->format('%r%a') + 1;
    }
}
