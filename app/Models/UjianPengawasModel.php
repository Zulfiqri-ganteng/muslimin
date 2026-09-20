<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Penugasan pengawas ujian per jadwal — SEPENUHNYA OPSIONAL.
 *
 * Jadwal ujian tetap sah tanpa satu pun pengawas; tabel ini cuma pendataan
 * tambahan bila kurikulum memang mau mencatatnya.
 *
 * Pivot dengan HARD DELETE (pola jadwal_ukk_penguji): tidak ada riwayat yang
 * berdiri sendiri di sini, jadi tak perlu soft delete.
 */
class UjianPengawasModel extends Model
{
    protected $table         = 'ujian_pengawas';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['jadwal_id', 'guru_id', 'ruang', 'peran', 'keterangan'];

    protected $useTimestamps  = true;
    protected $updatedField   = '';
    protected $useSoftDeletes = false;

    public const PERAN = ['pengawas', 'cadangan'];

    protected $validationRules = [
        'id'        => 'permit_empty|is_natural',
        'jadwal_id' => 'required|is_natural',
        'guru_id'   => 'permit_empty|is_natural',
        'ruang'     => 'permit_empty|max_length[100]',
        'peran'     => 'permit_empty|in_list[pengawas,cadangan]',
    ];
    protected $validationMessages = [
        'jadwal_id' => ['required' => 'Jadwal ujian wajib dipilih.'],
    ];

    /** Daftar pengawas + nama & kode gurunya. */
    public function withRelations()
    {
        return $this->select('ujian_pengawas.*, guru.nama AS guru_nama, guru.kode_guru')
            ->join('guru', 'guru.id = ujian_pengawas.guru_id', 'left');
    }

    /** Pengawas satu jadwal (ketua/pengawas dulu, baru cadangan). */
    public function untukJadwal(int $jadwalId): array
    {
        return $this->withRelations()
            ->where('ujian_pengawas.jadwal_id', $jadwalId)
            ->orderBy('ujian_pengawas.peran', 'ASC')
            ->orderBy('guru.nama', 'ASC')
            ->findAll();
    }

    /** Guru ini sudah ditugaskan di jadwal tsb? (guard anti-dobel) */
    public function sudahDitugaskan(int $jadwalId, int $guruId, ?int $exceptId = null): bool
    {
        $b = $this->where(['jadwal_id' => $jadwalId, 'guru_id' => $guruId]);
        if ($exceptId !== null) {
            $b = $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }

    /**
     * Sesi lain yang membuat guru ini kebagian dua tugas pada jam bersamaan.
     *
     * Dicari lintas periode — seorang guru tetap tak bisa mengawasi dua ruang
     * sekaligus walau sesinya milik gelombang ujian yang berbeda.
     *
     * @param array $jadwal baris jadwal yang sedang dituju (tanggal + jam)
     *
     * @return array<int, array<string, mixed>> jadwal yang bertabrakan
     */
    public function bentrokGuru(int $guruId, array $jadwal, ?int $exceptId = null): array
    {
        $b = $this->select(
            'ujian_pengawas.id, ujian_pengawas.ruang, ujian_jadwal.tanggal,'
            . ' ujian_jadwal.jam_mulai, ujian_jadwal.jam_selesai, ujian_jadwal.tingkat,'
            . ' mata_pelajaran.nama_mapel'
        )
            ->join('ujian_jadwal', 'ujian_jadwal.id = ujian_pengawas.jadwal_id')
            ->join('mata_pelajaran', 'mata_pelajaran.id = ujian_jadwal.mapel_id', 'left')
            ->where('ujian_pengawas.guru_id', $guruId)
            ->where('ujian_jadwal.tanggal', $jadwal['tanggal'])
            ->where('ujian_jadwal.deleted_at', null)
            ->where('ujian_jadwal.id !=', (int) $jadwal['id']);

        if ($exceptId !== null) {
            $b = $b->where('ujian_pengawas.id !=', $exceptId);
        }

        $hasil = [];
        foreach ($b->findAll() as $row) {
            if (UjianJadwalModel::jamBerimpit(
                $jadwal['jam_mulai'] ?? null,
                $jadwal['jam_selesai'] ?? null,
                $row['jam_mulai'],
                $row['jam_selesai']
            )) {
                $hasil[] = $row;
            }
        }

        return $hasil;
    }

    /** Jumlah pengawas per jadwal untuk sederet id, [jadwal_id => jumlah]. */
    public function countForJadwal(array $jadwalIds): array
    {
        if ($jadwalIds === []) {
            return [];
        }

        $rows = $this->select('jadwal_id, COUNT(*) AS jml')
            ->whereIn('jadwal_id', $jadwalIds)
            ->groupBy('jadwal_id')
            ->findAll();

        return array_column($rows, 'jml', 'jadwal_id');
    }
}
