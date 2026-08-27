<?php

namespace App\Models;

use CodeIgniter\Model;

class JadwalUkkModel extends Model
{
    protected $table         = 'jadwal_ukk';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'paket_soal_id', 'tempat_uji_id', 'tahun_ajaran_id',
        'tanggal_mulai', 'tanggal_selesai', 'sesi', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'              => 'permit_empty|is_natural',
        'paket_soal_id'   => 'required|is_natural',
        'tempat_uji_id'   => 'permit_empty|is_natural',
        'tahun_ajaran_id' => 'permit_empty|is_natural',
        'tanggal_mulai'   => 'required|valid_date[Y-m-d]',
        'tanggal_selesai' => 'permit_empty|valid_date[Y-m-d]',
    ];
    protected $validationMessages = [
        'paket_soal_id' => ['required' => 'Paket soal wajib dipilih.'],
        'tanggal_mulai' => ['required' => 'Tanggal mulai wajib diisi.'],
    ];

    /** Daftar jadwal + nama paket soal & tempat uji. */
    public function withRelations()
    {
        return $this->select(
            'jadwal_ukk.*, paket_soal_ukk.kode AS paket_kode, paket_soal_ukk.nama AS paket_nama,'
            . ' tempat_uji.nama AS tempat_nama'
        )
            ->join('paket_soal_ukk', 'paket_soal_ukk.id = jadwal_ukk.paket_soal_id', 'left')
            ->join('tempat_uji', 'tempat_uji.id = jadwal_ukk.tempat_uji_id', 'left');
    }

    /** Opsi dropdown [id => "tanggal - paket soal"] untuk pendaftaran peserta. */
    public function options(): array
    {
        $out = [];
        foreach ($this->withRelations()->orderBy('tanggal_mulai', 'DESC')->findAll() as $r) {
            $out[$r['id']] = $r['tanggal_mulai'] . ' - ' . $r['paket_nama'];
        }

        return $out;
    }

    /** Opsi jadwal MILIK SATU paket soal [id => "tanggal (sesi)"], untuk pendaftaran peserta. */
    public function optionsUntukPaket(int $paketSoalId): array
    {
        $out = [];
        foreach ($this->where('paket_soal_id', $paketSoalId)->orderBy('tanggal_mulai', 'DESC')->findAll() as $r) {
            $out[$r['id']] = $r['tanggal_mulai'] . ($r['sesi'] ? ' (' . $r['sesi'] . ')' : '');
        }

        return $out;
    }
}
