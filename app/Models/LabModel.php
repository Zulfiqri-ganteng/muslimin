<?php

namespace App\Models;

use CodeIgniter\Model;

class LabModel extends Model
{
    protected $table         = 'lab';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode', 'nama', 'jenis', 'ruang', 'kapasitas', 'teknisi_id', 'keterangan'];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const JENIS = ['komputer', 'jaringan', 'multimedia', 'lainnya'];

    protected $validationRules = [
        'id'         => 'permit_empty|is_natural',
        'kode'       => 'required|max_length[30]|is_unique[lab.kode,id,{id}]',
        'nama'       => 'required|max_length[150]',
        'jenis'      => 'permit_empty|in_list[komputer,jaringan,multimedia,lainnya]',
        'kapasitas'  => 'permit_empty|is_natural',
        'teknisi_id' => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode lab sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama lab wajib diisi.'],
    ];

    /** Daftar lab + nama penanggung jawab (teknisi). */
    public function withRelations()
    {
        return $this->select('lab.*, teknisi.nama AS teknisi_nama')
            ->join('teknisi', 'teknisi.id = lab.teknisi_id', 'left');
    }

    /** Opsi dropdown [id => nama] untuk form (mis. lokasi aset). */
    public function options(): array
    {
        return cache()->remember('opt_lab', 21600, function () {
            $out = [];
            foreach ($this->select('id, nama')->orderBy('nama', 'ASC')->findAll() as $r) {
                $out[$r['id']] = $r['nama'];
            }

            return $out;
        });
    }
}
