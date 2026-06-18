<?php

namespace App\Models;

use CodeIgniter\Model;

class JamPelajaranModel extends Model
{
    protected $table          = 'jam_pelajaran';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['shift', 'jam_ke', 'waktu_mulai', 'waktu_selesai', 'durasi', 'is_istirahat'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'shift'         => 'required|in_list[pagi,siang]',
        'jam_ke'        => 'required|is_natural_no_zero',
        'waktu_mulai'   => 'required',
        'waktu_selesai' => 'required',
    ];

    /** Jam KBM (bukan istirahat) per shift, urut jam_ke. */
    public function urutShift(string $shift): array
    {
        return $this->where('shift', $shift)->orderBy('jam_ke', 'ASC')->findAll();
    }
}
