<?php

namespace App\Models;

use CodeIgniter\Model;

class JurusanModel extends Model
{
    protected $table          = 'jurusan';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['kode', 'nama', 'label_ujian', 'urutan_ujian', 'urutan_ujian_siang'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'   => 'permit_empty|is_natural',
        'kode' => 'required|max_length[20]|is_unique[jurusan.kode,id,{id}]',
        'nama' => 'required|max_length[100]',
        'label_ujian' => 'permit_empty|max_length[50]', // label di judul kolom jadwal ujian (kosong = kode)
        'urutan_ujian' => 'permit_empty|is_natural|less_than_equal_to[999]', // urutan kolom di jadwal ujian (pagi; siang bila kolom siang kosong)
        'urutan_ujian_siang' => 'permit_empty|is_natural|less_than_equal_to[999]', // urutan kolom lembar SIANG (kosong = ikut urutan_ujian)
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
