<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * INTI MODUL: satu baris = satu siswa yang TIDAK HADIR pada satu sesi ujian,
 * sekaligus catatan pengelolaan ujian susulannya.
 *
 * Yang dicatat hanya siswa yang berhalangan — siswa yang hadir tidak pernah
 * masuk tabel ini (lihat keputusan #2 di docs/DESAIN-UJIAN.md). Alur status:
 *
 *   belum  →  dijadwalkan  →  selesai
 *                 └──────────→  batal
 *
 * `mapel_id` & `tanggal_ujian` sengaja didenormalisasi dari jadwal supaya
 * baris tetap terbaca kalau jadwalnya dihapus (jadwal_id jadi NULL).
 */
class UjianSusulanModel extends Model
{
    protected $table         = 'ujian_susulan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'periode_id', 'jadwal_id', 'siswa_id', 'mapel_id', 'tanggal_ujian',
        'alasan', 'keterangan', 'status',
        'tanggal_susulan', 'jam_susulan', 'ruang_susulan', 'pengawas_guru_id',
        'tanggal_pelaksanaan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const ALASAN = ['sakit', 'izin', 'alpa', 'lainnya'];
    public const STATUS = ['belum', 'dijadwalkan', 'selesai', 'batal'];

    public const STATUS_LABEL = [
        'belum'       => 'Belum dijadwalkan',
        'dijadwalkan' => 'Sudah dijadwalkan',
        'selesai'     => 'Selesai',
        'batal'       => 'Batal',
    ];

    protected $validationRules = [
        'id'                  => 'permit_empty|is_natural',
        'periode_id'          => 'required|is_natural',
        'jadwal_id'           => 'permit_empty|is_natural',
        'siswa_id'            => 'required|is_natural',
        'mapel_id'            => 'permit_empty|is_natural',
        'tanggal_ujian'       => 'permit_empty|valid_date[Y-m-d]',
        'alasan'              => 'permit_empty|in_list[sakit,izin,alpa,lainnya]',
        'status'              => 'permit_empty|in_list[belum,dijadwalkan,selesai,batal]',
        'tanggal_susulan'     => 'permit_empty|valid_date[Y-m-d]',
        'tanggal_pelaksanaan' => 'permit_empty|valid_date[Y-m-d]',
        'pengawas_guru_id'    => 'permit_empty|is_natural',
    ];
    protected $validationMessages = [
        'periode_id' => ['required' => 'Periode ujian wajib dipilih.'],
        'siswa_id'   => ['required' => 'Siswa wajib dipilih.'],
    ];

    /** Daftar susulan + identitas siswa, kelas, mapel, jadwal, dan pengawasnya. */
    public function withRelations()
    {
        return $this->select(
            'ujian_susulan.*, siswa.nis, siswa.nama AS siswa_nama,'
            . ' kelas.nama_kelas, kelas.tingkat, kelas.shift,'
            . ' mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel,'
            . ' ujian_jadwal.tanggal AS jadwal_tanggal, ujian_jadwal.jam_mulai AS jadwal_jam_mulai,'
            . ' ujian_jadwal.ruang AS jadwal_ruang,'
            . ' guru.nama AS pengawas_nama'
        )
            ->join('siswa', 'siswa.id = ujian_susulan.siswa_id', 'left')
            ->join('kelas', 'kelas.id = siswa.kelas_id', 'left')
            ->join('mata_pelajaran', 'mata_pelajaran.id = ujian_susulan.mapel_id', 'left')
            ->join('ujian_jadwal', 'ujian_jadwal.id = ujian_susulan.jadwal_id', 'left')
            ->join('guru', 'guru.id = ujian_susulan.pengawas_guru_id', 'left');
    }

    /**
     * Id siswa yang SUDAH tercatat tidak hadir pada satu sesi jadwal.
     *
     * Dipakai form pendataan untuk mencentang otomatis siswa yang sudah
     * didata, [siswa_id => baris].
     */
    public function siswaTercatat(int $jadwalId): array
    {
        $out = [];
        foreach ($this->where('jadwal_id', $jadwalId)->findAll() as $r) {
            $out[(int) $r['siswa_id']] = $r;
        }

        return $out;
    }

    /**
     * Catat ketidakhadiran satu siswa, tahan terhadap UNIQUE(siswa_id, jadwal_id).
     *
     * UNIQUE tetap berlaku pada baris yang sudah di-soft-delete, jadi baris
     * lama DIPULIHKAN alih-alih diinsert ulang (pola sama dengan
     * PesertaUkk::daftarkanStore di modul UKK).
     *
     * @return string 'insert' | 'update' | 'pulih'
     */
    public function catat(int $jadwalId, int $siswaId, array $data): string
    {
        // findAll(1), BUKAN first(): first() + withDeleted() pada model
        // ber-soft-delete membuat CI4 menyisipkan "GROUP BY <tabel>.id" yang
        // ditolak server ber-ONLY_FULL_GROUP_BY. Lihat catatan sama di
        // UjianPeriodeModel::ambilAtauBuat().
        $lama = $this->withDeleted()
            ->where(['siswa_id' => $siswaId, 'jadwal_id' => $jadwalId])
            ->findAll(1)[0] ?? null;

        // array_merge, bukan operator "+": id dari argumen harus selalu menang
        // walau pemanggil kebetulan menitipkan siswa_id/jadwal_id di $data.
        $data = array_merge($data, ['siswa_id' => $siswaId, 'jadwal_id' => $jadwalId]);

        if (! $lama) {
            $this->insert($data);

            return 'insert';
        }

        $pulih = $lama['deleted_at'] !== null;
        if ($pulih) {
            $this->protect(false);
            $this->update($lama['id'], $data + ['deleted_at' => null]);
            $this->protect(true);

            return 'pulih';
        }

        $this->update($lama['id'], $data);

        return 'update';
    }

    /** Ringkasan jumlah per status untuk satu periode, [status => jumlah]. */
    public function ringkasanStatus(int $periodeId): array
    {
        $out = array_fill_keys(self::STATUS, 0);

        $rows = $this->select('status, COUNT(*) AS jml')
            ->where('periode_id', $periodeId)
            ->groupBy('status')
            ->findAll();

        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['jml'];
        }

        return $out;
    }

    /** Ringkasan jumlah per alasan untuk satu periode, [alasan => jumlah]. */
    public function ringkasanAlasan(int $periodeId): array
    {
        $out = array_fill_keys(self::ALASAN, 0);

        $rows = $this->select('alasan, COUNT(*) AS jml')
            ->where('periode_id', $periodeId)
            ->groupBy('alasan')
            ->findAll();

        foreach ($rows as $r) {
            $out[$r['alasan']] = (int) $r['jml'];
        }

        return $out;
    }

    /** Jumlah siswa tidak hadir per jadwal, [jadwal_id => jumlah]. */
    public function countForJadwal(array $jadwalIds): array
    {
        if ($jadwalIds === []) {
            return [];
        }

        $rows = $this->select('jadwal_id, COUNT(*) AS jml')
            ->whereIn('jadwal_id', $jadwalIds)
            ->groupBy('jadwal_id')
            ->findAll();

        return array_column($rows, 'jml', 'jadwal_id');
    }
}
