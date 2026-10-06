<?php

namespace App\Models;

use App\Libraries\PklForm;
use CodeIgniter\Model;

/**
 * Master perusahaan PKL (tabel pkl_perusahaan).
 *
 * Hanya berisi perusahaan yang pernah DISETUJUI staf (diisi saat ACC, Tahap 3),
 * sehingga saran nama di form siswa bersih dari salah ketik. Pencarian memakai
 * `nama_norm` (huruf kecil, tanpa tanda baca & "PT/CV") — "telkom" menemukan
 * "PT. Telkom Indonesia, Tbk".
 */
class PklPerusahaanModel extends Model
{
    protected $table         = 'pkl_perusahaan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nama', 'nama_norm', 'alamat', 'kota', 'telepon', 'kontak_nama', 'kontak_jabatan',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Saran untuk kolom nama perusahaan di form siswa. Yang NAMANYA DIAWALI
     * kata ketikan didahulukan, baru yang sekadar mengandungnya.
     *
     * @return list<array<string, mixed>>
     */
    public function saran(string $q, int $batas = 6): array
    {
        $norm = PklForm::normPerusahaan($q);
        if (mb_strlen($norm) < 2) {
            return [];
        }

        // $norm hanya huruf/angka/spasi (lihat normPerusahaan) — tak ada karakter wildcard LIKE.
        $awalan = $this->db->escape($norm . '%');

        return $this->db->table($this->table)
            ->select('id, nama, alamat, kota, telepon, kontak_nama, kontak_jabatan')
            ->like('nama_norm', $norm)
            ->orderBy('(nama_norm LIKE ' . $awalan . ') DESC', '', false)
            ->orderBy('nama', 'ASC')
            ->limit($batas)
            ->get()->getResultArray();
    }
}
