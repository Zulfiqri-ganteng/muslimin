<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * HP penerima notifikasi jadwal guru (token Firebase Cloud Messaging).
 *
 * Satu HP = satu baris per admin (UNIQUE admin_id + device_id). Satu token
 * hanya milik satu baris (UNIQUE token_hash): bila HP berganti akun, baris
 * lama milik akun lain dibuang. Pendaftaran TIDAK terikat token login —
 * auto-logout karena idle tidak mencabutnya; hanya "Keluar" manual.
 */
class NotifPerangkatModel extends Model
{
    protected $table         = 'notif_perangkat';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['admin_id', 'device_id', 'token', 'token_hash', 'nama_perangkat', 'terima', 'last_seen_at'];
    protected $useTimestamps = true;

    /** Daftarkan / perbarui token satu HP untuk admin ini. */
    public function daftar(int $adminId, string $deviceId, string $token, ?string $nama): array
    {
        $hash = hash('sha256', $token);

        $this->db->transStart();
        // HP ini sebelumnya milik akun lain → pindah ke akun sekarang.
        $this->where('device_id', $deviceId)->where('admin_id !=', $adminId)->delete();
        // Token sama tercatat di baris lain (mis. device_id berubah setelah instal ulang).
        $this->where('token_hash', $hash)
            ->groupStart()->where('admin_id !=', $adminId)->orWhere('device_id !=', $deviceId)->groupEnd()
            ->delete();

        $ada  = $this->where(['admin_id' => $adminId, 'device_id' => $deviceId])->first();
        $data = [
            'admin_id'       => $adminId,
            'device_id'      => $deviceId,
            'token'          => $token,
            'token_hash'     => $hash,
            'nama_perangkat' => $nama !== null && $nama !== '' ? mb_substr($nama, 0, 100) : null,
            'terima'         => 1,
            'last_seen_at'   => date('Y-m-d H:i:s'),
        ];
        if ($ada) {
            $this->update($ada['id'], $data);
        } else {
            $this->insert($data);
        }
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return []; // gagal → pemanggil melaporkan galat, bukan "terdaftar"
        }

        return $this->where(['admin_id' => $adminId, 'device_id' => $deviceId])->first() ?? [];
    }

    public function milik(int $adminId, string $deviceId): ?array
    {
        return $this->where(['admin_id' => $adminId, 'device_id' => $deviceId])->first();
    }

    /** HP yang menerima notif, dikelompokkan per admin: [admin_id => list<row>]. */
    public function penerimaPerAdmin(): array
    {
        $out = [];
        foreach ($this->where('terima', 1)->findAll() as $r) {
            $out[(int) $r['admin_id']][] = $r;
        }

        return $out;
    }

    /** Buang token yang ditolak Firebase (aplikasi dihapus / token kedaluwarsa). */
    public function buangToken(string $token): void
    {
        $this->where('token_hash', hash('sha256', $token))->delete();
    }
}
