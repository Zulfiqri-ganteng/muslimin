<?php

namespace App\Libraries;

/**
 * Tautan & teks pesan WhatsApp modul Isian Biodata — satu sumber untuk
 * halaman admin web dan API Android, supaya pesan yang dikirim ke grup
 * siswa / wali kelas selalu sama persis.
 */
final class BiodataPesan
{
    private const BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** Tautan subdomain yang dibagikan ke siswa (skema mengikuti baseURL). */
    public static function tautan(): string
    {
        $skema = parse_url(config('App')->baseURL, PHP_URL_SCHEME) ?: 'https';

        return $skema . '://' . config('Biodata')->host;
    }

    /** Tautan cadangan di domain utama (bila subdomain bermasalah). */
    public static function tautanCadangan(): string
    {
        return rtrim(config('App')->baseURL, '/') . '/biodata';
    }

    /** "31 Oktober 2026 pukul 23.59", atau '' bila tanpa batas waktu. */
    public static function batasTeks(?string $batas): string
    {
        if ($batas === null || $batas === '') {
            return '';
        }
        $t = strtotime($batas);

        return (int) date('j', $t) . ' ' . self::BULAN[(int) date('n', $t)] . ' ' . date('Y', $t) . ' pukul ' . date('H.i', $t);
    }

    /** Ajakan mengisi untuk grup WhatsApp siswa. */
    public static function bagikan(array $setting): string
    {
        $batas = self::batasTeks($setting['biodata_tutup'] ?? null);

        return "Assalamu'alaikum Wr. Wb.\n\n"
            . 'Kepada seluruh siswa ' . ($setting['school_name'] ?? 'sekolah') . ", mohon segera mengisi *BIODATA SISWA* melalui tautan berikut:\n"
            . self::tautan() . "\n\n"
            . "Siapkan *Kartu Keluarga (KK)* — semua data WAJIB diisi sesuai KK.\n"
            . ($batas !== '' ? 'Batas pengisian: *' . $batas . "*\n" : '')
            . "\nCara mengisi: pilih kelas → pilih nama → isi data → kirim.\nTerima kasih.";
    }

    /**
     * Daftar nama yang belum mengisi di satu kelas, untuk wali kelas / grup kelas.
     *
     * @param list<string> $nama
     */
    public static function belumMengisi(string $kelas, array $nama): string
    {
        $teks = '*Siswa ' . $kelas . ' yang BELUM mengisi biodata* (' . count($nama) . " siswa):\n";
        foreach (array_values($nama) as $i => $n) {
            $teks .= ($i + 1) . '. ' . $n . "\n";
        }

        return $teks . "\nSegera isi di: " . self::tautan() . "\nSiapkan Kartu Keluarga (KK). Terima kasih.";
    }
}
