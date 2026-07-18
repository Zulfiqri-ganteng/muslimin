<?php

namespace App\Models;

use CodeIgniter\Model;

class JabatanModel extends Model
{
    protected $table          = 'jabatan';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['kode', 'nama', 'kategori', 'parent_id', 'jurusan_id', 'level', 'is_struktural', 'keterangan'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const KATEGORI = ['struktural', 'kurikulum', 'kesiswaan', 'wali_kelas', 'mapel', 'pembina', 'lainnya'];

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural',
        'kode'          => 'required|max_length[30]|is_unique[jabatan.kode,id,{id}]',
        'nama'          => 'required|max_length[150]',
        'kategori'      => 'permit_empty|in_list[struktural,kurikulum,kesiswaan,wali_kelas,mapel,pembina,lainnya]',
        'parent_id'     => 'permit_empty|is_natural',
        'jurusan_id'    => 'permit_empty|is_natural',
        'level'         => 'permit_empty|is_natural',
        'is_struktural' => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [
        'kode' => ['is_unique' => 'Kode jabatan sudah dipakai.', 'required' => 'Kode jabatan wajib diisi.'],
        'nama' => ['required' => 'Nama jabatan wajib diisi.'],
    ];

    /** Builder daftar jabatan + nama induk & jurusan (left join). */
    public function withRelations()
    {
        return $this->select('jabatan.*, induk.nama AS induk_nama, jurusan.kode AS jurusan_kode, jurusan.nama AS jurusan_nama')
            ->join('jabatan AS induk', 'induk.id = jabatan.parent_id AND induk.deleted_at IS NULL', 'left')
            ->join('jurusan', 'jurusan.id = jabatan.jurusan_id', 'left');
    }

    /** Opsi jabatan untuk dropdown (id => nama), di-cache. */
    public function options(): array
    {
        return cache()->remember('opt_jabatan', 21600, function () {
            $rows = $this->select('id, kode, nama')
                ->orderBy('level', 'ASC')->orderBy('nama', 'ASC')->findAll();
            $out = [];
            foreach ($rows as $r) {
                $out[$r['id']] = $r['nama'];
            }

            return $out;
        });
    }

    /**
     * ID jabatan yang ditandai struktural — penyandangnya wajib hadir kerja
     * walau hari itu tidak punya jadwal mengajar.
     */
    public function strukturalIds(): array
    {
        return cache()->remember('jabatan_struktural_ids', 21600, function () {
            return array_map('intval', array_column(
                $this->select('id')->where('is_struktural', 1)->findAll(),
                'id'
            ));
        });
    }

    /**
     * Kandidat induk saat mengedit jabatan $excludeId: dirinya sendiri dan
     * seluruh keturunannya dibuang agar hierarki tidak pernah melingkar.
     */
    public function parentOptions(?int $excludeId = null): array
    {
        $opts = $this->options();
        if ($excludeId === null) {
            return $opts;
        }

        foreach ($this->descendantIds($excludeId) as $id) {
            unset($opts[$id]);
        }
        unset($opts[$excludeId]);

        return $opts;
    }

    /** Seluruh id keturunan sebuah jabatan (telusur berjenjang, tanpa rekursi SQL). */
    public function descendantIds(int $id): array
    {
        $semua = $this->select('id, parent_id')->findAll();
        $anak  = [];
        foreach ($semua as $r) {
            $anak[(int) ($r['parent_id'] ?? 0)][] = (int) $r['id'];
        }

        $hasil = [];
        $antre = $anak[$id] ?? [];
        while ($antre !== []) {
            $kini = array_pop($antre);
            if (isset($hasil[$kini])) {
                continue; // jaga-jaga bila data lama sempat melingkar
            }
            $hasil[$kini] = true;
            foreach ($anak[$kini] ?? [] as $c) {
                $antre[] = $c;
            }
        }

        return array_keys($hasil);
    }
}
