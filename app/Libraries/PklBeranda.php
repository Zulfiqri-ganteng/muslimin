<?php

namespace App\Libraries;

/**
 * Kotak ringkasan Beranda PKL yang dipakai BERSAMA web (Admin\Pkl::index) dan Android (GET api/v1/pkl/ringkasan →
 * `surat_ringkas`, `surat_sekolah`), supaya angka dan aturan haknya selalu sama. Null = peran ini tidak berhak melihat kotaknya.
 */
final class PklBeranda
{
    /**
     * Surat Izin PKL & biaya — bagi pemegang hak 'surat' (unduh surat + catat biaya) atau 'laporan' (Laporan Pembayaran).
     *
     * @return array{belum_bernomor: int, belum_dikabari: int, uang_bulan_ini: int}|null
     */
    public static function suratRingkas(?string $peran): ?array
    {
        if (! HakAkses::bolehPkl($peran, 'surat') && ! HakAkses::bolehPkl($peran, 'laporan')) {
            return null;
        }
        $db = db_connect();

        return [
            'belum_bernomor' => (int) $db->query("SELECT COUNT(*) n FROM pkl_pengajuan p WHERE p.status = 'disetujui' AND NOT EXISTS (SELECT 1 FROM pkl_surat s WHERE s.pengajuan_id = p.id)")->getRowArray()['n'],
            'belum_dikabari' => (int) $db->query("SELECT COUNT(*) n FROM pkl_anggota a JOIN pkl_pengajuan p ON p.id = a.pengajuan_id AND p.status = 'disetujui' JOIN pkl_surat s ON s.pengajuan_id = p.id WHERE a.dikabari_at IS NULL")->getRowArray()['n'],
            'uang_bulan_ini' => (int) ($db->query('SELECT COALESCE(SUM(nominal), 0) n FROM pkl_pembayaran WHERE created_at >= ?', [date('Y-m-01 00:00:00')])->getRowArray()['n'] ?? 0),
        ];
    }

    /**
     * Surat sekolah (Izin ASTS, Izin TKA, Pernyataan Ortu, Balasan, Penarikan) — bagi yang boleh membuka modul Surat DAN memegang
     * hak surat sekolah apa pun (buat/ACC/unduh). Hitungan per status (SuratSekolah::hitungStatus) + belum_unduh (sudah di-ACC,
     * belum pernah diunduh).
     *
     * @return array<string, int>|null
     */
    public static function suratSekolah(?string $peran): ?array
    {
        $db = db_connect();
        if (! HakAkses::boleh($peran, 'admin/surat') || ! PklHak::boleh($peran, 'surat_acc:*|surat_buat:*|surat_unduh:*') || ! $db->tableExists('surat_sekolah')) {
            return null;
        }

        return (new SuratSekolah())->hitungStatus() + [
            'belum_unduh' => (int) $db->query("SELECT COUNT(*) n FROM surat_sekolah WHERE status = 'disetujui' AND cetak_ke = 0")->getRowArray()['n'],
        ];
    }
}
