<?php

namespace App\Models;

use CodeIgniter\Model;

class AsetModel extends Model
{
    protected $table         = 'aset';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nomor_aset', 'nama', 'kategori', 'lab_id', 'merk', 'spesifikasi',
        'tahun_pengadaan', 'sumber_dana', 'harga', 'kondisi', 'status', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const KATEGORI = ['komputer', 'laptop', 'printer', 'proyektor', 'jaringan', 'furnitur', 'lainnya'];
    public const KONDISI  = ['baik', 'rusak_ringan', 'rusak_berat'];
    public const STATUS   = ['tersedia', 'dipinjam', 'perbaikan', 'dihapus'];

    protected $validationRules = [
        'id'              => 'permit_empty|is_natural',
        'nomor_aset'      => 'required|max_length[50]|is_unique[aset.nomor_aset,id,{id}]',
        'nama'            => 'required|max_length[150]',
        'kategori'        => 'permit_empty|in_list[komputer,laptop,printer,proyektor,jaringan,furnitur,lainnya]',
        'lab_id'          => 'permit_empty|is_natural',
        'tahun_pengadaan' => 'permit_empty|is_natural',
        'harga'           => 'permit_empty|decimal',
        'kondisi'         => 'permit_empty|in_list[baik,rusak_ringan,rusak_berat]',
        'status'          => 'permit_empty|in_list[tersedia,dipinjam,perbaikan,dihapus]',
    ];
    protected $validationMessages = [
        'nomor_aset' => ['is_unique' => 'Nomor aset sudah dipakai.', 'required' => 'Nomor aset wajib diisi.'],
        'nama'       => ['required' => 'Nama aset wajib diisi.'],
    ];

    /** Daftar aset + nama lab (lokasi). Detail komputer diambil terpisah. */
    public function withRelations()
    {
        return $this->select('aset.*, lab.nama AS lab_nama, lab.kode AS lab_kode')
            ->join('lab', 'lab.id = aset.lab_id', 'left');
    }
}
