<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Hasil satu instrumen pada satu kunjungan supervisi (unik jadwal + instrumen): skor total/maks, nilai, predikat, temuan (kekuatan,
 * kelemahan), rekomendasi, tindak lanjut (JSON daftar kode). Skor per butir di supervisi_nilai_butir. Logika: Libraries\SupervisiPenilaian.
 */
class SupervisiNilaiModel extends Model
{
    protected $table         = 'supervisi_nilai';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'jadwal_id', 'instrumen_id', 'nama_instrumen', 'skor_total', 'skor_maks', 'nilai', 'predikat', 'kekuatan', 'kelemahan',
        'rekomendasi', 'tindak_lanjut', 'dinilai_at', 'dinilai_oleh',
    ];
}
