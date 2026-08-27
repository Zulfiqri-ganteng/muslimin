<?php

namespace App\Models;

use CodeIgniter\Model;

class TempatUjiModel extends Model
{
    protected $table         = 'tempat_uji';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode', 'nama', 'alamat', 'kapasitas', 'lab_id', 'keterangan'];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'        => 'permit_empty|is_natural',
        'kode'      => 'required|max_length[30]|is_unique[tempat_uji.kode,id,{id}]',
        'nama'      => 'required|max_length[150]',
        'kapasitas' => 'permit_empty|is_natural',
        'lab_id'    => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode tempat uji sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama tempat uji wajib diisi.'],
    ];

    /** Daftar tempat uji + nama lab tertaut (bila ada). */
    public function withRelations()
    {
        return $this->select('tempat_uji.*, lab.nama AS lab_nama')
            ->join('lab', 'lab.id = tempat_uji.lab_id', 'left');
    }

    /** Opsi dropdown [id => "kode - nama"] untuk form jadwal UKK. */
    public function options(): array
    {
        return cache()->remember('opt_tempat_uji', 21600, function () {
            $out = [];
            foreach ($this->select('id, kode, nama')->orderBy('nama', 'ASC')->findAll() as $r) {
                $out[$r['id']] = $r['kode'] . ' - ' . $r['nama'];
            }

            return $out;
        });
    }
}
