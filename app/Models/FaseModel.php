<?php

namespace App\Models;

use CodeIgniter\Model;

class FaseModel extends Model
{
    protected $table          = 'fase';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['kode', 'nama', 'urutan', 'deskripsi'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'     => 'permit_empty|is_natural',
        'kode'   => 'required|max_length[2]|is_unique[fase.kode,id,{id}]',
        'nama'   => 'required|max_length[30]',
        'urutan' => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode fase sudah dipakai.', 'required' => 'Kode fase wajib diisi.'],
        'nama' => ['required' => 'Nama fase wajib diisi.'],
    ];

    /** Fase urut tampil (untuk dropdown & grid). */
    public function urut(): array
    {
        return $this->orderBy('urutan', 'ASC')->findAll();
    }

    /** Opsi fase untuk dropdown (id => "Fase E"), di-cache. */
    public function options(): array
    {
        return cache()->remember('opt_fase', 21600, function () {
            $rows = $this->orderBy('urutan', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['nama'];
            }
            return $out;
        });
    }
}
