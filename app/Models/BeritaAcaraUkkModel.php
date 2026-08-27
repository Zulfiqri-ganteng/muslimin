<?php

namespace App\Models;

use CodeIgniter\Model;

class BeritaAcaraUkkModel extends Model
{
    protected $table         = 'berita_acara_ukk';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['jadwal_ukk_id', 'nomor_ba', 'tanggal', 'catatan', 'keterangan'];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'jadwal_ukk_id' => 'required|is_natural',
        'nomor_ba'      => 'required|max_length[60]|is_unique[berita_acara_ukk.nomor_ba,id,{id}]',
        'tanggal'       => 'required|valid_date[Y-m-d]',
    ];
    protected $validationMessages = [
        'jadwal_ukk_id' => ['required' => 'Jadwal UKK wajib dipilih.'],
        'nomor_ba'      => ['is_unique' => 'Nomor berita acara sudah dipakai.', 'required' => 'Nomor berita acara wajib diisi.'],
    ];

    /** Daftar berita acara + info jadwal & paket soal. */
    public function withRelations()
    {
        return $this->select(
            'berita_acara_ukk.*, jadwal_ukk.tanggal_mulai AS jadwal_tanggal,'
            . ' paket_soal_ukk.nama AS paket_nama, tempat_uji.nama AS tempat_nama'
        )
            ->join('jadwal_ukk', 'jadwal_ukk.id = berita_acara_ukk.jadwal_ukk_id', 'left')
            ->join('paket_soal_ukk', 'paket_soal_ukk.id = jadwal_ukk.paket_soal_id', 'left')
            ->join('tempat_uji', 'tempat_uji.id = jadwal_ukk.tempat_uji_id', 'left');
    }

    /** Nomor berita acara berikutnya, format "BA-UKK-{tahun}-001". */
    public function nomorBerikutnya(): string
    {
        $prefix = 'BA-UKK-' . date('Y') . '-';
        $last   = $this->withDeleted()->select('nomor_ba')
            ->like('nomor_ba', $prefix, 'after')
            ->orderBy('nomor_ba', 'DESC')
            ->first();

        $next = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last['nomor_ba'], $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
