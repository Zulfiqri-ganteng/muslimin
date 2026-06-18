<?php

namespace App\Models;

use CodeIgniter\Model;

class KelasModel extends Model
{
    protected $table          = 'kelas';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['nama_kelas', 'tingkat', 'jurusan_id', 'wali_kelas_id', 'shift'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'nama_kelas'    => 'required|max_length[50]|is_unique[kelas.nama_kelas,id,{id}]',
        'tingkat'       => 'required|in_list[X,XI,XII]',
        'shift'         => 'required|in_list[pagi,siang]',
        'jurusan_id'    => 'permit_empty|is_natural',
        'wali_kelas_id' => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'nama_kelas' => ['is_unique' => 'Nama kelas sudah ada.', 'required' => 'Nama kelas wajib diisi.'],
    ];

    /** Builder daftar kelas + nama jurusan & wali (left join). */
    public function withRelations()
    {
        return $this->select('kelas.*, jurusan.kode AS jurusan_kode, jurusan.nama AS jurusan_nama, guru.nama AS wali_nama')
            ->join('jurusan', 'jurusan.id = kelas.jurusan_id', 'left')
            ->join('guru', 'guru.id = kelas.wali_kelas_id', 'left');
    }

    /** Opsi kelas untuk dropdown (id => nama_kelas), di-cache. */
    public function options(): array
    {
        return cache()->remember('opt_kelas', 21600, function () {
            $rows = $this->select('id, nama_kelas')->orderBy('tingkat', 'ASC')->orderBy('nama_kelas', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['nama_kelas'];
            }
            return $out;
        });
    }
}
