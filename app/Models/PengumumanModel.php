<?php

namespace App\Models;

use CodeIgniter\Model;

class PengumumanModel extends Model
{
    /** Kategori = label di kartu "Informasi untuk Anda" situs publik (kolom bawaan DB: 'Pengumuman'). */
    public const KATEGORI = ['Pengumuman', 'Berita', 'Agenda', 'Prestasi'];
    /** Gambar opsional (WEBP) di public/uploads/pengumuman/. */
    public const FOLDER = 'pengumuman';

    protected $table          = 'pengumuman';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['judul', 'isi', 'kategori', 'gambar', 'is_aktif'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'judul'    => 'required|max_length[200]',
        'isi'      => 'required',
        'kategori' => 'permit_empty|in_list[Pengumuman,Berita,Agenda,Prestasi]',
    ];
    protected $validationMessages = [
        'judul'    => ['required' => 'Judul wajib diisi.'],
        'isi'      => ['required' => 'Isi pengumuman wajib diisi.'],
        'kategori' => ['in_list' => 'Kategori tidak dikenal.'],
    ];

    /** Pengumuman aktif terbaru (untuk beranda publik), di-cache. */
    public function aktif(int $limit = 5): array
    {
        return cache()->remember('pengumuman_aktif', 600, fn () =>
            $this->where('is_aktif', 1)->orderBy('id', 'DESC')->findAll($limit)
        );
    }
}
