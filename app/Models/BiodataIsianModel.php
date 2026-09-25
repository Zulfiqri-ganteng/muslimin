<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Kotak masuk isian biodata siswa (tabel biodata_isian).
 *
 * Satu siswa = satu baris (UNIQUE siswa_id). Isian disimpan utuh sebagai
 * JSON di kolom `data` dan baru ditulis ke tabel siswa setelah admin
 * menyetujuinya. Siklus status:
 *
 *   menunggu  ──setujui──▶ disetujui
 *      │                      │
 *      └──kembalikan──▶ perbaikan ──siswa kirim ulang──▶ menunggu
 *
 * Selama `menunggu`/`disetujui` isian TERKUNCI bagi siswa; hanya status
 * `perbaikan` (dibuka admin) yang boleh diisi ulang.
 */
class BiodataIsianModel extends Model
{
    protected $table         = 'biodata_isian';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'siswa_id', 'nisn', 'data', 'data_sebelum', 'status', 'catatan_admin',
        'kirim_ke', 'ip_address', 'diverifikasi_at', 'diverifikasi_oleh',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const STATUS = ['menunggu', 'disetujui', 'perbaikan'];

    /**
     * Kolom tabel siswa yang BOLEH diisi siswa lewat form publik — sekaligus
     * daftar putih saat isian disetujui. Kelas, status, tahun masuk, dan
     * keterangan sengaja tidak ada: itu wewenang admin.
     */
    public const KOLOM = [
        // Data diri
        'nama', 'nis', 'nisn', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'agama', 'status_keluarga', 'anak_ke',
        // Alamat & kontak siswa
        'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'no_hp',
        // Riwayat masuk
        'sekolah_asal', 'diterima_kelas', 'diterima_tanggal',
        // Orang tua
        'nama_ayah', 'nama_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu',
        'ortu_alamat', 'ortu_rt', 'ortu_rw', 'ortu_kelurahan', 'ortu_kecamatan', 'ortu_kota', 'ortu_telepon',
        // Wali
        'nama_wali', 'alamat_wali', 'no_hp_wali', 'pekerjaan_wali',
    ];

    /** Isi kolom `data` sebagai array (hanya kunci yang sah). */
    public static function decode(?array $row): array
    {
        $data = json_decode((string) ($row['data'] ?? ''), true);
        if (! is_array($data)) {
            return [];
        }

        return array_intersect_key($data, array_flip(self::KOLOM));
    }

    /**
     * Apakah form isian publik sedang dibuka?
     * Saklar harus menyala DAN (bila batas waktu diisi) belum lewat.
     */
    public static function formTerbuka(array $setting): bool
    {
        if (empty($setting['biodata_open'])) {
            return false;
        }
        $tutup = $setting['biodata_tutup'] ?? null;

        return $tutup === null || $tutup === '' || strtotime((string) $tutup) > time();
    }

    /** Isian milik satu siswa (atau null bila belum pernah mengisi). */
    public function milikSiswa(int $siswaId): ?array
    {
        return $this->where('siswa_id', $siswaId)->first();
    }

    // =================================================================
    // Kueri halaman admin. TIDAK di-cache: angka harus langsung bergerak
    // saat siswa mengirim isian. Semua hanya menghitung siswa AKTIF yang
    // belum dihapus — siswa lulus/pindah tidak dikejar mengisi.
    // =================================================================

    /** @return array{total:int, sudah:int, menunggu:int, disetujui:int, perbaikan:int, belum:int, persen:int, persen_teks:string, rasio:float} */
    public function ringkasan(): array
    {
        $r = $this->db->table('siswa s')
            ->select("COUNT(*) AS total, SUM(b.id IS NOT NULL) AS sudah, SUM(b.status = 'menunggu') AS menunggu,"
                . " SUM(b.status = 'disetujui') AS disetujui, SUM(b.status = 'perbaikan') AS perbaikan", false)
            ->join('biodata_isian b', 'b.siswa_id = s.id', 'left')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif')
            ->get()->getRowArray() ?? [];

        $out = [];
        foreach (['total', 'sudah', 'menunggu', 'disetujui', 'perbaikan'] as $k) {
            $out[$k] = (int) ($r[$k] ?? 0);
        }
        $out['belum']  = $out['total'] - $out['sudah'];
        return $out + self::persen($out['sudah'], $out['total']);
    }

    /**
     * Persentase siap tampil:
     *   persen      — dibulatkan ke BAWAH (99,9% tidak pernah tampil "100%")
     *   persen_teks — "<1%" bila sudah ada yang mengisi tapi belum 1%, agar
     *                 admin tak mengira belum ada satu pun yang mengisi
     *   rasio       — nilai presisi untuk lebar batang progres
     *
     * @return array{persen:int, persen_teks:string, rasio:float}
     */
    private static function persen(int $sudah, int $total): array
    {
        $rasio  = $total > 0 ? round($sudah * 100 / $total, 2) : 0.0;
        $persen = (int) floor($rasio);

        return [
            'persen'      => $persen,
            'persen_teks' => ($persen === 0 && $sudah > 0) ? '<1%' : $persen . '%',
            'rasio'       => $rasio,
        ];
    }

    /**
     * Rekap per kelas, urut tingkat lalu nama kelas secara alami
     * ("X TKJ 2" sebelum "X TKJ 10").
     *
     * @return list<array<string, mixed>>
     */
    public function perKelas(): array
    {
        $rows = $this->db->table('kelas k')
            ->select("k.id, k.nama_kelas, k.tingkat, g.nama AS wali, COUNT(s.id) AS total,"
                . " SUM(b.id IS NOT NULL) AS sudah, SUM(b.status = 'menunggu') AS menunggu,"
                . " SUM(b.status = 'disetujui') AS disetujui, SUM(b.status = 'perbaikan') AS perbaikan", false)
            ->join('siswa s', 's.kelas_id = k.id')
            ->join('biodata_isian b', 'b.siswa_id = s.id', 'left')
            ->join('guru g', 'g.id = k.wali_kelas_id', 'left')
            ->where('k.deleted_at', null)
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif')
            ->groupBy('k.id')
            ->get()->getResultArray();

        foreach ($rows as &$r) {
            // Semua angka dikirim sebagai int — klien bertipe (Flutter) gagal membaca "56".
            foreach (['id', 'total', 'sudah', 'menunggu', 'disetujui', 'perbaikan'] as $k) {
                $r[$k] = (int) $r[$k];
            }
            $r['belum'] = $r['total'] - $r['sudah'];
            $r += self::persen($r['sudah'], $r['total']);
        }
        unset($r);

        $urut = ['X' => 0, 'XI' => 1, 'XII' => 2];
        usort($rows, static fn ($a, $b) => (($urut[$a['tingkat']] ?? 9) <=> ($urut[$b['tingkat']] ?? 9))
            ?: strnatcasecmp($a['nama_kelas'], $b['nama_kelas']));

        return $rows;
    }

    /**
     * Daftar isian berstatus tertentu (kotak masuk).
     * Menunggu diurutkan dari yang PALING LAMA (antrean), lainnya terbaru dulu.
     *
     * @return array{0: list<array<string, mixed>>, 1: int} [baris, total]
     */
    public function daftar(string $status, int $kelasId, string $q, int $per, int $page): array
    {
        $b = $this->db->table('biodata_isian b')
            ->select('b.id, b.siswa_id, b.nisn, b.status, b.kirim_ke, b.catatan_admin, b.created_at, b.updated_at,'
                . ' b.diverifikasi_at, s.nama, s.jenis_kelamin, k.nama_kelas')
            ->join('siswa s', 's.id = b.siswa_id')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif')
            ->where('b.status', $status);
        $this->saring($b, $kelasId, $q);

        $total = $b->countAllResults(false);
        $urut  = match ($status) {
            'menunggu'  => 'b.updated_at ASC',
            'disetujui' => 'b.diverifikasi_at DESC',
            default     => 'b.updated_at DESC',
        };
        $rows = $b->orderBy($urut, '', false)->orderBy('b.id', 'ASC')
            ->limit($per, ($page - 1) * $per)->get()->getResultArray();

        return [$rows, $total];
    }

    /**
     * Siswa aktif yang BELUM mengirim isian, urut kelas (alami) lalu nama.
     * $per = 0 → tanpa batas (dipakai untuk pesan WhatsApp satu kelas).
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    public function belumMengisi(int $kelasId, string $q, int $per = 0, int $page = 1): array
    {
        $b = $this->db->table('siswa s')
            ->select('s.id, s.nama, s.jenis_kelamin, k.nama_kelas')
            ->join('biodata_isian b', 'b.siswa_id = s.id', 'left')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif')
            ->where('b.id', null);
        $this->saring($b, $kelasId, $q);

        $total = $b->countAllResults(false);
        $ids   = array_column($this->perKelas(), 'id');
        if ($ids !== []) {
            $b->orderBy('FIELD(s.kelas_id, ' . implode(',', array_map('intval', $ids)) . ')', '', false);
        }
        $b->orderBy('s.nama', 'ASC');
        if ($per > 0) {
            $b->limit($per, ($page - 1) * $per);
        }

        return [$b->get()->getResultArray(), $total];
    }

    /** Isian menunggu berikutnya (antrean tertua) — untuk tombol "setujui lalu lanjut". */
    public function menungguBerikutnya(int $kecualiId, int $kelasId = 0): ?array
    {
        $b = $this->db->table('biodata_isian b')
            ->select('b.id')
            ->join('siswa s', 's.id = b.siswa_id')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif')
            ->where('b.status', 'menunggu')
            ->where('b.id !=', $kecualiId);
        $this->saring($b, $kelasId, '');

        return $b->orderBy('b.updated_at', 'ASC')->orderBy('b.id', 'ASC')->limit(1)->get()->getRowArray();
    }

    /** Id isian menunggu sesuai saringan (untuk "setujui semua"). @return list<int> */
    public function idMenunggu(int $kelasId, string $q, int $batas): array
    {
        $b = $this->db->table('biodata_isian b')
            ->select('b.id')
            ->join('siswa s', 's.id = b.siswa_id')
            ->where('s.deleted_at', null)
            ->where('s.status', 'aktif')
            ->where('b.status', 'menunggu');
        $this->saring($b, $kelasId, $q);

        return array_map('intval', array_column(
            $b->orderBy('b.updated_at', 'ASC')->limit($batas)->get()->getResultArray(),
            'id'
        ));
    }

    /**
     * Saringan kelas + kata kunci (nama / NISN) yang dipakai bersama.
     * Syarat: builder memakai alias "s" (siswa) dan "b" (biodata_isian).
     */
    private function saring(\CodeIgniter\Database\BaseBuilder $builder, int $kelasId, string $q): void
    {
        if ($kelasId > 0) {
            $builder->where('s.kelas_id', $kelasId);
        }
        if ($q !== '') {
            $builder->groupStart()->like('s.nama', $q)->orLike('s.nisn', $q)->orLike('b.nisn', $q)->groupEnd();
        }
    }
}
