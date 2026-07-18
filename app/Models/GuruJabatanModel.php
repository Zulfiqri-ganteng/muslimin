<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pivot guru ↔ jabatan. Satu guru boleh menyandang beberapa jabatan
 * (mis. "Guru MTK" sekaligus "Wakasek Kurikulum"); salah satunya ditandai utama.
 */
class GuruJabatanModel extends Model
{
    protected $table         = 'guru_jabatan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['guru_id', 'jabatan_id', 'is_utama', 'tmt', 'tmt_selesai', 'keterangan'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Peta guru_id => daftar jabatan, untuk ditempel ke daftar guru tanpa
     * query per baris (hindari N+1).
     *
     * @param int[] $guruIds Kosongkan untuk mengambil seluruh guru.
     *
     * @return array<int, array<int, array{id:int,nama:string,kode:string,is_utama:bool,is_struktural:bool}>>
     */
    public function mapByGuru(array $guruIds = []): array
    {
        $builder = $this->select('guru_jabatan.guru_id, guru_jabatan.is_utama, jabatan.id, jabatan.kode, jabatan.nama, jabatan.is_struktural')
            ->join('jabatan', 'jabatan.id = guru_jabatan.jabatan_id')
            ->where('jabatan.deleted_at', null)
            ->orderBy('guru_jabatan.is_utama', 'DESC')
            ->orderBy('jabatan.level', 'ASC');

        if ($guruIds !== []) {
            $builder = $builder->whereIn('guru_jabatan.guru_id', $guruIds);
        }

        $peta = [];
        foreach ($builder->findAll() as $r) {
            $peta[(int) $r['guru_id']][] = [
                'id'            => (int) $r['id'],
                'kode'          => $r['kode'],
                'nama'          => $r['nama'],
                'is_utama'      => (bool) $r['is_utama'],
                'is_struktural' => (bool) $r['is_struktural'],
            ];
        }

        return $peta;
    }

    /** Daftar jabatan satu guru (id jabatan => nama). */
    public function forGuru(int $guruId): array
    {
        return $this->mapByGuru([$guruId])[$guruId] ?? [];
    }

    /**
     * Ganti seluruh jabatan seorang guru dalam satu transaksi.
     *
     * @param int[] $jabatanIds
     */
    public function syncForGuru(int $guruId, array $jabatanIds, ?int $utamaId = null): void
    {
        $jabatanIds = array_values(array_unique(array_filter(array_map('intval', $jabatanIds))));

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        $this->where('guru_id', $guruId)->delete();
        if ($jabatanIds !== []) {
            // Bila penanda utama tidak valid, jabatan pertama yang dipakai.
            if ($utamaId === null || ! in_array($utamaId, $jabatanIds, true)) {
                $utamaId = $jabatanIds[0];
            }
            $now  = date('Y-m-d H:i:s');
            $rows = [];
            foreach ($jabatanIds as $jid) {
                $rows[] = [
                    'guru_id'    => $guruId,
                    'jabatan_id' => $jid,
                    'is_utama'   => $jid === $utamaId ? 1 : 0,
                    'created_at' => $now,
                ];
            }
            $this->insertBatch($rows);
        }

        $db->transComplete();
    }

    /**
     * ID guru penyandang jabatan struktural — dipakai panel Kehadiran Kerja
     * untuk memunculkan wakil kepala dsb. walau hari itu tanpa jadwal KBM.
     */
    public function guruStrukturalIds(): array
    {
        return array_map('intval', array_column(
            $this->select('guru_jabatan.guru_id')
                ->join('jabatan', 'jabatan.id = guru_jabatan.jabatan_id')
                ->where('jabatan.is_struktural', 1)
                ->where('jabatan.deleted_at', null)
                ->groupBy('guru_jabatan.guru_id')
                ->findAll(),
            'guru_id'
        ));
    }
}
