<?php

namespace App\Models;

use CodeIgniter\Model;

class HariModel extends Model
{
    protected $table         = 'hari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama', 'urutan', 'aktif'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'id'     => 'permit_empty|is_natural',
        'nama'   => 'required|max_length[15]|is_unique[hari.nama,id,{id}]',
        'urutan' => 'required|is_natural_no_zero',
    ];
    protected $validationMessages = [
        'nama' => ['is_unique' => 'Nama hari sudah ada.', 'required' => 'Nama hari wajib diisi.'],
    ];

    /** Hari aktif urut tampil (untuk grid jadwal & dropdown). */
    public function aktifUrut(): array
    {
        return $this->where('aktif', 1)->orderBy('urutan', 'ASC')->findAll();
    }

    /**
     * Cari baris hari berdasar nomor hari PHP date('N') (1=Senin..7=Minggu).
     * Tahan variasi penulisan master ("SENIN", "JUM'AT", dst) — dicocokkan
     * setelah dinormalkan (huruf besar, tanpa tanda baca).
     */
    public function byWeekday(int $n): ?array
    {
        $names  = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $target = $this->normalNama($names[$n] ?? '');
        if ($target === '') {
            return null;
        }
        foreach ($this->findAll() as $h) {
            if ($this->normalNama($h['nama']) === $target) {
                return $h;
            }
        }
        return null;
    }

    private function normalNama(string $s): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper($s));
    }
}
