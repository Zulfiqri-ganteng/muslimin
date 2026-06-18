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
        'id'   => 'permit_empty|is_natural',
        'kode' => 'required|max_length[20]|is_unique[jurusan.kode,id,{id}]',
        'nama' => 'required|max_length[100]',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode jurusan sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama jurusan wajib diisi.'],
    ];

    /** Opsi jurusan untuk dropdown (id => "KODE - nama"), di-cache. */
    public function options(): array
    {
        return cache()->remember('opt_jurusan', 21600, function () {
            $rows = $this->orderBy('kode', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['kode'] . ' - ' . $r['nama'];
            }
            return $out;
        });
    }
}
