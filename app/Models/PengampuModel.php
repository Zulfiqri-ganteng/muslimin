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
}
