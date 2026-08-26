<?php

namespace App\Libraries;

/**
 * Agregasi Laporan Laboratorium — dipakai bersama oleh web
 * (App\Controllers\Admin\LaporanLab) dan API (Api\Admin\LaporanLab).
 *
 * Memakai raw query builder dengan kolom deleted_at yang DIKUALIFIKASI
 * (mis. aset.deleted_at) agar aman saat ada JOIN — meniru SiswaModel::statistik.
 */
class LabReport
{
    /** Hitung seluruh agregat laporan untuk rentang tanggal. */
    public static function hitung(string $dari, string $sampai): array
    {
        $db    = db_connect();
        $today = date('Y-m-d');

        // ---- Aset (snapshot) ----
        $asetTotal   = (int) $db->table('aset')->where('aset.deleted_at', null)->countAllResults();
        $asetKondisi = self::petakan(
            $db->table('aset')->select('kondisi, COUNT(*) c')->where('deleted_at', null)->groupBy('kondisi')->get()->getResultArray(),
            'kondisi',
            ['baik' => 0, 'rusak_ringan' => 0, 'rusak_berat' => 0]
        );
        $asetStatus = self::petakan(
            $db->table('aset')->select('status, COUNT(*) c')->where('deleted_at', null)->groupBy('status')->get()->getResultArray(),
            'status',
            ['tersedia' => 0, 'dipinjam' => 0, 'perbaikan' => 0, 'dihapus' => 0]
        );
        $asetPerLab = $db->table('aset')
            ->select('lab.nama AS lab_nama, COUNT(*) c')
            ->join('lab', 'lab.id = aset.lab_id', 'left')
            ->where('aset.deleted_at', null)
            ->groupBy('aset.lab_id')->orderBy('c', 'DESC')->get()->getResultArray();

        // ---- Peminjaman ----
        $pmRange = static fn ($b) => $b->where('peminjaman.deleted_at', null)
            ->where('tanggal_pinjam >=', $dari)->where('tanggal_pinjam <=', $sampai);
        $pmTotal  = (int) $pmRange($db->table('peminjaman'))->countAllResults();
        $pmStatus = self::petakan(
            $pmRange($db->table('peminjaman'))->select('status, COUNT(*) c')->groupBy('status')->get()->getResultArray(),
            'status',
            ['dipinjam' => 0, 'dikembalikan' => 0, 'terlambat' => 0, 'hilang' => 0]
        );
        $pmSedang    = (int) $db->table('peminjaman')->where('deleted_at', null)->where('status', 'dipinjam')->countAllResults();
        $pmTerlambat = (int) $db->table('peminjaman')->where('deleted_at', null)->where('status', 'dipinjam')
            ->where('tanggal_kembali_rencana IS NOT NULL')->where('tanggal_kembali_rencana <', $today)->countAllResults();

        // ---- Kerusakan ----
        $krRange = static fn ($b) => $b->where('kerusakan.deleted_at', null)
            ->where('tanggal_lapor >=', $dari)->where('tanggal_lapor <=', $sampai);
        $krTotal  = (int) $krRange($db->table('kerusakan'))->countAllResults();
        $krStatus = self::petakan(
            $krRange($db->table('kerusakan'))->select('status, COUNT(*) c')->groupBy('status')->get()->getResultArray(),
            'status',
            ['dilaporkan' => 0, 'diproses' => 0, 'selesai' => 0, 'tak_teratasi' => 0]
        );

        // ---- Perbaikan ----
        $pbRange = static fn ($b) => $b->where('perbaikan.deleted_at', null)
            ->where('tanggal >=', $dari)->where('tanggal <=', $sampai);
        $pbTotal = (int) $pbRange($db->table('perbaikan'))->countAllResults();
        $pbJenis = self::petakan(
            $pbRange($db->table('perbaikan'))->select('jenis, COUNT(*) c')->groupBy('jenis')->get()->getResultArray(),
            'jenis',
            ['perbaikan' => 0, 'maintenance' => 0, 'penggantian' => 0]
        );
        $pbBiaya = (float) ($pbRange($db->table('perbaikan'))->selectSum('biaya', 't')->get()->getRow()->t ?? 0);

        // ---- Sparepart (snapshot) ----
        $spTotal   = (int) $db->table('sparepart')->where('deleted_at', null)->countAllResults();
        $spMenipis = $db->table('sparepart')->select('kode, nama, stok, stok_minimum, satuan')
            ->where('deleted_at', null)->where('stok <= stok_minimum', null, false)
            ->orderBy('nama', 'ASC')->get()->getResultArray();

        // ---- Jurnal pemakaian ----
        $jrRange = static fn ($b) => $b->where('jurnal_lab.deleted_at', null)
            ->where('tanggal >=', $dari)->where('tanggal <=', $sampai);
        $jrTotal  = (int) $jrRange($db->table('jurnal_lab'))->countAllResults();
        $jrPerLab = $jrRange($db->table('jurnal_lab'))
            ->select('lab.nama AS lab_nama, COUNT(*) c')
            ->join('lab', 'lab.id = jurnal_lab.lab_id', 'left')
            ->groupBy('jurnal_lab.lab_id')->orderBy('c', 'DESC')->get()->getResultArray();

        return compact(
            'asetTotal', 'asetKondisi', 'asetStatus', 'asetPerLab',
            'pmTotal', 'pmStatus', 'pmSedang', 'pmTerlambat',
            'krTotal', 'krStatus', 'pbTotal', 'pbJenis', 'pbBiaya',
            'spTotal', 'spMenipis', 'jrTotal', 'jrPerLab'
        ) + ['spMenipisCount' => count($spMenipis)];
    }

    private static function petakan(array $rows, string $key, array $default): array
    {
        $out = $default;
        foreach ($rows as $row) {
            if (array_key_exists($row[$key], $out)) {
                $out[$row[$key]] = (int) $row['c'];
            }
        }

        return $out;
    }
}
