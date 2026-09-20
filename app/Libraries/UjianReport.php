<?php

namespace App\Libraries;

/**
 * Agregasi laporan modul Ujian — dipakai bersama oleh tab Rekap
 * (App\Controllers\Admin\Ujian) dan cetakan PDF/Excel
 * (App\Controllers\Admin\LaporanUjian), nanti juga API.
 *
 * Memakai raw query builder dengan kolom deleted_at yang DIKUALIFIKASI
 * (mis. ujian_susulan.deleted_at) agar aman saat ada JOIN — meniru
 * LabReport & SiswaModel::statistik.
 */
class UjianReport
{
    /** Hitung seluruh agregat satu periode ujian. */
    public static function hitung(int $periodeId): array
    {
        $db = db_connect();

        // ---- Jadwal ----
        $jadwalTotal = (int) $db->table('ujian_jadwal')
            ->where('ujian_jadwal.deleted_at', null)
            ->where('periode_id', $periodeId)
            ->countAllResults();

        $jadwalPerTingkat = self::petakan(
            $db->table('ujian_jadwal')->select('tingkat, COUNT(*) c')
                ->where('ujian_jadwal.deleted_at', null)->where('periode_id', $periodeId)
                ->groupBy('tingkat')->get()->getResultArray(),
            'tingkat',
            ['X' => 0, 'XI' => 0, 'XII' => 0]
        );

        // ---- Ketidakhadiran / susulan ----
        $susulan = static fn () => $db->table('ujian_susulan')
            ->where('ujian_susulan.deleted_at', null)
            ->where('ujian_susulan.periode_id', $periodeId);

        $takHadirTotal = (int) $susulan()->countAllResults();

        $perStatus = self::petakan(
            $susulan()->select('status, COUNT(*) c')->groupBy('status')->get()->getResultArray(),
            'status',
            ['belum' => 0, 'dijadwalkan' => 0, 'selesai' => 0, 'batal' => 0]
        );

        $perAlasan = self::petakan(
            $susulan()->select('alasan, COUNT(*) c')->groupBy('alasan')->get()->getResultArray(),
            'alasan',
            ['sakit' => 0, 'izin' => 0, 'alpa' => 0, 'lainnya' => 0]
        );

        // Ringkasan berjenjang: pakai SUM(CASE) supaya cukup satu kali jalan
        // ke database untuk semua kolom status sekaligus.
        $hitungStatus = 'COUNT(*) total,'
            . " SUM(CASE WHEN ujian_susulan.status='belum' THEN 1 ELSE 0 END) belum,"
            . " SUM(CASE WHEN ujian_susulan.status='dijadwalkan' THEN 1 ELSE 0 END) dijadwalkan,"
            . " SUM(CASE WHEN ujian_susulan.status='selesai' THEN 1 ELSE 0 END) selesai,"
            . " SUM(CASE WHEN ujian_susulan.status='batal' THEN 1 ELSE 0 END) batal";

        $perKelas = $db->table('ujian_susulan')
            ->select('kelas.nama_kelas, kelas.tingkat, ' . $hitungStatus)
            ->join('siswa', 'siswa.id = ujian_susulan.siswa_id', 'left')
            ->join('kelas', 'kelas.id = siswa.kelas_id', 'left')
            ->where('ujian_susulan.deleted_at', null)
            ->where('ujian_susulan.periode_id', $periodeId)
            // Semua kolom non-agregat ikut di GROUP BY: server produksi bisa
            // menyalakan ONLY_FULL_GROUP_BY (default MySQL 5.7+) sementara
            // MariaDB lokal tidak — query ini harus sah di keduanya.
            ->groupBy(['siswa.kelas_id', 'kelas.nama_kelas', 'kelas.tingkat'])
            ->orderBy('kelas.tingkat', 'ASC')->orderBy('kelas.nama_kelas', 'ASC')
            ->get()->getResultArray();

        $perMapel = $db->table('ujian_susulan')
            ->select('mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel, ' . $hitungStatus)
            ->join('mata_pelajaran', 'mata_pelajaran.id = ujian_susulan.mapel_id', 'left')
            ->where('ujian_susulan.deleted_at', null)
            ->where('ujian_susulan.periode_id', $periodeId)
            ->groupBy(['ujian_susulan.mapel_id', 'mata_pelajaran.kode_mapel', 'mata_pelajaran.nama_mapel'])
            ->orderBy('total', 'DESC')
            ->get()->getResultArray();

        $perTanggal = $db->table('ujian_susulan')
            ->select('tanggal_ujian, COUNT(*) total')
            ->where('ujian_susulan.deleted_at', null)
            ->where('ujian_susulan.periode_id', $periodeId)
            ->where('tanggal_ujian IS NOT NULL')
            ->groupBy('tanggal_ujian')->orderBy('tanggal_ujian', 'ASC')
            ->get()->getResultArray();

        // Siswa dengan ketidakhadiran terbanyak — penanda siswa yang perlu
        // ditindaklanjuti wali kelas, bukan sekadar angka rekap.
        $siswaTerbanyak = $db->table('ujian_susulan')
            ->select('siswa.nis, siswa.nama, kelas.nama_kelas, COUNT(*) total')
            ->join('siswa', 'siswa.id = ujian_susulan.siswa_id', 'left')
            ->join('kelas', 'kelas.id = siswa.kelas_id', 'left')
            ->where('ujian_susulan.deleted_at', null)
            ->where('ujian_susulan.periode_id', $periodeId)
            ->groupBy(['ujian_susulan.siswa_id', 'siswa.nis', 'siswa.nama', 'kelas.nama_kelas'])
            ->having('total >', 1)
            ->orderBy('total', 'DESC')->orderBy('siswa.nama', 'ASC')
            ->limit(15)
            ->get()->getResultArray();

        $pengawasTotal = (int) $db->table('ujian_pengawas')
            ->join('ujian_jadwal', 'ujian_jadwal.id = ujian_pengawas.jadwal_id')
            ->where('ujian_jadwal.deleted_at', null)
            ->where('ujian_jadwal.periode_id', $periodeId)
            ->countAllResults();

        return [
            'jadwalTotal'      => $jadwalTotal,
            'jadwalPerTingkat' => $jadwalPerTingkat,
            'pengawasTotal'    => $pengawasTotal,
            'takHadirTotal'    => $takHadirTotal,
            'perStatus'        => $perStatus,
            'perAlasan'        => $perAlasan,
            'perKelas'         => $perKelas,
            'perMapel'         => $perMapel,
            'perTanggal'       => $perTanggal,
            'siswaTerbanyak'   => $siswaTerbanyak,
        ];
    }

    /**
     * Daftar peserta satu sesi ujian, dikelompokkan per kelas sasaran.
     *
     * Dipakai cetak Daftar Hadir. Peserta diturunkan dari siswa aktif pada
     * kelas sasaran — memang tidak ada tabel peserta (lihat DESAIN-UJIAN.md).
     *
     * @return array<int, array{kelas: array, siswa: array}>
     */
    public static function pesertaSesi(array $kelasSasaran): array
    {
        $db  = db_connect();
        $out = [];

        foreach ($kelasSasaran as $kelas) {
            $siswa = $db->table('siswa')
                ->select('siswa.id, siswa.nis, siswa.nama, siswa.jenis_kelamin')
                ->where('siswa.deleted_at', null)
                ->where('siswa.status', 'aktif')
                ->where('siswa.kelas_id', (int) $kelas['id'])
                ->orderBy('siswa.nama', 'ASC')
                ->get()->getResultArray();

            if ($siswa !== []) {
                $out[] = ['kelas' => $kelas, 'siswa' => $siswa];
            }
        }

        return $out;
    }

    /**
     * Ringkasan keempat jenis ujian pada satu tahun pelajaran, untuk kartu
     * di Dashboard.
     *
     * Sengaja HANYA MEMBACA: periode yang belum ada ditampilkan bernilai nol,
     * tidak dibuat otomatis — dashboard tidak boleh punya efek samping.
     *
     * Hasilnya di-cache, dan tahun pelajaran ikut disimpan di dalam payload
     * supaya cache membatalkan dirinya sendiri begitu tahun di Pengaturan
     * Sekolah berganti (tanpa perlu kunci cache per tahun).
     */
    public static function dashboard(string $tahun): array
    {
        $tersimpan = cache('dash_ujian');
        if (is_array($tersimpan) && ($tersimpan['tahun'] ?? null) === $tahun) {
            return $tersimpan;
        }

        $db = db_connect();

        $periode = $db->table('ujian_periode')
            ->select('id, jenis, status')
            ->where('ujian_periode.deleted_at', null)
            ->where('tahun_ajaran', $tahun)
            ->get()->getResultArray();

        $ids = array_map('intval', array_column($periode, 'id'));

        $jadwal  = [];
        $takHadir = [];
        $belum   = [];

        if ($ids !== []) {
            foreach ($db->table('ujian_jadwal')->select('periode_id, COUNT(*) c')
                ->where('ujian_jadwal.deleted_at', null)->whereIn('periode_id', $ids)
                ->groupBy('periode_id')->get()->getResultArray() as $r) {
                $jadwal[(int) $r['periode_id']] = (int) $r['c'];
            }

            foreach ($db->table('ujian_susulan')
                ->select("periode_id, COUNT(*) c, SUM(CASE WHEN status='belum' THEN 1 ELSE 0 END) b")
                ->where('ujian_susulan.deleted_at', null)->whereIn('periode_id', $ids)
                ->groupBy('periode_id')->get()->getResultArray() as $r) {
                $takHadir[(int) $r['periode_id']] = (int) $r['c'];
                $belum[(int) $r['periode_id']]    = (int) $r['b'];
            }
        }

        $perJenis = array_column($periode, null, 'jenis');

        $kartu = [];
        foreach (\App\Models\UjianPeriodeModel::JENIS as $jenis) {
            $baris = $perJenis[$jenis] ?? null;
            $pid   = $baris ? (int) $baris['id'] : 0;

            $kartu[] = [
                'jenis'     => $jenis,
                'label'     => \App\Models\UjianPeriodeModel::JENIS_LABEL[$jenis] ?? $jenis,
                'slug'      => \App\Models\UjianPeriodeModel::keSlug($jenis),
                'status'    => $baris['status'] ?? 'draft',
                'jadwal'    => $jadwal[$pid] ?? 0,
                'takHadir'  => $takHadir[$pid] ?? 0,
                'belum'     => $belum[$pid] ?? 0,
            ];
        }

        $hasil = ['tahun' => $tahun, 'kartu' => $kartu];
        cache()->save('dash_ujian', $hasil, 1800);

        return $hasil;
    }

    /** Ubah hasil GROUP BY jadi peta [nilai => jumlah] berikut nilai defaultnya. */
    private static function petakan(array $rows, string $key, array $default): array
    {
        foreach ($rows as $r) {
            $default[$r[$key]] = (int) $r['c'];
        }

        return $default;
    }
}
