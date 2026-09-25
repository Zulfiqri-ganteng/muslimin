<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Salinan sesi KBM per tanggal, diambil saat absensi tanggal itu disimpan.
 * Menjaga rekap bulan lalu tetap sama walau jadwal KBM diubah belakangan.
 */
class AbsensiSnapshotModel extends Model
{
    protected $table         = 'absensi_snapshot';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['tanggal', 'jadwal_id', 'guru_id', 'kelas_id', 'jam_id', 'shift'];

    /**
     * Ganti salinan satu tanggal dengan $sessions (baris JadwalModel::sessionsForHari).
     */
    public function simpan(string $tanggal, array $sessions): void
    {
        $rows = [];
        foreach ($sessions as $s) {
            $rows[] = [
                'tanggal'   => $tanggal,
                'jadwal_id' => ((int) ($s['jadwal_id'] ?? 0)) ?: null,
                'guru_id'   => (int) $s['guru_id'],
                'kelas_id'  => (int) $s['kelas_id'],
                'jam_id'    => (int) $s['jam_id'],
                'shift'     => ($s['jam_shift'] ?? $s['shift'] ?? 'pagi') === 'siang' ? 'siang' : 'pagi',
            ];
        }

        $this->db->transStart();
        $this->where('tanggal', $tanggal)->delete();
        if ($rows !== []) {
            $this->insertBatch($rows);
        }
        $this->db->transComplete();
    }

    /**
     * Salinan pada rentang: [tanggal] => list<{guru_id,kelas_id,jam_id,shift}>.
     */
    public function rentang(string $dari, string $sampai): array
    {
        $out  = [];
        $rows = $this->select('tanggal, guru_id, kelas_id, jam_id, shift')
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->findAll();
        foreach ($rows as $r) {
            $out[$r['tanggal']][] = [
                'guru_id'  => (int) $r['guru_id'],
                'kelas_id' => (int) $r['kelas_id'],
                'jam_id'   => (int) $r['jam_id'],
                'shift'    => $r['shift'],
            ];
        }

        return $out;
    }
}
