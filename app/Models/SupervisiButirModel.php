<?php

namespace App\Models;

use CodeIgniter\Model;

/** Butir penilaian satu instrumen supervisi, berkelompok aspek (mis. "Kegiatan Inti"). Logika: Libraries\Supervisi. */
class SupervisiButirModel extends Model
{
    protected $table         = 'supervisi_butir';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['instrumen_id', 'aspek', 'uraian', 'urut', 'aktif'];
}
