<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'school_name', 'school_level', 'logo', 'headmaster_name', 'headmaster_nip',
        'city', 'academic_year', 'address', 'phone', 'email', 'website',
        'form_open', 'form_intro', 'jadwal_publik',
    ];

    protected $useTimestamps = true;
    protected $createdField  = '';
    protected $updatedField  = 'updated_at';

    /**
     * Ambil baris pengaturan tunggal (id = 1). Buat default bila belum ada.
     */
    public function get(): array
    {
        $row = $this->find(1);
        if (! $row) {
            $this->insert([
                'id'            => 1,
                'school_name'   => 'NAMA SEKOLAH ANDA',
                'city'          => 'Bekasi',
                'academic_year' => '2026/2027',
                'form_open'     => 1,
            ], false);
            $row = $this->find(1);
        }
        return $row;
    }

    /**
     * Simpan pengaturan (selalu id = 1).
     */
    public function store(array $data): bool
    {
        $data['id'] = 1;
        return $this->save($data);
    }
}
