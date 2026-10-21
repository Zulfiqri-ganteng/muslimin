<?php

namespace App\Libraries;

use App\Models\PklPengajuanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Surat Balasan PKL dan Surat Penarikan Izin PKL — dua surat bernomor yang ditujukan ke SATU tempat PKL, dibuat dari ajuan
 * yang sudah DISETUJUI (rancangan: docs/DESAIN-SURAT-SEKOLAH.md, L5–L6). Template = contoh sekolah persis.
 *
 *   - Balasan: semua siswa aktif ajuan + periode PKL. Periode ikut disimpan ke ajuan (pkl_pengajuan.tanggal_mulai/selesai,
 *     tercatat di riwayat ajuan) supaya tampil di detail ajuan, Status Siswa, dan surat ASTS/TKA per perusahaan.
 *     Satu ajuan = satu Balasan aktif (batalkan dulu bila ingin membuat ulang).
 *   - Penarikan: siswa pilihan dari ajuan itu (boleh sebagian) + tanggal efektif + alasan. Status PKL siswa TIDAK berubah
 *     (keputusan user 2026-10-09: hanya surat + riwayat).
 *
 * Isi surat dibekukan di surat_sekolah (perusahaan_nama + isi JSON: sapaan, alamat, periode_mulai/selesai | tgl_efektif,
 * alasan, kunci) supaya tidak berubah diam-diam bila ajuan diubah; "Ubah data surat" tersedia selama belum bernomor.
 */
final class SuratPkl
{
    public const SAPAAN_BAWAAN = 'Pimpinan';
    public const SAPAAN_SARAN  = ['Pimpinan', 'Direktur', 'Kepala', 'HRD', 'Rektorat'];

    /** Alasan penarikan yang sering dipakai — kalimat sesudah "Penarikan tersebut dilakukan karena …". Yang pertama = contoh sekolah. */
    public const ALASAN_SARAN = [
        'adanya keperluan administrasi sekolah yang harus diselesaikan oleh peserta didik yang bersangkutan',
        'peserta didik yang bersangkutan akan dipindahkan ke tempat PKL lain',
        'peserta didik yang bersangkutan tidak mematuhi tata tertib tempat PKL',
        'permintaan orang tua/wali peserta didik yang bersangkutan',
        'peserta didik yang bersangkutan sakit sehingga tidak dapat melanjutkan kegiatan PKL',
    ];

    public const MAKS_AJUAN    = 100; // Balasan massal sekaligus
    public const MAKS_HARI_PKL = 400; // rentang periode yang masih masuk akal

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public static function untuk(?string $jenis): bool
    {
        return in_array($jenis, [SuratJenis::BALASAN, SuratJenis::PENARIKAN], true);
    }

    // =================================================================
    // Teks tanggal (murni)
    // =================================================================

    private static function tgl(string $ymd): ?\DateTimeImmutable
    {
        $t = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

        return $t !== false && $t->format('Y-m-d') === $ymd ? $t : null;
    }

    private static function hariBulan(\DateTimeImmutable $t): string
    {
        return (int) $t->format('j') . ' ' . IsianBantu::BULAN[(int) $t->format('n') - 1];
    }

    /**
     * Lama PKL dalam bulan = selisih hari ÷ 30,4375 (rata-rata hari sebulan), dibulatkan, minimal 1; tanggal tak sah → 0.
     * 24 Agustus → 24 November = 92 hari = 3 bulan. Rumus hari (bukan kalender) supaya pratinjau JavaScript di formulir
     * selalu sama persis dengan surat.
     */
    public static function lamaBulan(string $mulai, string $selesai): int
    {
        $a = self::tgl($mulai);
        $b = self::tgl($selesai);
        if ($a === null || $b === null || $b <= $a) {
            return 0;
        }

        return max(1, (int) round($a->diff($b)->days / 30.4375));
    }

    /** "3 (tiga) bulan" — tanggal tak sah → "". */
    public static function lamaTeks(string $mulai, string $selesai): string
    {
        $n = self::lamaBulan($mulai, $selesai);

        return $n > 0 ? $n . ' (' . HonorCetak::terbilang($n) . ') bulan' : '';
    }

    /** "24 Agustus s.d. 24 November 2026"; lintas tahun "24 November 2026 s.d. 24 Februari 2027"; sebulan "1 s.d. 30 September 2026". */
    public static function periodeTeks(string $mulai, string $selesai): string
    {
        $a = self::tgl($mulai);
        $b = self::tgl($selesai);
        if ($a === null || $b === null || $b < $a) {
            return '';
        }
        if ($a->format('Y-m') === $b->format('Y-m')) {
            return (int) $a->format('j') . ' s.d. ' . self::hariBulan($b) . ' ' . $b->format('Y');
        }
        if ($a->format('Y') === $b->format('Y')) {
            return self::hariBulan($a) . ' s.d. ' . self::hariBulan($b) . ' ' . $b->format('Y');
        }

        return self::hariBulan($a) . ' ' . $a->format('Y') . ' s.d. ' . self::hariBulan($b) . ' ' . $b->format('Y');
    }

    // =================================================================
    // Masukan (murni)
    // =================================================================

    /** Sapaan di bawah "Kepada Yth." tanpa awalan "Bapak/Ibu" (sudah tercetak di surat); kosong → "Pimpinan". */
    public static function sapaan(string $s): string
    {
        $s = IsianBantu::rapikan($s);
        $s = trim((string) preg_replace('~^(yth\.?\s*)?(bapak\s*/\s*ibu|bapak|ibu)\s+~iu', '', $s));

        return $s !== '' ? $s : self::SAPAAN_BAWAAN;
    }

    /**
     * Alasan penarikan: pilihan saran (alasan_pilih = indeks) atau ketikan sendiri (alasan_lain). Dirapikan supaya pas di
     * kalimat "… dilakukan karena {alasan}.": tanpa awalan "karena", tanpa titik akhir, huruf pertama kecil bila kata biasa.
     *
     * @param array<string, mixed> $in
     */
    public static function alasan(array $in): string
    {
        $pilih = (string) ($in['alasan_pilih'] ?? '');
        if ($pilih !== '' && ctype_digit($pilih) && isset(self::ALASAN_SARAN[(int) $pilih])) {
            return self::ALASAN_SARAN[(int) $pilih];
        }
        $t = IsianBantu::rapikan((string) ($in['alasan_lain'] ?? ''));
        $t = (string) preg_replace('~^(karena|sebab|dikarenakan|disebabkan)\s+~iu', '', $t);
        $t = rtrim($t, ' .');
        $a = mb_substr($t, 0, 1);
        $b = mb_substr($t, 1, 1);
        if ($b !== '' && $a !== mb_strtolower($a) && $b === mb_strtolower($b) && $b !== mb_strtoupper($b)) {
            $t = mb_strtolower($a) . mb_substr($t, 1);
        }

        return $t;
    }

    /**
     * Periksa isian formulir (pilihan ajuan/siswa diperiksa di buat()/ubahSurat()). Isian "alamat" hanya ada di formulir
     * UBAH (saat membuat, alamat diambil dari ajuan).
     *
     * @param array<string, mixed> $in
     *
     * @return array{ok: bool, galat: list<string>, tanggal_surat: string, isi: array<string, mixed>}
     */
    public static function periksa(string $jenis, array $in): array
    {
        $galat = [];
        $thn   = (int) date('Y');
        $surat = IsianBantu::tanggal(trim((string) ($in['tanggal_surat'] ?? '')), $thn - 1, $thn + 2);
        if ($surat === null) {
            $galat[] = 'Tanggal surat belum diisi atau tidak sah.';
        }
        $sapaan = self::sapaan((string) ($in['sapaan'] ?? ''));
        if (mb_strlen($sapaan) > 60) {
            $galat[] = 'Sapaan terlalu panjang (maksimal 60 huruf).';
        }
        $isi = ['sapaan' => $sapaan];

        if ($jenis === SuratJenis::BALASAN) {
            $mulai = IsianBantu::tanggal(trim((string) ($in['periode_mulai'] ?? '')), $thn - 2, $thn + 3);
            $akhir = IsianBantu::tanggal(trim((string) ($in['periode_selesai'] ?? '')), $thn - 2, $thn + 3);
            if ($mulai === null) {
                $galat[] = 'Tanggal mulai PKL belum diisi atau tidak sah.';
            }
            if ($akhir === null) {
                $galat[] = 'Tanggal selesai PKL belum diisi atau tidak sah.';
            }
            if ($mulai !== null && $akhir !== null) {
                if ($akhir <= $mulai) {
                    $galat[] = 'Tanggal selesai PKL harus setelah tanggal mulai.';
                } elseif (IsianBantu::hariInklusif($mulai, $akhir) > self::MAKS_HARI_PKL) {
                    $galat[] = 'Periode PKL lebih dari ' . self::MAKS_HARI_PKL . ' hari — periksa lagi bulan/tahunnya.';
                }
            }
            $isi += ['periode_mulai' => (string) $mulai, 'periode_selesai' => (string) $akhir];
            if (array_key_exists('alamat', $in)) {
                $alamat = IsianBantu::rapikan((string) $in['alamat']);
                if (mb_strlen($alamat) > 300) {
                    $galat[] = 'Alamat terlalu panjang (maksimal 300 huruf).';
                }
                $isi['alamat'] = $alamat;
            }
        } else {
            $efektif = IsianBantu::tanggal(trim((string) ($in['tgl_efektif'] ?? '')), $thn - 2, $thn + 2);
            if ($efektif === null) {
                $galat[] = 'Tanggal "terhitung sejak" (mulai berlakunya penarikan) belum diisi atau tidak sah.';
            }
            $alasan = self::alasan($in);
            if ($alasan === '') {
                $galat[] = 'Alasan penarikan wajib diisi.';
            } elseif (mb_strlen($alasan) > 300) {
                $galat[] = 'Alasan terlalu panjang (maksimal 300 huruf).';
            }
            $isi += ['tgl_efektif' => (string) $efektif, 'alasan' => $alasan];
        }

        return ['ok' => $galat === [], 'galat' => $galat, 'tanggal_surat' => (string) $surat, 'isi' => $isi];
    }

    /** Kunci pencegah ganda: Balasan = satu per ajuan; Penarikan = ajuan + siswa + tanggal efektif. */
    public static function kunci(string $jenis, int $ajuanId, array $isi, array $siswaIds = []): string
    {
        if ($jenis === SuratJenis::BALASAN) {
            return sha1('balasan|' . $ajuanId);
        }
        $siswaIds = array_map('intval', $siswaIds);
        sort($siswaIds);

        return sha1('penarikan|' . $ajuanId . '|' . implode(',', $siswaIds) . '|' . ($isi['tgl_efektif'] ?? ''));
    }

    /**
     * Judul ringkas di Daftar Surat.
     *
     * @param list<string> $nama nama siswa di surat
     */
    public static function judul(string $jenis, string $perusahaan, array $nama): string
    {
        $n     = count($nama);
        $akhir = $jenis === SuratJenis::BALASAN || $n === 0
            ? ' (' . $n . ' siswa)'
            : ' - ' . ($n <= 2 ? implode(', ', $nama) : $nama[0] . ' dan ' . ($n - 1) . ' siswa lain');

        return mb_substr($perusahaan . $akhir, 0, 190);
    }

    /**
     * Penanda khusus template balasan_pkl.docx / penarikan_pkl.docx (menimpa penanda umum yang sama namanya).
     *
     * @param array<string, mixed> $surat baris surat_sekolah
     * @param array<string, mixed> $isi   isian JSON yang sudah didekode
     *
     * @return array<string, string>
     */
    public static function token(string $jenis, array $surat, array $isi): array
    {
        $v = [
            'sapaan'        => trim((string) ($isi['sapaan'] ?? '')) !== '' ? (string) $isi['sapaan'] : self::SAPAAN_BAWAAN,
            // Seperti contoh sekolah: hari tanpa nol di depan ("Bekasi, 20 Agustus 2026").
            'tanggal_surat' => IsianBantu::tanggalIndo((string) ($surat['tanggal_surat'] ?? '')),
        ];
        if ($jenis === SuratJenis::BALASAN) {
            $mulai   = (string) ($isi['periode_mulai'] ?? '');
            $selesai = (string) ($isi['periode_selesai'] ?? '');
            $v += ['lama_pkl' => self::lamaTeks($mulai, $selesai), 'periode_pkl' => self::periodeTeks($mulai, $selesai)];
            if (array_key_exists('alamat', $isi)) {
                $v['alamat_lengkap'] = (string) $isi['alamat'];
            }
        } else {
            $v += ['tanggal_efektif' => IsianBantu::tanggalIndo((string) ($isi['tgl_efektif'] ?? '')), 'alasan' => (string) ($isi['alasan'] ?? '')];
        }

        return $v;
    }

    /**
     * Kaki ACC dua baris. Template Balasan/Penarikan menaruh kaki ACC di PALING BAWAH (sesudah kontak "NB : … 1. …", seperti
     * Surat Izin PKL; permintaan klien 2026-10-21 — dulu di atas "NB :"). Surat padat 5 siswa nyaris sepenuh halaman F4, jadi
     * keterangannya dipadatkan: baris 1 = "disetujui secara elektronik" + penyetuju, baris 2 = waktu, kode verifikasi, pencetak.
     */
    public static function kakiDuaBaris(string $kaki): string
    {
        $baris = array_values(array_filter(array_map('trim', explode("\n", $kaki)), static fn (string $b): bool => $b !== ''));
        if (count($baris) <= 2) {
            return implode("\n", $baris);
        }

        return $baris[0] . ' ' . $baris[1] . "\n" . implode(' · ', array_slice($baris, 2));
    }

    /**
     * Peringatan periode PKL terhadap Pengaturan PKL (batas mulai/selesai, lama minimal/maksimal) — tidak memblokir,
     * ditampilkan di formulir & detail surat (dan membuat surat dilewati ACC massal).
     *
     * @param array<string, mixed> $p baris pkl_pengaturan
     *
     * @return list<string>
     */
    public static function peringatanPeriode(string $mulai, string $selesai, array $p): array
    {
        if (self::tgl($mulai) === null || self::tgl($selesai) === null || $selesai <= $mulai) {
            return [];
        }
        $out   = [];
        $awal  = (string) ($p['mulai_paling_awal'] ?? '');
        $akhir = (string) ($p['selesai_paling_akhir'] ?? '');
        if ($awal !== '' && $mulai < $awal) {
            $out[] = 'Tanggal mulai PKL (' . IsianBantu::tanggalIndo($mulai) . ') lebih awal dari batas sekolah ' . IsianBantu::tanggalIndo($awal) . '.';
        }
        if ($akhir !== '' && $selesai > $akhir) {
            $out[] = 'Tanggal selesai PKL (' . IsianBantu::tanggalIndo($selesai) . ') melewati batas sekolah ' . IsianBantu::tanggalIndo($akhir) . '.';
        }
        $hari = IsianBantu::hariInklusif($mulai, $selesai);
        $min  = (int) ($p['durasi_min_hari'] ?? 0);
        $maks = (int) ($p['durasi_maks_hari'] ?? 0);
        if ($min > 0 && $hari < $min) {
            $out[] = 'Lama PKL ' . $hari . ' hari, kurang dari minimal ' . $min . ' hari di Pengaturan PKL.';
        } elseif ($maks > 0 && $hari > $maks) {
            $out[] = 'Lama PKL ' . $hari . ' hari, lebih dari maksimal ' . $maks . ' hari di Pengaturan PKL.';
        }

        return $out;
    }

    // =================================================================
    // Data ajuan
    // =================================================================

    /** Alamat satu baris dari alamat + kota ajuan (kota tidak diulang bila sudah tertulis). */
    public static function alamatAjuan(string $alamat, string $kota): string
    {
        $alamat = IsianBantu::rapikan($alamat);
        $kota   = IsianBantu::rapikan($kota);

        return trim($alamat . ($kota !== '' && ! str_contains(mb_strtolower($alamat), mb_strtolower($kota)) ? ($alamat !== '' ? ', ' : '') . $kota : ''));
    }

    /**
     * Ajuan DISETUJUI dengan anggota yang masih aktif (pengaju dulu), terbaru dulu; $ids = hanya ajuan itu. Tiap ajuan:
     * kode, perusahaan (nama master bila tertaut), alamat, periode tercatat, siswa, Balasan aktif (bila ada), jumlah
     * Penarikan yang belum dibatalkan.
     *
     * @param list<int>|null $ids
     *
     * @return list<array{id: int, kode: string, perusahaan: string, alamat: string, mulai: ?string, selesai: ?string, siswa: list<array{siswa_id: int, kelas_id: ?int, hp: ?string, nama: string, kelas: string}>, balasan: ?array{id: int, status: string, nomor: ?string}, penarikan: int}>
     */
    public function ajuan(?array $ids = null): array
    {
        $b = $this->db->table('pkl_pengajuan p')
            ->select('p.id, p.perusahaan_nama, p.perusahaan_alamat, p.perusahaan_kota, p.tanggal_mulai, p.tanggal_selesai, m.nama AS master_nama')
            ->select('a.siswa_id, a.kelas_id, a.hp, s.nama, k.nama_kelas')
            ->join('pkl_anggota a', 'a.pengajuan_id = p.id')
            ->join('siswa s', 's.id = a.siswa_id')
            ->join('kelas k', 'k.id = a.kelas_id', 'left')
            ->join('pkl_perusahaan m', 'm.id = p.perusahaan_id', 'left')
            ->where('s.deleted_at', null)->where('s.status', 'aktif')
            ->where('p.status', 'disetujui');
        if ($ids !== null) {
            $b->whereIn('p.id', $ids !== [] ? $ids : [0]);
        }
        $rows = $b->orderBy('p.id', 'DESC')->orderBy('a.peran', 'ASC')->orderBy('a.id', 'ASC')->get()->getResultArray(); // ENUM: pengaju sebelum teman

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[$id] ??= [
                'id' => $id, 'kode' => PklPengajuanModel::kode($id),
                'perusahaan' => (string) ($r['master_nama'] ?? '') !== '' ? (string) $r['master_nama'] : (string) $r['perusahaan_nama'],
                'alamat' => self::alamatAjuan((string) ($r['perusahaan_alamat'] ?? ''), (string) ($r['perusahaan_kota'] ?? '')),
                'mulai' => $r['tanggal_mulai'] ?: null, 'selesai' => $r['tanggal_selesai'] ?: null,
                'siswa' => [], 'balasan' => null, 'penarikan' => 0,
            ];
            $out[$id]['siswa'][] = [
                'siswa_id' => (int) $r['siswa_id'], 'kelas_id' => ((int) $r['kelas_id']) ?: null,
                'hp' => trim((string) ($r['hp'] ?? '')) !== '' ? (string) $r['hp'] : null, 'nama' => (string) $r['nama'], 'kelas' => (string) ($r['nama_kelas'] ?? ''),
            ];
        }
        if ($out !== []) {
            foreach ($this->db->table('surat_sekolah')->select('id, jenis, status, nomor, pengajuan_id')->whereIn('pengajuan_id', array_keys($out))
                ->whereIn('jenis', [SuratJenis::BALASAN, SuratJenis::PENARIKAN])->where('status !=', 'dibatalkan')->orderBy('id', 'ASC')->get()->getResultArray() as $s) {
                $aid = (int) $s['pengajuan_id'];
                if ($s['jenis'] === SuratJenis::BALASAN) {
                    $out[$aid]['balasan'] ??= ['id' => (int) $s['id'], 'status' => (string) $s['status'], 'nomor' => $s['nomor'] ?? null];
                } else {
                    $out[$aid]['penarikan']++;
                }
            }
        }

        return array_values($out);
    }

    // =================================================================
    // Membuat & mengubah
    // =================================================================

    /**
     * Buat surat dari isian yang SUDAH lolos periksa(). Balasan: satu surat per ajuan terpilih (periode sama untuk semua);
     * Penarikan: satu surat untuk SATU ajuan + siswa terpilih.
     *
     * @param array{tanggal_surat: string, isi: array<string, mixed>} $h
     * @param list<int>                                                $ajuanIds
     * @param list<int>                                                $siswaIds siswa yang ditarik (Penarikan)
     * @param array<string, mixed>                                     $konteks
     *
     * @param array{terima?: array<int, list<int>>, alasan_tolak?: string} $opsi Balasan: per ajuan, siswa yang DITERIMA perusahaan
     *        (ajuan yang tidak disebut = semua diterima); yang tidak dicentang dikeluarkan dulu sebagai "ditolak perusahaan" (pilahTerima)
     *
     * @return array{ok: bool, dibuat: list<int>, sama: list<string>, gagal: list<string>, pesan: string, lapor: int, dilepas: list<string>}
     *         lapor = jumlah laporan "diterima" siswa yang ikut ditandai selesai (Balasan); dilepas = nama siswa yang dikeluarkan
     */
    public function buat(string $jenis, array $h, array $ajuanIds, array $siswaIds, array $konteks, ?array $p = null, array $opsi = []): array
    {
        $ajuanIds = array_values(array_unique(array_filter(array_map('intval', $ajuanIds), static fn (int $x) => $x > 0)));
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds), static fn (int $x) => $x > 0)));
        $dibuat   = [];
        $sama     = [];
        $gagal    = [];
        $lapor    = 0;
        $dilepas  = [];

        if ($ajuanIds === []) {
            $gagal[] = $jenis === SuratJenis::BALASAN ? 'Pilih minimal satu ajuan PKL yang sudah disetujui.' : 'Pilih ajuan PKL tempat siswa ditarik.';
        } elseif ($jenis === SuratJenis::PENARIKAN && count($ajuanIds) > 1) {
            $gagal[]  = 'Surat penarikan dibuat untuk satu tempat PKL sekaligus.';
            $ajuanIds = [];
        } elseif (count($ajuanIds) > self::MAKS_AJUAN) {
            $gagal[]  = 'Terlalu banyak sekaligus (maksimal ' . self::MAKS_AJUAN . ' ajuan).';
            $ajuanIds = [];
        }

        $svc  = new SuratSekolah($this->db);
        $peta = [];
        foreach ($ajuanIds !== [] ? $this->ajuan($ajuanIds) : [] as $a) {
            $peta[$a['id']] = $a;
        }
        foreach ($ajuanIds as $aid) {
            $a = $peta[$aid] ?? null;
            if ($a === null) {
                $gagal[] = 'Ajuan ' . PklPengajuanModel::kode($aid) . ' tidak ditemukan, belum disetujui, atau siswanya tidak aktif lagi.';
                continue;
            }
            if ($jenis === SuratJenis::BALASAN) {
                // Laporan siswa "sebagian diterima" / "semua ditolak" yang belum diproses: surat lewat kotak laporan saja.
                if (($khusus = (new PklLapor($this->db))->menungguKhusus($aid)) !== null) {
                    $gagal[] = PklLapor::pesanKhusus($khusus);
                    continue;
                }
                $pilah = self::pilahTerima($a, array_key_exists($aid, $opsi['terima'] ?? []) ? (array) $opsi['terima'][$aid] : null, (string) ($opsi['alasan_tolak'] ?? ''));
                if (! $pilah['ok']) {
                    $gagal[] = $a['perusahaan'] . ' (' . $a['kode'] . ') — ' . $pilah['pesan'];
                    continue;
                }
                $siswa = $pilah['diterima'];
                $isi   = $h['isi'] + ['alamat' => $a['alamat']];
            } else {
                $siswa = array_values(array_filter($a['siswa'], static fn (array $s) => in_array($s['siswa_id'], $siswaIds, true)));
                if ($siswa === []) {
                    $gagal[] = 'Pilih minimal satu siswa yang ditarik dari ' . $a['perusahaan'] . '.';
                    continue;
                }
                if (count($siswa) !== count($siswaIds)) {
                    $gagal[] = 'Ada siswa pilihan yang bukan anggota aktif ajuan ' . $a['kode'] . '. Muat ulang halaman lalu pilih lagi.';
                    continue;
                }
                $isi = $h['isi'];
            }
            $isi['kunci'] = self::kunci($jenis, $aid, $isi, array_column($siswa, 'siswa_id'));
            if (($ada = $svc->adaKunci($isi['kunci'])) !== null) {
                $sama[] = $a['perusahaan'] . ' (' . $a['kode'] . ') — ' . ($jenis === SuratJenis::BALASAN ? 'sudah punya Surat Balasan' : 'surat penarikan yang sama sudah ada')
                    . ' (' . SuratSekolahModel::kode($ada['id']) . ($ada['nomor'] ? ', nomor ' . $ada['nomor'] : '') . ').';
                continue;
            }
            if ($jenis === SuratJenis::BALASAN && $pilah['tolak'] !== []) {
                $k = $this->lepasTolak($aid, $pilah['tolak'], $pilah['alasan'], $konteks, $p);
                if (! $k['ok']) {
                    $gagal[] = $a['perusahaan'] . ' (' . $a['kode'] . ') — siswa yang tidak diterima gagal dikeluarkan: ' . $k['pesan'];
                    continue;
                }
                $dilepas = array_merge($dilepas, array_column($pilah['tolak'], 'nama'));
            }
            $r = $svc->buat($jenis, [
                'judul' => self::judul($jenis, $a['perusahaan'], array_column($siswa, 'nama')), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
                'perusahaan_nama' => $a['perusahaan'], 'pengajuan_id' => $aid,
                'siswa' => array_map(static fn (array $s) => ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp']], $siswa),
            ], $konteks, $p);
            if (! $r['ok']) {
                $gagal[] = $a['perusahaan'] . ' — ' . (string) ($r['pesan'] ?? 'gagal disimpan.');
                continue;
            }
            $dibuat[] = (int) $r['id'];
            $this->catatAjuan($jenis, $aid, (int) $r['id'], $isi, array_column($siswa, 'nama'), $konteks, true);
            // Laporan "diterima" siswa yang masih menunggu untuk ajuan ini → ditandai selesai, tertaut ke surat ini (PklLapor).
            if ($jenis === SuratJenis::BALASAN && (new PklLapor($this->db))->tautkanAjuan($aid, (int) $r['id'], $konteks)) {
                $lapor++;
            }
        }

        $pesan = [];
        if ($dibuat !== []) {
            $pesan[] = count($dibuat) . ' surat dibuat.';
        }
        if ($sama !== []) {
            $pesan[] = count($sama) . ' dilewati karena sudah ada.';
        }
        if ($gagal !== []) {
            $pesan[] = count($gagal) . ' tidak bisa dibuat.';
        }
        if ($dilepas !== []) {
            $pesan[] = self::teksDilepas($dilepas);
        }

        return ['ok' => $dibuat !== [], 'dibuat' => $dibuat, 'sama' => $sama, 'gagal' => $gagal, 'pesan' => implode(' ', $pesan), 'lapor' => $lapor, 'dilepas' => $dilepas];
    }

    /**
     * Balasan: pisahkan anggota aktif ajuan menjadi yang DITERIMA perusahaan (masuk surat) dan yang TIDAK. $ids = centang "diterima"
     * dari staf; null = tidak diatur → semua diterima. Minimal satu diterima (semua ditolak = tombol "Ditolak perusahaan" di ajuan).
     * Yang tidak diterima wajib beralasan (dibaca siswa).
     *
     * @param array{kode: string, siswa: list<array{siswa_id: int, nama: string}>} $a baris ajuan()
     * @param list<int|string>|null                                            $ids
     *
     * @return array{ok: bool, pesan?: string, diterima: list<array<string, mixed>>, tolak: list<array<string, mixed>>, alasan: string}
     */
    public static function pilahTerima(array $a, ?array $ids, string $alasan): array
    {
        $kosong = ['diterima' => [], 'tolak' => [], 'alasan' => ''];
        if ($ids === null) {
            return ['ok' => true, 'diterima' => $a['siswa']] + $kosong;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', array_filter($ids, 'is_scalar')), static fn (int $x) => $x > 0)));
        if (array_diff($ids, array_column($a['siswa'], 'siswa_id')) !== []) {
            return ['ok' => false, 'pesan' => 'Ada siswa pilihan yang bukan anggota aktif ajuan ini. Muat ulang halaman lalu pilih lagi.'] + $kosong;
        }
        if ($ids === []) {
            return ['ok' => false, 'pesan' => 'Centang minimal satu siswa yang diterima perusahaan. Bila semua ditolak, buka ajuan ' . $a['kode'] . ' lalu tekan "Ditolak perusahaan".'] + $kosong;
        }
        $diterima = array_values(array_filter($a['siswa'], static fn (array $s) => in_array($s['siswa_id'], $ids, true)));
        $tolak    = array_values(array_filter($a['siswa'], static fn (array $s) => ! in_array($s['siswa_id'], $ids, true)));
        $alasan   = $tolak !== [] ? IsianBantu::rapikan($alasan) : '';
        if ($tolak !== [] && mb_strlen($alasan) < PklKeluar::MIN_ALASAN) {
            return ['ok' => false, 'pesan' => 'Tulis alasan untuk siswa yang tidak diterima (minimal ' . PklKeluar::MIN_ALASAN . ' huruf) — siswa akan membacanya.'] + $kosong;
        }
        if (mb_strlen($alasan) > PklAjuan::MAKS_CATATAN) {
            return ['ok' => false, 'pesan' => 'Alasan terlalu panjang (maksimal ' . PklAjuan::MAKS_CATATAN . ' huruf).'] + $kosong;
        }

        return ['ok' => true, 'diterima' => $diterima, 'tolak' => $tolak, 'alasan' => $alasan];
    }

    /**
     * Siswa yang tidak diterima perusahaan dikeluarkan dari ajuan (jenis "ditolak perusahaan": bebas mengajukan tempat lain, tetap
     * tercatat di riwayatnya, Surat Izin yang terbit tetap sah). Hak: pemegang hak Buat Surat Balasan — tanpa hak ACC ajuan, SAMA
     * dengan memproses laporan "sebagian diterima" di kotak Lapor Diterima (PklLapor::proses), karena surat ini memang menegaskan
     * siapa yang diterima perusahaan dan masih melewati ACC Waka Hubin.
     *
     * @param list<array{siswa_id: int}> $tolak
     *
     * @return array{ok: bool, pesan: string}
     */
    private function lepasTolak(int $aid, array $tolak, string $alasan, array $konteks, ?array $p): array
    {
        return (new PklKeluar($this->db))->keluarkan($aid, [
            'siswa_ids' => array_column($tolak, 'siswa_id'), 'jenis' => 'ditolak_perusahaan', 'catatan' => $alasan,
        ], $konteks + ['sumber' => 'staf'], $p, false);
    }

    /** @param list<string> $nama */
    public static function teksDilepas(array $nama): string
    {
        return implode(', ', $nama) . ' tidak diterima perusahaan: dikeluarkan dari ajuan (ditolak perusahaan) dan bebas mengajukan tempat PKL lain.';
    }

    /**
     * Alasan bawaan untuk siswa yang tidak diterima + saran dari template alasan "keluar" yang judulnya "Ditolak perusahaan…"
     * (Pengaturan PKL → Template alasan).
     *
     * @return array{bawaan: string, saran: list<array{judul: string, isi: string}>}
     */
    public function alasanTolak(): array
    {
        $saran = [];
        foreach ((new PklAlasan($this->db))->daftar('keluar') as $t) {
            if (str_starts_with(mb_strtolower($t['judul']), 'ditolak perusahaan')) {
                $saran[] = ['judul' => $t['judul'], 'isi' => $t['isi']];
            }
        }
        $bawaan = 'Perusahaan tidak menerima siswa ini untuk PKL. Silakan mengajukan tempat PKL lain.';
        foreach ($saran as $s) {
            $bawaan = mb_strtolower($s['judul']) === 'ditolak perusahaan' ? $s['isi'] : $bawaan;
        }

        return ['bawaan' => $bawaan, 'saran' => $saran];
    }

    /**
     * Ubah data surat (belum bernomor, status menunggu/dikembalikan). Balasan: tanggal, sapaan, alamat, periode (periode
     * ajuan ikut diperbarui) + siswa yang DITERIMA perusahaan ($opsi['terima'], bila diatur; yang tidak dicentang dikeluarkan dari
     * ajuan sebagai "ditolak perusahaan" — permintaan klien 2026-10-21: operator memperbaiki sendiri, bukan lewat lapor ulang siswa).
     * Penarikan: tanggal, sapaan, tanggal efektif, alasan, dan siswa (dari ajuan yang sama).
     *
     * @param array<string, mixed>                                     $m       hasil SuratSekolah::muat()
     * @param array{tanggal_surat: string, isi: array<string, mixed>} $h       hasil periksa()
     * @param list<int>                                                $siswaIds siswa yang ditarik (Penarikan)
     * @param array<string, mixed>                                     $konteks
     * @param array{terima?: ?list<int>, alasan_tolak?: string}       $opsi    Balasan: terima null/tak ada = daftar siswa tetap
     *
     * @return array{ok: bool, pesan?: string, kode?: string, dilepas?: list<string>}
     */
    public function ubahSurat(array $m, array $h, array $siswaIds, array $konteks, array $opsi = []): array
    {
        $surat = $m['surat'];
        $jenis = (string) $surat['jenis'];
        $aid   = (int) ($surat['pengajuan_id'] ?? 0);
        $lama  = $surat['isi_arr'];
        $isi   = $h['isi'];
        $data  = ['tanggal_surat' => $h['tanggal_surat']];
        $tolak = [];
        $alasanTolak = '';

        if ($jenis === SuratJenis::BALASAN) {
            $isi += ['alamat' => (string) ($lama['alamat'] ?? '')];
            $nama = array_column($m['siswa'], 'nama');
            if (($opsi['terima'] ?? null) !== null) {
                $ajuan = $aid > 0 ? ($this->ajuan([$aid])[0] ?? null) : null;
                if ($ajuan === null) {
                    return ['ok' => false, 'kode' => 'siswa', 'pesan' => 'Ajuan PKL surat ini sudah tidak berstatus disetujui, jadi daftar siswa tidak bisa diubah.'];
                }
                $pilah = self::pilahTerima($ajuan, (array) $opsi['terima'], (string) ($opsi['alasan_tolak'] ?? ''));
                if (! $pilah['ok']) {
                    return ['ok' => false, 'kode' => 'siswa', 'pesan' => (string) $pilah['pesan']];
                }
                $data['siswa'] = array_map(static fn (array $s) => ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp']], $pilah['diterima']);
                $nama          = array_column($pilah['diterima'], 'nama');
                $tolak         = $pilah['tolak'];
                $alasanTolak   = $pilah['alasan'];
            }
        } else {
            $ajuan = $aid > 0 ? ($this->ajuan([$aid])[0] ?? null) : null;
            $ids   = array_values(array_unique(array_filter(array_map('intval', $siswaIds), static fn (int $x) => $x > 0)));
            if ($ajuan !== null && $ids !== []) {
                $siswa = array_values(array_filter($ajuan['siswa'], static fn (array $s) => in_array($s['siswa_id'], $ids, true)));
                if (count($siswa) !== count($ids)) {
                    return ['ok' => false, 'pesan' => 'Ada siswa pilihan yang bukan anggota aktif ajuan ini. Muat ulang halaman lalu pilih lagi.'];
                }
                $data['siswa'] = array_map(static fn (array $s) => ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp']], $siswa);
                $nama          = array_column($siswa, 'nama');
                $sid           = array_column($siswa, 'siswa_id');
            } elseif ($ids === []) {
                return ['ok' => false, 'pesan' => 'Pilih minimal satu siswa yang ditarik.'];
            } else {
                // Ajuan sudah tidak disetujui lagi: daftar siswa lama dipertahankan.
                $nama = array_column($m['siswa'], 'nama');
                $sid  = array_map('intval', array_column($m['siswa'], 'siswa_id'));
            }
        }
        $isi['kunci'] = self::kunci($jenis, $aid, $isi, $sid ?? []);
        $svc          = new SuratSekolah($this->db);
        if (($ada = $svc->adaKunci($isi['kunci'], (int) $surat['id'])) !== null) {
            return ['ok' => false, 'pesan' => 'Surat yang sama sudah ada (' . SuratSekolahModel::kode($ada['id']) . ($ada['nomor'] ? ', nomor ' . $ada['nomor'] : '') . '). Tidak dibuat dobel.'];
        }
        if (($s = SuratSekolah::alasanTakBisaUbah($surat)) !== null) {
            return ['ok' => false, 'kode' => 'status', 'pesan' => $s]; // diperiksa SEBELUM siswa dikeluarkan dari ajuan
        }
        if ($tolak !== []) {
            $k = $this->lepasTolak($aid, $tolak, $alasanTolak, $konteks, null);
            if (! $k['ok']) {
                return ['ok' => false, 'kode' => 'siswa', 'pesan' => 'Siswa yang tidak diterima gagal dikeluarkan dari ajuan: ' . $k['pesan']];
            }
        }

        $r = $svc->ubah((int) $surat['id'], $data + [
            'judul' => self::judul($jenis, (string) $surat['perusahaan_nama'], $nama), 'isi' => $isi,
        ], $konteks);
        if (! $r['ok']) {
            return ['ok' => false, 'pesan' => (string) ($r['pesan'] ?? 'Perubahan gagal disimpan.')];
        }
        if ($aid > 0) {
            $this->catatAjuan($jenis, $aid, (int) $surat['id'], $isi, $nama, $konteks, false);
        }

        return ['ok' => true, 'dilepas' => array_column($tolak, 'nama')];
    }

    /**
     * Jejak di ajuan PKL: Balasan → periode disimpan ke ajuan (bila berubah) + riwayat; Penarikan → riwayat.
     *
     * @param array<string, mixed> $isi
     * @param list<string>         $nama
     * @param array<string, mixed> $konteks
     */
    private function catatAjuan(string $jenis, int $aid, int $suratId, array $isi, array $nama, array $konteks, bool $baru): void
    {
        $kode  = SuratSekolahModel::kode($suratId);
        $ajuan = new PklAjuan($this->db);
        try {
            if ($jenis === SuratJenis::BALASAN) {
                $mulai   = (string) ($isi['periode_mulai'] ?? '');
                $selesai = (string) ($isi['periode_selesai'] ?? '');
                $a       = $this->db->table('pkl_pengajuan')->select('tanggal_mulai, tanggal_selesai')->where('id', $aid)->get()->getRowArray();
                if ($a !== null && ((string) $a['tanggal_mulai'] !== $mulai || (string) $a['tanggal_selesai'] !== $selesai)) {
                    $this->db->table('pkl_pengajuan')->where('id', $aid)->update(['tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai, 'updated_at' => date('Y-m-d H:i:s')]);
                    $ajuan->catat($aid, 'periode', $konteks, 'Periode PKL ' . self::periodeTeks($mulai, $selesai) . ' — dari Surat Balasan ' . $kode);
                } elseif ($baru) {
                    $ajuan->catat($aid, 'surat_sekolah', $konteks, 'Surat Balasan ' . $kode . ' dibuat');
                }
            } else {
                $ajuan->catat($aid, 'surat_sekolah', $konteks, mb_substr('Surat Penarikan ' . $kode . ($baru ? ' dibuat' : ' diubah') . ': ' . implode(', ', $nama)
                    . ' ditarik sejak ' . IsianBantu::tanggalIndo((string) ($isi['tgl_efektif'] ?? '')), 0, 255));
            }
        } catch (\Throwable $e) {
            log_message('error', '[Surat] jejak ajuan #' . $aid . ' gagal: ' . $e->getMessage());
        }
    }
}
