<?php

namespace App\Models;

use CodeIgniter\Model;

class KelasModel extends Model
{
    protected $table          = 'kelas';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['nama_kelas', 'tingkat', 'fase_id', 'jurusan_id', 'wali_kelas_id', 'shift'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'nama_kelas'    => 'required|max_length[50]|is_unique[kelas.nama_kelas,id,{id}]',
        'tingkat'       => 'required|in_list[X,XI,XII]',
        'shift'         => 'required|in_list[pagi,siang]',
        'fase_id'       => 'permit_empty|is_natural',
        'jurusan_id'    => 'permit_empty|is_natural',
        'wali_kelas_id' => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'nama_kelas' => ['is_unique' => 'Nama kelas sudah ada.', 'required' => 'Nama kelas wajib diisi.'],
    ];

    /** Builder daftar kelas + nama jurusan, wali, & fase (left join). */
    public function withRelations()
    {
        return $this->select('kelas.*, jurusan.kode AS jurusan_kode, jurusan.nama AS jurusan_nama, guru.nama AS wali_nama, fase.kode AS fase_kode, fase.nama AS fase_nama')
            ->join('jurusan', 'jurusan.id = kelas.jurusan_id', 'left')
            ->join('guru', 'guru.id = kelas.wali_kelas_id', 'left')
            ->join('fase', 'fase.id = kelas.fase_id', 'left');
    }

    /**
     * Banding NATURAL dua nama kelas: "X TKJ 2" sebelum "X TKJ 10", dan X
     * sebelum XI sebelum XII. Urutan teks biasa (MySQL/strcmp) keliru menaruh
     * "X TKJ 10" tepat setelah "X TKJ 1"; strnatcasecmp() PHP juga keliru
     * karena MENGABAIKAN spasi ("XI AKL" jatuh di antara "X AKL" & "X TKJ").
     * Maka nama dipecah jadi potongan angka / bukan-angka: angka dibanding
     * nilainya, teks dibanding apa adanya (tanpa beda huruf besar-kecil).
     */
    public static function bandingNatural(string $a, string $b): int
    {
        $potong = static function (string $s): array {
            preg_match_all('/\d+|\D+/', strtolower(preg_replace('/\s+/', ' ', trim($s))), $m);

            return $m[0];
        };
        $pa = $potong($a);
        $pb = $potong($b);

        for ($i = 0, $n = min(count($pa), count($pb)); $i < $n; $i++) {
            $x   = $pa[$i];
            $y   = $pb[$i];
            $cmp = ctype_digit($x) && ctype_digit($y)
                ? ((int) $x <=> (int) $y ?: strlen($x) <=> strlen($y))
                : strcmp($x, $y);
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return count($pa) <=> count($pb);
    }

    /**
     * Peringkat urut natural nama kelas (lihat bandingNatural).
     *
     * @param array<int,string> $nama kelas_id => nama_kelas
     *
     * @return array<int,int> kelas_id => peringkat (mulai 1)
     */
    public static function urutNatural(array $nama): array
    {
        uasort($nama, static fn ($a, $b) => self::bandingNatural((string) $a, (string) $b));

        $urut = [];
        $i    = 0;
        foreach (array_keys($nama) as $id) {
            $urut[(int) $id] = ++$i;
        }

        return $urut;
    }

    /** Opsi kelas untuk dropdown (id => nama_kelas), di-cache. */
    public function options(): array
    {
        return cache()->remember('opt_kelas', 21600, function () {
            $rows = $this->select('id, nama_kelas')->orderBy('tingkat', 'ASC')->orderBy('nama_kelas', 'ASC')->findAll();
            $out  = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['nama_kelas'];
            }
            return $out;
        });
    }
}
