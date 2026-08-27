<?php

namespace App\Models;

use CodeIgniter\Model;

class PengujiEksternalModel extends Model
{
    protected $table         = 'penguji_eksternal';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode', 'nama', 'instansi', 'jabatan', 'no_hp', 'email', 'keterangan'];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'    => 'permit_empty|is_natural',
        'kode'  => 'required|max_length[30]|is_unique[penguji_eksternal.kode,id,{id}]',
        'nama'  => 'required|max_length[150]',
        'email' => 'permit_empty|valid_email|max_length[100]',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode penguji sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama penguji wajib diisi.'],
    ];

    /** Opsi dropdown [id => "nama - instansi"] untuk penugasan jadwal UKK. */
    public function options(): array
    {
        return cache()->remember('opt_penguji_eksternal', 21600, function () {
            $out = [];
            foreach ($this->select('id, nama, instansi')->orderBy('nama', 'ASC')->findAll() as $r) {
                $out[$r['id']] = $r['nama'] . ($r['instansi'] !== null && $r['instansi'] !== '' ? ' - ' . $r['instansi'] : '');
            }

            return $out;
        });
    }
}
