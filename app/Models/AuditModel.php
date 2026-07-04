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

    /** Builder daftar log + nama admin (untuk viewer, urut terbaru). */
    public function filtered(string $q = '', string $aksi = '', string $tabel = '')
    {
        $b = $this->select('audit_log.*, admins.full_name AS admin_nama')
            ->join('admins', 'admins.id = audit_log.admin_id', 'left');
        if ($q !== '') {
            $b->like('audit_log.deskripsi', $q);
        }
        if ($aksi !== '') {
            $b->where('audit_log.aksi', $aksi);
        }
        if ($tabel !== '') {
            $b->where('audit_log.tabel', $tabel);
        }
        return $b->orderBy('audit_log.id', 'DESC');
    }

    /** Daftar nama tabel unik (untuk dropdown filter). */
    public function tabelList(): array
    {
        return array_column(
            $this->select('tabel')->distinct()->orderBy('tabel', 'ASC')->findAll(),
            'tabel'
        );
    }

    /** Hapus log lebih lama dari N hari (housekeeping shared hosting). */
    public function purgeOlderThan(int $days): int
    {
        $batas = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $this->where('created_at <', $batas)->delete();
        return $this->db->affectedRows();
    }

    /** Catat satu aktivitas admin. Tahan-error agar tak mengganggu alur utama. */
    public function record(string $aksi, string $tabel, ?int $recordId = null, string $deskripsi = ''): void
    {
        try {
            $this->insert([
                // Web memakai session; API mobile (stateless) memakai ApiAuth.
                'admin_id'   => session('admin')['id'] ?? \App\Libraries\ApiAuth::id(),
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
