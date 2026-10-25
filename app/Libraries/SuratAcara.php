<?php

namespace App\Libraries;

use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Surat Izin ASTS dan Surat Izin TKA ("surat acara"): pemberitahuan kegiatan sekolah + permohonan dispensasi bagi siswa PKL.
 * Dua jenis ini berbagi satu bentuk isian, jadi logikanya satu di sini (rancangan: docs/DESAIN-SURAT-SEKOLAH.md, L3):
 *
 *   - format tanggal otomatis, nama hari dihitung dari tanggal (contoh sekolah pernah salah: "Senin s.d. Jumat" untuk 5–8 Okt 2026);
 *   - validasi masukan form (tanggal surat tidak boleh setelah acara selesai, rentang wajar, tahun pelajaran sah);
 *   - dua cakupan: "umum" = SATU surat untuk semua perusahaan (persis contoh, satu nomor); "perusahaan" = satu surat per
 *     perusahaan yang dipilih, memuat nama perusahaan + daftar siswanya, nomor berbeda-beda;
 *   - pencegah surat ganda: kegiatan + tanggal + sesi + cakupan (+ perusahaan) yang sama tidak dibuat dua kali selama
 *     suratnya belum dibatalkan;
 *   - penanda ${…} khusus template (izin_asts.docx / izin_tka.docx) lewat token().
 *
 * Isian khusus disimpan di surat_sekolah.isi (JSON): kunci (sidik pencegah ganda), kegiatan, semester + tahun_pelajaran (ASTS),
 * sesi (TKA), tgl_mulai, tgl_selesai, tempat, mode.
 */
final class SuratAcara
{
    public const HARI          = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    public const SESI_SARAN    = ['Gelombang 1', 'Gelombang 2', 'Gelombang 3'];
    public const TEMPAT_BAWAAN = 'SMK Bina Nusa';
    public const MAKS_HARI     = 31;
    public const MAKS_PERUSAHAAN = 300;

    /**
     * Tingkat kelas peserta kegiatan (kelas siswa SAAT INI): TKA khusus kelas XII (keputusan sekolah 2026-10-21) — surat TKA per
     * perusahaan hanya memuat siswa XII, dan perusahaan tanpa siswa XII tidak ikut. Jenis yang tidak tertulis = semua tingkat.
     */
    public const TINGKAT = [SuratJenis::TKA => ['XII']];

    /**
     * Ujian yang memakai jenis surat izin_asts ("Surat Izin ASTS/ASAS/ASAT", sejak 2026-10-21): kode => [nama resmi, jenis di menu
     * Ujian]. ASAT selalu semester Genap dan namanya tercetak tanpa semester ("… Akhir Tahun (ASAT) Tahun Pelajaran 2026/2027").
     */
    public const UJIAN = [
        'ASTS' => ['Asesmen Sumatif Tengah Semester', ['ASTS1', 'ASTS2']],
        'ASAS' => ['Asesmen Sumatif Akhir Semester', ['ASAS']],
        'ASAT' => ['Asesmen Sumatif Akhir Tahun', ['ASAT']],
    ];

    /** Kode ujian yang sah dari isian ('' / tak dikenal → ASTS, bawaan surat lama). */
    public static function kodeUjian(?string $kode): string
    {
        $kode = strtoupper(trim((string) $kode));

        return isset(self::UJIAN[$kode]) ? $kode : 'ASTS';
    }

    /** "Asesmen Sumatif Tengah Semester (ASTS) Ganjil" / "Asesmen Sumatif Akhir Tahun (ASAT)". */
    public static function namaUjian(string $kode, string $semester = ''): string
    {
        $kode = self::kodeUjian($kode);

        return trim(self::UJIAN[$kode][0] . ' (' . $kode . ')' . ($kode !== 'ASAT' ? ' ' . trim($semester) : ''));
    }

    /** jenis => [singkatan, nama resmi] */
    private const KEGIATAN = [
        SuratJenis::ASTS => ['ASTS', 'Asesmen Sumatif Tengah Semester (ASTS)'],
        SuratJenis::TKA  => ['TKA', 'Tes Kemampuan Akademik (TKA)'],
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public static function acara(?string $jenis): bool
    {
        return $jenis !== null && isset(self::KEGIATAN[$jenis]);
    }

    // =================================================================
    // Format tanggal (murni)
    // =================================================================

    private static function tgl(string $ymd): ?\DateTimeImmutable
    {
        $t = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

        return $t !== false && $t->format('Y-m-d') === $ymd ? $t : null;
    }

    /** "5" bulan, "September" — tanpa nol di depan hari. */
    private static function hariBulan(\DateTimeImmutable $t): string
    {
        return (int) $t->format('j') . ' ' . IsianBantu::BULAN[(int) $t->format('n') - 1];
    }

    /**
     * Baris "Hari/Tanggal" surat: "Senin s.d. Jumat, 14 - 18 September 2026"; lintas bulan "28 September - 2 Oktober 2026";
     * lintas tahun "30 Desember 2026 - 1 Januari 2027"; satu hari "Senin, 14 September 2026". Tanggal tak sah → "".
     */
    public static function hariTanggal(string $mulai, string $selesai): string
    {
        $a = self::tgl($mulai);
        $b = self::tgl($selesai);
        if ($a === null || $b === null || $b < $a) {
            return '';
        }
        $ha = self::HARI[(int) $a->format('w')];
        $hb = self::HARI[(int) $b->format('w')];
        if ($a == $b) {
            return $ha . ', ' . self::hariBulan($a) . ' ' . $a->format('Y');
        }
        if ($a->format('Y-m') === $b->format('Y-m')) {
            return $ha . ' s.d. ' . $hb . ', ' . (int) $a->format('j') . ' - ' . self::hariBulan($b) . ' ' . $b->format('Y');
        }
        if ($a->format('Y') === $b->format('Y')) {
            return $ha . ' s.d. ' . $hb . ', ' . self::hariBulan($a) . ' - ' . self::hariBulan($b) . ' ' . $b->format('Y');
        }

        return $ha . ' s.d. ' . $hb . ', ' . self::hariBulan($a) . ' ' . $a->format('Y') . ' - ' . self::hariBulan($b) . ' ' . $b->format('Y');
    }

    /** Frasa dalam kalimat: "14 sampai dengan 18 September 2026"; satu hari "14 September 2026". Tanggal tak sah → "". */
    public static function tanggalSampai(string $mulai, string $selesai): string
    {
        $a = self::tgl($mulai);
        $b = self::tgl($selesai);
        if ($a === null || $b === null || $b < $a) {
            return '';
        }
        if ($a == $b) {
            return self::hariBulan($a) . ' ' . $a->format('Y');
        }
        if ($a->format('Y-m') === $b->format('Y-m')) {
            return (int) $a->format('j') . ' sampai dengan ' . self::hariBulan($b) . ' ' . $b->format('Y');
        }
        if ($a->format('Y') === $b->format('Y')) {
            return self::hariBulan($a) . ' sampai dengan ' . self::hariBulan($b) . ' ' . $b->format('Y');
        }

        return self::hariBulan($a) . ' ' . $a->format('Y') . ' sampai dengan ' . self::hariBulan($b) . ' ' . $b->format('Y');
    }

    // =================================================================
    // Validasi & penyusunan isian (murni)
    // =================================================================

    /**
     * Periksa masukan form. $modeTetap dipakai saat MENGUBAH surat (cakupan tidak boleh diganti).
     *
     * Kembalian: ok, galat (daftar pesan), tanggal_surat, isi (array siap simpan, tanpa kunci), mode, pilihan (kunci
     * perusahaan yang dicentang; hanya mode "perusahaan").
     *
     * @param array<string, mixed> $in
     *
     * siswa_pilih (hanya TKA per perusahaan): kunci perusahaan => siswa yang dicentang, atau null bila tidak dikirim (aplikasi lama).
     *
     * @return array{ok: bool, galat: list<string>, tanggal_surat: string, isi: array<string, mixed>, mode: string, pilihan: list<string>, siswa_pilih: array<string, list<int>>|null}
     */
    public static function periksa(string $jenis, array $in, ?string $modeTetap = null): array
    {
        $galat = [];
        $thn   = (int) date('Y');
        $surat = IsianBantu::tanggal(trim((string) ($in['tanggal_surat'] ?? '')), $thn - 1, $thn + 2);
        $mulai = IsianBantu::tanggal(trim((string) ($in['tgl_mulai'] ?? '')), $thn - 1, $thn + 2);
        $akhir = IsianBantu::tanggal(trim((string) ($in['tgl_selesai'] ?? '')), $thn - 1, $thn + 2);

        if ($surat === null) {
            $galat[] = 'Tanggal surat belum diisi atau tidak sah.';
        }
        if ($mulai === null) {
            $galat[] = 'Tanggal mulai kegiatan belum diisi atau tidak sah.';
        }
        if ($akhir === null) {
            $galat[] = 'Tanggal selesai kegiatan belum diisi atau tidak sah.';
        }
        if ($mulai !== null && $akhir !== null) {
            if ($akhir < $mulai) {
                $galat[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            } elseif (IsianBantu::hariInklusif($mulai, $akhir) > self::MAKS_HARI) {
                $galat[] = 'Rentang kegiatan terlalu panjang (maksimal ' . self::MAKS_HARI . ' hari). Periksa lagi tanggalnya.';
            }
            if ($surat !== null && $surat > $akhir) {
                $galat[] = 'Tanggal surat tidak boleh setelah kegiatan selesai.';
            }
        }

        $tempat = IsianBantu::rapikan((string) ($in['tempat'] ?? ''));
        if ($tempat === '') {
            $tempat = self::TEMPAT_BAWAAN;
        }
        if (mb_strlen($tempat) > 100) {
            $galat[] = 'Tempat terlalu panjang (maksimal 100 huruf).';
        }

        $mode = $modeTetap ?? (string) ($in['mode'] ?? 'umum');
        if (! in_array($mode, ['umum', 'perusahaan'], true)) {
            $mode    = 'umum';
            $galat[] = 'Cakupan surat tidak dikenal.';
        }

        $isi = ['kegiatan' => self::KEGIATAN[$jenis][0] ?? '', 'tgl_mulai' => (string) $mulai, 'tgl_selesai' => (string) $akhir, 'tempat' => $tempat, 'mode' => $mode];
        if ($jenis === SuratJenis::ASTS) {
            // Ujian: ASTS (bawaan — juga untuk formulir/API lama tanpa isian ini), ASAS, ASAT. ASAT selalu semester Genap.
            $mentah   = strtoupper(trim((string) ($in['kegiatan'] ?? '')));
            $kegiatan = self::kodeUjian($mentah);
            if ($mentah !== '' && $mentah !== $kegiatan) {
                $galat[] = 'Pilih ujiannya: ASTS, ASAS, atau ASAT.';
            }
            $semester = $kegiatan === 'ASAT' ? 'Genap' : (string) ($in['semester'] ?? '');
            $tp       = IsianBantu::rapikan((string) ($in['tahun_pelajaran'] ?? ''));
            if (! in_array($semester, ['Ganjil', 'Genap'], true)) {
                $galat[] = 'Semester wajib dipilih (Ganjil atau Genap).';
            }
            if (preg_match('~^(\d{4})/(\d{4})$~', $tp, $m) !== 1 || (int) $m[2] !== (int) $m[1] + 1) {
                $galat[] = 'Tahun pelajaran harus berbentuk 2026/2027 (tahun kedua = tahun pertama + 1).';
            }
            $isi = ['kegiatan' => $kegiatan, 'semester' => $semester, 'tahun_pelajaran' => $tp] + $isi;
        } else {
            $sesi = IsianBantu::rapikan((string) ($in['sesi'] ?? ''));
            if ($sesi === '' || mb_strlen($sesi) > 40) {
                $galat[] = 'Sesi / gelombang wajib diisi (maksimal 40 huruf), mis. Gelombang 1.';
            }
            $isi = ['kegiatan' => 'TKA', 'sesi' => $sesi] + $isi;
        }

        $pilihan    = [];
        $siswaPilih = null;
        if ($mode === 'perusahaan' && $modeTetap === null) {
            $pilihan = array_values(array_unique(array_filter(array_map('strval', (array) ($in['perusahaan'] ?? [])), static fn (string $k) => preg_match('~^[mn]:[\p{L}\p{N} _.\-]{1,160}$~u', $k) === 1)));
            if ($pilihan === []) {
                $galat[] = 'Pilih minimal satu perusahaan (atau ganti cakupan menjadi "Umum").';
            } elseif (count($pilihan) > self::MAKS_PERUSAHAAN) {
                $galat[] = 'Terlalu banyak perusahaan sekaligus (maksimal ' . self::MAKS_PERUSAHAAN . ').';
            }
            // TKA (gelombang per siswa, 2026-10-26): siswa yang dicentang per perusahaan, `siswa[kunci] = [siswa_id…]`. Formulir web
            // selalu mengirim `siswa_form` (perusahaan tanpa centang = tidak ada siswa); tanpa keduanya (aplikasi lama) = null →
            // semua siswa perusahaan yang belum tercantum di surat TKA lain.
            if ($jenis === SuratJenis::TKA && (! empty($in['siswa_form']) || is_array($in['siswa'] ?? null))) {
                $siswaPilih = [];
                foreach ($pilihan as $k) {
                    $ids              = $in['siswa'][$k] ?? [];
                    $siswaPilih[$k]   = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn (int $i): bool => $i > 0)));
                }
            }
        }

        return ['ok' => $galat === [], 'galat' => $galat, 'tanggal_surat' => (string) $surat, 'isi' => $isi, 'mode' => $mode, 'pilihan' => $pilihan, 'siswa_pilih' => $siswaPilih];
    }

    /** Sidik pencegah surat ganda: kegiatan + semester/tahun + sesi + tanggal + cakupan (+ perusahaan). */
    public static function kunci(string $jenis, array $isi, string $perusahaan = ''): string
    {
        $bagian = [
            $jenis, (string) ($isi['mode'] ?? 'umum'), (string) ($isi['tgl_mulai'] ?? ''), (string) ($isi['tgl_selesai'] ?? ''),
            mb_strtolower((string) ($isi['sesi'] ?? '')), (string) ($isi['semester'] ?? ''), (string) ($isi['tahun_pelajaran'] ?? ''), $perusahaan,
        ];
        // ASAS/ASAT ikut sidik (ASTS tidak, supaya sidik surat ASTS lama tetap sama).
        if ($jenis === SuratJenis::ASTS && self::kodeUjian($isi['kegiatan'] ?? '') !== 'ASTS') {
            $bagian[] = self::kodeUjian($isi['kegiatan'] ?? '');
        }

        return sha1(implode('|', $bagian));
    }

    /** "ASTS Ganjil 2026/2027" / "ASAT 2026/2027" / "TKA Gelombang 1". */
    public static function namaPendek(string $jenis, array $isi): string
    {
        if ($jenis !== SuratJenis::ASTS) {
            return trim('TKA ' . ($isi['sesi'] ?? ''));
        }
        $kode = self::kodeUjian($isi['kegiatan'] ?? '');

        return trim(preg_replace('/\s+/u', ' ', $kode . ' ' . ($kode !== 'ASAT' ? ($isi['semester'] ?? '') : '') . ' ' . ($isi['tahun_pelajaran'] ?? '')) ?? $kode);
    }

    /** Judul ringkas untuk Daftar Surat. */
    public static function judul(string $jenis, array $isi, string $perusahaan = '', int $jmlSiswa = 0): string
    {
        $dasar = self::namaPendek($jenis, $isi);
        $akhir = ($isi['mode'] ?? 'umum') === 'perusahaan' && $perusahaan !== ''
            ? $perusahaan . ($jmlSiswa > 0 ? ' (' . $jmlSiswa . ' siswa)' : '')
            : 'surat umum';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $dasar) ?? $dasar) . ' - ' . $akhir, 0, 190);
    }

    // =================================================================
    // Penanda template
    // =================================================================

    /**
     * Penanda khusus izin_asts.docx / izin_tka.docx. Isian yang belum ada (surat lama/uji) menghasilkan teks kosong,
     * bukan galat.
     *
     * @param array<string, mixed> $surat baris surat_sekolah
     * @param array<string, mixed> $isi   isian JSON yang sudah didekode
     *
     * @return array<string, string>
     */
    public static function token(string $jenis, array $surat, array $isi): array
    {
        $mulai   = (string) ($isi['tgl_mulai'] ?? '');
        $selesai = (string) ($isi['tgl_selesai'] ?? '');
        $mode    = (string) ($isi['mode'] ?? 'umum');
        $nama    = trim((string) ($surat['perusahaan_nama'] ?? ''));
        $tempat  = trim((string) ($isi['tempat'] ?? '')) !== '' ? (string) $isi['tempat'] : self::TEMPAT_BAWAAN;

        $v = [
            // Surat acara menulis hari tanpa nol di depan ("Bekasi, 5 Oktober 2026"), seperti contoh sekolah — menimpa penanda umum.
            'tanggal_surat'  => IsianBantu::tanggalIndo((string) ($surat['tanggal_surat'] ?? '')),
            'hari_tanggal'   => self::hariTanggal($mulai, $selesai),
            'tanggal_sampai' => self::tanggalSampai($mulai, $selesai),
            'tempat'         => $tempat,
            // Satu baris (nama perusahaan di belakang "Bapak/Ibu Pimpinan"): halaman 1 surat A4 nyaris penuh, satu baris tambahan
            // sudah memakai hampir seluruh sisa ruangnya.
            'tujuan_surat'   => $mode === 'perusahaan' && $nama !== '' ? 'Bapak/Ibu Pimpinan ' . $nama : 'Bapak/Ibu Pimpinan / Pembimbing PKL',
        ];
        if ($jenis === SuratJenis::ASTS) {
            $kode     = self::kodeUjian($isi['kegiatan'] ?? '');
            $kegiatan = self::namaUjian($kode, (string) ($isi['semester'] ?? ''));
            $tp       = trim((string) ($isi['tahun_pelajaran'] ?? ''));
            $v += [
                'perihal'          => 'Pemberitahuan Pelaksanaan ' . $kegiatan,
                'kegiatan'         => $kegiatan,
                'kegiatan_lengkap' => $kegiatan . ($tp !== '' ? ' Tahun Pelajaran ' . $tp : ''),
                'singkatan'        => $kode, // "…mengikuti pelaksanaan ASAS di sekolah…" (dulu "ASTS" tertulis mati di template)
            ];
        } else {
            $v += ['sesi' => (string) ($isi['sesi'] ?? ''), 'singkatan' => 'TKA'];
        }

        return $v;
    }

    // =================================================================
    // Data perusahaan (mode "per perusahaan") & pencegah ganda
    // =================================================================

    /**
     * Perusahaan yang punya siswa PKL (ajuan DISETUJUI, siswa aktif), digabung per perusahaan. Kunci grup: "m:{id master}"
     * bila ada, selain itu "n:{nama ternormalisasi}". Bila $mulai/$selesai diberikan dan periode PKL perusahaan itu sudah
     * tercatat, `luar_periode` = true bila periodenya tidak menjangkau tanggal kegiatan (periode kosong = tidak diketahui).
     * $jenis memberi saringan tingkat kelas (self::TINGKAT): TKA = hanya siswa yang SEKARANG kelas XII.
     *
     * @return list<array{kunci: string, nama: string, alamat: string, kota: string, ajuan_ids: list<int>, siswa: list<array{siswa_id: int, kelas_id: ?int, hp: ?string, nama: string, kelas: string, ajuan_id: int}>, mulai: ?string, selesai: ?string, luar_periode: bool}>
     */
    public function perusahaan(?string $mulai = null, ?string $selesai = null, ?string $jenis = null): array
    {
        $b = $this->db->table('pkl_pengajuan p')
            ->select('p.id AS ajuan_id, p.perusahaan_id, p.perusahaan_norm, p.perusahaan_nama, p.perusahaan_alamat, p.perusahaan_kota, p.tanggal_mulai, p.tanggal_selesai, m.nama AS master_nama')
            ->select('a.siswa_id, a.kelas_id, a.hp, a.peran, s.nama AS siswa_nama, k.nama_kelas')
            ->join('pkl_anggota a', 'a.pengajuan_id = p.id')
            ->join('siswa s', "s.id = a.siswa_id AND s.status = 'aktif'")
            ->join('kelas k', 'k.id = a.kelas_id', 'left')
            ->join('pkl_perusahaan m', 'm.id = p.perusahaan_id', 'left')
            ->where('p.status', 'disetujui');
        if (isset(self::TINGKAT[(string) $jenis])) {
            $b->join('kelas ks', 'ks.id = s.kelas_id')->whereIn('ks.tingkat', self::TINGKAT[(string) $jenis]);
        }
        $rows = $b->orderBy('p.perusahaan_nama', 'ASC')->orderBy('p.id', 'ASC')->orderBy('a.peran', 'ASC')->orderBy('a.id', 'ASC') // ENUM urut menurut indeks: pengaju sebelum teman
            ->get()->getResultArray();

        $grup = [];
        foreach ($rows as $r) {
            $kunci = ! empty($r['perusahaan_id']) ? 'm:' . (int) $r['perusahaan_id'] : 'n:' . mb_substr((string) $r['perusahaan_norm'], 0, 150);
            if (! isset($grup[$kunci])) {
                $grup[$kunci] = [
                    'kunci' => $kunci, 'nama' => (string) ($r['master_nama'] ?? '') !== '' ? (string) $r['master_nama'] : (string) $r['perusahaan_nama'],
                    'alamat' => (string) ($r['perusahaan_alamat'] ?? ''), 'kota' => (string) ($r['perusahaan_kota'] ?? ''),
                    'ajuan_ids' => [], 'siswa' => [], 'mulai' => null, 'selesai' => null, 'luar_periode' => false, '_periode_lengkap' => true,
                ];
            }
            $g = &$grup[$kunci];
            if (! in_array((int) $r['ajuan_id'], $g['ajuan_ids'], true)) {
                $g['ajuan_ids'][] = (int) $r['ajuan_id'];
                if (empty($r['tanggal_mulai']) || empty($r['tanggal_selesai'])) {
                    $g['_periode_lengkap'] = false;
                } else {
                    $g['mulai']   = $g['mulai'] === null || $r['tanggal_mulai'] < $g['mulai'] ? (string) $r['tanggal_mulai'] : $g['mulai'];
                    $g['selesai'] = $g['selesai'] === null || $r['tanggal_selesai'] > $g['selesai'] ? (string) $r['tanggal_selesai'] : $g['selesai'];
                }
            }
            $ada = false;
            foreach ($g['siswa'] as $x) {
                $ada = $ada || $x['siswa_id'] === (int) $r['siswa_id'];
            }
            if (! $ada) {
                $g['siswa'][] = ['siswa_id' => (int) $r['siswa_id'], 'kelas_id' => ((int) $r['kelas_id']) ?: null, 'hp' => trim((string) ($r['hp'] ?? '')) !== '' ? (string) $r['hp'] : null, 'nama' => (string) $r['siswa_nama'], 'kelas' => (string) ($r['nama_kelas'] ?? ''), 'ajuan_id' => (int) $r['ajuan_id']];
            }
            unset($g);
        }

        $out = [];
        foreach ($grup as $g) {
            if (! $g['_periode_lengkap']) { // ada ajuan tanpa periode → periode perusahaan tidak diketahui
                $g['mulai'] = $g['selesai'] = null;
            }
            unset($g['_periode_lengkap']);
            if ($mulai !== null && $selesai !== null && $g['mulai'] !== null && $g['selesai'] !== null) {
                $g['luar_periode'] = $g['selesai'] < $mulai || $g['mulai'] > $selesai;
            }
            if ($g['siswa'] !== []) {
                $out[] = $g;
            }
        }

        return $out;
    }

    /**
     * perusahaan() untuk Surat Izin TKA + penempatan gelombang tiap siswa (formulir staf web & API, 2026-10-26): siswa[].tka =
     * surat TKA (belum dibatalkan) yang sudah memuat namanya {surat_id, kode, label, status_teks} atau null; jml_bebas = siswa yang
     * belum tercantum di surat TKA mana pun (hanya mereka yang bisa dimasukkan; yang lain dipindah lewat tombol "Pindah").
     *
     * @return list<array<string, mixed>>
     */
    public function perusahaanTka(?string $mulai = null, ?string $selesai = null): array
    {
        $grup   = $this->perusahaan($mulai, $selesai, SuratJenis::TKA);
        $tempat = (new LayananSiswa($this->db))->penempatanTka(array_merge([], ...array_map(static fn (array $g): array => array_column($g['siswa'], 'siswa_id'), $grup)));
        foreach ($grup as &$g) {
            $bebas = 0;
            foreach ($g['siswa'] as &$x) {
                $t       = $tempat[$x['siswa_id']][0] ?? null;
                $x['tka'] = $t === null ? null : ['surat_id' => $t['surat_id'], 'kode' => $t['kode'], 'label' => $t['label'], 'status_teks' => $t['status_teks']];
                $bebas  += $t === null ? 1 : 0;
            }
            unset($x);
            $g['jml_bebas'] = $bebas;
        }
        unset($g);

        return $grup;
    }

    /**
     * Surat acara yang sama (kunci sama) dan belum dibatalkan — pencegah surat ganda.
     *
     * @return array{id: int, status: string, nomor: ?string}|null
     */
    public function adaSama(string $kunci, ?int $kecuali = null): ?array
    {
        return (new SuratSekolah($this->db))->adaKunci($kunci, $kecuali);
    }

    /**
     * Periode ujian di menu Ujian (ASTS 1, ASAS, ASTS 2, ASAT) untuk mengisi otomatis ujian, semester, tahun, dan tanggal; tahun
     * terbaru dulu, dalam satu tahun menurut urutan pelaksanaan.
     *
     * @return list<array{label: string, kegiatan: string, semester: string, tahun: string, mulai: ?string, selesai: ?string}>
     */
    public function periodeUjian(): array
    {
        $peta = [];
        foreach (self::UJIAN as $kode => [, $jenisUjian]) {
            foreach ($jenisUjian as $j) {
                $peta[$j] = $kode;
            }
        }
        $nama = ['ASTS1' => 'ASTS 1', 'ASTS2' => 'ASTS 2', 'ASAS' => 'ASAS', 'ASAT' => 'ASAT'];
        $out  = [];
        foreach ($this->db->table('ujian_periode')->whereIn('jenis', array_keys($peta))->where('deleted_at', null)
            ->orderBy('tahun_ajaran', 'DESC')->orderBy("FIELD(jenis, 'ASTS1', 'ASAS', 'ASTS2', 'ASAT')", '', false)->get()->getResultArray() as $r) {
            $tgl      = ! empty($r['tanggal_mulai']) && ! empty($r['tanggal_selesai']) ? self::hariTanggal((string) $r['tanggal_mulai'], (string) $r['tanggal_selesai']) : 'tanggal belum diisi di menu Ujian';
            $kode     = $peta[(string) $r['jenis']];
            $semester = $kode === 'ASAT' ? 'Genap' : (string) $r['semester'];
            $out[] = [
                'label'    => ($nama[(string) $r['jenis']] ?? (string) $r['jenis']) . ' · ' . $r['tahun_ajaran'] . ($kode !== 'ASAT' ? ' · ' . $semester : '') . ' (' . $tgl . ')',
                'kegiatan' => $kode, 'semester' => $semester, 'tahun' => (string) $r['tahun_ajaran'],
                'mulai' => $r['tanggal_mulai'] ?: null, 'selesai' => $r['tanggal_selesai'] ?: null,
            ];
        }

        return $out;
    }

    /**
     * Isian awal formulir baru (web & API). ASTS mengambil periode Ujian terdekat yang belum lewat (atau, bila tak ada,
     * semester & tahun dari yang terbaru dengan tanggal kosong); TKA mulai kosong dengan sesi "Gelombang 1".
     *
     * @param list<array{label: string, semester: string, tahun: string, mulai: ?string, selesai: ?string}> $periode periodeUjian()
     *
     * @return array<string, string>
     */
    public static function nilaiAwal(string $jenis, array $periode, string $tahunPelajaran): array
    {
        $awal = ['tanggal_surat' => date('Y-m-d'), 'mode' => 'umum', 'tempat' => self::TEMPAT_BAWAAN, 'tgl_mulai' => '', 'tgl_selesai' => '',
            'kegiatan' => $jenis === SuratJenis::ASTS ? 'ASTS' : 'TKA',
            'semester' => 'Ganjil', 'tahun_pelajaran' => $tahunPelajaran, 'sesi' => $jenis === SuratJenis::TKA ? self::SESI_SARAN[0] : ''];
        if ($jenis !== SuratJenis::ASTS || $periode === []) {
            return $awal;
        }
        $terdekat = null; // periode bertanggal yang belum lewat, paling awal
        foreach ($periode as $x) {
            if ($x['selesai'] !== null && $x['selesai'] >= date('Y-m-d') && ($terdekat === null || (string) $x['mulai'] < (string) $terdekat['mulai'])) {
                $terdekat = $x;
            }
        }
        if ($terdekat !== null) {
            return ['kegiatan' => $terdekat['kegiatan'], 'semester' => $terdekat['semester'], 'tahun_pelajaran' => $terdekat['tahun'], 'tgl_mulai' => (string) $terdekat['mulai'], 'tgl_selesai' => (string) $terdekat['selesai']] + $awal;
        }

        return ['semester' => $periode[0]['semester'], 'tahun_pelajaran' => $periode[0]['tahun']] + $awal;
    }

    // =================================================================
    // Membuat surat
    // =================================================================

    /**
     * Buat surat dari masukan yang SUDAH lolos periksa(). Cakupan umum → 1 surat; cakupan perusahaan → 1 surat per perusahaan
     * terpilih (yang kembar dilewati dan dilaporkan). Surat Izin TKA per perusahaan memakai aturan gelombang per siswa →
     * buatTkaPerusahaan() (`ditambah` = surat gelombang itu yang sudah ada dan ditambah siswanya).
     *
     * @param array{tanggal_surat: string, isi: array<string, mixed>, mode: string, pilihan: list<string>, siswa_pilih?: array<string, list<int>>|null} $h hasil periksa()
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, dibuat: list<int>, ditambah: list<int>, sama: list<string>, gagal: list<string>, pesan: string}
     */
    public function buat(string $jenis, array $h, array $konteks, ?array $p = null): array
    {
        $svc      = new SuratSekolah($this->db);
        $dibuat   = [];
        $ditambah = [];
        $sama     = [];
        $gagal    = [];

        if ($h['mode'] === 'perusahaan' && $jenis === SuratJenis::TKA) {
            [$dibuat, $ditambah, $sama, $gagal] = $this->buatTkaPerusahaan($h, $konteks, $p);
        } elseif ($h['mode'] === 'umum') {
            $isi = $h['isi'] + ['kunci' => self::kunci($jenis, $h['isi'])];
            if (($ada = $this->adaSama($isi['kunci'])) !== null) {
                $sama[] = 'Surat umum ini sudah ada (' . SuratSekolahModel::kode($ada['id']) . ($ada['nomor'] ? ', nomor ' . $ada['nomor'] : '') . ').';
            } else {
                $r = $svc->buat($jenis, ['judul' => self::judul($jenis, $isi), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi], $konteks, $p);
                if ($r['ok']) {
                    $dibuat[] = (int) $r['id'];
                } else {
                    $gagal[] = (string) ($r['pesan'] ?? 'Surat gagal disimpan.');
                }
            }
        } else {
            $peta = [];
            foreach ($this->perusahaan($h['isi']['tgl_mulai'], $h['isi']['tgl_selesai'], $jenis) as $g) {
                $peta[$g['kunci']] = $g;
            }
            foreach ($h['pilihan'] as $kunci) {
                $g = $peta[$kunci] ?? null;
                if ($g === null) {
                    $gagal[] = 'Perusahaan pilihan tidak ditemukan lagi (mungkin persetujuannya dibatalkan).';
                    continue;
                }
                $isi = $h['isi'] + ['pkey' => $kunci, 'kunci' => self::kunci($jenis, $h['isi'], $kunci)];
                if (($ada = $this->adaSama($isi['kunci'])) !== null) {
                    $sama[] = $g['nama'] . ' — sudah ada (' . SuratSekolahModel::kode($ada['id']) . ($ada['nomor'] ? ', nomor ' . $ada['nomor'] : '') . ').';
                    continue;
                }
                $r = $svc->buat($jenis, [
                    'judul'  => self::judul($jenis, $isi, $g['nama'], count($g['siswa'])), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
                    'perusahaan_nama' => $g['nama'], 'pengajuan_id' => count($g['ajuan_ids']) === 1 ? $g['ajuan_ids'][0] : null,
                    'siswa' => array_map(static fn (array $s) => ['siswa_id' => $s['siswa_id'], 'kelas_id' => $s['kelas_id'], 'hp' => $s['hp']], $g['siswa']),
                ], $konteks, $p);
                if ($r['ok']) {
                    $dibuat[] = (int) $r['id'];
                } else {
                    $gagal[] = $g['nama'] . ' — ' . (string) ($r['pesan'] ?? 'gagal disimpan.');
                }
            }
        }

        $pesan = [];
        if ($dibuat !== []) {
            $pesan[] = count($dibuat) . ' surat dibuat.';
        }
        if ($ditambah !== []) {
            $pesan[] = count($ditambah) . ' surat yang sudah ada ditambah siswanya.';
        }
        if ($sama !== []) {
            $pesan[] = count($sama) . ($jenis === SuratJenis::TKA && $h['mode'] === 'perusahaan' ? ' catatan (lihat di bawah).' : ' dilewati karena sudah ada.');
        }
        if ($gagal !== []) {
            $pesan[] = count($gagal) . ' gagal.';
        }

        return ['ok' => $dibuat !== [] || $ditambah !== [], 'dibuat' => $dibuat, 'ditambah' => $ditambah, 'sama' => $sama, 'gagal' => $gagal, 'pesan' => implode(' ', $pesan)];
    }

    /**
     * Surat Izin TKA per perusahaan dari staf (gelombang PER SISWA, 2026-10-26; aturan sama dengan permintaan siswa
     * LayananSiswa::ajukanTka): tiap perusahaan hanya memuat siswa yang DICENTANG (siswa_pilih; null = semua siswa perusahaan) dan
     * BELUM tercantum di surat TKA lain — siswa yang sudah tercantum dilaporkan (pindahkan lewat tombol "Pindah" di surat itu).
     * Surat perusahaan untuk gelombang ini yang masih bisa diubah → siswa ditambahkan; tidak ada / sudah disetujui → surat baru.
     *
     * @return array{0: list<int>, 1: list<int>, 2: list<string>, 3: list<string>} [dibuat, ditambah, catatan, gagal]
     */
    private function buatTkaPerusahaan(array $h, array $konteks, ?array $p): array
    {
        $svc    = new SuratSekolah($this->db);
        $ls     = new LayananSiswa($this->db);
        $j      = ['tgl_mulai' => (string) $h['isi']['tgl_mulai'], 'tgl_selesai' => (string) $h['isi']['tgl_selesai'], 'sesi' => (string) $h['isi']['sesi']];
        $peta   = [];
        foreach ($this->perusahaan($j['tgl_mulai'], $j['tgl_selesai'], SuratJenis::TKA) as $g) {
            $peta[$g['kunci']] = $g;
        }
        $dibuat = $ditambah = $catatan = $gagal = [];
        foreach ($h['pilihan'] as $kunci) {
            $g = $peta[$kunci] ?? null;
            if ($g === null) {
                $gagal[] = 'Perusahaan pilihan tidak ditemukan lagi (mungkin persetujuannya dibatalkan).';

                continue;
            }
            $anggota = array_column($g['siswa'], null, 'siswa_id');
            $pilih   = ($h['siswa_pilih'] ?? null) === null ? array_keys($anggota) : array_values(array_intersect(array_keys($anggota), $h['siswa_pilih'][$kunci] ?? []));
            $tempat  = $ls->penempatanTka($pilih);
            $sudah   = [];
            $bebas   = [];
            foreach ($pilih as $sid) {
                if (! empty($tempat[$sid])) {
                    $sudah[] = $anggota[$sid]['nama'] . ' (' . $tempat[$sid][0]['label'] . ', ' . $tempat[$sid][0]['kode'] . ')';
                } else {
                    $bebas[] = $sid;
                }
            }
            if ($sudah !== []) {
                $catatan[] = $g['nama'] . ' — sudah punya surat TKA, tidak dimasukkan lagi: ' . implode(', ', $sudah) . '. Bila gelombangnya salah, pakai tombol "Pindah" di surat itu.';
            }
            if ($bebas === []) {
                if ($sudah === []) {
                    $catatan[] = $g['nama'] . ' — tidak ada siswa yang dicentang.';
                }

                continue;
            }
            $rows   = array_map(static fn (int $sid): array => ['siswa_id' => $sid, 'kelas_id' => $anggota[$sid]['kelas_id'], 'hp' => $anggota[$sid]['hp']], $bebas);
            $target = null;
            foreach ($ls->suratGelombang($kunci, $j) as $d) {
                $target ??= $d['bisa_ubah'] ? $d['id'] : null;
            }
            if ($target !== null) {
                $m    = $svc->muat($target);
                $baru = array_merge(array_map(static fn (array $x): array => ['siswa_id' => (int) $x['siswa_id'], 'kelas_id' => $x['kelas_id'] ?? null, 'hp' => $x['hp'] ?? null], $m['siswa']), $rows);
                $u    = $svc->ubah($target, ['judul' => self::judul(SuratJenis::TKA, $m['surat']['isi_arr'], (string) $m['surat']['perusahaan_nama'], count($baru)),
                    'tanggal_surat' => (string) $m['surat']['tanggal_surat'], 'isi' => $m['surat']['isi_arr'], 'siswa' => $baru], $konteks);
                if (! $u['ok']) {
                    $gagal[] = $g['nama'] . ' — ' . (string) ($u['pesan'] ?? 'gagal disimpan.');

                    continue;
                }
                $svc->catat($target, 'minta', $konteks, 'Ditambahkan staf: ' . implode(', ', array_map(static fn (int $sid): string => $anggota[$sid]['nama'], $bebas)));
                $ditambah[] = $target;
                $catatan[]  = $g['nama'] . ' — ' . count($bebas) . ' siswa ditambahkan ke ' . SuratSekolahModel::kode($target) . ' (surat gelombang ini sudah ada, masih menunggu ACC).';

                continue;
            }
            $dasar = self::kunci(SuratJenis::TKA, $h['isi'], $kunci);
            $sidik = $dasar;
            for ($n = 1; $svc->adaKunci($sidik) !== null && $n <= 50; $n++) { // surat gelombang ini sudah disetujui → surat tambahan
                $sidik = sha1($dasar . '|' . $n);
            }
            if ($svc->adaKunci($sidik) !== null) {
                $gagal[] = $g['nama'] . ' — surat untuk gelombang ini sudah terlalu banyak.';

                continue;
            }
            $ajuan = array_values(array_unique(array_map(static fn (int $sid): int => (int) ($anggota[$sid]['ajuan_id'] ?? 0), $bebas)));
            $r     = $svc->buat(SuratJenis::TKA, [
                'judul' => self::judul(SuratJenis::TKA, $h['isi'], $g['nama'], count($bebas)), 'tanggal_surat' => $h['tanggal_surat'],
                'isi' => $h['isi'] + ['pkey' => $kunci, 'kunci' => $sidik], 'perusahaan_nama' => $g['nama'],
                'pengajuan_id' => count($ajuan) === 1 && $ajuan[0] > 0 ? $ajuan[0] : null, 'siswa' => $rows,
            ], $konteks, $p);
            if ($r['ok']) {
                $dibuat[] = (int) $r['id'];
            } else {
                $gagal[] = $g['nama'] . ' — ' . (string) ($r['pesan'] ?? 'gagal disimpan.');
            }
        }

        return [$dibuat, $ditambah, $catatan, $gagal];
    }

    /**
     * Ubah data surat acara yang SUDAH dipastikan boleh diubah (SuratSekolah::alasanTakBisaUbah = null). Cakupan tidak
     * berubah; perusahaan & daftar siswa tetap. Dipakai web (Admin\SuratIzin) dan API (Api\Surat).
     *
     * kode: ok | galat (isian salah, 422) | sama (surat yang sama sudah ada, 409) | gagal (penyimpanan, 409)
     *
     * @param array{surat: array<string, mixed>, siswa: list<array<string, mixed>>} $m hasil SuratSekolah::muat()
     * @param array<string, mixed>                                                  $in
     * @param array<string, mixed>                                                  $konteks
     *
     * @return array{ok: bool, kode: string, galat: list<string>, pesan: string}
     */
    public function ubahSurat(array $m, array $in, array $konteks): array
    {
        $s     = $m['surat'];
        $jenis = (string) $s['jenis'];
        $id    = (int) $s['id'];
        $lama  = (array) ($s['isi_arr'] ?? []);
        $h     = self::periksa($jenis, $in, (string) ($lama['mode'] ?? 'umum'));
        if (! $h['ok']) {
            return ['ok' => false, 'kode' => 'galat', 'galat' => $h['galat'], 'pesan' => implode(' ', $h['galat'])];
        }

        $pkey = (string) ($lama['pkey'] ?? '');
        $isi  = $h['isi'] + ($pkey !== '' ? ['pkey' => $pkey] : []);
        $isi += ['kunci' => self::kunci($jenis, $h['isi'], $pkey)];
        if (($sama = $this->adaSama($isi['kunci'], $id)) !== null) {
            $pesan = 'Surat yang sama sudah ada (' . SuratSekolahModel::kode($sama['id']) . ($sama['nomor'] ? ', nomor ' . $sama['nomor'] : '') . '). Tidak dibuat dobel.';

            return ['ok' => false, 'kode' => 'sama', 'galat' => [$pesan], 'pesan' => $pesan];
        }

        $r = (new SuratSekolah($this->db))->ubah($id, [
            'judul' => self::judul($jenis, $isi, (string) $s['perusahaan_nama'], count($m['siswa'])), 'tanggal_surat' => $h['tanggal_surat'], 'isi' => $isi,
        ], $konteks);

        return $r['ok']
            ? ['ok' => true, 'kode' => 'ok', 'galat' => [], 'pesan' => 'Data surat diperbarui.']
            : ['ok' => false, 'kode' => 'gagal', 'galat' => [], 'pesan' => (string) ($r['pesan'] ?? 'Perubahan gagal disimpan.')];
    }
}
