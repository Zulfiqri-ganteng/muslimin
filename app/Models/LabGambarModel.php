<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Galeri gambar SIMLAB (polimorfik). Setiap baris = satu foto (.webp) milik
 * sebuah entitas (aset/kerusakan/perbaikan/lab/sparepart/peminjaman).
 */
class LabGambarModel extends Model
{
    protected $table         = 'lab_gambar';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['entitas', 'entitas_id', 'file', 'urutan'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = ''; // tabel tak punya updated_at

    /** Entitas yang boleh punya galeri. */
    public const ENTITAS = ['aset', 'kerusakan', 'perbaikan', 'lab', 'sparepart', 'peminjaman'];

    /** Semua foto milik satu record (urut). */
    public function forEntitas(string $entitas, int $id): array
    {
        return $this->where('entitas', $entitas)->where('entitas_id', $id)
            ->orderBy('urutan', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /** Jumlah foto per record untuk sekumpulan id (id => jumlah). */
    public function countForIds(string $entitas, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $this->select('entitas_id, COUNT(*) AS c')
            ->where('entitas', $entitas)->whereIn('entitas_id', $ids)
            ->groupBy('entitas_id')->findAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['entitas_id']] = (int) $r['c'];
        }

        return $out;
    }

    /**
     * Hapus seluruh foto milik record tertentu: berkas fisik + baris DB.
     * Dipanggil saat entitas induk dihapus (cleanupRelations).
     */
    public function hapusUntuk(string $entitas, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        helper('labimage');
        foreach ($this->where('entitas', $entitas)->whereIn('entitas_id', $ids)->findAll() as $r) {
            labimage_delete($r['file']);
        }
        $this->where('entitas', $entitas)->whereIn('entitas_id', $ids)->delete();
    }
}
