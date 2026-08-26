<?php

namespace App\Models;

use CodeIgniter\Model;

class SparepartModel extends Model
{
    protected $table         = 'sparepart';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'kode', 'nama', 'kategori', 'satuan', 'stok', 'stok_minimum', 'harga', 'lokasi', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'           => 'permit_empty|is_natural',
        'kode'         => 'required|max_length[30]|is_unique[sparepart.kode,id,{id}]',
        'nama'         => 'required|max_length[150]',
        'stok'         => 'permit_empty|integer',
        'stok_minimum' => 'permit_empty|integer',
        'harga'        => 'permit_empty|decimal',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode sparepart sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama sparepart wajib diisi.'],
    ];

    /** Sparepart dengan stok di bawah/menyentuh batas minimum. */
    public function stokMenipis()
    {
        return $this->where('stok <= stok_minimum', null, false)->orderBy('nama', 'ASC')->findAll();
    }
}
