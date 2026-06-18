<?php

namespace App\Models;

use CodeIgniter\Model;

class HariModel extends Model
{
    protected $table         = 'hari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama', 'urutan', 'aktif'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'id'     => 'permit_empty|is_natural',
        'nama'   => 'required|max_length[15]|is_unique[hari.nama,id,{id}]',
        'urutan' => 'required|is_natural_no_zero',
    ];
    protected $validationMessages = [
        'nama' => ['is_unique' => 'Nama hari sudah ada.', 'required' => 'Nama hari wajib diisi.'],
    ];

    /** Hari aktif urut tampil (untuk grid jadwal & dropdown). */
    public function aktifUrut(): array
    {
        return $this->where('aktif', 1)->orderBy('urutan', 'ASC')->findAll();
    }
}
