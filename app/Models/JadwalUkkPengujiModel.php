<?php

namespace App\Models;

use CodeIgniter\Model;

/** Penugasan penguji (internal →guru / eksternal →penguji_eksternal) per jadwal UKK. Hard delete, pola sama jadwal_lab. */
class JadwalUkkPengujiModel extends Model
{
    protected $table         = 'jadwal_ukk_penguji';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['jadwal_ukk_id', 'tipe', 'guru_id', 'penguji_eksternal_id', 'peran'];

    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'jadwal_ukk_id' => 'required|is_natural',
        'tipe'          => 'required|in_list[internal,eksternal]',
        'guru_id'       => 'permit_empty|is_natural',
        'penguji_eksternal_id' => 'permit_empty|is_natural',
        'peran'         => 'permit_empty|in_list[ketua,anggota]',
    ];
    protected $validationMessages = [
        'jadwal_ukk_id' => ['required' => 'Jadwal UKK wajib dipilih.'],
        'tipe'          => ['required' => 'Tipe penguji wajib dipilih.'],
    ];

    /** Daftar penguji satu jadwal + nama guru/penguji eksternal. */
    public function forJadwal(int $jadwalId): array
    {
        return $this->select(
            'jadwal_ukk_penguji.*, guru.nama AS guru_nama, guru.kode_guru,'
            . ' penguji_eksternal.nama AS eksternal_nama, penguji_eksternal.instansi AS eksternal_instansi'
        )
            ->join('guru', 'guru.id = jadwal_ukk_penguji.guru_id', 'left')
            ->join('penguji_eksternal', 'penguji_eksternal.id = jadwal_ukk_penguji.penguji_eksternal_id', 'left')
            ->where('jadwal_ukk_id', $jadwalId)
            ->orderBy('peran', 'ASC')
            ->findAll();
    }
}
