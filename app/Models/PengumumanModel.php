<?php

namespace App\Models;

use CodeIgniter\Model;

class PengumumanModel extends Model
{
    protected $table          = 'pengumuman';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['judul', 'isi', 'is_aktif'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'judul' => 'required|max_length[200]',
        'isi'   => 'required',
    ];
    protected $validationMessages = [
        'judul' => ['required' => 'Judul wajib diisi.'],
        'isi'   => ['required' => 'Isi pengumuman wajib diisi.'],
    ];

    /** Pengumuman aktif terbaru (untuk beranda publik), di-cache. */
    public function aktif(int $limit = 5): array
    {
        return cache()->remember('pengumuman_aktif', 600, fn () =>
            $this->where('is_aktif', 1)->orderBy('id', 'DESC')->findAll($limit)
        );
    }
}
