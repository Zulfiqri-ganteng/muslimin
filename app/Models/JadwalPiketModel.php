<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jadwal guru piket bergilir: satu baris = guru X piket pada hari Y shift Z.
 * Dipakai absensi untuk mengisikan guru piket ke Kehadiran Kerja & bagian
 * "Guru piket" pada pesan WhatsApp.
 */
class JadwalPiketModel extends Model
{
    protected $table         = 'jadwal_piket';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['hari_id', 'shift', 'guru_id'];

    public const SHIFTS = ['pagi', 'siang'];

    /** Guru piket satu hari: ['pagi' => [guru_id…], 'siang' => […]]. */
    public function forHari(int $hariId): array
    {
        $out = array_fill_keys(self::SHIFTS, []);
        foreach ($this->select('shift, guru_id')->where('hari_id', $hariId)->findAll() as $r) {
            $out[$r['shift']][] = (int) $r['guru_id'];
        }

        return $out;
    }

    /** Seluruh jadwal: [hari_id]['pagi'|'siang'] => [guru_id…]. */
    public function grid(): array
    {
        $out = [];
        foreach ($this->select('hari_id, shift, guru_id')->findAll() as $r) {
            $out[(int) $r['hari_id']][$r['shift']][] = (int) $r['guru_id'];
        }

        return $out;
    }

    /**
     * Ganti seluruh isi jadwal piket dengan $grid ([hari_id][shift] => [guru_id…])
     * dalam satu transaksi.
     */
    public function simpanGrid(array $grid): void
    {
        $rows = [];
        $now  = date('Y-m-d H:i:s');
        foreach ($grid as $hariId => $perShift) {
            foreach (self::SHIFTS as $shift) {
                $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($perShift[$shift] ?? [])))));
                foreach ($ids as $gid) {
                    $rows[] = ['hari_id' => (int) $hariId, 'shift' => $shift, 'guru_id' => $gid, 'created_at' => $now];
                }
            }
        }

        $this->db->transStart();
        // DELETE (bukan TRUNCATE) agar tetap di dalam transaksi.
        $this->builder()->emptyTable();
        if ($rows !== []) {
            $this->insertBatch($rows);
        }
        $this->db->transComplete();
    }
}
