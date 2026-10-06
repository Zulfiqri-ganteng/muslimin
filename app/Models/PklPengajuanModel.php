<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ajuan PKL (tabel pkl_pengajuan) + kueri bacanya. Penulisan yang melibatkan
 * banyak tabel (ajuan + anggota + riwayat, dalam transaksi) ada di
 * App\Libraries\PklAjuan.
 *
 * Siklus status:
 *
 *   menunggu ──ACC──▶ disetujui ──batal ACC──▶ (menunggu/perbaikan/ditolak)
 *      │  ▲
 *      │  └──siswa kirim ulang── perbaikan ◀──kembalikan──┐
 *      └──────────────────────────────────────────────────┘
 *      └──tolak──▶ ditolak   (siswa boleh mengajukan baru)
 *
 * Aktif = menunggu/perbaikan/disetujui: selama ajuan aktif, SEMUA anggotanya
 * terkunci (pkl_anggota.siswa_aktif terisi) dan tak bisa mengajukan lagi.
 */
class PklPengajuanModel extends Model
{
    protected $table         = 'pkl_pengajuan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'status', 'sumber', 'tahun_ajaran', 'perusahaan_id', 'perusahaan_nama', 'perusahaan_norm',
        'perusahaan_alamat', 'perusahaan_kota', 'perusahaan_telepon', 'kontak_nama', 'kontak_jabatan',
        'tanggal_mulai', 'tanggal_selesai', 'catatan_staf', 'kirim_ke', 'ip_address',
        'diputuskan_at', 'diputuskan_oleh',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const STATUS = ['menunggu', 'perbaikan', 'disetujui', 'ditolak'];
    public const AKTIF  = ['menunggu', 'perbaikan', 'disetujui'];
    public const SUMBER = ['siswa', 'staf', 'impor'];

    /** Nomor bukti yang dilihat siswa & staf, mis. PKL-00012. */
    public static function kode(int $id): string
    {
        return 'PKL-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public static function aktif(string $status): bool
    {
        return in_array($status, self::AKTIF, true);
    }

    /**
     * Siswa aktif satu kelas beserta status PKL-nya — bahan daftar nama di form.
     *
     * `aktif`           = status ajuan aktif miliknya (menunggu/perbaikan/disetujui) atau null.
     * `peran`           = pengaju/teman di ajuan aktif itu.
     * `pernah_ditolak`  = pernah punya ajuan yang ditolak (boleh mengajukan baru).
     *
     * Hanya status yang dikembalikan — TIDAK ada data perusahaan/ajuan, supaya
     * tak membuka rahasia siswa lain lewat daftar nama yang bisa dilihat siapa saja.
     *
     * @return list<array<string, mixed>>
     */
    public function daftarSiswaKelas(int $kelasId): array
    {
        return $this->db->table('siswa s')
            ->select("s.id, s.nama, s.jenis_kelamin, a.peran, p.status AS aktif,"
                . " EXISTS(SELECT 1 FROM pkl_anggota ar JOIN pkl_pengajuan pr ON pr.id = ar.pengajuan_id"
                . " WHERE ar.siswa_id = s.id AND pr.status = 'ditolak') AS pernah_ditolak", false)
            ->join('pkl_anggota a', 'a.siswa_aktif = s.id', 'left')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id', 'left')
            ->where('s.kelas_id', $kelasId)
            ->where('s.status', 'aktif')
            ->where('s.deleted_at', null)
            ->orderBy('s.nama', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Ajuan AKTIF milik seorang siswa (sebagai pengaju maupun teman), atau null.
     * Hasil: seluruh kolom ajuan + `peran` + `anggota_id` + `tgl_lahir_anggota`.
     */
    public function aktifMilik(int $siswaId): ?array
    {
        return $this->db->table('pkl_anggota a')
            ->select('p.*, a.id AS anggota_id, a.peran, a.tanggal_lahir AS tgl_lahir_anggota')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')
            ->where('a.siswa_aktif', $siswaId)
            ->get()->getRowArray();
    }

    /**
     * Anggota satu ajuan, pengaju di baris pertama. Kelas dibaca dari SNAPSHOT
     * saat mengajukan (pkl_anggota.kelas_id), bukan kelas siswa sekarang.
     *
     * @return list<array<string, mixed>>
     */
    public function anggotaDetail(int $pengajuanId): array
    {
        return $this->db->table('pkl_anggota a')
            ->select('a.id, a.siswa_id, a.peran, a.hp, a.tanggal_lahir, a.kelas_id, a.siswa_aktif,'
                . ' s.nama, s.jenis_kelamin, s.nis, s.nisn, s.tanggal_lahir AS tgl_lahir_master,'
                . ' s.status AS status_siswa, s.kelas_id AS kelas_sekarang, s.deleted_at AS siswa_dihapus,'
                . ' k.nama_kelas, k.tingkat')
            ->join('siswa s', 's.id = a.siswa_id')
            ->join('kelas k', 'k.id = a.kelas_id', 'left')
            ->where('a.pengajuan_id', $pengajuanId)
            ->orderBy("(a.peran = 'pengaju')", 'DESC', false)
            ->orderBy('s.nama', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Data siswa-siswa yang hendak dipilih sebagai teman, untuk validasi:
     * ada/tidak, aktif/tidak, kelas & tingkatnya, dan ajuan aktif miliknya.
     * Siswa yang tidak ada (atau sudah dihapus) tidak muncul di hasil.
     *
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>> dikunci id siswa
     */
    public function siswaUntukDipilih(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db->table('siswa s')
            ->select('s.id, s.nama, s.status, s.kelas_id, k.nama_kelas, k.tingkat, a.pengajuan_id AS aktif_di')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->join('pkl_anggota a', 'a.siswa_aktif = s.id', 'left')
            ->whereIn('s.id', array_map('intval', $ids))
            ->where('s.deleted_at', null)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r;
        }

        return $out;
    }

    // =================================================================
    // Kueri halaman staf. Tanpa cache: angka harus langsung bergerak.
    // =================================================================

    /** Jumlah ajuan per status + total. @return array<string, int> */
    public function hitungStatus(): array
    {
        $out = array_fill_keys(self::STATUS, 0);
        foreach ($this->db->query('SELECT status, COUNT(*) n FROM pkl_pengajuan GROUP BY status')->getResultArray() as $r) {
            if (isset($out[$r['status']])) {
                $out[$r['status']] = (int) $r['n'];
            }
        }
        $out['total'] = array_sum($out);

        return $out;
    }

    /**
     * Kotak masuk: ajuan satu status, tertua dulu untuk `menunggu` (antrean), terbaru dulu untuk yang lain.
     * Memuat pengaju, kelasnya (saat mengajukan), dan jumlah siswa.
     *
     * @return array{0: list<array<string, mixed>>, 1: int} [baris, total]
     */
    public function daftar(string $status, string $q, int $kelasId, int $per, int $page): array
    {
        $b = $this->db->table('pkl_pengajuan p')
            ->select('p.id, p.status, p.sumber, p.perusahaan_nama, p.perusahaan_kota, p.tanggal_mulai, p.tanggal_selesai,'
                . ' p.kirim_ke, p.catatan_staf, p.created_at, p.updated_at, sp.nama AS pengaju, kp.nama_kelas AS pengaju_kelas,'
                . ' (SELECT COUNT(*) FROM pkl_anggota ax WHERE ax.pengajuan_id = p.id) AS jumlah', false)
            ->join("pkl_anggota ap", "ap.pengajuan_id = p.id AND ap.peran = 'pengaju'", 'left')
            ->join('siswa sp', 'sp.id = ap.siswa_id', 'left')
            ->join('kelas kp', 'kp.id = ap.kelas_id', 'left');
        if (in_array($status, self::STATUS, true)) {
            $b->where('p.status', $status);
        }
        if ($kelasId > 0) {
            $b->where('EXISTS (SELECT 1 FROM pkl_anggota ak WHERE ak.pengajuan_id = p.id AND ak.kelas_id = ' . (int) $kelasId . ')', null, false);
        }
        if ($q !== '') {
            $like = $this->db->escapeLikeString($q);
            $b->groupStart()
                ->like('p.perusahaan_nama', $q)
                ->orWhere("EXISTS (SELECT 1 FROM pkl_anggota an JOIN siswa sn ON sn.id = an.siswa_id WHERE an.pengajuan_id = p.id AND sn.nama LIKE '%{$like}%' ESCAPE '!')", null, false)
                ->groupEnd();
        }

        $total = $b->countAllResults(false);
        $urut  = $status === 'menunggu' ? 'p.updated_at ASC' : 'p.updated_at DESC';
        $rows  = $b->orderBy($urut, '', false)->orderBy('p.id', 'ASC')->limit($per, ($page - 1) * $per)->get()->getResultArray();

        return [$rows, $total];
    }

    /** Satu ajuan lengkap dengan nama perusahaan master (bila sudah ditautkan). */
    public function detail(int $id): ?array
    {
        return $this->db->table('pkl_pengajuan p')
            ->select('p.*, m.nama AS master_nama, m.kota AS master_kota')
            ->join('pkl_perusahaan m', 'm.id = p.perusahaan_id', 'left')
            ->where('p.id', $id)
            ->get()->getRowArray();
    }

    /** Jejak kejadian satu ajuan, terbaru di atas. @return list<array<string, mixed>> */
    public function riwayat(int $id): array
    {
        return $this->db->table('pkl_riwayat')->where('pengajuan_id', $id)->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * Ajuan LAIN (belum ditolak) yang perusahaannya sama/mirip — bahan peringatan "perusahaan sama".
     *
     * @return list<array<string, mixed>>
     */
    public function ajuanSerupa(string $norm, int $kecualiId): array
    {
        if ($norm === '') {
            return [];
        }

        $rows = $this->db->table('pkl_pengajuan')
            ->select('id, status, perusahaan_nama, perusahaan_norm')
            ->where('id !=', $kecualiId)->where('status !=', 'ditolak')
            ->get()->getResultArray();

        return array_values(array_filter($rows, static function (array $r) use ($norm): bool {
            $lain = (string) $r['perusahaan_norm'];
            if ($lain === $norm) {
                return true;
            }
            similar_text($norm, $lain, $persen);

            return $persen >= 80.0;
        }));
    }

    /** Kelas (tingkat tertentu atau semua) yang punya siswa aktif — untuk saringan & pemilih. @return list<array<string, mixed>> */
    public function kelasBersiswa(array $tingkat = []): array
    {
        $b = $this->db->table('kelas k')
            ->select('k.id, k.nama_kelas, k.tingkat')
            ->join('siswa s', 's.kelas_id = k.id')
            ->where('k.deleted_at', null)->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->groupBy('k.id');
        if ($tingkat !== []) {
            $b->whereIn('k.tingkat', $tingkat);
        }
        $rows  = $b->get()->getResultArray();
        $urut  = ['X' => 0, 'XI' => 1, 'XII' => 2];
        usort($rows, static fn ($a, $c) => (($urut[$a['tingkat']] ?? 9) <=> ($urut[$c['tingkat']] ?? 9)) ?: strnatcasecmp($a['nama_kelas'], $c['nama_kelas']));

        return $rows;
    }

    /**
     * Status PKL turunan tiap siswa aktif di tingkat yang boleh (dihitung dari ajuan + tanggal hari ini):
     *   belum · ditolak · menunggu · perbaikan · belum_mulai · sedang · selesai · disetujui (tanggal kosong)
     * SQL dasar dipakai bersama oleh daftar & ringkasan supaya angkanya selalu sepakat.
     *
     * @return array{0: string, 1: list<mixed>} [SQL turunan, parameter]
     */
    private function sqlSiswaTurunan(array $tingkat, string $hariIni, int $kelasId, string $q): array
    {
        $in   = implode(',', array_fill(0, count($tingkat), '?'));
        $bind = [$hariIni, $hariIni];
        $sql  = "SELECT s.id, s.nama, s.jenis_kelamin, k.id AS kelas_id, k.nama_kelas, k.tingkat,"
            . " p.id AS ajuan_id, p.perusahaan_nama, p.tanggal_mulai, p.tanggal_selesai, a.peran,"
            . " CASE"
            . " WHEN p.id IS NULL AND EXISTS (SELECT 1 FROM pkl_anggota ar JOIN pkl_pengajuan pr ON pr.id = ar.pengajuan_id WHERE ar.siswa_id = s.id AND pr.status = 'ditolak') THEN 'ditolak'"
            . " WHEN p.id IS NULL THEN 'belum'"
            . " WHEN p.status = 'menunggu' THEN 'menunggu'"
            . " WHEN p.status = 'perbaikan' THEN 'perbaikan'"
            . " WHEN p.tanggal_mulai IS NOT NULL AND ? < p.tanggal_mulai THEN 'belum_mulai'"
            . " WHEN p.tanggal_selesai IS NOT NULL AND ? > p.tanggal_selesai THEN 'selesai'"
            . " WHEN p.tanggal_mulai IS NOT NULL THEN 'sedang'"
            . " ELSE 'disetujui' END AS fase"
            . " FROM siswa s JOIN kelas k ON k.id = s.kelas_id AND k.deleted_at IS NULL"
            . " LEFT JOIN pkl_anggota a ON a.siswa_aktif = s.id LEFT JOIN pkl_pengajuan p ON p.id = a.pengajuan_id"
            . " WHERE s.status = 'aktif' AND s.deleted_at IS NULL AND k.tingkat IN ({$in})";
        array_push($bind, ...$tingkat);
        if ($kelasId > 0) {
            $sql   .= ' AND k.id = ?';
            $bind[] = $kelasId;
        }
        if ($q !== '') {
            $sql   .= " AND s.nama LIKE ? ESCAPE '!'";
            $bind[] = '%' . $this->db->escapeLikeString($q) . '%';
        }

        return [$sql, $bind];
    }

    /**
     * Daftar siswa + status turunannya. $fase kosong = semua.
     *
     * @return array{0: list<array<string, mixed>>, 1: int} [baris, total]
     */
    public function statusSiswa(array $tingkat, int $kelasId, string $q, string $fase, int $per, int $page): array
    {
        if ($tingkat === []) {
            return [[], 0];
        }
        [$sql, $bind] = $this->sqlSiswaTurunan($tingkat, date('Y-m-d'), $kelasId, $q);
        $where        = '';
        if ($fase === 'sudah_mengisi') {
            $where = " WHERE t.fase IN ('menunggu','perbaikan','belum_mulai','sedang','selesai','disetujui')";
        } elseif ($fase === 'sudah_pkl') {
            $where = " WHERE t.fase IN ('belum_mulai','sedang','selesai','disetujui')";
        } elseif ($fase === 'belum_mengisi') {
            $where = " WHERE t.fase IN ('belum','ditolak')";
        } elseif ($fase !== '') {
            $where = ' WHERE t.fase = ?';
            $bind[] = $fase;
        }

        $total = (int) ($this->db->query("SELECT COUNT(*) n FROM ({$sql}) t{$where}", $bind)->getRowArray()['n'] ?? 0);
        $rows  = $this->db->query(
            "SELECT t.* FROM ({$sql}) t{$where} ORDER BY FIELD(t.tingkat,'X','XI','XII'), t.nama_kelas, t.nama LIMIT " . (int) $per . ' OFFSET ' . (int) (($page - 1) * $per),
            $bind
        )->getResultArray();

        return [$rows, $total];
    }

    /**
     * Angka utama untuk Waka Hubin: sudah/belum mengisi dan sudah/belum PKL + rincian fase.
     *
     * @return array<string, int|float>
     */
    public function ringkasanSiswa(array $tingkat, int $kelasId = 0): array
    {
        $kunci = ['belum', 'ditolak', 'menunggu', 'perbaikan', 'belum_mulai', 'sedang', 'selesai', 'disetujui'];
        $out   = array_fill_keys($kunci, 0);
        if ($tingkat !== []) {
            [$sql, $bind] = $this->sqlSiswaTurunan($tingkat, date('Y-m-d'), $kelasId, '');
            foreach ($this->db->query("SELECT fase, COUNT(*) n FROM ({$sql}) t GROUP BY fase", $bind)->getResultArray() as $r) {
                $out[$r['fase']] = (int) $r['n'];
            }
        }

        $total              = array_sum($out);
        $out['total']       = $total;
        $out['sudah_pkl']   = $out['belum_mulai'] + $out['sedang'] + $out['selesai'] + $out['disetujui'];
        $out['sudah_isi']   = $out['sudah_pkl'] + $out['menunggu'] + $out['perbaikan'];
        $out['belum_isi']   = $out['belum'] + $out['ditolak'];
        $out['belum_pkl']   = $total - $out['sudah_pkl'];
        $out['persen_isi']  = $total > 0 ? (int) floor($out['sudah_isi'] * 100 / $total) : 0;
        $out['persen_pkl']  = $total > 0 ? (int) floor($out['sudah_pkl'] * 100 / $total) : 0;

        return $out;
    }

    /**
     * Nama + kelas sekarang beberapa siswa — untuk menampilkan ulang pilihan di form staf yang gagal disimpan.
     *
     * @param list<int> $ids
     *
     * @return array<int, array{id:int, nama:string, kelas:string}>
     */
    public function ringkasSiswa(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($this->db->table('siswa s')->select('s.id, s.nama, k.nama_kelas')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')->whereIn('s.id', $ids)->where('s.deleted_at', null)
            ->get()->getResultArray() as $r) {
            $out[(int) $r['id']] = ['id' => (int) $r['id'], 'nama' => $r['nama'], 'kelas' => (string) ($r['nama_kelas'] ?? '')];
        }

        return $out;
    }

    /** Banyak ajuan yang dikirim dari satu IP dalam $detik terakhir (penjaga banjir kiriman). */
    public function kirimanDariIp(string $ip, int $detik): int
    {
        return $this->db->table($this->table)
            ->where('ip_address', $ip)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $detik))
            ->countAllResults();
    }
}
