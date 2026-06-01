<?php

namespace App\Models;

use CodeIgniter\Model;

class SubmissionModel extends Model
{
    protected $table            = 'submissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'nama_lengkap', 'nip_nuptk', 'tempat_lahir', 'tanggal_lahir',
        'pendidikan_terakhir', 'guru_mapel', 'status_kepegawaian', 'nomor_hp',
        'mapel_diampu', 'total_jam', 'tugas_tambahan', 'tugas_lainnya',
        'kesediaan_jam', 'preferensi', 'ketersediaan_hari', 'keterangan_tambahan',
        'bersedia_mengajar', 'komitmen_setuju', 'status', 'catatan_admin', 'edit_token', 'ip_address',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Field yang disimpan sebagai JSON (string) di database. */
    public static array $jsonFields = [
        'mapel_diampu', 'tugas_tambahan', 'kesediaan_jam', 'preferensi', 'ketersediaan_hari',
    ];

    /**
     * Decode field-field JSON menjadi array PHP pada satu baris.
     */
    public static function decode(?array $row): ?array
    {
        if (! $row) {
            return $row;
        }
        foreach (self::$jsonFields as $f) {
            if (array_key_exists($f, $row)) {
                $decoded     = json_decode($row[$f] ?? '', true);
                $row[$f]     = is_array($decoded) ? $decoded : [];
            }
        }
        return $row;
    }

    /**
     * Decode banyak baris sekaligus.
     */
    public static function decodeAll(array $rows): array
    {
        return array_map(fn ($r) => self::decode($r), $rows);
    }

    /**
     * Statistik untuk dashboard admin.
     */
    public function getStats(): array
    {
        $total = $this->countAllResults(false);

        $byStatus = [];
        foreach (['PNS', 'PPPK', 'GTY', 'GTT'] as $s) {
            $byStatus[$s] = $this->where('status_kepegawaian', $s)->countAllResults(true);
        }

        $today = $this->where('DATE(created_at)', date('Y-m-d'))->countAllResults(true);

        return [
            'total'    => $total,
            'byStatus' => $byStatus,
            'today'    => $today,
        ];
    }
}
