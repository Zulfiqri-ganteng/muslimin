<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jadwal pelaksanaan ujian: satu baris = satu mapel diujikan untuk satu
 * tingkat (opsional dipersempit ke satu jurusan) pada satu tanggal & jam.
 *
 * Kolom `shift` wajib diperhatikan: kelas X semuanya pagi, XI semuanya siang,
 * tapi XII terbelah 5 pagi + 6 siang — jadi menjadwalkan per tingkat saja
 * tidak cukup untuk kelas XII.
 */
class UjianJadwalModel extends Model
{
    protected $table         = 'ujian_jadwal';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'periode_id', 'mapel_id', 'tingkat', 'jurusan_id', 'shift',
        'tanggal', 'jam_mulai', 'jam_selesai', 'ruang', 'keterangan',
    ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    public const TINGKAT = ['X', 'XI', 'XII'];
    public const SHIFT   = ['pagi', 'siang', 'semua'];

    protected $validationRules = [
        'id'          => 'permit_empty|is_natural',
        'periode_id'  => 'required|is_natural',
        'mapel_id'    => 'permit_empty|is_natural',
        'tingkat'     => 'required|in_list[X,XI,XII]',
        'jurusan_id'  => 'permit_empty|is_natural',
        'shift'       => 'permit_empty|in_list[pagi,siang,semua]',
        'tanggal'     => 'required|valid_date[Y-m-d]',
        'jam_mulai'   => 'permit_empty|max_length[8]',
        'jam_selesai' => 'permit_empty|max_length[8]',
        'ruang'       => 'permit_empty|max_length[100]',
    ];
    protected $validationMessages = [
        'periode_id' => ['required' => 'Periode ujian wajib dipilih.'],
        'tingkat'    => ['required' => 'Tingkat wajib dipilih.'],
        'tanggal'    => ['required' => 'Tanggal ujian wajib diisi.'],
    ];

    /** Daftar jadwal + nama mapel, jurusan, dan periodenya. */
    public function withRelations()
    {
        return $this->select(
            'ujian_jadwal.*, mata_pelajaran.kode_mapel, mata_pelajaran.nama_mapel,'
            . ' jurusan.kode AS jurusan_kode, jurusan.nama AS jurusan_nama,'
            . ' ujian_periode.jenis AS periode_jenis, ujian_periode.tahun_ajaran AS periode_tahun'
        )
            ->join('mata_pelajaran', 'mata_pelajaran.id = ujian_jadwal.mapel_id', 'left')
            ->join('jurusan', 'jurusan.id = ujian_jadwal.jurusan_id', 'left')
            ->join('ujian_periode', 'ujian_periode.id = ujian_jadwal.periode_id', 'left');
    }

    /** Jadwal satu periode, diurut waktu pelaksanaan. */
    public function untukPeriode(int $periodeId)
    {
        return $this->withRelations()
            ->where('ujian_jadwal.periode_id', $periodeId)
            ->orderBy('ujian_jadwal.tanggal', 'ASC')
            ->orderBy('ujian_jadwal.jam_mulai', 'ASC')
            ->orderBy('ujian_jadwal.tingkat', 'ASC');
    }

    /**
     * Cari jadwal lain yang BENTROK: tingkat sama, tanggal sama, shift
     * beririsan ('semua' bentrok dengan apa pun), dan jam saling menimpa.
     *
     * Yang dicegah adalah satu rombongan siswa kebagian DUA ujian berbeda
     * pada jam yang sama. Maka:
     *  - MAPEL YANG SAMA di jam sama BUKAN bentrok — itu ujian paralel yang
     *    dipecah ke beberapa ruang, hal biasa untuk tingkat berisi 15 kelas.
     *  - Jurusan berbeda bukan bentrok; baris tanpa jurusan berlaku untuk
     *    semua jurusan sehingga selalu ikut diperiksa.
     *  - Jadwal tanpa jam dianggap memenuhi seharian.
     *
     * @return array<int, array<string, mixed>> baris yang bertabrakan
     */
    public function bentrok(array $data, ?int $exceptId = null): array
    {
        $b = $this->withRelations()
            ->where('ujian_jadwal.periode_id', (int) $data['periode_id'])
            ->where('ujian_jadwal.tingkat', $data['tingkat'])
            ->where('ujian_jadwal.tanggal', $data['tanggal']);

        if ($exceptId !== null) {
            $b = $b->where('ujian_jadwal.id !=', $exceptId);
        }

        $shift = $data['shift'] ?? 'semua';
        if ($shift !== 'semua') {
            $b = $b->whereIn('ujian_jadwal.shift', [$shift, 'semua']);
        }

        // Mapel yang sama = ujian paralel (beda ruang), bukan tabrakan.
        // Baris ber-mapel NULL tetap diperiksa karena isinya tak diketahui.
        $mapelId = (int) ($data['mapel_id'] ?? 0);
        if ($mapelId > 0) {
            $b = $b->groupStart()
                ->where('ujian_jadwal.mapel_id !=', $mapelId)
                ->orWhere('ujian_jadwal.mapel_id', null)
                ->groupEnd();
        }

        // Jurusan: baris tanpa jurusan berlaku untuk semua jurusan di tingkat
        // itu, jadi selalu ikut diperiksa.
        $jurusanId = (int) ($data['jurusan_id'] ?? 0);
        if ($jurusanId > 0) {
            $b = $b->groupStart()
                ->where('ujian_jadwal.jurusan_id', $jurusanId)
                ->orWhere('ujian_jadwal.jurusan_id', null)
                ->groupEnd();
        }

        $mulai   = $data['jam_mulai'] ?? null;
        $selesai = $data['jam_selesai'] ?? null;

        $hasil = [];
        foreach ($b->findAll() as $row) {
            if (self::jamBerimpit($mulai, $selesai, $row['jam_mulai'], $row['jam_selesai'])) {
                $hasil[] = $row;
            }
        }

        return $hasil;
    }

    /**
     * Dua rentang jam saling menimpa? Rentang kosong = seharian penuh.
     *
     * Publik & statis karena dipakai juga oleh UjianPengawasModel untuk
     * memeriksa guru yang ditugaskan di dua sesi pada jam yang sama.
     */
    public static function jamBerimpit(?string $mulaiA, ?string $selesaiA, ?string $mulaiB, ?string $selesaiB): bool
    {
        if (($mulaiA ?? '') === '' || ($mulaiB ?? '') === '') {
            return true;
        }

        $awalA  = strtotime($mulaiA);
        $akhirA = ($selesaiA ?? '') !== '' ? strtotime($selesaiA) : $awalA;
        $awalB  = strtotime($mulaiB);
        $akhirB = ($selesaiB ?? '') !== '' ? strtotime($selesaiB) : $awalB;

        // Bersentuhan di ujung (selesai A == mulai B) tidak dihitung bentrok.
        return $awalA < $akhirB && $awalB < $akhirA;
    }

    /**
     * Kelas yang menjadi sasaran satu sesi ujian.
     *
     * Penyaringnya bertingkat: tingkat wajib cocok; jurusan hanya dibatasi
     * bila sesi memang dikhususkan untuk satu jurusan; shift hanya dibatasi
     * bila sesi bukan "semua". Aturan shift penting karena kelas XII terbagi
     * pagi & siang, sementara X semuanya pagi dan XI semuanya siang.
     *
     * Memakai query builder mentah dengan `deleted_at` DIKUALIFIKASI nama
     * tabelnya — pola yang sama dipakai SiswaModel::statistik supaya klausa
     * soft-delete tidak ambigu saat ada JOIN.
     *
     * @return array<int, array<string, mixed>>
     */
    public function kelasSasaran(array $jadwal): array
    {
        $b = $this->db->table('kelas')
            ->select('kelas.id, kelas.nama_kelas, kelas.tingkat, kelas.shift, jurusan.kode AS jurusan_kode')
            ->join('jurusan', 'jurusan.id = kelas.jurusan_id', 'left')
            ->where('kelas.deleted_at', null)
            ->where('kelas.tingkat', $jadwal['tingkat']);

        if (! empty($jadwal['jurusan_id'])) {
            $b->where('kelas.jurusan_id', (int) $jadwal['jurusan_id']);
        }
        if (($jadwal['shift'] ?? 'semua') !== 'semua') {
            $b->where('kelas.shift', $jadwal['shift']);
        }

        return $b->orderBy('kelas.nama_kelas', 'ASC')->get()->getResultArray();
    }

    /** Opsi dropdown jadwal satu periode [id => "12 Okt · Matematika · X (pagi)"]. */
    public function optionsUntukPeriode(int $periodeId): array
    {
        $out = [];
        foreach ($this->untukPeriode($periodeId)->findAll() as $r) {
            $label = date('d M', strtotime($r['tanggal']))
                . ' · ' . ($r['nama_mapel'] ?? 'Tanpa mapel')
                . ' · ' . $r['tingkat'];
            if ($r['jurusan_kode']) {
                $label .= ' ' . $r['jurusan_kode'];
            }
            if ($r['shift'] !== 'semua') {
                $label .= ' (' . $r['shift'] . ')';
            }
            $out[$r['id']] = $label;
        }

        return $out;
    }
}
