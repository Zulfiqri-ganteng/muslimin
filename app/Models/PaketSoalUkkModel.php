<?php

namespace App\Models;

use CodeIgniter\Model;

class PaketSoalUkkModel extends Model
{
    protected $table         = 'paket_soal_ukk';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'kode', 'nama', 'jurusan_id', 'tahun_ajaran_id', 'deskripsi',
        'kisi_kisi_file', 'jobsheet_file',
        'bobot_persiapan', 'bobot_proses', 'bobot_hasil', 'bobot_sikap', 'bobot_waktu',
        'kkm', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'              => 'permit_empty|is_natural',
        'kode'            => 'required|max_length[30]|is_unique[paket_soal_ukk.kode,id,{id}]',
        'nama'            => 'required|max_length[150]',
        'jurusan_id'      => 'permit_empty|is_natural',
        'tahun_ajaran_id' => 'permit_empty|is_natural',
        'bobot_persiapan' => 'permit_empty|decimal',
        'bobot_proses'    => 'permit_empty|decimal',
        'bobot_hasil'     => 'permit_empty|decimal',
        'bobot_sikap'     => 'permit_empty|decimal',
        'bobot_waktu'     => 'permit_empty|decimal',
        'kkm'             => 'permit_empty|decimal',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode paket soal sudah dipakai.', 'required' => 'Kode wajib diisi.'],
        'nama' => ['required' => 'Nama paket soal wajib diisi.'],
    ];

    /** Daftar paket soal + nama jurusan & tahun ajaran. */
    public function withRelations()
    {
        return $this->select(
            'paket_soal_ukk.*, jurusan.nama AS jurusan_nama, jurusan.kode AS jurusan_kode,'
            . ' tahun_ajaran.tahun AS tahun_ajaran_tahun, tahun_ajaran.semester AS tahun_ajaran_semester'
        )
            ->join('jurusan', 'jurusan.id = paket_soal_ukk.jurusan_id', 'left')
            ->join('tahun_ajaran', 'tahun_ajaran.id = paket_soal_ukk.tahun_ajaran_id', 'left');
    }

    /** Jumlah total bobot 5 komponen (untuk validasi harus 100). */
    public function totalBobot(array $data): float
    {
        return (float) ($data['bobot_persiapan'] ?? 0)
            + (float) ($data['bobot_proses'] ?? 0)
            + (float) ($data['bobot_hasil'] ?? 0)
            + (float) ($data['bobot_sikap'] ?? 0)
            + (float) ($data['bobot_waktu'] ?? 0);
    }

    /** Opsi dropdown [id => "kode - nama"] untuk pendaftaran peserta & jadwal. */
    public function options(): array
    {
        return cache()->remember('opt_paket_soal_ukk', 21600, function () {
            $out = [];
            foreach ($this->select('id, kode, nama')->orderBy('nama', 'ASC')->findAll() as $r) {
                $out[$r['id']] = $r['kode'] . ' - ' . $r['nama'];
            }

            return $out;
        });
    }
}
