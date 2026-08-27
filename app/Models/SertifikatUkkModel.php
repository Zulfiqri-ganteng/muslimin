<?php

namespace App\Models;

use CodeIgniter\Model;

class SertifikatUkkModel extends Model
{
    protected $table         = 'sertifikat_ukk';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['peserta_ukk_id', 'nomor_sertifikat', 'tanggal_terbit', 'keterangan'];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'               => 'permit_empty|is_natural',
        'peserta_ukk_id'   => 'required|is_natural|is_unique[sertifikat_ukk.peserta_ukk_id,id,{id}]',
        'nomor_sertifikat' => 'required|max_length[60]|is_unique[sertifikat_ukk.nomor_sertifikat,id,{id}]',
        'tanggal_terbit'   => 'required|valid_date[Y-m-d]',
    ];
    protected $validationMessages = [
        'peserta_ukk_id'   => ['required' => 'Peserta wajib dipilih.', 'is_unique' => 'Peserta ini sudah punya sertifikat.'],
        'nomor_sertifikat' => ['is_unique' => 'Nomor sertifikat sudah dipakai.', 'required' => 'Nomor sertifikat wajib diisi.'],
    ];

    /** Daftar sertifikat + nama peserta/siswa/paket soal. */
    public function withRelations()
    {
        return $this->select(
            'sertifikat_ukk.*, siswa.nis, siswa.nama AS siswa_nama,'
            . ' paket_soal_ukk.nama AS paket_nama, peserta_ukk.nilai_akhir, peserta_ukk.predikat'
        )
            ->join('peserta_ukk', 'peserta_ukk.id = sertifikat_ukk.peserta_ukk_id', 'left')
            ->join('siswa', 'siswa.id = peserta_ukk.siswa_id', 'left')
            ->join('paket_soal_ukk', 'paket_soal_ukk.id = peserta_ukk.paket_soal_id', 'left');
    }

    /** Nomor sertifikat berikutnya, format "SERT-UKK-{tahun}-001". */
    public function nomorBerikutnya(): string
    {
        $prefix = 'SERT-UKK-' . date('Y') . '-';
        $last   = $this->withDeleted()->select('nomor_sertifikat')
            ->like('nomor_sertifikat', $prefix, 'after')
            ->orderBy('nomor_sertifikat', 'DESC')
            ->first();

        $next = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last['nomor_sertifikat'], $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
