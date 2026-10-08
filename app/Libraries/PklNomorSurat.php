<?php

namespace App\Libraries;

/**
 * Format nomor surat PKL bertoken. Pola default (surat sekolah): {urut}/SMK-BN/PKL/{bln_romawi}/{thn}
 *
 *   {urut}  nomor urut apa adanya (7)      {urut3} tiga angka (007)    {urut4} empat angka (0007)
 *   {tgl}   tanggal surat 2 angka (06)     {bln}   bulan 2 angka (01)
 *   {bln_romawi} bulan romawi (I..XII)     {thn}   tahun 4 angka (2027)
 *
 * Urutan dihitung PER TAHUN (lihat PklSurat::terbitkan).
 */
final class PklNomorSurat
{
    private const ROMAWI = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    private const TOKEN  = ['{urut}', '{urut3}', '{urut4}', '{tgl}', '{bln}', '{bln_romawi}', '{thn}'];

    /** Bawaan = format nomor surat resmi sekolah: 295/SMK-BN/PKL/VII/2026. */
    public const BAWAAN = '{urut}/SMK-BN/PKL/{bln_romawi}/{thn}';

    /**
     * Pilihan siap pakai di Pengaturan PKL (supaya tak perlu mengetik penanda): [pola, keterangan].
     * Nol di depan HANYA bisa lewat {urut3}/{urut4}; menulis "{urut}00" menghasilkan 100, 200, 600 — bukan 001.
     */
    public const PRESET = [
        ['{urut}/SMK-BN/PKL/{bln_romawi}/{thn}', 'Tanpa nol: 1, 2, 3 …'],
        ['{urut3}/SMK-BN/PKL/{bln_romawi}/{thn}', 'Tiga angka: 001, 002, 003 …'],
        ['{urut4}/SMK-BN/PKL/{bln_romawi}/{thn}', 'Empat angka: 0001, 0002, 0003 …'],
    ];

    public static function format(string $pola, int $urut, \DateTimeInterface $tanggal): string
    {
        return strtr($pola, [
            '{urut}'       => (string) $urut,
            '{urut3}'      => str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
            '{urut4}'      => str_pad((string) $urut, 4, '0', STR_PAD_LEFT),
            '{tgl}'        => $tanggal->format('d'),
            '{bln}'        => $tanggal->format('m'),
            '{bln_romawi}' => self::ROMAWI[(int) $tanggal->format('n') - 1],
            '{thn}'        => $tanggal->format('Y'),
        ]);
    }

    /** Contoh hasil pola untuk ditampilkan di Pengaturan. */
    public static function contoh(string $pola): string
    {
        return self::format($pola, 7, new \DateTimeImmutable('now'));
    }

    /**
     * Periksa pola. @return string|null pesan galat, atau null bila sah.
     */
    public static function periksa(string $pola): ?string
    {
        $pola = trim($pola);
        if ($pola === '' || mb_strlen($pola) > 100) {
            return 'Format nomor wajib diisi (maksimal 100 karakter).';
        }
        if (! str_contains($pola, '{urut}') && ! str_contains($pola, '{urut3}') && ! str_contains($pola, '{urut4}')) {
            return 'Format nomor harus memuat {urut} (atau {urut3} / {urut4}) agar tiap surat punya nomor berbeda.';
        }
        // Angka menempel di {urut} (mis. "{urut}00") bukan nol di depan: nomor 6 jadi "600". Tolak, arahkan ke {urut3}.
        if (preg_match('/\{urut[34]?\}\d|\d\{urut[34]?\}/', $pola)) {
            return 'Jangan menempelkan angka di depan/belakang {urut} (mis. "{urut}00" menghasilkan 100, 200, 600 — bukan 001). Untuk nomor 001, 002, 003 pakai {urut3}; untuk 0001 pakai {urut4}.';
        }
        preg_match_all('/\{[^}]*\}/', $pola, $m);
        foreach ($m[0] as $token) {
            if (! in_array($token, self::TOKEN, true)) {
                return 'Penanda ' . $token . ' tidak dikenal. Yang tersedia: ' . implode(' ', self::TOKEN) . '.';
            }
        }
        $sisa = strtr($pola, array_fill_keys(self::TOKEN, ''));
        if (! preg_match('~^[\p{L}\p{N} /._,-]*$~u', $sisa)) {
            return 'Format memuat karakter yang tidak diizinkan. Pakai huruf, angka, spasi, dan / . _ , -';
        }

        return null;
    }
}
