<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditModel extends Model
{
    protected $table         = 'audit_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'admin_id', 'aksi', 'tabel', 'record_id', 'deskripsi', 'ip_address', 'created_at',
    ];

    /** Catat satu aktivitas admin. Tahan-error agar tak mengganggu alur utama. */
    public function record(string $aksi, string $tabel, ?int $recordId = null, string $deskripsi = ''): void
    {
        try {
            $this->insert([
                'admin_id'   => session('admin')['id'] ?? null,
                'aksi'       => $aksi,
                'tabel'      => $tabel,
                'record_id'  => $recordId,
                'deskripsi'  => mb_substr($deskripsi, 0, 255),
                'ip_address' => service('request')->getIPAddress(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Audit gagal: ' . $e->getMessage());
        }
    }
}
