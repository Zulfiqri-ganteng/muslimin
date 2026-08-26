<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tindakan atas aset: perbaikan, maintenance rutin, atau penggantian komponen.
 * Penggantian komponen dicatat pula sebagai mutasi keluar di sparepart_mutasi
 * (lewat perbaikan_id) sehingga stok berkurang otomatis.
 */
class PerbaikanModel extends Model
{
    protected $table         = 'perbaikan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'aset_id', 'kerusakan_id', 'jenis', 'tanggal', 'teknisi_id',
        'tindakan', 'hasil', 'biaya', 'status', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const JENIS  = ['perbaikan', 'maintenance', 'penggantian'];
    public const HASIL  = ['berhasil', 'sebagian', 'gagal', 'ganti_unit'];
    public const STATUS = ['proses', 'selesai'];

    protected $validationRules = [
        'id'           => 'permit_empty|is_natural',
        'aset_id'      => 'required|is_natural',
        'kerusakan_id' => 'permit_empty|is_natural',
        'jenis'        => 'permit_empty|in_list[perbaikan,maintenance,penggantian]',
        'tanggal'      => 'required|valid_date[Y-m-d]',
        'tindakan'     => 'required|max_length[255]',
        'hasil'        => 'permit_empty|in_list[berhasil,sebagian,gagal,ganti_unit]',
        'biaya'        => 'permit_empty|decimal',
        'status'       => 'permit_empty|in_list[proses,selesai]',
    ];
    protected $validationMessages = [
        'aset_id'  => ['required' => 'Aset yang ditangani wajib dipilih.'],
        'tindakan' => ['required' => 'Tindakan perbaikan wajib diisi.'],
    ];

    /** Daftar perbaikan + info aset, teknisi, dan kerusakan terkait. */
    public function withRelations()
    {
        return $this->select('perbaikan.*, aset.nama AS aset_nama, aset.nomor_aset, teknisi.nama AS teknisi_nama, kerusakan.deskripsi AS kerusakan_deskripsi')
            ->join('aset', 'aset.id = perbaikan.aset_id', 'left')
            ->join('teknisi', 'teknisi.id = perbaikan.teknisi_id', 'left')
            ->join('kerusakan', 'kerusakan.id = perbaikan.kerusakan_id', 'left');
    }
}
