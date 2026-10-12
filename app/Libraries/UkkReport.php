<?php

namespace App\Libraries;

/**
 * Agregasi Rekap Hasil UKK — dipakai bersama oleh web (App\Controllers\Admin\LaporanUkk: halaman, PDF, Excel)
 * dan API (Api\Admin\LaporanUkk). Peserta per status, per paket soal, per jurusan; filter opsional per tahun ajaran.
 *
 * Memakai raw query builder dengan kolom deleted_at yang DIKUALIFIKASI (pola LabReport / SiswaModel::statistik)
 * supaya aman saat JOIN.
 */
class UkkReport
{
    /** @param int $tahunId 0 = semua tahun ajaran */
    public static function hitung(int $tahunId): array
    {
        $db = db_connect();

        $base = static fn () => $db->table('peserta_ukk')->where('peserta_ukk.deleted_at', null);
        $withTahun = static function ($b) use ($tahunId) {
            return $tahunId > 0 ? $b->where('peserta_ukk.tahun_ajaran_id', $tahunId) : $b;
        };

        $totalPeserta = (int) $withTahun($base())->countAllResults();

        $perStatus = ['terdaftar' => 0, 'hadir' => 0, 'tidak_hadir' => 0, 'lulus' => 0, 'tidak_lulus' => 0];
        foreach ($withTahun($base())->select('status, COUNT(*) AS jumlah')->groupBy('status')->get()->getResultArray() as $r) {
            if (isset($perStatus[$r['status']])) {
                $perStatus[$r['status']] = (int) $r['jumlah'];
            }
        }

        $rataRow = $withTahun($base())->select('AVG(nilai_akhir) AS rata')->where('nilai_akhir IS NOT NULL')->get()->getRowArray();
        $rataNilai = $rataRow && $rataRow['rata'] !== null ? round((float) $rataRow['rata'], 2) : null;

        // Per paket soal.
        $perPaket = [];
        $rows = $withTahun(
            $base()->select(
                'paket_soal_ukk.kode AS paket_kode, paket_soal_ukk.nama AS paket_nama,'
                . ' COUNT(*) AS total,'
                . ' SUM(CASE WHEN peserta_ukk.status = "lulus" THEN 1 ELSE 0 END) AS lulus,'
                . ' SUM(CASE WHEN peserta_ukk.status = "tidak_lulus" THEN 1 ELSE 0 END) AS tidak_lulus,'
                . ' AVG(peserta_ukk.nilai_akhir) AS rata_nilai'
            )->join('paket_soal_ukk', 'paket_soal_ukk.id = peserta_ukk.paket_soal_id AND paket_soal_ukk.deleted_at IS NULL', 'left')
        )->groupBy('peserta_ukk.paket_soal_id')->orderBy('paket_soal_ukk.nama', 'ASC')->get()->getResultArray();
        foreach ($rows as $r) {
            $perPaket[] = [
                'paket_kode'  => $r['paket_kode'] ?? '-',
                'paket_nama'  => $r['paket_nama'] ?? '(paket dihapus)',
                'total'       => (int) $r['total'],
                'lulus'       => (int) $r['lulus'],
                'tidak_lulus' => (int) $r['tidak_lulus'],
                'rata_nilai'  => $r['rata_nilai'] !== null ? round((float) $r['rata_nilai'], 2) : null,
            ];
        }

        // Per jurusan (lewat paket_soal_ukk.jurusan_id).
        $perJurusan = [];
        $rows = $withTahun(
            $base()->select(
                'jurusan.nama AS jurusan_nama,'
                . ' COUNT(*) AS total,'
                . ' SUM(CASE WHEN peserta_ukk.status = "lulus" THEN 1 ELSE 0 END) AS lulus,'
                . ' SUM(CASE WHEN peserta_ukk.status = "tidak_lulus" THEN 1 ELSE 0 END) AS tidak_lulus'
            )->join('paket_soal_ukk', 'paket_soal_ukk.id = peserta_ukk.paket_soal_id AND paket_soal_ukk.deleted_at IS NULL', 'left')
            ->join('jurusan', 'jurusan.id = paket_soal_ukk.jurusan_id', 'left')
        )->groupBy('jurusan.id')->orderBy('total', 'DESC')->get()->getResultArray();
        foreach ($rows as $r) {
            $perJurusan[] = [
                'jurusan_nama' => $r['jurusan_nama'],
                'total'        => (int) $r['total'],
                'lulus'        => (int) $r['lulus'],
                'tidak_lulus'  => (int) $r['tidak_lulus'],
            ];
        }

        return [
            'totalPeserta' => $totalPeserta,
            'perStatus'    => $perStatus,
            'rataNilai'    => $rataNilai,
            'perPaket'     => $perPaket,
            'perJurusan'   => $perJurusan,
        ];
    }
}
