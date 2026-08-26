<?php

namespace App\Models;

use CodeIgniter\Model;

/** Jurnal realisasi pemakaian lab (catatan aktual, beda dari jadwal rencana). */
class JurnalLabModel extends Model
{
    protected $table         = 'jurnal_lab';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'lab_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'guru_id', 'kelas_id',
        'kegiatan', 'jumlah_hadir', 'kondisi_setelah', 'kendala', 'teknisi_id', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const KONDISI_SETELAH = ['baik', 'ada_kendala'];

    protected $validationRules = [
        'id'              => 'permit_empty|is_natural',
        'lab_id'          => 'required|is_natural',
        'tanggal'         => 'required|valid_date[Y-m-d]',
        'guru_id'         => 'permit_empty|is_natural',
        'kelas_id'        => 'permit_empty|is_natural',
        'jumlah_hadir'    => 'permit_empty|is_natural',
        'kondisi_setelah' => 'permit_empty|in_list[baik,ada_kendala]',
    ];
    protected $validationMessages = [
        'lab_id'  => ['required' => 'Lab wajib dipilih.'],
        'tanggal' => ['required' => 'Tanggal pemakaian wajib diisi.'],
    ];

    /** Daftar jurnal + nama lab/guru/kelas/teknisi. */
    public function withRelations()
    {
        return $this->select('jurnal_lab.*, lab.nama AS lab_nama, guru.nama AS guru_nama, kelas.nama_kelas, teknisi.nama AS teknisi_nama')
            ->join('lab', 'lab.id = jurnal_lab.lab_id', 'left')
            ->join('guru', 'guru.id = jurnal_lab.guru_id', 'left')
            ->join('kelas', 'kelas.id = jurnal_lab.kelas_id', 'left')
            ->join('teknisi', 'teknisi.id = jurnal_lab.teknisi_id', 'left');
    }
}
