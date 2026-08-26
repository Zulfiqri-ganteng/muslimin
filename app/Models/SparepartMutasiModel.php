<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Mutasi stok sparepart (masuk/keluar). Append-only, tanpa soft delete;
 * created_at diisi manual saat insert. Keluar biasanya menyertai penggantian
 * komponen pada sebuah perbaikan (perbaikan_id).
 */
class SparepartMutasiModel extends Model
{
    protected $table         = 'sparepart_mutasi';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'sparepart_id', 'tanggal', 'tipe', 'jumlah', 'perbaikan_id', 'keterangan', 'petugas', 'created_at',
    ];

    protected $useTimestamps = false;

    public const TIPE = ['masuk', 'keluar'];

    protected $validationRules = [
        'sparepart_id' => 'required|is_natural',
        'tanggal'      => 'required|valid_date[Y-m-d]',
        'tipe'         => 'required|in_list[masuk,keluar]',
        'jumlah'       => 'required|is_natural_no_zero',
    ];

    /** Riwayat mutasi satu sparepart (terbaru dulu). */
    public function forSparepart(int $sparepartId)
    {
        return $this->where('sparepart_id', $sparepartId)
            ->orderBy('tanggal', 'DESC')->orderBy('id', 'DESC')->findAll();
    }
}
