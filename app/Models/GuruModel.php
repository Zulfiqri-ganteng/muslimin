<?php

namespace App\Models;

use CodeIgniter\Model;

class GuruModel extends Model
{
    protected $table          = 'guru';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['nip', 'kode_guru', 'nama', 'jenis_kelamin', 'status_guru', 'max_beban', 'keterangan'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'kode_guru'     => 'required|max_length[20]|is_unique[guru.kode_guru,id,{id}]',
        'nama'          => 'required|max_length[150]',
        'nip'           => 'permit_empty|max_length[60]',
        'jenis_kelamin' => 'permit_empty|in_list[L,P]',
        'status_guru'   => 'permit_empty|in_list[PNS,PPPK,GTY,GTT]',
        'max_beban'     => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'kode_guru' => ['is_unique' => 'Kode guru sudah dipakai.', 'required' => 'Kode guru wajib diisi.'],
        'nama'      => ['required' => 'Nama guru wajib diisi.'],
    ];

    /** Opsi guru untuk dropdown (id => "kode - nama"), dengan cache. */
    public function options(): array
    {
        return cache()->remember('opt_guru', 21600, function () {
            $rows = $this->select('id, kode_guru, nama')->orderBy('nama', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['kode_guru'] . ' - ' . $r['nama'];
            }
            return $out;
        });
    }
}
