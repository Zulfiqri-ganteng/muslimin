<?php

namespace App\Models;

use CodeIgniter\Model;

class SiswaModel extends Model
{
    protected $table         = 'siswa';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nis', 'nisn', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
        'agama', 'alamat', 'no_hp', 'nama_wali', 'no_hp_wali',
        'kelas_id', 'tahun_masuk', 'status', 'keterangan',
    ];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const STATUS = ['aktif', 'lulus', 'pindah', 'keluar'];

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'nis'           => 'required|max_length[30]|is_unique[siswa.nis,id,{id}]',
        'nisn'          => 'permit_empty|max_length[30]|is_unique[siswa.nisn,id,{id}]',
        'nama'          => 'required|max_length[150]',
        'jenis_kelamin' => 'permit_empty|in_list[L,P]',
        'tanggal_lahir' => 'permit_empty|valid_date[Y-m-d]',
        'kelas_id'      => 'permit_empty|is_natural',
        'tahun_masuk'   => 'permit_empty|is_natural',
        'status'        => 'permit_empty|in_list[aktif,lulus,pindah,keluar]',
    ];
    protected $validationMessages = [
        'nis'  => ['is_unique' => 'NIS sudah terdaftar.', 'required' => 'NIS wajib diisi.'],
        'nisn' => ['is_unique' => 'NISN sudah terdaftar.'],
        'nama' => ['required' => 'Nama siswa wajib diisi.'],
    ];

    /** Builder daftar siswa + kelas, tingkat, dan jurusannya (left join). */
    public function withRelations()
    {
        return $this->select('siswa.*, kelas.nama_kelas, kelas.tingkat, jurusan.kode AS jurusan_kode, jurusan.nama AS jurusan_nama')
            ->join('kelas', 'kelas.id = siswa.kelas_id', 'left')
            ->join('jurusan', 'jurusan.id = kelas.jurusan_id', 'left');
    }

    /**
     * Statistik agregat untuk tampilan publik & dashboard.
     *
     * Sengaja HANYA memakai COUNT + GROUP BY pada kolom ber-index dan tidak
     * pernah menarik baris siswa — ringan untuk shared hosting, dan tidak ada
     * data pribadi siswa yang bisa bocor ke halaman publik.
     *
     * Hasilnya di-cache; panggil siswa_stats_changed() saat data siswa berubah.
     */
    public function statistik(): array
    {
        return cache()->remember('stat_siswa', 1800, function () {
            $db = $this->db;

            $aktif = static fn (string $tabel = 'siswa') => $db->table($tabel)
                ->where('siswa.deleted_at', null)
                ->where('siswa.status', 'aktif');

            // Total siswa aktif
            $total = (int) $aktif()->countAllResults();

            // Per tingkat (X / XI / XII)
            $perTingkat = [];
            $rows = $aktif()->select('kelas.tingkat, COUNT(*) AS jumlah')
                ->join('kelas', 'kelas.id = siswa.kelas_id', 'left')
                ->groupBy('kelas.tingkat')->get()->getResultArray();
            foreach ($rows as $r) {
                if (($r['tingkat'] ?? '') !== '') {
                    $perTingkat[$r['tingkat']] = (int) $r['jumlah'];
                }
            }

            // Per jurusan
            $perJurusan = [];
            $rows = $aktif()->select('jurusan.kode, jurusan.nama, COUNT(*) AS jumlah')
                ->join('kelas', 'kelas.id = siswa.kelas_id', 'left')
                ->join('jurusan', 'jurusan.id = kelas.jurusan_id', 'left')
                ->groupBy('jurusan.id')->orderBy('jumlah', 'DESC')->get()->getResultArray();
            foreach ($rows as $r) {
                if (($r['nama'] ?? '') !== '') {
                    $perJurusan[] = [
                        'kode'   => $r['kode'],
                        'nama'   => $r['nama'],
                        'jumlah' => (int) $r['jumlah'],
                    ];
                }
            }

            // Per jenis kelamin
            $perJk = ['L' => 0, 'P' => 0];
            $rows  = $aktif()->select('jenis_kelamin, COUNT(*) AS jumlah')
                ->groupBy('jenis_kelamin')->get()->getResultArray();
            foreach ($rows as $r) {
                if (isset($perJk[$r['jenis_kelamin'] ?? ''])) {
                    $perJk[$r['jenis_kelamin']] = (int) $r['jumlah'];
                }
            }

            // Per tahun masuk (untuk grafik angkatan)
            $perTahun = [];
            $rows     = $aktif()->select('tahun_masuk, COUNT(*) AS jumlah')
                ->where('tahun_masuk IS NOT NULL')
                ->groupBy('tahun_masuk')->orderBy('tahun_masuk', 'ASC')->get()->getResultArray();
            foreach ($rows as $r) {
                $perTahun[(int) $r['tahun_masuk']] = (int) $r['jumlah'];
            }

            return [
                'total'         => $total,
                'per_tingkat'   => $perTingkat,
                'per_jurusan'   => $perJurusan,
                'jenis_kelamin' => $perJk,
                'per_tahun'     => $perTahun,
                'tahun'         => (int) date('Y'),
            ];
        });
    }

    /** Jumlah siswa aktif per kelas (kelas_id => jumlah), untuk daftar kelas. */
    public function jumlahPerKelas(): array
    {
        return cache()->remember('siswa_per_kelas', 1800, function () {
            $rows = $this->select('kelas_id, COUNT(*) AS jumlah')
                ->where('status', 'aktif')->where('kelas_id IS NOT NULL')
                ->groupBy('kelas_id')->findAll();
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r['kelas_id']] = (int) $r['jumlah'];
            }

            return $out;
        });
    }
}
