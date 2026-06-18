<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ketersediaan guru. Konvensi: HANYA baris berstatus 'tidak' yang disimpan
 * (hemat baris). Slot tanpa baris dianggap TERSEDIA. Dipakai untuk Rule R3.
 */
class KetersediaanGuruModel extends Model
{
    protected $table         = 'ketersediaan_guru';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['guru_id', 'hari_id', 'jam_id', 'status', 'keterangan'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** R3: apakah guru TIDAK tersedia pada slot ini? */
    public function isUnavailable(int $guruId, int $hariId, int $jamId): bool
    {
        return $this->where('guru_id', $guruId)->where('hari_id', $hariId)
            ->where('jam_id', $jamId)->where('status', 'tidak')->countAllResults() > 0;
    }

    /** Set slot tidak-tersedia milik guru => key "hariId-jamId". */
    public function unavailableSet(int $guruId): array
    {
        $rows = $this->select('hari_id, jam_id')
            ->where('guru_id', $guruId)->where('status', 'tidak')->findAll();
        $set = [];
        foreach ($rows as $r) {
            $set[$r['hari_id'] . '-' . $r['jam_id']] = true;
        }
        return $set;
    }

    /**
     * Simpan ulang ketersediaan guru untuk satu shift.
     * Hanya menghapus/mengisi slot pada jam shift tsb (shift lain tak tersentuh).
     *
     * @param int[] $shiftJamIds daftar jam_id milik shift yang diedit
     * @param array $unavailable daftar "hariId-jamId" yang ditandai TIDAK tersedia
     */
    public function syncShift(int $guruId, array $shiftJamIds, array $unavailable): void
    {
        $this->db->transStart();

        // bersihkan slot guru pada shift ini saja
        $this->where('guru_id', $guruId);
        if ($shiftJamIds) {
            $this->whereIn('jam_id', $shiftJamIds);
        } else {
            $this->where('1 =', 0); // tak ada jam → tak hapus apa pun
        }
        $this->delete();

        // sisipkan slot tidak-tersedia
        $now  = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($unavailable as $key) {
            [$hariId, $jamId] = array_pad(explode('-', (string) $key), 2, null);
            $hariId = (int) $hariId;
            $jamId  = (int) $jamId;
            if ($hariId && $jamId && in_array($jamId, $shiftJamIds, true)) {
                $rows[] = [
                    'guru_id' => $guruId, 'hari_id' => $hariId, 'jam_id' => $jamId,
                    'status'  => 'tidak', 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        if ($rows) {
            $this->insertBatch($rows);
        }

        $this->db->transComplete();
    }
}
