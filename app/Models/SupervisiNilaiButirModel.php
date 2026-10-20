<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Skor satu butir dalam hasil supervisi + SALINAN aspek & uraian butirnya. skor: 1–4, 0 = tidak dinilai, null = belum diisi (draf).
 * Logika: Libraries\SupervisiPenilaian.
 */
class SupervisiNilaiButirModel extends Model
{
    protected $table         = 'supervisi_nilai_butir';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['nilai_id', 'butir_id', 'aspek', 'uraian', 'urut', 'skor', 'catatan'];
}
