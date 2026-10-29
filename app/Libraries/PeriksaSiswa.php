<?php

namespace App\Libraries;

/**
 * Periksa Data Siswa (permintaan user 2026-10-29, sesudah kasus data ganda Riska/Rizka & 6 siswa isian asal): temuan ditampilkan di
 * Dashboard "Tugas hari ini" dan halaman Master Siswa → Periksa data (admin/master/siswa/periksa). Hanya MEMBACA.
 *
 *   ganda       — kemungkinan satu orang tercatat dua kali: nama mirip (huruf saja / bunyi / beda 1–2 huruf) di tingkat yang sama
 *                 (atau tanggal lahir sama) DAN salah satunya belum lengkap (tanggal lahir kosong / NISN tidak wajar). Dua data lengkap
 *                 (NISN sah berbeda) selalu dianggap dua orang — termasuk anak kembar.
 *   tidak_sesuai — siswa aktif dengan NIS/NISN tidak wajar (bukan angka, panjang salah, angka berulang) atau tanggal lahir kosong;
 *                 `bisa_masuk` = false bila NISN tidak sah DAN tanggal lahir kosong (tidak bisa masuk Layanan Siswa / Biodata).
 *   penempatan  — kelas hasil penempatan otomatis (`siswa:tempatkan-kelas`, keterangan "Perlu penempatan kelas …").
 *   + jumlah tidak aktif, terhapus (lama), tanpa kelas.
 * Hasil di-cache lewat master_cache('siswa') — otomatis segar saat data siswa berubah.
 */
class PeriksaSiswa
{
    public const KUNCI_CACHE = 'periksa|v1';

    /** @return array{ganda: list<array>, tidak_sesuai: list<array>, penempatan: list<array>, jumlah: array<string, int>} */
    public static function hasil(): array
    {
        return master_cache('siswa', self::KUNCI_CACHE, 600, static fn (): array => self::hitung());
    }

    public static function hitung(): array
    {
        $db   = db_connect();
        $rows = $db->table('siswa s')->select('s.id, s.nis, s.nisn, s.nama, s.tanggal_lahir, s.kelas_id, s.status, s.keterangan, k.nama_kelas, k.tingkat')
            ->join('kelas k', 'k.id = s.kelas_id', 'left')->where('s.deleted_at', null)->orderBy('s.nama')->get()->getResultArray();
        $aktif = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'aktif'));

        $tidakSesuai = [];
        foreach ($aktif as $s) {
            $m = self::masalah($s);
            if ($m !== []) {
                $tidakSesuai[] = self::ringkas($s) + ['masalah' => $m, 'bisa_masuk' => self::bisaMasuk($s)];
            }
        }
        $penempatan = array_values(array_map([self::class, 'ringkas'], array_filter($aktif,
            static fn (array $r): bool => str_starts_with((string) $r['keterangan'], 'Perlu penempatan kelas'))));
        $ganda = self::ganda($aktif);

        return [
            'ganda'        => $ganda,
            'tidak_sesuai' => $tidakSesuai,
            'penempatan'   => $penempatan,
            'jumlah'       => [
                'ganda'         => count($ganda),
                'tidak_sesuai'  => count($tidakSesuai),
                'tidak_bisa_masuk' => count(array_filter($tidakSesuai, static fn (array $r): bool => ! $r['bisa_masuk'])),
                'penempatan'    => count($penempatan),
                'tidak_aktif'   => count($rows) - count($aktif),
                'terhapus'      => (int) $db->table('siswa')->where('deleted_at IS NOT NULL')->countAllResults(),
                'tanpa_kelas'   => count(array_filter($aktif, static fn (array $r): bool => $r['kelas_id'] === null)),
            ],
        ];
    }

    /** NIS wajar: 4–12 angka, bukan angka berulang. */
    public static function nisWajar(?string $nis): bool
    {
        $nis = trim((string) $nis);

        return (bool) preg_match('/^\d{4,12}$/', $nis) && ! preg_match('/(\d)\1{5,}/', $nis);
    }

    /** NISN sah: tepat 10 angka, bukan angka berulang. */
    public static function nisnSah(?string $nisn): bool
    {
        $nisn = trim((string) $nisn);

        return (bool) preg_match('/^\d{10}$/', $nisn) && ! preg_match('/(\d)\1{5,}/', $nisn);
    }

    /** Bisa membuktikan diri di Layanan Siswa / Biodata (NISN sah ATAU tanggal lahir terisi). */
    public static function bisaMasuk(array $s): bool
    {
        return self::nisnSah($s['nisn'] ?? null) || ! empty($s['tanggal_lahir']);
    }

    /** @return list<string> */
    public static function masalah(array $s): array
    {
        $m = [];
        if (! self::nisWajar($s['nis'] ?? null)) {
            $m[] = 'NIS tidak wajar';
        }
        if (trim((string) ($s['nisn'] ?? '')) === '') {
            $m[] = 'NISN kosong';
        } elseif (! self::nisnSah($s['nisn'])) {
            $m[] = 'NISN tidak wajar';
        }
        if (empty($s['tanggal_lahir'])) {
            $m[] = 'Tanggal lahir kosong';
        }
        if (! self::bisaMasuk($s)) {
            $m[] = 'Tidak bisa masuk Layanan Siswa';
        }

        return $m;
    }

    /**
     * Pasangan kemungkinan data ganda (lihat kelas). Pembanding hanya: siswa "belum lengkap" × siswa setingkat, dan siswa bertanggal
     * lahir sama — jadi cepat walau ribuan siswa.
     *
     * @return list<array{a: array, b: array, alasan: string}>
     */
    public static function ganda(array $aktif): array
    {
        $kunci = static fn (string $nama): string => strtolower((string) preg_replace('/[^a-z]/i', '', $nama));
        $info  = [];
        foreach ($aktif as $s) {
            $k            = $kunci((string) $s['nama']);
            $info[$s['id']] = $s + ['k' => $k, 'bunyi' => metaphone($k), 'lengkap' => ! empty($s['tanggal_lahir']) && self::nisnSah($s['nisn'])];
        }
        $pasang = [];
        $cek    = static function (array $a, array $b) use (&$pasang): void {
            if ($a['id'] === $b['id']) {
                return;
            }
            $ab = min($a['id'], $b['id']) . '-' . max($a['id'], $b['id']);
            if (isset($pasang[$ab])) {
                return;
            }
            // Dua data LENGKAP (tanggal lahir + NISN sah yang pasti berbeda — NISN unik nasional) = dua orang berbeda, termasuk anak
            // kembar (tanggal lahir sama, nama mirip: terbukti di data hosting, mis. Raysya/Reysya, Sahira/Zahira).
            if ($a['lengkap'] && $b['lengkap']) {
                return;
            }
            $tglSama = ! empty($a['tanggal_lahir']) && $a['tanggal_lahir'] === $b['tanggal_lahir'];
            if ($a['tingkat'] !== null && $b['tingkat'] !== null && $a['tingkat'] !== $b['tingkat'] && ! $tglSama) {
                return;
            }
            $alasan = PeriksaSiswa::mirip($a, $b);
            if ($alasan !== null) {
                [$x, $y]      = $a['lengkap'] || ! $b['lengkap'] ? [$a, $b] : [$b, $a]; // data yang lebih lengkap di kiri
                $pasang[$ab] = ['a' => self::ringkas($x), 'b' => self::ringkas($y), 'alasan' => $alasan . ($tglSama ? ', tanggal lahir sama' : ', salah satu data belum lengkap')];
            }
        };
        $perTingkat = [];
        $perTgl     = [];
        foreach ($info as $s) {
            $perTingkat[(string) $s['tingkat']][] = $s;
            if (! empty($s['tanggal_lahir'])) {
                $perTgl[$s['tanggal_lahir']][] = $s;
            }
        }
        foreach ($info as $s) {
            if ($s['lengkap']) {
                continue;
            }
            $lawan = $s['tingkat'] === null ? $info : array_merge($perTingkat[(string) $s['tingkat']] ?? [], $perTingkat[''] ?? []);
            foreach ($lawan as $t) {
                $cek($s, $t);
            }
        }
        foreach ($perTgl as $grup) {
            $n = count($grup);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $cek($grup[$i], $grup[$j]);
                }
            }
        }

        return array_values($pasang);
    }

    /** Alasan kemiripan nama, atau null. Nama dibandingkan sebagai huruf saja (spasi/tanda baca diabaikan). */
    public static function mirip(array $a, array $b): ?string
    {
        if ($a['k'] === '' || $b['k'] === '') {
            return null;
        }
        if ($a['k'] === $b['k']) {
            return 'nama sama';
        }
        $pendek = min(strlen($a['k']), strlen($b['k']));
        if ($pendek >= 4 && $a['bunyi'] === $b['bunyi'] && $a['k'][0] === $b['k'][0] && abs(strlen($a['k']) - strlen($b['k'])) <= 2) {
            return 'nama berbunyi sama';
        }
        if ($pendek >= 8 && levenshtein($a['k'], $b['k']) <= max(1, (int) floor($pendek * 0.12))) {
            return 'nama hampir sama';
        }

        return null;
    }

    private static function ringkas(array $s): array
    {
        return ['id' => (int) $s['id'], 'nama' => (string) $s['nama'], 'nis' => (string) $s['nis'], 'nisn' => $s['nisn'], 'tanggal_lahir' => $s['tanggal_lahir'],
            'kelas' => $s['nama_kelas'], 'status' => (string) $s['status']];
    }
}
