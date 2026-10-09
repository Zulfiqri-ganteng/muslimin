<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Penomoran SATU URUTAN untuk semua surat keluar bidang PKL: Surat Izin PKL (tabel pkl_surat) dan Surat Sekolah
 * (tabel surat_sekolah) memakai urutan per tahun yang sama, sehingga tidak ada dua surat berbeda bernomor sama.
 *
 * Pemanggil WAJIB sudah berada di dalam transaksi yang mengunci baris pkl_pengaturan id = 1
 * (`SELECT id FROM pkl_pengaturan WHERE id = 1 FOR UPDATE`) — kunci itulah yang membuat dua penerbit bersamaan
 * tidak pernah mendapat nomor kembar (UNIQUE(tahun, urut) di tiap tabel hanya menjaga di dalam tabelnya sendiri).
 * Format nomor (token) tetap di Libraries\PklNomorSurat.
 */
final class SuratNomor
{
    /** Urut terbesar di tahun itu, dari kedua tabel surat (0 bila belum ada). */
    public static function maksTahun(BaseConnection $db, int $tahun): int
    {
        $maks = (int) ($db->query('SELECT COALESCE(MAX(urut), 0) m FROM pkl_surat WHERE tahun = ?', [$tahun])->getRowArray()['m'] ?? 0);
        // Penjaga: hosting yang belum menjalankan migrasi surat sekolah tetap bisa menerbitkan Surat Izin PKL.
        if ($db->tableExists('surat_sekolah')) {
            $maks = max($maks, (int) ($db->query('SELECT COALESCE(MAX(urut), 0) m FROM surat_sekolah WHERE tahun = ?', [$tahun])->getRowArray()['m'] ?? 0));
        }

        return $maks;
    }

    /**
     * Urut berikutnya = yang terbesar dari (nomor terakhir + 1) dan lantai "nomor berikutnya" di Pengaturan PKL
     * (lantai hanya berlaku di tahun yang tercatat di nomor_awal_tahun).
     *
     * @param array<string, mixed> $p baris pkl_pengaturan
     */
    public static function urutBerikutnya(BaseConnection $db, int $tahun, array $p): int
    {
        $lantai = ((int) ($p['nomor_awal_tahun'] ?? 0) === $tahun) ? max(1, (int) ($p['nomor_awal'] ?? 1)) : 1;

        return max(self::maksTahun($db, $tahun) + 1, $lantai);
    }

    /**
     * Pola nomor yang berlaku (Pengaturan PKL). Bila kosong atau rusak (mis. "{urut}00") dipakai pola bawaan —
     * nomor ngawur tidak boleh pernah terbit.
     *
     * @param array<string, mixed> $p baris pkl_pengaturan
     */
    public static function pola(array $p): string
    {
        $pola = (string) ($p['format_nomor'] ?? '') !== '' ? (string) $p['format_nomor'] : PklNomorSurat::BAWAAN;
        if (PklNomorSurat::periksa($pola) !== null) {
            log_message('error', '[Surat] format nomor surat tidak sah "' . $pola . '"; memakai format bawaan.');
            $pola = PklNomorSurat::BAWAAN;
        }

        return $pola;
    }
}
