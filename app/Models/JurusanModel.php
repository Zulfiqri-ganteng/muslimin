<?php

namespace App\Models;

use CodeIgniter\Model;

class JurusanModel extends Model
{
    protected $table          = 'jurusan';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['kode', 'nama'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'kode' => 'required|max_length[20]|is_unique[jurusan.kode,id,{id}]',
        'nama' => 'required|max_length[100]',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode jurusan sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama jurusan wajib diisi.'],
    ];
}
