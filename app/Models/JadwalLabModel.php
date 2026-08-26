<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jadwal pemakaian lab (modul terpisah dari Penjadwalan KBM).
 * "Jadwal praktik guru" = jadwal_lab yang difilter per guru.
 * UNIQUE(lab_id,hari_id,jam_id) menjamin satu lab tak dipakai dua kegiatan
 * pada slot yang sama. Hard delete (tanpa soft delete), seperti tabel jadwal.
 */
class JadwalLabModel extends Model
{
    protected $table         = 'jadwal_lab';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'lab_id', 'hari_id', 'jam_id', 'guru_id', 'kelas_id', 'mapel_id', 'kegiatan', 'keterangan',
    ];

    protected $useTimestamps = true;

    protected $validationRules = [
        'id'       => 'permit_empty|is_natural',
        'lab_id'   => 'required|is_natural',
        'hari_id'  => 'required|is_natural',
        'jam_id'   => 'required|is_natural',
        'guru_id'  => 'permit_empty|is_natural',
        'kelas_id' => 'permit_empty|is_natural',
        'mapel_id' => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'lab_id'  => ['required' => 'Lab wajib dipilih.'],
        'hari_id' => ['required' => 'Hari wajib dipilih.'],
        'jam_id'  => ['required' => 'Jam wajib dipilih.'],
    ];

    /** Jadwal satu lab lengkap dengan nama hari/jam/guru/kelas/mapel. */
    public function withRelations()
    {
        return $this->select(
            'jadwal_lab.*, lab.nama AS lab_nama, hari.nama AS hari_nama, hari.urutan AS hari_urutan,'
            . ' jam_pelajaran.jam_ke, jam_pelajaran.waktu_mulai, jam_pelajaran.waktu_selesai,'
            . ' guru.nama AS guru_nama, kelas.nama_kelas, mata_pelajaran.nama_mapel'
        )
            ->join('lab', 'lab.id = jadwal_lab.lab_id', 'left')
            ->join('hari', 'hari.id = jadwal_lab.hari_id', 'left')
            ->join('jam_pelajaran', 'jam_pelajaran.id = jadwal_lab.jam_id', 'left')
            ->join('guru', 'guru.id = jadwal_lab.guru_id', 'left')
            ->join('kelas', 'kelas.id = jadwal_lab.kelas_id', 'left')
            ->join('mata_pelajaran', 'mata_pelajaran.id = jadwal_lab.mapel_id', 'left');
    }

    /** Cek slot lab sudah terisi (untuk validasi anti bentrok saat simpan). */
    public function slotTerpakai(int $labId, int $hariId, int $jamId, ?int $exceptId = null): bool
    {
        $b = $this->where(['lab_id' => $labId, 'hari_id' => $hariId, 'jam_id' => $jamId]);
        if ($exceptId !== null) {
            $b = $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }
}
