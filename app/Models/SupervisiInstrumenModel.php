<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Instrumen (lembar penilaian) supervisi akademik — bawaan: Perencanaan, Pelaksanaan, Penilaian. Butirnya di supervisi_butir.
 * Logika: Libraries\Supervisi.
 */
class SupervisiInstrumenModel extends Model
{
    protected $table         = 'supervisi_instrumen';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['kode', 'nama', 'jenis', 'keterangan', 'aktif', 'urut'];
}
