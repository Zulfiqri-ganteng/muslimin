<?php

namespace App\Models;

use CodeIgniter\Model;

class TeknisiModel extends Model
{
    protected $table         = 'teknisi';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode', 'nama', 'peran', 'no_hp', 'guru_id', 'keterangan'];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const PERAN = ['teknisi', 'kepala_lab', 'laboran', 'lainnya'];

    protected $validationRules = [
        'id'      => 'permit_empty|is_natural',
        'kode'    => 'required|max_length[30]|is_unique[teknisi.kode,id,{id}]',
        'nama'    => 'required|max_length[150]',
        'peran'   => 'permit_empty|in_list[teknisi,kepala_lab,laboran,lainnya]',
        'guru_id' => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode teknisi sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama teknisi wajib diisi.'],
    ];

    /** Daftar teknisi + nama guru tertaut (bila ada). */
    public function withRelations()
    {
        return $this->select('teknisi.*, guru.nama AS guru_nama')
            ->join('guru', 'guru.id = teknisi.guru_id', 'left');
    }

    /** Opsi dropdown [id => nama] untuk form (mis. penanggung jawab lab). */
    public function options(): array
    {
        return cache()->remember('opt_teknisi', 21600, function () {
            $out = [];
            foreach ($this->select('id, nama')->orderBy('nama', 'ASC')->findAll() as $r) {
                $out[$r['id']] = $r['nama'];
            }

            return $out;
        });
    }
}
