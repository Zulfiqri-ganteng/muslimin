<?php

namespace App\Controllers\Api\Admin;

use App\Models\PengujiEksternalModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

/**
 * Master Penguji Eksternal UKK (API, fase UKK A1). Cermin App\Controllers\Admin\Master\PengujiEksternal.
 * Rute: /api/v1/admin/master/penguji-eksternal (index ?q=&per=&page= · store · update · destroy · bulk-delete)
 */
class PengujiEksternal extends BaseCrud
{
    protected string $module     = 'penguji_eksternal';
    protected string $auditTable = 'penguji_eksternal';
    protected string $entity     = 'penguji eksternal';

    protected function makeModel(): Model
    {
        return new PengujiEksternalModel();
    }

    protected function applyFilters($builder)
    {
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('nama', $q)->orLike('kode', $q)->orLike('instansi', $q)->groupEnd();
        }

        return $builder;
    }

    protected function orderByList($builder)
    {
        return $builder->orderBy('nama', 'ASC');
    }

    protected function collect(array $in): array
    {
        $teks = static fn (string $k): ?string => trim((string) ($in[$k] ?? '')) ?: null;

        return [
            'kode'       => strtoupper(trim((string) ($in['kode'] ?? ''))),
            'nama'       => trim((string) ($in['nama'] ?? '')),
            'instansi'   => $teks('instansi'),
            'jabatan'    => $teks('jabatan'),
            'no_hp'      => $teks('no_hp'),
            'email'      => $teks('email'),
            'keterangan' => $teks('keterangan'),
        ];
    }

    protected function transform(array $r): array
    {
        return [
            'id'         => (int) $r['id'],
            'kode'       => $r['kode'],
            'nama'       => $r['nama'],
            'instansi'   => $r['instansi'] ?? null,
            'jabatan'    => $r['jabatan'] ?? null,
            'no_hp'      => $r['no_hp'] ?? null,
            'email'      => $r['email'] ?? null,
            'keterangan' => $r['keterangan'] ?? null,
        ];
    }

    /**
     * Sama dengan web: penugasan di jadwal (jadwal_ukk_penguji, hard delete) dihapus; riwayat nilai (nilai_ukk) hanya
     * dilepas rujukannya supaya skor historis tetap tersimpan.
     */
    protected function cleanupRelations(BaseConnection $db, array $ids): void
    {
        $db->table('jadwal_ukk_penguji')->whereIn('penguji_eksternal_id', $ids)->delete();
        $db->table('nilai_ukk')->whereIn('penguji_eksternal_id', $ids)->update(['penguji_eksternal_id' => null]);
    }
}
