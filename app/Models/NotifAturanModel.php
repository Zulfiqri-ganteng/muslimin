<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Aturan notifikasi jadwal guru milik satu admin: HARI × SHIFT × GURU ×
 * JURUSAN + berapa menit sebelum jam masuk. `guru`/`jurusan` kosong = semua;
 * shift 'semua' = pagi + siang. Kolom JSON disimpan sebagai teks; pakai
 * rapikan() untuk membaca.
 */
class NotifAturanModel extends Model
{
    protected $table         = 'notif_aturan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['admin_id', 'nama', 'hari', 'shift', 'guru', 'jurusan', 'menit_sebelum', 'aktif'];
    protected $useTimestamps = true;

    /** Pilihan "berapa menit sebelum jam masuk". */
    public const MENIT = [0, 5, 10, 15, 30];

    /** Pilihan shift KBM: kode => label tampilan. */
    public const SHIFT = ['semua' => 'Pagi + Siang', 'pagi' => 'Pagi saja', 'siang' => 'Siang saja'];

    /** Batas jumlah aturan per admin (cegah daftar tak terkendali). */
    public const MAKS = 50;

    /** Baris DB → bentuk rapi: hari/guru/jurusan = list<int>, aktif = bool. */
    public static function rapikan(array $r): array
    {
        $ids = static function ($json): array {
            $v = is_string($json) ? json_decode($json, true) : null;

            return is_array($v) ? array_values(array_unique(array_map('intval', $v))) : [];
        };
        $shift = (string) ($r['shift'] ?? 'semua');

        return [
            'id'            => (int) $r['id'],
            'nama'          => (string) $r['nama'],
            'hari'          => $ids($r['hari'] ?? null),
            'shift'         => isset(self::SHIFT[$shift]) ? $shift : 'semua',
            'guru'          => $ids($r['guru'] ?? null),
            'jurusan'       => $ids($r['jurusan'] ?? null),
            'menit_sebelum' => (int) $r['menit_sebelum'],
            'aktif'         => (int) $r['aktif'] === 1,
        ];
    }

    /** @return list<array> aturan rapi milik admin (urut dibuat). */
    public function milik(int $adminId, bool $hanyaAktif = false): array
    {
        $b = $this->where('admin_id', $adminId);
        if ($hanyaAktif) {
            $b->where('aktif', 1);
        }

        return array_map([self::class, 'rapikan'], $b->orderBy('id', 'ASC')->findAll());
    }
}
