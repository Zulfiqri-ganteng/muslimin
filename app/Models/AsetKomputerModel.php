<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Detail 1:1 khusus komputer untuk sebuah aset (kategori komputer/laptop).
 * Tanpa soft delete — ikut terhapus (CASCADE) saat asetnya dihapus permanen.
 */
class AsetKomputerModel extends Model
{
    protected $table         = 'aset_komputer';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'aset_id', 'hostname', 'processor', 'ram', 'storage', 'gpu', 'os',
        'mac_address', 'ip_address', 'monitor', 'keterangan',
    ];

    protected $useTimestamps = true;

    protected $validationRules = [
        'id'      => 'permit_empty|is_natural',
        'aset_id' => 'required|is_natural|is_unique[aset_komputer.aset_id,id,{id}]',
    ];
    protected $validationMessages = [
        'aset_id' => ['is_unique' => 'Aset ini sudah punya detail komputer.'],
    ];

    /** Ambil detail komputer milik satu aset (atau null bila belum ada). */
    public function forAset(int $asetId): ?array
    {
        return $this->where('aset_id', $asetId)->first();
    }
}
