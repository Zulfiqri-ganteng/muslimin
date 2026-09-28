<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Riwayat notifikasi + penjaga ANTI DOBEL. `kunci` UNIQUE: satu slot jadwal
 * ("jadwal:{admin}:{tanggal}:{HH:MM}") hanya bisa diklaim sekali, jadi dua
 * proses cron yang tumpang tindih tidak mengirim notif yang sama dua kali.
 */
class NotifLogModel extends Model
{
    protected $table         = 'notif_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'admin_id', 'jenis', 'kunci', 'tanggal', 'slot', 'judul', 'isi', 'data',
        'jml_perangkat', 'berhasil', 'gagal', 'galat', 'created_at',
    ];

    /**
     * Klaim satu kunci: INSERT IGNORE. Mengembalikan id baris baru, atau null
     * bila kunci sudah pernah dipakai (sudah dikirim / sedang dikirim proses lain).
     */
    public function klaim(array $row): ?int
    {
        $row += ['created_at' => date('Y-m-d H:i:s')];
        if (isset($row['data']) && is_array($row['data'])) {
            $row['data'] = json_encode($row['data'], JSON_UNESCAPED_UNICODE);
        }
        $this->db->table($this->table)->ignore(true)->insert($row);

        return $this->db->affectedRows() === 1 ? (int) $this->db->insertID() : null;
    }

    public function sudahAda(string $kunci): bool
    {
        return $this->where('kunci', $kunci)->countAllResults() > 0;
    }
}
