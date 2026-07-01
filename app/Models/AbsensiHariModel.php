<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registry tanggal yang sudah diabsen. Satu baris = satu hari yang benar-benar
 * dikelola admin (walau semua hadir). Rekap hanya menghitung tanggal di sini.
 */
class AbsensiHariModel extends Model
{
    protected $table         = 'absensi_hari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['tanggal', 'created_by'];

    /** Apakah tanggal sudah tercatat (sudah pernah disimpan absensinya). */
    public function isRecorded(string $tanggal): bool
    {
        return $this->where('tanggal', $tanggal)->countAllResults() > 0;
    }

    /** Tandai tanggal sebagai sudah diabsen (idempoten). */
    public function mark(string $tanggal, ?int $adminId = null): void
    {
        if (! $this->isRecorded($tanggal)) {
            $this->insert(['tanggal' => $tanggal, 'created_by' => $adminId]);
        }
    }

    /** Batalkan pencatatan tanggal. */
    public function unmark(string $tanggal): void
    {
        $this->where('tanggal', $tanggal)->delete();
    }

    /** Daftar tanggal tercatat pada rentang (list 'Y-m-d', urut naik). */
    public function datesInRange(string $dari, string $sampai): array
    {
        return array_column(
            $this->select('tanggal')
                ->where('tanggal >=', $dari)
                ->where('tanggal <=', $sampai)
                ->orderBy('tanggal', 'ASC')
                ->findAll(),
            'tanggal'
        );
    }
}
