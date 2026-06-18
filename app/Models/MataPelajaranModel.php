<?php

namespace App\Models;

use CodeIgniter\Model;

class MataPelajaranModel extends Model
{
    protected $table          = 'mata_pelajaran';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['kode_mapel', 'nama_mapel', 'kelompok', 'jp_default'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'kode_mapel' => 'required|max_length[20]|is_unique[mata_pelajaran.kode_mapel,id,{id}]',
        'nama_mapel' => 'required|max_length[150]',
        'kelompok'   => 'permit_empty|max_length[50]',
        'jp_default' => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'kode_mapel' => ['is_unique' => 'Kode mapel sudah dipakai.', 'required' => 'Kode mapel wajib diisi.'],
        'nama_mapel' => ['required' => 'Nama mapel wajib diisi.'],
    ];

    /** Opsi mapel untuk dropdown (id => "kode - nama"), di-cache. */
    public function options(): array
    {
        return cache()->remember('opt_mapel', 21600, function () {
            $rows = $this->select('id, kode_mapel, nama_mapel')->orderBy('nama_mapel', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['kode_mapel'] . ' - ' . $r['nama_mapel'];
            }
            return $out;
        });
    }
}
