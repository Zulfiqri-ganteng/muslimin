<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Penugasan: guru mengajar mapel di kelas tertentu sebanyak N JP/minggu.
 * Sumber kuota JP (Rule R4) dan isi sel jadwal.
 */
class PengampuModel extends Model
{
    protected $table          = 'pengampu';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['kelas_id', 'mapel_id', 'guru_id', 'jp'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'kelas_id' => 'required|is_natural_no_zero',
        'mapel_id' => 'required|is_natural_no_zero',
        'guru_id'  => 'required|is_natural_no_zero',
        'jp'       => 'required|is_natural_no_zero',
    ];

    /** Daftar penugasan satu kelas + nama mapel & guru (urut nama mapel). */
    public function forKelas(int $kelasId): array
    {
        return $this->select('pengampu.*, mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel, mata_pelajaran.jp_default, guru.kode_guru, guru.nama AS guru_nama')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->join('guru', 'guru.id = pengampu.guru_id')
            ->where('pengampu.kelas_id', $kelasId)
            ->orderBy('mata_pelajaran.nama_mapel', 'ASC')
            ->findAll();
    }

    /** Total JP terjadwalkan (penugasan) untuk satu kelas. */
    public function totalJpKelas(int $kelasId): int
    {
        $row = $this->selectSum('jp')->where('kelas_id', $kelasId)->first();
        return (int) ($row['jp'] ?? 0);
    }

    /** Total beban JP per guru (untuk rekap), key = guru_id. */
    public function bebanPerGuru(): array
    {
        $rows = $this->select('guru_id, SUM(jp) AS total_jp')
            ->groupBy('guru_id')->findAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['guru_id']] = (int) $r['total_jp'];
        }
        return $out;
    }

    /**
     * Beban per guru (1 query, LEFT JOIN agar guru tanpa tugas tetap muncul).
     * Mengembalikan: id, kode_guru, nama, max_beban, total_jp, jml_tugas.
     */
    public function bebanPerGuruDetail(): array
    {
        return $this->db->table('guru g')
            ->select('g.id, g.kode_guru, g.nama, g.max_beban,
                      COALESCE(SUM(p.jp),0) AS total_jp, COUNT(p.id) AS jml_tugas')
            ->join('pengampu p', 'p.guru_id = g.id AND p.deleted_at IS NULL', 'left')
            ->where('g.deleted_at', null)
            ->groupBy('g.id')
            ->orderBy('total_jp', 'DESC')->orderBy('g.nama', 'ASC')
            ->get()->getResultArray();
    }

    /** Baris rekap detail (guru × mapel × kelas + jp), 1 query JOIN, urut nama guru. */
    public function rekapRows(): array
    {
        return $this->select('pengampu.guru_id, pengampu.jp,
                guru.kode_guru, guru.nama AS guru_nama, guru.max_beban,
                mata_pelajaran.nama_mapel, kelas.nama_kelas')
            ->join('guru', 'guru.id = pengampu.guru_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = pengampu.mapel_id')
            ->join('kelas', 'kelas.id = pengampu.kelas_id')
            ->orderBy('guru.nama', 'ASC')->orderBy('mata_pelajaran.nama_mapel', 'ASC')
            ->findAll();
    }
}
