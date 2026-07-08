<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Absensi manual guru per sesi mengajar.
 *
 * Prinsip "hanya simpan pengecualian": baris HANYA ada bila status != 'hadir'.
 * Tidak adanya baris untuk sebuah sesi = guru HADIR. Ini menjaga DB tetap
 * ringan (shared hosting) & cocok dengan alur "default hadir, tinggal centang
 * yang tidak hadir".
 */
class AbsensiGuruModel extends Model
{
    protected $table         = 'absensi_guru';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'tanggal', 'jadwal_id', 'guru_id', 'kelas_id', 'mapel_id', 'hari_id',
        'jam_id', 'status', 'jam_masuk', 'keterangan', 'created_by',
    ];

    /** Status yang valid. 'hadir' = default (tak disimpan sebagai baris). */
    public const STATUSES = ['hadir', 'telat', 'izin', 'sakit', 'alpa'];

    /** Prioritas status untuk menurunkan STATUS HARIAN guru (terburuk menang). */
    public const PRIORITY = ['hadir' => 0, 'telat' => 1, 'izin' => 2, 'sakit' => 3, 'alpa' => 4];

    /** Status "terburuk" dari dua status (untuk status harian per guru). */
    public static function worst(string $a, string $b): string
    {
        return (self::PRIORITY[$b] ?? 0) > (self::PRIORITY[$a] ?? 0) ? $b : $a;
    }

    /**
     * Status harian per guru pada rentang: [guru_id][tanggal] => status terburuk
     * hari itu. Hanya pengecualian (tanpa baris = hadir, dihitung pemanggil).
     */
    public function dayStatusPerGuru(string $dari, string $sampai): array
    {
        $map = [];
        $rows = $this->select('tanggal, guru_id, status')
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->findAll();
        foreach ($rows as $r) {
            $gid = (int) $r['guru_id'];
            $tgl = $r['tanggal'];
            $map[$gid][$tgl] = self::worst($map[$gid][$tgl] ?? 'hadir', $r['status']);
        }
        return $map;
    }

    /** Pengecualian pada satu tanggal, key "kelas_id-jam_id" => baris. */
    public function forDate(string $tanggal): array
    {
        $map = [];
        foreach ($this->where('tanggal', $tanggal)->findAll() as $r) {
            $map[$r['kelas_id'] . '-' . $r['jam_id']] = $r;
        }
        return $map;
    }

    /**
     * Simpan absensi satu tanggal (transaksi). Untuk tiap sesi yang dikirim:
     * - status 'hadir'      → hapus baris pengecualian bila ada.
     * - status selain hadir → insert / update baris.
     * Sesi yang tak dikirim TIDAK disentuh (mis. jadwal lama yang sudah dihapus).
     *
     * @param array $items tiap elemen: kelas_id, jam_id, guru_id, hari_id,
     *                     mapel_id, jadwal_id, status, jam_masuk, keterangan
     */
    public function syncDate(string $tanggal, array $items, ?int $adminId = null): void
    {
        $existing = [];
        foreach ($this->where('tanggal', $tanggal)->findAll() as $e) {
            $existing[$e['kelas_id'] . '-' . $e['jam_id']] = $e;
        }

        $this->db->transStart();
        foreach ($items as $it) {
            $kelasId = (int) ($it['kelas_id'] ?? 0);
            $jamId   = (int) ($it['jam_id'] ?? 0);
            if ($kelasId === 0 || $jamId === 0) {
                continue;
            }
            $status = $it['status'] ?? 'hadir';
            if (! in_array($status, self::STATUSES, true)) {
                $status = 'hadir';
            }
            $key = $kelasId . '-' . $jamId;
            $cur = $existing[$key] ?? null;

            if ($status === 'hadir') {
                if ($cur) {
                    $this->delete($cur['id']);
                }
                continue;
            }

            $jamMasuk = trim((string) ($it['jam_masuk'] ?? ''));
            $ket      = trim((string) ($it['keterangan'] ?? ''));
            $data = [
                'tanggal'    => $tanggal,
                'jadwal_id'  => ((int) ($it['jadwal_id'] ?? 0)) ?: null,
                'guru_id'    => (int) ($it['guru_id'] ?? 0),
                'kelas_id'   => $kelasId,
                'mapel_id'   => ((int) ($it['mapel_id'] ?? 0)) ?: null,
                'hari_id'    => (int) ($it['hari_id'] ?? 0),
                'jam_id'     => $jamId,
                'status'     => $status,
                'jam_masuk'  => $jamMasuk !== '' ? $jamMasuk : null,
                'keterangan' => $ket !== '' ? mb_substr($ket, 0, 255) : null,
                'created_by' => $adminId,
            ];

            if ($cur) {
                $this->update($cur['id'], $data);
            } else {
                $this->insert($data);
            }
        }
        $this->db->transComplete();
    }

    /**
     * Rekap jumlah status per guru pada rentang tanggal (untuk laporan/gaji).
     * Hanya menghitung pengecualian; "hadir" dihitung di luar dari total sesi.
     */
    public function rekapRange(string $dari, string $sampai): array
    {
        return $this->select('guru_id, status, COUNT(*) AS jml')
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->groupBy('guru_id, status')
            ->findAll();
    }

    /**
     * Rincian ketidakhadiran satu guru pada rentang tanggal (untuk halaman
     * rincian). Join master untuk tampilkan kelas/mapel/jam. LEFT JOIN agar
     * baris tetap muncul walau master terkait sudah dihapus.
     */
    public function detailForGuru(int $guruId, string $dari, string $sampai): array
    {
        return $this->select('absensi_guru.tanggal, absensi_guru.status, absensi_guru.jam_masuk, absensi_guru.keterangan,
                kelas.nama_kelas, mata_pelajaran.nama_mapel,
                jam_pelajaran.jam_ke, jam_pelajaran.waktu_mulai, jam_pelajaran.waktu_selesai')
            ->join('kelas', 'kelas.id = absensi_guru.kelas_id', 'left')
            ->join('mata_pelajaran', 'mata_pelajaran.id = absensi_guru.mapel_id', 'left')
            ->join('jam_pelajaran', 'jam_pelajaran.id = absensi_guru.jam_id', 'left')
            ->where('absensi_guru.guru_id', $guruId)
            ->where('absensi_guru.tanggal >=', $dari)
            ->where('absensi_guru.tanggal <=', $sampai)
            ->orderBy('absensi_guru.tanggal', 'ASC')
            ->orderBy('jam_pelajaran.waktu_mulai', 'ASC')
            ->findAll();
    }
}
