<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Absensi KEHADIRAN KERJA guru (per orang per hari, di luar jadwal KBM).
 *
 * Berbeda dari {@see AbsensiGuruModel} (per sesi, "hanya simpan pengecualian"):
 * di sini SATU baris = "guru ini masuk kerja pada tanggal ini" dan status
 * 'hadir' TETAP disimpan (tak ada jadwal untuk menebak siapa yang seharusnya
 * hadir). Dipakai untuk wakil kepala/staf yang hadir di hari tanpa jadwal
 * (mis. Sabtu/Minggu). Ikut dihitung di rekap absensi.
 */
class AbsensiKerjaModel extends Model
{
    protected $table         = 'absensi_kerja';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'tanggal', 'guru_id', 'status', 'jam_masuk', 'keterangan', 'created_by',
    ];

    /** Status valid — sama dengan absensi mengajar. */
    public const STATUSES = ['hadir', 'telat', 'izin', 'sakit', 'alpa'];

    /**
     * Catatan kehadiran kerja pada satu tanggal (join nama guru), urut nama.
     *
     * @return list<array{id:int,guru_id:int,nama:string,kode_guru:?string,status:string,jam_masuk:?string,keterangan:?string}>
     */
    public function forDate(string $tanggal): array
    {
        $rows = $this->select('absensi_kerja.id, absensi_kerja.guru_id, absensi_kerja.status,
                absensi_kerja.jam_masuk, absensi_kerja.keterangan,
                guru.nama, guru.kode_guru')
            ->join('guru', 'guru.id = absensi_kerja.guru_id', 'left')
            ->where('absensi_kerja.tanggal', $tanggal)
            ->orderBy('guru.nama', 'ASC')
            ->findAll();

        return array_map(static fn ($r) => [
            'id'         => (int) $r['id'],
            'guru_id'    => (int) $r['guru_id'],
            'nama'       => $r['nama'] ?? '(guru terhapus)',
            'kode_guru'  => $r['kode_guru'] ?? null,
            'status'     => $r['status'],
            'jam_masuk'  => $r['jam_masuk'] ? substr($r['jam_masuk'], 0, 5) : null,
            'keterangan' => $r['keterangan'] ?? null,
        ], $rows);
    }

    /**
     * Sinkronkan kehadiran kerja satu tanggal ke daftar yang dikirim (transaksi):
     * upsert per guru, lalu hapus baris guru yang tak lagi ada di daftar.
     * Beda dari absensi mengajar: baris 'hadir' TETAP disimpan.
     *
     * @param array $items tiap elemen: guru_id, status, jam_masuk, keterangan
     */
    public function syncDate(string $tanggal, array $items, ?int $adminId = null): void
    {
        $existing = [];
        foreach ($this->where('tanggal', $tanggal)->findAll() as $e) {
            $existing[(int) $e['guru_id']] = $e;
        }

        $keep = [];
        $this->db->transStart();
        foreach ($items as $it) {
            $guruId = (int) ($it['guru_id'] ?? 0);
            if ($guruId === 0) {
                continue;
            }
            $status = $it['status'] ?? 'hadir';
            if (! in_array($status, self::STATUSES, true)) {
                $status = 'hadir';
            }
            $jamMasuk = trim((string) ($it['jam_masuk'] ?? ''));
            $ket      = trim((string) ($it['keterangan'] ?? ''));
            $data = [
                'tanggal'    => $tanggal,
                'guru_id'    => $guruId,
                'status'     => $status,
                'jam_masuk'  => $jamMasuk !== '' ? $jamMasuk : null,
                'keterangan' => $ket !== '' ? mb_substr($ket, 0, 255) : null,
                'created_by' => $adminId,
            ];

            $cur = $existing[$guruId] ?? null;
            if ($cur) {
                $this->update($cur['id'], $data);
            } else {
                $this->insert($data);
            }
            $keep[$guruId] = true;
        }

        // Guru yang dihapus dari daftar → buang catatannya.
        foreach ($existing as $guruId => $e) {
            if (! isset($keep[$guruId])) {
                $this->delete($e['id']);
            }
        }
        $this->db->transComplete();
    }

    /**
     * Status kehadiran kerja per guru pada rentang: [guru_id][tanggal] => status.
     * Satu guru hanya punya satu baris per tanggal (unik), jadi langsung dipetakan.
     */
    public function dayStatusPerGuru(string $dari, string $sampai): array
    {
        $map = [];
        $rows = $this->select('tanggal, guru_id, status')
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->findAll();
        foreach ($rows as $r) {
            $map[(int) $r['guru_id']][$r['tanggal']] = $r['status'];
        }
        return $map;
    }

    /**
     * Rincian kehadiran kerja satu guru pada rentang (untuk halaman rincian).
     *
     * @return list<array{tanggal:string,status:string,jam_masuk:?string,keterangan:?string}>
     */
    public function detailForGuru(int $guruId, string $dari, string $sampai): array
    {
        return $this->select('tanggal, status, jam_masuk, keterangan')
            ->where('guru_id', $guruId)
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->orderBy('tanggal', 'ASC')
            ->findAll();
    }
}
