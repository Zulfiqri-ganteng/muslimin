<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pivot kompetensi: guru mana yang BISA mengajar mapel tertentu.
 */
class GuruMapelModel extends Model
{
    protected $table         = 'guru_mapel';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['guru_id', 'mapel_id', 'created_at'];
    protected $useTimestamps = false;

    /** Peta mapel_id => [guru_id,...] untuk sekumpulan mapel (1 query). */
    public function mapForMapelIds(array $mapelIds): array
    {
        if (empty($mapelIds)) {
            return [];
        }
        $rows = $this->select('guru_id, mapel_id')->whereIn('mapel_id', $mapelIds)->findAll();
        $map  = [];
        foreach ($rows as $r) {
            $map[(int) $r['mapel_id']][] = (int) $r['guru_id'];
        }
        return $map;
    }

    /** Daftar guru_id berkompetensi pada satu mapel. */
    public function guruIds(int $mapelId): array
    {
        return array_map('intval', array_column(
            $this->select('guru_id')->where('mapel_id', $mapelId)->findAll(),
            'guru_id'
        ));
    }

    /** Set ulang kompetensi 1 mapel ke daftar guru tertentu (dalam transaksi). */
    public function sync(int $mapelId, array $guruIds): void
    {
        $guruIds = array_values(array_unique(array_map('intval', $guruIds)));

        $this->db->transStart();
        $this->where('mapel_id', $mapelId)->delete();
        if ($guruIds) {
            $now  = date('Y-m-d H:i:s');
            $rows = array_map(fn ($gid) => [
                'guru_id' => $gid, 'mapel_id' => $mapelId, 'created_at' => $now,
            ], $guruIds);
            $this->insertBatch($rows);
        }
        $this->db->transComplete();
    }
}
