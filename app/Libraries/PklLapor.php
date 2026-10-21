<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Lapor diterima PKL — siswa melapor "sudah diterima perusahaan (DUDI)" beserta periode PKL lewat form publik (pkl/lapor),
 * lalu staf memprosesnya menjadi Surat Balasan PKL. SATU sumber aturan untuk form siswa, kotak laporan staf (web) dan API.
 * Rancangan: docs/DESAIN-HAK-AKSES.md bagian 4.
 *
 * Aturan:
 *   - hanya untuk ajuan PKL berstatus DISETUJUI; satu laporan per ajuan (kirim ulang memperbarui laporan yang belum diproses);
 *     anggota kelompok mana pun boleh melapor, dibuktikan dengan nomor HP-nya (HP di ajuan atau di data siswa), percobaan dibatasi
 *     LoginThrottle; tanpa unggah berkas (semua surat berformat sekolah — keputusan sekolah 2026-10-13);
 *   - laporan tidak bisa dikirim bila Surat Balasan ajuan itu sudah ada / laporan sudah diproses;
 *   - staf: pemegang hak ACC Surat Balasan → "Setujui & buat" (surat langsung disetujui atas namanya); hanya hak Buat → "Buat"
 *     (surat masuk antrean ACC); keduanya boleh "Kembalikan" dengan alasan. Laporan DIKLAIM dulu secara atomik (status baru →
 *     diproses) sehingga dua staf yang menekan bersamaan tidak membuat surat ganda.
 * Jejak: riwayat ajuan PKL (aksi lapor_diterima / lapor_proses / lapor_kembali) + Audit Log.
 *
 * HASIL PERUSAHAAN (revisi klien 2026-10-20, kolom pkl_lapor.hasil + diterima):
 *   - 'semua'    = semua anggota diterima (seperti dulu) → Surat Balasan untuk semua;
 *   - 'sebagian' = siswa mencentang siapa yang diterima (minimal 1, tidak semua). Saat staf memproses, anggota yang TIDAK diterima
 *                  dikeluarkan dulu (PklAjuan::keluarkan, jenis ditolak_perusahaan → bebas mengajukan baru, tetap tercatat), lalu
 *                  Surat Balasan dibuat HANYA untuk yang diterima;
 *   - 'ditolak'  = perusahaan menolak semua → tanpa tanggal; staf mengonfirmasi → ajuan "Ditolak perusahaan" (tanpa surat).
 *   Siswa tidak pernah melepas temannya sendiri: pelepasan terjadi saat STAF memproses. Laporan 'sebagian'/'ditolak' hanya diproses
 *   lewat kotak laporan (formulir "Periksa & ubah dulu" dan pembuatan Balasan manual menolaknya, supaya surat tidak memuat siswa
 *   yang ditolak).
 */
final class PklLapor
{
    public const STATUS = ['baru', 'diproses', 'dikembalikan'];

    /** hasil => teks */
    public const HASIL = [
        'semua'    => 'Semua diterima',
        'sebagian' => 'Sebagian diterima',
        'ditolak'  => 'Semua ditolak perusahaan',
    ];

    private static ?bool $kolomHasil = null;

    /** status => [teks, kelas lencana] */
    public const TAMPIL = [
        'baru'         => ['Menunggu diproses', 'lencana-kuning'],
        'diproses'     => ['Surat Balasan dibuat', 'lencana-hijau'],
        'dikembalikan' => ['Dikembalikan ke siswa', 'lencana-merah'],
    ];

    public const MAKS_ALAMAT  = 300;
    public const MAKS_CATATAN = 300;

    /** Kunci sesi izin kirim: [pengajuan_id => ['siswa' => id, 't' => waktu]]. */
    private const SESI = 'pkl_lapor_izin';
    private const TTL  = 3600;

    private static ?bool $tabelAda = null;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public static function tabelAda(): bool
    {
        if (self::$tabelAda === null) {
            try {
                self::$tabelAda = db_connect()->tableExists('pkl_lapor');
            } catch (\Throwable $e) {
                self::$tabelAda = false;
            }
        }

        return self::$tabelAda;
    }

    /** Kolom pkl_lapor.hasil & diterima (migrasi 2026-10-25-000001) sudah ada? */
    public static function kolomHasil(): bool
    {
        if (self::$kolomHasil === null) {
            try {
                self::$kolomHasil = self::tabelAda() && db_connect()->fieldExists('hasil', 'pkl_lapor');
            } catch (\Throwable $e) {
                self::$kolomHasil = false;
            }
        }

        return self::$kolomHasil;
    }

    /**
     * Rincian laporan terhadap anggota AKTIF ajuan sekarang: siapa diterima, siapa ditolak perusahaan (belum dikeluarkan).
     * 'semua' → semua diterima; 'ditolak' → semua ditolak; 'sebagian' → sesuai centang (anggota yang belum tercentang = ditolak).
     *
     * @param array<string, mixed>                                $l       baris pkl_lapor
     * @param list<array{siswa_id: int, nama: string}>            $anggota anggota aktif ajuan (SuratPkl::ajuan()['siswa'])
     *
     * @return array{hasil: string, diterima: list<array<string, mixed>>, ditolak: list<array<string, mixed>>}
     */
    public static function rincian(array $l, array $anggota): array
    {
        $hasil = (string) ($l['hasil'] ?? 'semua');
        if (! isset(self::HASIL[$hasil])) {
            $hasil = 'semua';
        }
        if ($hasil === 'semua') {
            return ['hasil' => $hasil, 'diterima' => $anggota, 'ditolak' => []];
        }
        if ($hasil === 'ditolak') {
            return ['hasil' => $hasil, 'diterima' => [], 'ditolak' => $anggota];
        }
        $ids = self::idDiterima($l);

        return [
            'hasil'    => $hasil,
            'diterima' => array_values(array_filter($anggota, static fn (array $s): bool => in_array((int) $s['siswa_id'], $ids, true))),
            'ditolak'  => array_values(array_filter($anggota, static fn (array $s): bool => ! in_array((int) $s['siswa_id'], $ids, true))),
        ];
    }

    /** @return list<int> id siswa yang dicentang "diterima" pada laporan 'sebagian' */
    public static function idDiterima(array $l): array
    {
        $j = json_decode((string) ($l['diterima'] ?? ''), true);

        return is_array($j) ? array_values(array_unique(array_map('intval', array_filter($j, 'is_scalar')))) : [];
    }

    /**
     * Laporan "sebagian diterima" / "semua ditolak" yang MASIH MENUNGGU diproses staf: ajuan_id => id siswa yang DITERIMA (kosong =
     * semua ditolak). Anggota ajuan itu yang tidak ada di daftar = dilaporkan tidak diterima perusahaan; ia tetap tercatat (terkunci)
     * di ajuan sampai staf memproses laporannya. Dipakai form pengajuan siswa supaya tidak menulis "ajuan sudah disetujui, tidak perlu
     * mengisi lagi" kepada siswa yang sebenarnya ditolak perusahaan.
     *
     * @param list<int> $ajuanIds
     *
     * @return array<int, list<int>>
     */
    public function tolakMenunggu(array $ajuanIds): array
    {
        $ajuanIds = array_values(array_unique(array_filter(array_map('intval', $ajuanIds))));
        if ($ajuanIds === [] || ! self::kolomHasil()) {
            return [];
        }
        $out = [];
        foreach ($this->db->table('pkl_lapor')->select('pengajuan_id, hasil, diterima')->whereIn('pengajuan_id', $ajuanIds)
            ->where('status', 'baru')->whereIn('hasil', ['sebagian', 'ditolak'])->get()->getResultArray() as $l) {
            $out[(int) $l['pengajuan_id']] = $l['hasil'] === 'ditolak' ? [] : self::idDiterima($l);
        }

        return $out;
    }

    /** Siswa $siswaId dilaporkan TIDAK diterima perusahaan pada laporan ajuan $ajuanId yang masih menunggu ($peta = tolakMenunggu()). */
    public static function dilaporkanTolak(array $peta, int $ajuanId, int $siswaId): bool
    {
        return isset($peta[$ajuanId]) && ! in_array($siswaId, $peta[$ajuanId], true);
    }

    /** Form lapor terbuka? (migrasi sudah jalan + saklar Pengaturan PKL menyala — bawaan menyala) */
    public static function terbuka(array $p): bool
    {
        return self::tabelAda() && (int) ($p['lapor_buka'] ?? 1) === 1;
    }

    /** Keterangan status untuk kalimat "Laporan ini …". */
    public static function kalimatStatus(string $status): string
    {
        return match ($status) {
            'baru'         => 'masih menunggu diproses',
            'diproses'     => 'sudah diproses (Surat Balasan sudah dibuat)',
            'dikembalikan' => 'sudah dikembalikan ke siswa',
            default        => 'berstatus ' . $status,
        };
    }

    // =================================================================
    // Sisi siswa (form publik)
    // =================================================================

    /**
     * Kelas yang punya siswa aktif dengan ajuan PKL DISETUJUI, per tingkat, urut alami.
     *
     * @return array<string, list<array{id: int, nama: string}>>
     */
    public function kelas(): array
    {
        $rows = $this->db->table('kelas k')
            ->select('k.id, k.nama_kelas, k.tingkat')
            ->join('siswa s', 's.kelas_id = k.id')
            ->join('pkl_anggota a', 'a.siswa_aktif = s.id')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id')
            ->where('k.deleted_at', null)->where('s.deleted_at', null)->where('s.status', 'aktif')->where('p.status', 'disetujui')
            ->groupBy('k.id')
            ->get()->getResultArray();
        $urut = ['X' => 0, 'XI' => 1, 'XII' => 2];
        usort($rows, static fn ($a, $b) => (($urut[$a['tingkat']] ?? 9) <=> ($urut[$b['tingkat']] ?? 9)) ?: strnatcasecmp($a['nama_kelas'], $b['nama_kelas']));
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['tingkat']][] = ['id' => (int) $r['id'], 'nama' => (string) $r['nama_kelas']];
        }

        return $out;
    }

    /**
     * Siswa aktif satu kelas + status lapor. HANYA status (tanpa data perusahaan) — daftar ini bisa dilihat siapa saja.
     * Status: boleh | sudah_lapor | dikembalikan | selesai | belum_disetujui | ajukan_lagi (ditolak / dikeluarkan dari kelompok,
     * belum punya ajuan baru) | belum_ajuan.
     *
     * @return list<array{id: int, nama: string, status: string}>
     */
    public function siswaKelas(int $kelasId): array
    {
        $tolak = "EXISTS (SELECT 1 FROM pkl_anggota ar JOIN pkl_pengajuan pr ON pr.id = ar.pengajuan_id WHERE ar.siswa_id = s.id AND pr.status = 'ditolak')"
            . (PklKeluar::tabelAda() ? ' OR EXISTS (SELECT 1 FROM pkl_anggota_keluar kr WHERE kr.siswa_id = s.id)' : '');
        $rows = $this->db->table('siswa s')
            ->select('s.id, s.nama, p.id AS ajuan_id, p.status AS ajuan_status, l.status AS lapor_status')
            ->select("(SELECT COUNT(*) FROM surat_sekolah ss WHERE ss.pengajuan_id = p.id AND ss.jenis = 'balasan_pkl' AND ss.status != 'dibatalkan') AS balasan", false)
            ->select('(' . $tolak . ') AS pernah_tolak', false)
            ->join('pkl_anggota a', 'a.siswa_aktif = s.id', 'left')
            ->join('pkl_pengajuan p', 'p.id = a.pengajuan_id', 'left')
            ->join('pkl_lapor l', 'l.pengajuan_id = p.id', 'left')
            ->where('s.kelas_id', $kelasId)->where('s.status', 'aktif')->where('s.deleted_at', null)
            ->orderBy('s.nama', 'ASC')
            ->get()->getResultArray();

        return array_map(static function (array $r): array {
            $st = match (true) {
                $r['ajuan_id'] === null && (int) $r['pernah_tolak'] > 0    => 'ajukan_lagi',
                $r['ajuan_id'] === null                                    => 'belum_ajuan',
                $r['ajuan_status'] !== 'disetujui'                         => 'belum_disetujui',
                (int) $r['balasan'] > 0 || $r['lapor_status'] === 'diproses' => 'selesai',
                $r['lapor_status'] === 'baru'                              => 'sudah_lapor',
                $r['lapor_status'] === 'dikembalikan'                      => 'dikembalikan',
                default                                                    => 'boleh',
            };

            return ['id' => (int) $r['id'], 'nama' => (string) $r['nama'], 'status' => $st];
        }, $rows);
    }

    /**
     * Bukti diri dengan nomor HP → ringkasan ajuan + laporan yang ada; membuka izin kirim (sesi, 1 jam) untuk ajuan itu.
     *
     * @return array{ok: bool, http: int, pesan: string, data?: array<string, mixed>}
     */
    public function cek(int $siswaId, string $hpIn, string $ip): array
    {
        $s = $siswaId > 0 ? $this->db->table('siswa')->select('id, nama, no_hp')->where('id', $siswaId)->where('status', 'aktif')->where('deleted_at', null)->get()->getRowArray() : null;
        if ($s === null) {
            return $this->gagal(404, 'Data siswa tidak ditemukan. Pilih ulang kelas dan namamu.');
        }
        $milik = (new PklPengajuanModel())->aktifMilik($siswaId);
        if ($milik === null) {
            return $this->gagal(409, 'Kamu tidak punya ajuan PKL yang aktif (belum mengajukan, atau ajuan/kelompokmu sebelumnya ditolak). Ajukan tempat PKL lewat form pengajuan PKL; setelah disetujui sekolah dan diterima perusahaan, baru lapor di sini.');
        }
        if ($milik['status'] !== 'disetujui') {
            return $this->gagal(409, 'Ajuan PKL-mu (' . PklPengajuanModel::kode((int) $milik['id']) . ') belum disetujui sekolah' . ($milik['status'] === 'perbaikan' ? ' — ajuan itu sedang dikembalikan untuk diperbaiki' : '') . '. Lapor diterima baru bisa setelah ajuan disetujui.');
        }
        if ($hpIn === '' || ! IsianBantu::teleponMurni($hpIn) || ! IsianBantu::hpSah(IsianBantu::telepon($hpIn))) {
            return $this->gagal(422, 'Isi nomor HP-mu dengan benar (contoh: 081234567890).');
        }

        $throttle = new LoginThrottle();
        $kunci    = 'pkllapor:' . $siswaId;
        $sisa     = $throttle->retryAfter($kunci, $ip);
        if ($sisa > 0) {
            return $this->gagal(429, 'Terlalu banyak percobaan. Coba lagi dalam ' . (int) ceil($sisa / 60) . ' menit.');
        }
        $hp    = IsianBantu::telepon($hpIn);
        $cocok = $hp !== '' && ($hp === IsianBantu::telepon((string) ($milik['hp_anggota'] ?? '')) || $hp === IsianBantu::telepon((string) ($s['no_hp'] ?? '')));
        if (! $cocok) {
            $throttle->hit($kunci, $ip, 'pkl');

            return $this->gagal(422, 'Nomor HP tidak cocok dengan yang tercatat di ajuan PKL-mu atau di data sekolah. Pakai nomor yang dulu kamu isi saat mengajukan.');
        }
        $throttle->clear($kunci, $ip);

        $ajuanId = (int) $milik['id'];
        $izin    = (array) session(self::SESI);
        $izin[$ajuanId] = ['siswa' => $siswaId, 'nama' => (string) $s['nama'], 'hp' => $hp, 't' => time()];
        session()->set(self::SESI, $izin);

        return ['ok' => true, 'http' => 200, 'pesan' => 'Nomor HP cocok.', 'data' => $this->ringkasan($ajuanId)];
    }

    /**
     * Ringkasan untuk siswa yang sudah membuktikan dirinya: ajuan (kode, perusahaan, alamat, periode tercatat), anggota (nama + kelas),
     * laporan yang ada, dan apakah Surat Balasan sudah dibuat.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(int $ajuanId): array
    {
        $a = (new SuratPkl($this->db))->ajuan([$ajuanId])[0] ?? null;
        $l = $this->untukAjuan($ajuanId);

        return [
            'ajuan_id'   => $ajuanId,
            'kode'       => PklPengajuanModel::kode($ajuanId),
            'perusahaan' => (string) ($a['perusahaan'] ?? ''),
            'alamat'     => (string) ($a['alamat'] ?? ''),
            'mulai'      => $a['mulai'] ?? null,
            'selesai'    => $a['selesai'] ?? null,
            'anggota'    => array_map(static fn (array $x): array => ['siswa_id' => (int) $x['siswa_id'], 'nama' => $x['nama'], 'kelas' => $x['kelas']], $a['siswa'] ?? []),
            'balasan'    => ($a['balasan'] ?? null) !== null,
            'hasil_ada'  => self::kolomHasil(),
            'lapor'      => $l === null ? null : [
                'status' => $l['status'], 'status_teks' => self::TAMPIL[$l['status']][0] ?? $l['status'], 'alasan' => $l['alasan'],
                'mulai' => $l['tanggal_mulai'], 'selesai' => $l['tanggal_selesai'], 'alamat' => $l['alamat'], 'catatan' => $l['catatan'],
                'pelapor' => $l['pelapor_nama'], 'waktu' => $l['updated_at'],
                'hasil' => (string) ($l['hasil'] ?? 'semua'), 'diterima' => self::idDiterima($l),
            ],
        ];
    }

    /**
     * Siswa mengirim / memperbarui laporan. $in: ajuan_id, tanggal_mulai, tanggal_selesai, alamat (opsional), catatan (opsional).
     *
     * @param array<string, mixed> $in
     *
     * @return array{ok: bool, http: int, pesan: string, galat?: array<string, string>, data?: array<string, mixed>}
     */
    public function kirim(array $in, string $ip): array
    {
        $ajuanId = (int) ($in['ajuan_id'] ?? 0);
        $izin    = (array) session(self::SESI);
        $milik   = $izin[$ajuanId] ?? null;
        if (! is_array($milik) || (time() - (int) ($milik['t'] ?? 0)) > self::TTL) {
            return $this->gagal(403, 'Sesi sudah habis. Pilih namamu dan ketik nomor HP lagi.');
        }
        $a = (new SuratPkl($this->db))->ajuan([$ajuanId])[0] ?? null;
        if ($a === null) {
            return $this->gagal(409, 'Ajuan PKL ini tidak lagi berstatus disetujui. Hubungi operator sekolah.');
        }
        if ($a['balasan'] !== null) {
            return $this->gagal(409, 'Surat Balasan untuk kelompokmu sudah dibuat sekolah. Tidak perlu lapor lagi; bila ada yang salah, hubungi operator sekolah.');
        }
        $lama = $this->untukAjuan($ajuanId);
        if ($lama !== null && $lama['status'] === 'diproses') {
            return $this->gagal(409, 'Laporanmu sudah diproses sekolah. Bila ada yang salah, hubungi operator sekolah.');
        }

        // Validasi (pesan per kolom supaya tampil di bawah kolomnya).
        $galat = [];
        // Hasil dari perusahaan: semua diterima (bawaan, juga bagi server yang belum migrasi) / sebagian / semua ditolak.
        $hasil = self::kolomHasil() ? trim((string) ($in['hasil'] ?? 'semua')) : 'semua';
        if ($hasil === '') {
            $hasil = 'semua';
        }
        $idAnggota = array_map('intval', array_column($a['siswa'], 'siswa_id'));
        $diterima  = [];
        if (! isset(self::HASIL[$hasil])) {
            $galat['hasil'] = 'Pilih hasil dari perusahaan: semua diterima, sebagian diterima, atau semua ditolak.';
        } elseif ($hasil === 'sebagian') {
            $pilih = array_values(array_unique(array_map('intval', array_filter((array) ($in['diterima'] ?? []), 'is_scalar'))));
            if (count($idAnggota) < 2) {
                $galat['hasil'] = 'Kelompokmu hanya 1 siswa — pilih "Semua diterima" atau "Semua ditolak".';
            } elseif ($pilih === []) {
                $galat['diterima'] = 'Centang siswa yang DITERIMA perusahaan (minimal satu).';
            } elseif (array_diff($pilih, $idAnggota) !== []) {
                $galat['diterima'] = 'Ada siswa pilihan yang bukan anggota kelompokmu. Muat ulang halaman lalu pilih lagi.';
            } elseif (count($pilih) >= count($idAnggota)) {
                $galat['diterima'] = 'Semua siswa tercentang — kalau semua diterima, pilih "Semua diterima".';
            } else {
                $diterima = array_values(array_intersect($idAnggota, $pilih)); // urutan = urutan anggota
            }
        }
        $thn   = (int) date('Y');
        $mulai = null;
        $akhir = null;
        if ($hasil !== 'ditolak') { // laporan "semua ditolak" tidak punya periode
            $mulai = IsianBantu::tanggal(trim((string) ($in['tanggal_mulai'] ?? '')), $thn - 1, $thn + 2);
            $akhir = IsianBantu::tanggal(trim((string) ($in['tanggal_selesai'] ?? '')), $thn - 1, $thn + 3);
            if ($mulai === null) {
                $galat['tanggal_mulai'] = 'Isi tanggal mulai PKL (sesuai yang disepakati dengan perusahaan).';
            }
            if ($akhir === null) {
                $galat['tanggal_selesai'] = 'Isi tanggal selesai PKL.';
            }
            if ($mulai !== null && $akhir !== null) {
                if ($akhir <= $mulai) {
                    $galat['tanggal_selesai'] = 'Tanggal selesai harus setelah tanggal mulai.';
                } elseif (IsianBantu::hariInklusif($mulai, $akhir) > SuratPkl::MAKS_HARI_PKL) {
                    $galat['tanggal_selesai'] = 'Periode PKL lebih dari ' . SuratPkl::MAKS_HARI_PKL . ' hari — periksa lagi bulan/tahunnya.';
                }
            }
        }
        $alamat  = $hasil === 'ditolak' ? '' : IsianBantu::rapikan((string) ($in['alamat'] ?? ''));
        $catatan = IsianBantu::rapikan((string) ($in['catatan'] ?? ''));
        if (mb_strlen($alamat) > self::MAKS_ALAMAT) {
            $galat['alamat'] = 'Alamat terlalu panjang (maksimal ' . self::MAKS_ALAMAT . ' huruf).';
        }
        if (mb_strlen($catatan) > self::MAKS_CATATAN) {
            $galat['catatan'] = 'Catatan terlalu panjang (maksimal ' . self::MAKS_CATATAN . ' huruf).';
        }
        if ($galat !== []) {
            return ['ok' => false, 'http' => 422, 'pesan' => 'Periksa lagi isianmu.', 'galat' => $galat];
        }
        // Alamat sama dengan yang tercatat di ajuan → tidak disimpan sebagai perubahan.
        if ($alamat !== '' && mb_strtolower($alamat) === mb_strtolower((string) $a['alamat'])) {
            $alamat = '';
        }

        $now  = date('Y-m-d H:i:s');
        $data = [
            'siswa_id' => (int) $milik['siswa'], 'pelapor_nama' => mb_substr((string) $milik['nama'], 0, 150), 'hp' => mb_substr((string) $milik['hp'], 0, 20),
            'tanggal_mulai' => $mulai, 'tanggal_selesai' => $akhir, 'alamat' => $alamat !== '' ? $alamat : null, 'catatan' => $catatan !== '' ? $catatan : null,
            'status' => 'baru', 'alasan' => null, 'surat_id' => null, 'diproses_oleh' => null, 'diproses_peran' => null, 'diproses_admin_id' => null, 'diproses_at' => null,
            'ip_address' => $ip, 'updated_at' => $now,
        ];
        if (self::kolomHasil()) {
            $data += ['hasil' => $hasil, 'diterima' => $hasil === 'sebagian' ? json_encode($diterima) : null];
        }
        $idLapor = $lama !== null ? (int) $lama['id'] : null;
        try {
            if ($lama === null) {
                $ok      = $this->db->table('pkl_lapor')->insert($data + ['pengajuan_id' => $ajuanId, 'kirim_ke' => 1, 'created_at' => $now]);
                $idLapor = $ok ? (int) $this->db->insertID() : null;
            } else {
                $ok = $this->db->table('pkl_lapor')->where('id', (int) $lama['id'])->where('status !=', 'diproses')
                    ->set($data)->set('kirim_ke', 'kirim_ke + 1', false)->update();
                $ok = $ok && $this->db->affectedRows() > 0; // 0 = baru saja diproses staf
            }
        } catch (\Throwable $e) {
            // Dua anggota mengirim bersamaan → UNIQUE pengajuan_id: laporan pertama sudah tersimpan.
            log_message('error', '[PklLapor] kirim gagal: ' . $e->getMessage());
            $ok = false;
        }
        if (! $ok) {
            return $this->gagal(409, 'Laporan gagal disimpan (mungkin temanmu baru saja mengirim). Muat ulang halaman, lalu periksa statusnya.');
        }

        $periode = SuratPkl::periodeTeks((string) $mulai, (string) $akhir);
        $rinci   = self::rincian(['hasil' => $hasil, 'diterima' => json_encode($diterima)], $a['siswa']);
        $namaT   = array_column($rinci['diterima'], 'nama');
        $namaX   = array_column($rinci['ditolak'], 'nama');
        $ringkas = match ($hasil) {
            'ditolak'  => 'perusahaan MENOLAK semua siswa' . ($catatan !== '' ? ' (' . $catatan . ')' : ''),
            'sebagian' => count($namaT) . ' dari ' . count($a['siswa']) . ' diterima (' . implode(', ', $namaT) . '); ditolak perusahaan: ' . implode(', ', $namaX) . '; periode ' . $periode,
            default    => 'periode ' . $periode,
        };
        $konteks = ['oleh' => 'Siswa: ' . $milik['nama'], 'admin_id' => null, 'peran' => null, 'ip' => $ip];
        (new PklAjuan($this->db))->catat($ajuanId, 'lapor_diterima', $konteks, ($lama === null ? 'Lapor hasil perusahaan' : 'Lapor hasil perusahaan (kirim ulang)') . ': ' . $ringkas . ($alamat !== '' ? '; alamat diperbarui' : ''));
        (new AuditModel())->record($lama === null ? 'create' : 'update', 'pkl_lapor', $idLapor, mb_substr(PklPengajuanModel::kode($ajuanId) . ': siswa ' . $milik['nama'] . ' melapor (' . $a['perusahaan'] . '): ' . $ringkas, 0, 255));

        return ['ok' => true, 'http' => 200, 'pesan' => 'Laporan terkirim.', 'data' => [
            'kode' => PklPengajuanModel::kode($ajuanId), 'perusahaan' => $a['perusahaan'], 'periode' => $periode, 'pelapor' => $milik['nama'],
            'anggota' => array_column($a['siswa'], 'nama'), 'ulang' => $lama !== null,
            'hasil' => $hasil, 'hasil_teks' => self::HASIL[$hasil], 'diterima' => $namaT, 'ditolak' => $namaX,
        ]];
    }

    // =================================================================
    // Sisi staf (kotak laporan, detail ajuan, API)
    // =================================================================

    /** Laporan satu ajuan (baris pkl_lapor) atau null. */
    public function untukAjuan(int $ajuanId): ?array
    {
        if (! self::tabelAda()) {
            return null;
        }

        return $this->db->table('pkl_lapor')->where('pengajuan_id', $ajuanId)->get()->getRowArray() ?: null;
    }

    /**
     * Laporan ajuan ini yang MENUNGGU dan berhasil "sebagian diterima" / "semua ditolak" — Surat Balasan untuk ajuan itu hanya boleh
     * dibuat lewat kotak laporan (siswa yang ditolak dikeluarkan dulu). null bila tidak ada.
     */
    public function menungguKhusus(int $ajuanId): ?array
    {
        $l = self::kolomHasil() ? $this->untukAjuan($ajuanId) : null;

        return $l !== null && $l['status'] === 'baru' && (string) ($l['hasil'] ?? 'semua') !== 'semua' ? $l : null;
    }

    /** Pesan baku bila Surat Balasan dibuat di luar kotak laporan padahal laporannya sebagian/ditolak. */
    public static function pesanKhusus(array $l): string
    {
        return 'Ajuan ' . PklPengajuanModel::kode((int) $l['pengajuan_id']) . ' punya laporan siswa "' . (self::HASIL[(string) $l['hasil']] ?? $l['hasil'])
            . '" yang belum diproses. Proses lewat kotak Lapor Diterima PKL: siswa yang ditolak perusahaan dikeluarkan dulu, lalu Surat Balasan hanya memuat siswa yang diterima.';
    }

    public function muat(int $id): ?array
    {
        if (! self::tabelAda()) {
            return null;
        }

        return $this->db->table('pkl_lapor')->where('id', $id)->get()->getRowArray() ?: null;
    }

    /** @return array<string, int> status => jumlah */
    public function hitung(): array
    {
        $out = array_fill_keys(self::STATUS, 0);
        if (! self::tabelAda()) {
            return $out;
        }
        foreach ($this->db->table('pkl_lapor')->select('status, COUNT(*) n')->groupBy('status')->get()->getResultArray() as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }

        return $out;
    }

    /**
     * Daftar laporan untuk kotak staf (terbaru dulu; 'baru' = yang lama menunggu di atas), lengkap dengan ajuan, anggota, Balasan,
     * dan peringatan periode terhadap Pengaturan PKL (tidak memblokir).
     *
     * @return list<array<string, mixed>>
     */
    public function daftar(string $status = 'baru', int $batas = 200, ?array $p = null): array
    {
        if (! self::tabelAda() || ! in_array($status, self::STATUS, true)) {
            return [];
        }
        $p  ??= (new PklPengaturanModel())->ambil();
        $rows = $this->db->table('pkl_lapor')->where('status', $status)->orderBy('updated_at', $status === 'baru' ? 'ASC' : 'DESC')->limit($batas)->get()->getResultArray();
        if ($rows === []) {
            return [];
        }
        $peta = [];
        foreach ((new SuratPkl($this->db))->ajuan(array_map('intval', array_column($rows, 'pengajuan_id'))) as $a) {
            $peta[$a['id']] = $a;
        }
        $out = [];
        foreach ($rows as $r) {
            $a     = $peta[(int) $r['pengajuan_id']] ?? null;
            $rinci = self::rincian($r, $a['siswa'] ?? []);
            $out[] = $r + [
                'kode'       => PklPengajuanModel::kode((int) $r['pengajuan_id']),
                'perusahaan' => (string) ($a['perusahaan'] ?? '(ajuan tidak lagi disetujui)'),
                'alamat_ajuan' => (string) ($a['alamat'] ?? ''),
                'anggota'    => $a['siswa'] ?? [],
                // Hasil perusahaan (revisi 2026-10-20): siapa diterima & siapa ditolak menurut laporan, terhadap anggota aktif sekarang.
                'hasil'          => $rinci['hasil'],
                'hasil_teks'     => self::HASIL[$rinci['hasil']],
                'siswa_diterima' => $rinci['diterima'],
                'siswa_ditolak'  => $rinci['ditolak'],
                'balasan'    => $a['balasan'] ?? null,
                'ajuan_sah'  => $a !== null,
                'periode'    => SuratPkl::periodeTeks((string) $r['tanggal_mulai'], (string) $r['tanggal_selesai']),
                'peringatan' => SuratPkl::peringatanPeriode((string) $r['tanggal_mulai'], (string) $r['tanggal_selesai'], $p),
            ];
        }

        return $out;
    }

    /** Aksi yang boleh dilakukan peran ini atas kotak laporan: setujui (buat + ACC sekali klik), buat (masuk antrean ACC), kembalikan. */
    public static function hak(?string $peran, ?array $p = null): array
    {
        $p    ??= (new PklPengaturanModel())->ambil();
        $perlu = SuratJenis::perluAcc(SuratJenis::BALASAN, $p);
        $buat  = PklHak::bolehSurat($peran, 'buat', SuratJenis::BALASAN);
        $acc   = PklHak::bolehSurat($peran, 'acc', SuratJenis::BALASAN);

        return [
            'lihat'      => $buat || $acc,
            // Jenis wajib ACC: penyetuju boleh langsung membuat + menyetujui. Tanpa ACC: membuat = langsung siap unduh (hak Buat).
            'setujui'    => $perlu ? $acc : $buat,
            'buat'       => $perlu && $buat,
            'kembalikan' => $buat || $acc,
            'perlu_acc'  => $perlu,
        ];
    }

    /**
     * Proses laporan menjadi Surat Balasan. $setujui = true: "Setujui & buat" (surat langsung disetujui atas nama pemroses bila jenis
     * wajib ACC); false: "Buat" (surat masuk antrean ACC). Isian surat: tanggal hari ini, sapaan bawaan, periode & alamat dari laporan.
     *
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip', 'saluran']
     *
     * @return array{ok: bool, http: int, pesan: string, surat_id?: int, status_surat?: string}
     */
    public function proses(int $id, bool $setujui, array $konteks, ?array $p = null): array
    {
        $p   ??= (new PklPengaturanModel())->ambil();
        $peran = (string) ($konteks['peran'] ?? '');
        $hak   = self::hak($peran, $p);
        $tolakHak = fn (): array => $this->gagal(403, $setujui
            ? 'Akun ' . HakAkses::label($peran) . ' tidak punya hak menyetujui Surat Balasan (sekarang: ' . PklHak::teksPemegang(PklHak::kunciSurat($hak['perlu_acc'] ? 'acc' : 'buat', SuratJenis::BALASAN)) . '). Admin bisa mengaturnya di Sistem → Hak Akses.'
            : 'Akun ' . HakAkses::label($peran) . ' tidak punya hak membuat Surat Balasan. Admin bisa mengaturnya di Sistem → Hak Akses.');
        if (! $hak['lihat']) {
            return $tolakHak();
        }
        $l = $this->muat($id);
        if ($l === null) {
            return $this->gagal(404, 'Laporan tidak ditemukan (mungkin ajuannya sudah dihapus).');
        }
        $hasil = (string) ($l['hasil'] ?? 'semua');
        // Laporan "semua ditolak perusahaan" tidak membuat surat: cukup hak memproses kotak laporan (Buat ATAU ACC Surat Balasan).
        if ($hasil !== 'ditolak' && ! ($setujui ? $hak['setujui'] : $hak['buat'])) {
            return $tolakHak();
        }
        if ($l['status'] !== 'baru') {
            return $this->gagal(409, 'Laporan ini ' . self::kalimatStatus((string) $l['status']) . '. Muat ulang halaman.');
        }
        $ajuanId = (int) $l['pengajuan_id'];
        $kode    = PklPengajuanModel::kode($ajuanId);

        // Klaim atomik: hanya satu pemroses yang lolos.
        $now = date('Y-m-d H:i:s');
        $this->db->table('pkl_lapor')->where('id', $id)->where('status', 'baru')->update([
            'status' => 'diproses', 'diproses_oleh' => mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150), 'diproses_peran' => $peran !== '' ? mb_substr($peran, 0, 20) : null,
            'diproses_admin_id' => ($konteks['admin_id'] ?? null) ?: null, 'diproses_at' => $now, 'updated_at' => $now,
        ]);
        if ($this->db->affectedRows() < 1) {
            return $this->gagal(409, 'Laporan ini baru saja diproses staf lain. Muat ulang halaman.');
        }
        $batalKlaim = function (int $idLapor): void {
            $this->db->table('pkl_lapor')->where('id', $idLapor)->update(['status' => 'baru', 'diproses_oleh' => null, 'diproses_peran' => null, 'diproses_admin_id' => null, 'diproses_at' => null]);
        };
        $catatanSiswa = trim((string) ($l['catatan'] ?? ''));
        $dariLaporan  = 'Laporan siswa ' . ($l['pelapor_nama'] ?: '-') . ' (' . date('d-m-Y', strtotime((string) $l['updated_at'])) . ')' . ($catatanSiswa !== '' ? ': ' . $catatanSiswa : '');

        // ---- Semua ditolak perusahaan → ajuan "Ditolak perusahaan", tanpa surat ----
        if ($hasil === 'ditolak') {
            // Ajuan sudah ditolak lebih dulu (mis. Hubin menekan "Ditolak perusahaan" di halaman ajuan) → laporan cukup ditandai selesai.
            if ((string) ($this->db->table('pkl_pengajuan')->select('status')->where('id', $ajuanId)->get()->getRowArray()['status'] ?? '') === 'ditolak') {
                (new PklAjuan($this->db))->catat($ajuanId, 'lapor_proses', $konteks, 'Laporan "semua ditolak perusahaan" ditandai selesai: ajuan sudah berstatus ditolak');

                return ['ok' => true, 'http' => 200, 'pesan' => 'Ajuan ' . $kode . ' sudah berstatus ditolak, jadi laporan ditandai selesai.', 'status_surat' => ''];
            }
            $r = (new PklKeluar($this->db))->ditolakPerusahaan($ajuanId, ['catatan' => 'Perusahaan tidak menerima siswa PKL. ' . $dariLaporan], $konteks, false);
            if (! $r['ok']) {
                $batalKlaim($id);

                return $this->gagal($r['http'] === 409 ? 409 : $r['http'], $r['pesan'] . ($r['http'] === 409 ? ' Kembalikan laporan ke siswa bila sudah tidak sesuai.' : ''));
            }
            (new PklAjuan($this->db))->catat($ajuanId, 'lapor_proses', $konteks, 'Laporan "semua ditolak perusahaan" dikonfirmasi: ajuan ditandai Ditolak perusahaan');
            (new AuditModel())->record('update', 'pkl_lapor', $id, mb_substr('Laporan ' . $kode . ' (semua ditolak perusahaan) dikonfirmasi oleh ' . ($konteks['oleh'] ?? '?') . (($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));

            return ['ok' => true, 'http' => 200, 'pesan' => 'Laporan ' . $kode . ' dikonfirmasi: ajuan ditandai DITOLAK PERUSAHAAN dan seluruh siswanya bebas mengajukan tempat PKL lain.', 'status_surat' => ''];
        }

        // ---- Sebagian diterima → siswa yang ditolak dikeluarkan dulu, surat hanya untuk yang diterima ----
        $keluarTeks = '';
        if ($hasil === 'sebagian') {
            $a = (new SuratPkl($this->db))->ajuan([$ajuanId])[0] ?? null;
            if ($a === null) {
                $batalKlaim($id);

                return $this->gagal(409, 'Ajuan ' . $kode . ' tidak lagi berstatus disetujui, jadi laporan tidak bisa diproses. Kembalikan laporan ke siswa.');
            }
            $rinci = self::rincian($l, $a['siswa']);
            if ($rinci['diterima'] === []) {
                $batalKlaim($id);

                return $this->gagal(409, 'Siswa yang dilaporkan diterima sudah tidak tercatat di ajuan ' . $kode . '. Kembalikan laporan ke siswa agar diperiksa ulang.');
            }
            if ($rinci['ditolak'] !== []) {
                if ($a['balasan'] !== null) {
                    $batalKlaim($id);

                    return $this->gagal(409, 'Ajuan ' . $kode . ' sudah punya Surat Balasan ' . SuratSekolahModel::kode((int) $a['balasan']['id']) . ' yang memuat SEMUA siswa. Batalkan surat itu dulu, lalu proses laporan ini supaya surat baru hanya memuat siswa yang diterima.');
                }
                $k = (new PklKeluar($this->db))->keluarkan($ajuanId, [
                    'siswa_ids' => array_column($rinci['ditolak'], 'siswa_id'), 'jenis' => 'ditolak_perusahaan',
                    'catatan'   => 'Tidak diterima perusahaan. ' . $dariLaporan,
                ], $konteks + ['sumber' => 'lapor'], $p, false);
                if (! $k['ok']) {
                    $batalKlaim($id);

                    return $this->gagal($k['http'], 'Siswa yang ditolak perusahaan gagal dikeluarkan: ' . $k['pesan']);
                }
                $keluarTeks = implode(', ', array_column($rinci['ditolak'], 'nama')) . ' dikeluarkan dari ajuan (ditolak perusahaan) dan bebas mengajukan tempat lain. ';
            }
        }

        $isian =['tanggal_surat' => date('Y-m-d'), 'sapaan' => SuratPkl::SAPAAN_BAWAAN, 'periode_mulai' => (string) $l['tanggal_mulai'], 'periode_selesai' => (string) $l['tanggal_selesai']];
        if ((string) ($l['alamat'] ?? '') !== '') {
            $isian['alamat'] = (string) $l['alamat'];
        }
        $h = SuratPkl::periksa(SuratJenis::BALASAN, $isian);
        if (! $h['ok']) {
            $batalKlaim($id);

            return $this->gagal(422, 'Isian laporan tidak bisa dipakai untuk surat: ' . implode(' ', $h['galat']) . ' Kembalikan laporan ke siswa dengan alasannya.');
        }
        $svc  = new SuratPkl($this->db);
        $buat = $svc->buat(SuratJenis::BALASAN, $h, [$ajuanId], [], $konteks, $p);

        if (! $buat['ok']) {
            // Surat Balasan ajuan ini SUDAH ada (dibuat manual): laporan ditandai selesai, tertaut ke surat itu.
            $ada = (new SuratSekolah($this->db))->adaKunci(SuratPkl::kunci(SuratJenis::BALASAN, $ajuanId, []));
            if ($ada !== null) {
                $this->db->table('pkl_lapor')->where('id', $id)->update(['surat_id' => (int) $ada['id']]);
                (new PklAjuan($this->db))->catat($ajuanId, 'lapor_proses', $konteks, 'Laporan diterima ditandai selesai: Surat Balasan ' . SuratSekolahModel::kode((int) $ada['id']) . ' sudah ada sebelumnya');

                return ['ok' => true, 'http' => 200, 'pesan' => 'Surat Balasan untuk ' . $kode . ' sudah ada (' . SuratSekolahModel::kode((int) $ada['id']) . '), jadi laporan ditandai selesai. Periksa periode di surat itu: laporan siswa ' . SuratPkl::periodeTeks((string) $l['tanggal_mulai'], (string) $l['tanggal_selesai']) . '.', 'surat_id' => (int) $ada['id'], 'status_surat' => (string) $ada['status']];
            }
            $batalKlaim($id);

            return $this->gagal(409, $keluarTeks . 'Surat Balasan gagal dibuat: ' . implode(' ', array_merge($buat['sama'], $buat['gagal'])));
        }

        $suratId = (int) $buat['dibuat'][0];
        $this->db->table('pkl_lapor')->where('id', $id)->update(['surat_id' => $suratId]);
        $akhir = 'menunggu ACC (' . PklHak::teksPemegang(PklHak::kunciSurat('acc', SuratJenis::BALASAN)) . ')';
        if (! $hak['perlu_acc']) {
            $akhir = 'langsung siap diunduh (jenis ini tanpa ACC)';
        } elseif ($setujui) {
            $acc   = (new SuratKeputusan(null, $this->db))->putuskan($suratId, 'acc', [], $konteks, $p);
            $akhir = $acc['ok'] ? 'langsung DISETUJUI atas nama ' . ($konteks['oleh'] ?? 'Anda') . ' dan siap diunduh' : 'dibuat tetapi BELUM di-ACC: ' . $acc['pesan'];
        }
        $status = (string) ($this->db->table('surat_sekolah')->select('status')->where('id', $suratId)->get()->getRowArray()['status'] ?? '');
        (new PklAjuan($this->db))->catat($ajuanId, 'lapor_proses', $konteks, mb_substr('Laporan diterima diproses: Surat Balasan ' . SuratSekolahModel::kode($suratId) . ' ' . ($status === 'disetujui' ? 'disetujui' : 'menunggu ACC'), 0, 255));
        (new AuditModel())->record('update', 'pkl_lapor', $id, mb_substr('Laporan diterima ' . $kode . ' diproses oleh ' . ($konteks['oleh'] ?? '?') . ' → Surat Balasan ' . SuratSekolahModel::kode($suratId) . ' (' . $status . ')' . (($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));

        return ['ok' => true, 'http' => 200, 'pesan' => $keluarTeks . 'Surat Balasan ' . SuratSekolahModel::kode($suratId) . ' untuk ' . $kode . ' ' . $akhir . '.', 'surat_id' => $suratId, 'status_surat' => $status];
    }

    /**
     * Surat Balasan ajuan $ajuanId baru saja dibuat lewat jalur selain kotak laporan (formulir "Periksa & ubah dulu", formulir
     * biasa, pilih banyak, API): laporan ajuan itu yang MASIH MENUNGGU ditandai diproses & ditautkan ke surat tersebut.
     * Dipanggil SuratPkl::buat. Laporan yang sedang diproses kotak laporan sudah berstatus diproses (diklaim), jadi tidak tersentuh.
     *
     * @param array<string, mixed> $konteks
     */
    public function tautkanAjuan(int $ajuanId, int $suratId, array $konteks): bool
    {
        $l = $this->untukAjuan($ajuanId);

        // Hanya laporan "semua diterima": laporan sebagian/ditolak wajib lewat kotak laporan (siswa yang ditolak dikeluarkan dulu).
        return $l !== null && $l['status'] === 'baru' && (string) ($l['hasil'] ?? 'semua') === 'semua' && $this->tautkanSurat((int) $l['id'], $suratId, $konteks);
    }

    /**
     * Tandai laporan $id diproses & tautkan ke surat $suratId. Hanya laporan berstatus baru (atomik: yang sudah
     * diproses/dikembalikan dibiarkan).
     *
     * @param array<string, mixed> $konteks
     */
    public function tautkanSurat(int $id, int $suratId, array $konteks): bool
    {
        $l = $this->muat($id);
        if ($l === null || $l['status'] !== 'baru') {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('pkl_lapor')->where('id', $id)->where('status', 'baru')->update([
            'status' => 'diproses', 'surat_id' => $suratId, 'diproses_oleh' => mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150),
            'diproses_peran' => ($konteks['peran'] ?? '') !== '' ? mb_substr((string) $konteks['peran'], 0, 20) : null,
            'diproses_admin_id' => ($konteks['admin_id'] ?? null) ?: null, 'diproses_at' => $now, 'updated_at' => $now,
        ]);
        if ($this->db->affectedRows() < 1) {
            return false;
        }
        (new PklAjuan($this->db))->catat((int) $l['pengajuan_id'], 'lapor_proses', $konteks, 'Laporan diterima ditandai selesai: Surat Balasan ' . SuratSekolahModel::kode($suratId) . ' dibuat');

        return true;
    }

    /**
     * Kembalikan laporan ke siswa dengan alasan (siswa melihatnya saat membuka form lagi, lalu bisa mengirim ulang).
     *
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, http: int, pesan: string}
     */
    public function kembalikan(int $id, string $alasan, array $konteks): array
    {
        $peran = (string) ($konteks['peran'] ?? '');
        if (! self::hak($peran)['kembalikan']) {
            return $this->gagal(403, 'Akun ' . HakAkses::label($peran) . ' tidak punya hak memproses laporan Surat Balasan. Admin bisa mengaturnya di Sistem → Hak Akses.');
        }
        $alasan = IsianBantu::rapikan($alasan);
        if (mb_strlen($alasan) < 5) {
            return $this->gagal(422, 'Alasan wajib diisi (minimal 5 huruf) — siswa akan membacanya.');
        }
        if (mb_strlen($alasan) > PklAjuan::maksCatatan()) {
            return $this->gagal(422, 'Alasan terlalu panjang (maksimal ' . PklAjuan::maksCatatan() . ' huruf).');
        }
        $l = $this->muat($id);
        if ($l === null) {
            return $this->gagal(404, 'Laporan tidak ditemukan.');
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('pkl_lapor')->where('id', $id)->where('status', 'baru')->update([
            'status' => 'dikembalikan', 'alasan' => $alasan, 'diproses_oleh' => mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150),
            'diproses_peran' => $peran !== '' ? mb_substr($peran, 0, 20) : null, 'diproses_admin_id' => ($konteks['admin_id'] ?? null) ?: null, 'diproses_at' => $now, 'updated_at' => $now,
        ]);
        if ($this->db->affectedRows() < 1) {
            return $this->gagal(409, 'Laporan ini ' . self::kalimatStatus((string) $l['status']) . '. Muat ulang halaman.');
        }
        $kode = PklPengajuanModel::kode((int) $l['pengajuan_id']);
        (new PklAjuan($this->db))->catat((int) $l['pengajuan_id'], 'lapor_kembali', $konteks, 'Laporan diterima dikembalikan: ' . $alasan);
        (new AuditModel())->record('update', 'pkl_lapor', $id, mb_substr('Laporan diterima ' . $kode . ' dikembalikan oleh ' . ($konteks['oleh'] ?? '?') . ': ' . $alasan, 0, 255));

        return ['ok' => true, 'http' => 200, 'pesan' => 'Laporan ' . $kode . ' dikembalikan ke siswa. Siswa bisa memperbaiki dan mengirim ulang lewat form lapor.'];
    }

    /** @return array{ok: false, http: int, pesan: string} */
    private function gagal(int $http, string $pesan): array
    {
        return ['ok' => false, 'http' => $http, 'pesan' => $pesan];
    }
}
