<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Guru yang BELUM HADIR saat laporan kehadiran dikirim, per tanggal + shift.
 *
 * Status sementara (bukan status akhir absensi): admin menandainya ketika di
 * lapangan guru belum datang, lalu menghapusnya lewat "Sudah datang". Baris
 * yang tetap tersisa dihitung TIDAK HADIR di rekap (AbsensiRekap::dailyStatus).
 */
class AbsensiBelumModel extends Model
{
    protected $table         = 'absensi_belum';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['tanggal', 'shift', 'guru_id', 'created_by'];

    public const SHIFTS = ['pagi', 'siang'];

    /**
     * Guru belum hadir pada satu tanggal, dikelompokkan per shift:
     * ['pagi' => [guru_id, ...], 'siang' => [...]].
     */
    public function forDate(string $tanggal): array
    {
        $out = array_fill_keys(self::SHIFTS, []);
        foreach ($this->select('shift, guru_id')->where('tanggal', $tanggal)->findAll() as $r) {
            $out[$r['shift']][] = (int) $r['guru_id'];
        }

        return $out;
    }

    /**
     * Samakan daftar belum hadir satu tanggal + shift dengan $guruIds
     * (tambah yang baru, hapus yang tidak dikirim) dalam satu transaksi.
     *
     * @param int[] $guruIds
     */
    public function syncShift(string $tanggal, string $shift, array $guruIds, ?int $adminId = null): void
    {
        if (! in_array($shift, self::SHIFTS, true)) {
            return;
        }
        $guruIds = array_values(array_unique(array_filter(array_map('intval', $guruIds))));

        $existing = array_map('intval', array_column(
            $this->select('guru_id')->where('tanggal', $tanggal)->where('shift', $shift)->findAll(),
            'guru_id'
        ));

        $this->db->transStart();
        $hapus = array_diff($existing, $guruIds);
        if ($hapus !== []) {
            $this->where('tanggal', $tanggal)->where('shift', $shift)->whereIn('guru_id', $hapus)->delete();
        }
        foreach (array_diff($guruIds, $existing) as $gid) {
            $this->insert(['tanggal' => $tanggal, 'shift' => $shift, 'guru_id' => $gid, 'created_by' => $adminId]);
        }
        $this->db->transComplete();
    }

    /**
     * Baris rincian rekap satu guru: tanggal + shift yang masih "belum hadir"
     * (dihitung tidak hadir). Bentuk kunci mengikuti rincian absensi lain.
     */
    public function detailForGuru(int|array $guruId, string $dari, string $sampai): array
    {
        $rows = $this->select('tanggal, shift')
            ->whereIn('guru_id', (array) $guruId)
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->orderBy('tanggal', 'ASC')
            ->findAll();

        return array_map(static fn ($r) => [
            'tanggal'    => $r['tanggal'],
            'status'     => 'alpa',
            'jam_masuk'  => null,
            'keterangan' => 'Ditandai belum hadir & tidak ditandai "Sudah datang"',
            'label'      => 'Belum hadir · KBM ' . $r['shift'],
        ], $rows);
    }

    /**
     * Pasangan (guru, tanggal, shift) belum hadir pada rentang, hanya untuk
     * perhitungan rekap: [guru_id][tanggal][shift] => true.
     */
    public function mapRange(string $dari, string $sampai): array
    {
        $map = [];
        $rows = $this->select('tanggal, shift, guru_id')
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->findAll();
        foreach ($rows as $r) {
            $map[(int) $r['guru_id']][$r['tanggal']][$r['shift']] = true;
        }

        return $map;
    }
}
