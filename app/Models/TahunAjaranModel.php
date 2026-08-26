<?php

namespace App\Models;

use CodeIgniter\Model;

class TahunAjaranModel extends Model
{
    protected $table          = 'tahun_ajaran';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['tahun', 'semester', 'is_aktif'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'       => 'permit_empty|is_natural',
        'tahun'    => 'required|max_length[20]',
        'semester' => 'required|in_list[Ganjil,Genap]',
    ];
    protected $validationMessages = [
        'tahun'    => ['required' => 'Tahun ajaran wajib diisi (mis. 2026/2027).'],
        'semester' => ['required' => 'Semester wajib dipilih.', 'in_list' => 'Semester harus Ganjil atau Genap.'],
    ];

    /** Tahun ajaran yang sedang aktif, bila ada. */
    public function aktif(): ?array
    {
        return $this->where('is_aktif', 1)->first();
    }

    /** Opsi untuk dropdown (id => "2026/2027 - Ganjil"), terbaru dulu. */
    public function options(): array
    {
        return cache()->remember('opt_tahun_ajaran', 21600, function () {
            $rows = $this->orderBy('tahun', 'DESC')->orderBy('semester', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['tahun'] . ' - ' . $r['semester'];
            }
            return $out;
        });
    }
}
