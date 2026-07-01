<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jadwal KBM (isi tiap sel grid). HARD DELETE (bukan soft) karena ada
 * UNIQUE slot (kelas+hari+jam) & (guru+hari+jam) agar slot bisa diisi ulang.
 */
class JadwalModel extends Model
{
    protected $table         = 'jadwal';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['tahun_ajaran_id', 'kelas_id', 'hari_id', 'jam_id', 'pengampu_id', 'guru_id', 'created_by'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Semua sel jadwal satu kelas, key "hariId-jamId" => data sel (untuk render grid). */
    public function gridForKelas(int $kelasId): array
    {
        $rows = $this->select('jadwal.id, jadwal.hari_id, jadwal.jam_id, jadwal.pengampu_id, jadwal.guru_id,
                mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel, guru.kode_guru, guru.nama AS guru_nama')
            ->join('pengampu', 'pengampu.id = jadwal.pengampu_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->join('guru', 'guru.id = jadwal.guru_id')
            ->where('jadwal.kelas_id', $kelasId)
            ->findAll();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['hari_id'] . '-' . $r['jam_id']] = $r;
        }
        return $map;
    }

    /** Semua sel jadwal satu GURU lintas kelas, key "hariId-jamId" => data sel. */
    public function gridForGuru(int $guruId): array
    {
        $rows = $this->select('jadwal.hari_id, jadwal.jam_id, jam_pelajaran.shift,
                mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel, kelas.nama_kelas')
            ->join('pengampu', 'pengampu.id = jadwal.pengampu_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->join('jam_pelajaran', 'jam_pelajaran.id = jadwal.jam_id')
            ->where('jadwal.guru_id', $guruId)
            ->findAll();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['hari_id'] . '-' . $r['jam_id']] = $r;
        }
        return $map;
    }

    /**
     * Semua sesi mengajar pada satu hari (lintas kelas), untuk absensi harian.
     * Diurutkan per nama guru lalu jam, agar mudah dikelompokkan per guru.
     */
    public function sessionsForHari(int $hariId): array
    {
        return $this->select('jadwal.id AS jadwal_id, jadwal.kelas_id, jadwal.guru_id,
                jadwal.jam_id, jadwal.hari_id, pengampu.mapel_id,
                guru.nama AS guru_nama, guru.kode_guru,
                kelas.nama_kelas, kelas.shift,
                mata_pelajaran.nama_mapel, mata_pelajaran.kode_mapel,
                jam_pelajaran.jam_ke, jam_pelajaran.waktu_mulai, jam_pelajaran.waktu_selesai')
            ->join('pengampu', 'pengampu.id = jadwal.pengampu_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->join('guru', 'guru.id = jadwal.guru_id')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->join('jam_pelajaran', 'jam_pelajaran.id = jadwal.jam_id')
            ->where('jadwal.hari_id', $hariId)
            ->orderBy('guru.nama', 'ASC')
            ->orderBy('jam_pelajaran.waktu_mulai', 'ASC')
            ->findAll();
    }

    /** Sel kelas pada slot tertentu (R2 bentrok kelas). */
    public function cellOccupied(int $kelasId, int $hariId, int $jamId): ?array
    {
        return $this->where('kelas_id', $kelasId)->where('hari_id', $hariId)->where('jam_id', $jamId)->first();
    }

    /** Bentrok guru (R1): guru mengajar kelas LAIN di slot sama. Mengembalikan nama kelas bentrok. */
    public function guruConflict(int $guruId, int $hariId, int $jamId, ?int $exceptId = null): ?array
    {
        $b = $this->select('jadwal.id, kelas.nama_kelas')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->where('jadwal.guru_id', $guruId)
            ->where('jadwal.hari_id', $hariId)
            ->where('jadwal.jam_id', $jamId);
        if ($exceptId) {
            $b->where('jadwal.id !=', $exceptId);
        }
        return $b->first();
    }

    /** Jumlah JP yang sudah terjadwal per pengampu untuk satu kelas (R4), key pengampu_id => count. */
    public function placedCountByKelas(int $kelasId): array
    {
        $rows = $this->select('pengampu_id, COUNT(*) AS jml')
            ->where('kelas_id', $kelasId)->groupBy('pengampu_id')->findAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['pengampu_id']] = (int) $r['jml'];
        }
        return $out;
    }

    /** Jumlah JP terjadwal untuk satu pengampu (R4). */
    public function placedCount(int $pengampuId): int
    {
        return $this->where('pengampu_id', $pengampuId)->countAllResults();
    }

    // ===================== DETEKSI BENTROK (laporan) =====================

    /** R1: guru terjadwal >1x pada slot sama (harusnya kosong krn UNIQUE; defensif). */
    public function conflictsGuru(): array
    {
        return $this->select('guru.nama AS guru_nama, guru.kode_guru, hari.nama AS hari,
                jam_pelajaran.jam_ke, COUNT(*) AS jml,
                GROUP_CONCAT(kelas.nama_kelas SEPARATOR ", ") AS kelas_list')
            ->join('guru', 'guru.id = jadwal.guru_id')
            ->join('hari', 'hari.id = jadwal.hari_id')
            ->join('jam_pelajaran', 'jam_pelajaran.id = jadwal.jam_id')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->groupBy('jadwal.guru_id, jadwal.hari_id, jadwal.jam_id')
            ->having('jml >', 1)
            ->findAll();
    }

    /** R2: satu kelas punya >1 entri pada slot sama (defensif). */
    public function conflictsKelas(): array
    {
        return $this->select('kelas.nama_kelas, hari.nama AS hari, jam_pelajaran.jam_ke,
                COUNT(*) AS jml, GROUP_CONCAT(mata_pelajaran.nama_mapel SEPARATOR ", ") AS mapel_list')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->join('hari', 'hari.id = jadwal.hari_id')
            ->join('jam_pelajaran', 'jam_pelajaran.id = jadwal.jam_id')
            ->join('pengampu', 'pengampu.id = jadwal.pengampu_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->groupBy('jadwal.kelas_id, jadwal.hari_id, jadwal.jam_id')
            ->having('jml >', 1)
            ->findAll();
    }

    /** R3: jadwal yang menabrak ketersediaan guru (status 'tidak'). */
    public function ketViolations(): array
    {
        return $this->select('guru.nama AS guru_nama, guru.kode_guru, kelas.nama_kelas,
                hari.nama AS hari, jam_pelajaran.jam_ke, mata_pelajaran.nama_mapel')
            ->join('ketersediaan_guru kg', 'kg.guru_id = jadwal.guru_id AND kg.hari_id = jadwal.hari_id AND kg.jam_id = jadwal.jam_id AND kg.status = "tidak"')
            ->join('guru', 'guru.id = jadwal.guru_id')
            ->join('kelas', 'kelas.id = jadwal.kelas_id')
            ->join('hari', 'hari.id = jadwal.hari_id')
            ->join('jam_pelajaran', 'jam_pelajaran.id = jadwal.jam_id')
            ->join('pengampu', 'pengampu.id = jadwal.pengampu_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->findAll();
    }

    /** R4: penugasan dengan jumlah JP terjadwal != JP seharusnya. */
    public function jpMismatch(): array
    {
        return $this->db->table('pengampu p')
            ->select('k.nama_kelas, m.nama_mapel, g.nama AS guru_nama, p.jp AS jp_harus,
                      COUNT(j.id) AS jp_terjadwal')
            ->join('kelas k', 'k.id = p.kelas_id')
            ->join('mata_pelajaran m', 'm.id = p.mapel_id')
            ->join('guru g', 'g.id = p.guru_id')
            ->join('jadwal j', 'j.pengampu_id = p.id', 'left')
            ->where('p.deleted_at', null)
            ->groupBy('p.id')
            ->having('jp_terjadwal != p.jp')
            ->orderBy('k.nama_kelas', 'ASC')
            ->get()->getResultArray();
    }
}
