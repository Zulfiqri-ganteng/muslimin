<?php

namespace App\Libraries;

/**
 * Pola nama berkas surat PKL (satu surat). Bawaan mengikuti kebiasaan sekolah:
 *
 *   "295 Surat Izin PKL BINUS - Ilyasha ALL XII TKJ 5.docx"
 *
 *   {urut}         nomor urut surat apa adanya (295)     {urut3} 295   {urut4} 0295
 *   {nama_depan}   nama depan pengaju (Ilyasha). Bila nama depan ≤ 2 huruf (mis. "M Fahri"), dua kata pertama.
 *   {nama_pengaju} nama lengkap pengaju
 *   {all}          "ALL" bila siswanya lebih dari satu, kosong bila sendirian
 *   {kelas}        kelas pengaju (XII TKJ 5)             {perusahaan} nama perusahaan   {thn} tahun surat
 *
 * Spasi ganda dirapikan; karakter terlarang di nama berkas Windows dibuang.
 */
final class PklNamaBerkas
{
    public const BAWAAN = '{urut} Surat Izin PKL BINUS - {nama_depan} {all} {kelas}';

    private const TOKEN = ['{urut}', '{urut3}', '{urut4}', '{nama_depan}', '{nama_pengaju}', '{all}', '{kelas}', '{perusahaan}', '{thn}'];

    /** @return string|null pesan galat, atau null bila pola sah */
    public static function periksa(string $pola): ?string
    {
        $pola = trim($pola);
        if ($pola === '' || mb_strlen($pola) > 150) {
            return 'Pola nama berkas wajib diisi (maksimal 150 karakter).';
        }
        preg_match_all('/\{[^}]*\}/', $pola, $m);
        foreach ($m[0] as $token) {
            if (! in_array($token, self::TOKEN, true)) {
                return 'Penanda ' . $token . ' tidak dikenal. Yang tersedia: ' . implode(' ', self::TOKEN) . '.';
            }
        }
        $sisa = strtr($pola, array_fill_keys(self::TOKEN, ''));
        if (! preg_match('~^[\p{L}\p{N} ._,()+-]*$~u', $sisa)) {
            return 'Pola memuat karakter yang tidak diizinkan di nama berkas. Pakai huruf, angka, spasi, dan . _ , ( ) + -';
        }

        return null;
    }

    /**
     * Nama berkas (tanpa ekstensi) untuk satu surat.
     *
     * @param array{urut?: int|string, nama_pengaju?: string, kelas?: string, perusahaan?: string, thn?: int|string, jumlah?: int} $v
     */
    public static function format(string $pola, array $v): string
    {
        $pola   = trim($pola) !== '' && self::periksa($pola) === null ? $pola : self::BAWAAN;
        $urut   = (int) ($v['urut'] ?? 0);
        $nama   = trim((string) ($v['nama_pengaju'] ?? ''));
        $hasil  = strtr($pola, [
            '{urut}'         => (string) $urut,
            '{urut3}'        => str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
            '{urut4}'        => str_pad((string) $urut, 4, '0', STR_PAD_LEFT),
            '{nama_depan}'   => self::namaDepan($nama),
            '{nama_pengaju}' => $nama,
            '{all}'          => ((int) ($v['jumlah'] ?? 1)) > 1 ? 'ALL' : '',
            '{kelas}'        => trim((string) ($v['kelas'] ?? '')),
            '{perusahaan}'   => trim((string) ($v['perusahaan'] ?? '')),
            '{thn}'          => (string) ($v['thn'] ?? ''),
        ]);

        return self::aman($hasil);
    }

    /** Contoh hasil pola untuk ditampilkan di Pengaturan. */
    public static function contoh(string $pola): string
    {
        return self::format($pola, ['urut' => 295, 'nama_pengaju' => 'Ilyasha Hawari', 'kelas' => 'XII TKJ 5', 'perusahaan' => 'PT Antarestar Harapan Indah', 'thn' => 2026, 'jumlah' => 5]) . '.docx';
    }

    /** Nama depan: kata pertama; bila ≤ 2 huruf (singkatan seperti "M"), dua kata pertama. */
    public static function namaDepan(string $nama): string
    {
        $kata = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($kata === []) {
            return '';
        }
        if (mb_strlen(rtrim($kata[0], '.')) <= 2 && isset($kata[1])) {
            return $kata[0] . ' ' . $kata[1];
        }

        return $kata[0];
    }

    /** Buang karakter terlarang nama berkas, rapikan spasi, batasi panjang. */
    public static function aman(string $s): string
    {
        $s = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $s) ?? $s;
        $s = trim((string) preg_replace('/\s+/u', ' ', $s), " .\t");
        $s = trim((string) preg_replace('/\s+-\s*$/u', '', $s));

        return $s !== '' ? mb_substr($s, 0, 120) : 'Surat PKL';
    }
}
