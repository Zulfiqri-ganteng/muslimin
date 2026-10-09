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
     * @return array{ok: bool, galat: list<string>, tanggal_surat: string, isi: array<string, mixed>, mode: string, pilihan: list<string>}
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
            $semester = (string) ($in['semester'] ?? '');
            $tp       = IsianBantu::rapikan((string) ($in['tahun_pelajaran'] ?? ''));
            if (! in_array($semester, ['Ganjil', 'Genap'], true)) {
                $galat[] = 'Semester wajib dipilih (Ganjil atau Genap).';
            }
            if (preg_match('~^(\d{4})/(\d{4})$~', $tp, $m) !== 1 || (int) $m[2] !== (int) $m[1] + 1) {
                $galat[] = 'Tahun pelajaran harus berbentuk 2026/2027 (tahun kedua = tahun pertama + 1).';
            }
            $isi = ['kegiatan' => 'ASTS', 'semester' => $semester, 'tahun_pelajaran' => $tp] + $isi;
        } else {
            $sesi = IsianBantu::rapikan((string) ($in['sesi'] ?? ''));
            if ($sesi === '' || mb_strlen($sesi) > 40) {
                $galat[] = 'Sesi / gelombang wajib diisi (maksimal 40 huruf), mis. Gelombang 1.';
            }
            $isi = ['kegiatan' => 'TKA', 'sesi' => $sesi] + $isi;
        }

        $pilihan = [];
        if ($mode === 'perusahaan' && $modeTetap === null) {
            $pilihan = array_values(array_unique(array_filter(array_map('strval', (array) ($in['perusahaan'] ?? [])), static fn (string $k) => preg_match('~^[mn]:[\p{L}\p{N} _.\-]{1,160}$~u', $k) === 1)));
            if ($pilihan === []) {
                $galat[] = 'Pilih minimal satu perusahaan (atau ganti cakupan menjadi "Umum").';
            } elseif (count($pilihan) > self::MAKS_PERUSAHAAN) {
                $galat[] = 'Terlalu banyak perusahaan sekaligus (maksimal ' . self::MAKS_PERUSAHAAN . ').';
            }
        }

        return ['ok' => $galat === [], 'galat' => $galat, 'tanggal_surat' => (string) $surat, 'isi' => $isi, 'mode' => $mode, 'pilihan' => $pilihan];
    }

    /** Sidik pencegah surat ganda: kegiatan + semester/tahun + sesi + tanggal + cakupan (+ perusahaan). */
    public static function kunci(string $jenis, array $isi, string $perusahaan = ''): string
    {
        return sha1(implode('|', [
            $jenis, (string) ($isi['mode'] ?? 'umum'), (string) ($isi['tgl_mulai'] ?? ''), (string) ($isi['tgl_selesai'] ?? ''),
            mb_strtolower((string) ($isi['sesi'] ?? '')), (string) ($isi['semester'] ?? ''), (string) ($isi['tahun_pelajaran'] ?? ''), $perusahaan,
        ]));
    }

    /** Judul ringkas untuk Daftar Surat. */
    public static function judul(string $jenis, array $isi, string $perusahaan = '', int $jmlSiswa = 0): string
    {
        $dasar = $jenis === SuratJenis::ASTS
            ? 'ASTS ' . ($isi['semester'] ?? '') . ' ' . ($isi['tahun_pelajaran'] ?? '')
            : 'TKA ' . ($isi['sesi'] ?? '');
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
            $semester = trim((string) ($isi['semester'] ?? ''));
            $kegiatan = trim('Asesmen Sumatif Tengah Semester (ASTS) ' . $semester);
            $tp       = trim((string) ($isi['tahun_pelajaran'] ?? ''));
            $v += [
                'perihal'          => 'Pemberitahuan Pelaksanaan ' . $kegiatan,
                'kegiatan'         => $kegiatan,
                'kegiatan_lengkap' => $kegiatan . ($tp !== '' ? ' Tahun Pelajaran ' . $tp : ''),
            ];
        } else {
            $v += ['sesi' => (string) ($isi['sesi'] ?? '')];
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
     *
     * @return list<array{kunci: string, nama: string, alamat: string, kota: string, ajuan_ids: list<int>, siswa: list<array{siswa_id: int, kelas_id: ?int, hp: ?string, nama: string, kelas: string}>, mulai: ?string, selesai: ?string, luar_periode: bool}>
     */
    public function perusahaan(?string $mulai = null, ?string $selesai = null): array
    {
        $rows = $this->db->table('pkl_pengajuan p')
            ->select('p.id AS ajuan_id, p.perusahaan_id, p.perusahaan_norm, p.perusahaan_nama, p.perusahaan_alamat, p.perusahaan_kota, p.tanggal_mulai, p.tanggal_selesai, m.nama AS master_nama')
            ->select('a.siswa_id, a.kelas_id, a.hp, a.peran, s.nama AS siswa_nama, k.nama_kelas')
            ->join('pkl_anggota a', 'a.pengajuan_id = p.id')
            ->join('siswa s', "s.id = a.siswa_id AND s.status = 'aktif'")
            ->join('kelas k', 'k.id = a.kelas_id', 'left')
            ->join('pkl_perusahaan m', 'm.id = p.perusahaan_id', 'left')
            ->where('p.status', 'disetujui')
            ->orderBy('p.perusahaan_nama', 'ASC')->orderBy('p.id', 'ASC')->orderBy('a.peran', 'ASC')->orderBy('a.id', 'ASC') // ENUM urut menurut indeks: pengaju sebelum teman
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
                $g['siswa'][] = ['siswa_id' => (int) $r['siswa_id'], 'kelas_id' => ((int) $r['kelas_id']) ?: null, 'hp' => trim((string) ($r['hp'] ?? '')) !== '' ? (string) $r['hp'] : null, 'nama' => (string) $r['siswa_nama'], 'kelas' => (string) ($r['nama_kelas'] ?? '')];
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
     * Surat acara yang sama (kunci sama) dan belum dibatalkan — pencegah surat ganda.
     *
     * @return array{id: int, status: string, nomor: ?string}|null
     */
    public function adaSama(string $kunci, ?int $kecuali = null): ?array
    {
        $b = $this->db->table('surat_sekolah')->select('id, status, nomor')->where('status !=', 'dibatalkan')->like('isi', '"kunci":"' . $kunci . '"');
        if ($kecuali !== null) {
            $b->where('id !=', $kecuali);
        }
        $r = $b->orderBy('id', 'ASC')->get(1)->getRowArray();

        return $r === null ? null : ['id' => (int) $r['id'], 'status' => (string) $r['status'], 'nomor' => $r['nomor'] ?? null];
    }

    /**
     * Periode ASTS di menu Ujian (ASTS 1 / ASTS 2) untuk mengisi otomatis tanggal, terbaru dulu.
     *
     * @return list<array{label: string, semester: string, tahun: string, mulai: ?string, selesai: ?string}>
     */
    public function periodeUjian(): array
    {
        $out = [];
        foreach ($this->db->table('ujian_periode')->whereIn('jenis', ['ASTS1', 'ASTS2'])->where('deleted_at', null)->orderBy('tahun_ajaran', 'DESC')->orderBy('jenis', 'ASC')->get()->getResultArray() as $r) {
            $tgl   = ! empty($r['tanggal_mulai']) && ! empty($r['tanggal_selesai']) ? self::hariTanggal((string) $r['tanggal_mulai'], (string) $r['tanggal_selesai']) : 'tanggal belum diisi di menu Ujian';
            $out[] = [
                'label'    => ($r['jenis'] === 'ASTS1' ? 'ASTS 1' : 'ASTS 2') . ' · ' . $r['tahun_ajaran'] . ' · ' . $r['semester'] . ' (' . $tgl . ')',
                'semester' => (string) $r['semester'], 'tahun' => (string) $r['tahun_ajaran'],
                'mulai' => $r['tanggal_mulai'] ?: null, 'selesai' => $r['tanggal_selesai'] ?: null,
            ];
        }

        return $out;
    }

    // =================================================================
    // Membuat surat
    // =================================================================

    /**
     * Buat surat dari masukan yang SUDAH lolos periksa(). Cakupan umum → 1 surat; cakupan perusahaan → 1 surat per perusahaan
     * terpilih (yang kembar dilewati dan dilaporkan).
     *
     * @param array{tanggal_surat: string, isi: array<string, mixed>, mode: string, pilihan: list<string>} $h hasil periksa()
     * @param array<string, mixed> $konteks
     *
     * @return array{ok: bool, dibuat: list<int>, sama: list<string>, gagal: list<string>, pesan: string}
     */
    public function buat(string $jenis, array $h, array $konteks, ?array $p = null): array
    {
        $svc    = new SuratSekolah($this->db);
        $dibuat = [];
        $sama   = [];
        $gagal  = [];

        if ($h['mode'] === 'umum') {
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
            foreach ($this->perusahaan($h['isi']['tgl_mulai'], $h['isi']['tgl_selesai']) as $g) {
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
        if ($sama !== []) {
            $pesan[] = count($sama) . ' dilewati karena sudah ada.';
        }
        if ($gagal !== []) {
            $pesan[] = count($gagal) . ' gagal.';
        }

        return ['ok' => $dibuat !== [], 'dibuat' => $dibuat, 'sama' => $sama, 'gagal' => $gagal, 'pesan' => implode(' ', $pesan)];
    }
}
