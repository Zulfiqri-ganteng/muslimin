<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Saklar notifikasi per admin. Belum ada baris = bawaan (aktif, tidak dijeda,
 * diam saat ujian).
 */
class NotifPengaturanModel extends Model
{
    protected $table            = 'notif_pengaturan';
    protected $primaryKey       = 'admin_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = ['admin_id', 'aktif', 'jeda_sampai', 'diam_saat_ujian', 'updated_at'];

    /** @return array{aktif:bool,jeda_sampai:?string,diam_saat_ujian:bool} */
    public function ambil(int $adminId): array
    {
        $r = $this->find($adminId);

        return [
            'aktif'           => $r ? (int) $r['aktif'] === 1 : true,
            'jeda_sampai'     => $r['jeda_sampai'] ?? null,
            'diam_saat_ujian' => $r ? (int) $r['diam_saat_ujian'] === 1 : true,
        ];
    }

    /** Simpan (insert / update) pengaturan admin. */
    public function simpan(int $adminId, bool $aktif, ?string $jedaSampai, bool $diamUjian): array
    {
        $data = [
            'aktif'           => $aktif ? 1 : 0,
            'jeda_sampai'     => $jedaSampai,
            'diam_saat_ujian' => $diamUjian ? 1 : 0,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];
        if ($this->find($adminId)) {
            $this->update($adminId, $data);
        } else {
            $this->insert(['admin_id' => $adminId] + $data);
        }

        return $this->ambil($adminId);
    }
}
