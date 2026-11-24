<?php

namespace App\Libraries;

use App\Models\UjianJadwalModel;

/**
 * ISTIRAHAT UJIAN (keluhan klien 2026-11-24, tahap 1) — satu-satunya sumber aturan istirahat untuk tab Jadwal Ujian, cetak Jadwal
 * Ujian (JadwalUjianCetak::matriks), tab & cetak Jadwal Pengawas, dan Generate pengawas (semuanya lewat matriks → sesi()).
 *
 * Per periode + shift + TANGGAL ada tiga keadaan:
 *   otomatis    : belum diatur → dihitung dari jeda jam ujian (perilaku sejak 2026-11-22, tak berubah);
 *   diatur      : admin mengisi jamnya (1–3 istirahat sehari) → HANYA yang diisi itu yang tampil;
 *   ditiadakan  : admin menghapus istirahat hari itu → tidak ada baris ISTIRAHAT walau ada jeda.
 * Istirahat yang diatur tidak boleh menabrak jam ujian shift & hari itu — dijaga dua arah: simpan() di sini dan tolakJadwal() yang
 * dipanggil saat jadwal ujian disimpan/diimpor (web & API). Tabel: ujian_istirahat (migrasi 2026-11-24-000001); selama migrasi belum
 * jalan, semuanya otomatis dan simpan() menolak dengan pesan jelas.
 */
class IstirahatUjian
{
    public const SHIFT = ['pagi', 'siang'];
    public const MODE  = ['atur', 'otomatis', 'tanpa'];

    /** Paling banyak istirahat yang bisa diatur dalam satu hari. */
    public const MAKS = 3;

    private const TABEL = 'ujian_istirahat';
    private const HARI  = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    /** Tabelnya sudah ada (migrasi sudah jalan)? */
    public static function siap(): bool
    {
        return db_connect()->tableExists(self::TABEL);
    }

    // =================================================================
    //  Baca
    // =================================================================

    /**
     * Pengaturan satu periode: [shift][tanggal] => daftar jam. Tanggal yang TIDAK ADA kuncinya = otomatis; daftar kosong = ditiadakan.
     *
     * @return array<string, array<string, list<array{mulai: string, selesai: string}>>>
     */
    public static function pengaturan(int $periodeId): array
    {
        $out = array_fill_keys(self::SHIFT, []);
        if (! self::siap()) {
            return $out;
        }
        foreach (db_connect()->table(self::TABEL)->select('shift, tanggal, jam_mulai, jam_selesai')->where('periode_id', $periodeId)
            ->orderBy('tanggal', 'ASC')->orderBy('jam_mulai', 'ASC')->get()->getResultArray() as $r) {
            $out[$r['shift']][$r['tanggal']] ??= [];
            if ($r['jam_mulai'] !== null && $r['jam_selesai'] !== null) {
                $out[$r['shift']][$r['tanggal']][] = ['mulai' => (string) $r['jam_mulai'], 'selesai' => (string) $r['jam_selesai']];
            }
        }

        return $out;
    }

    /**
     * Susun satu hari: sesi ujian (urut jam) + baris istirahat di tempatnya.
     *
     * @param list<string>                                        $slots "mulai|selesai" per sesi (jam kosong = string kosong)
     * @param list<array{mulai: string, selesai: string}>|null    $atur  null = otomatis dari jeda; [] = ditiadakan; isi = jam yang diatur
     *
     * @return list<array{jenis: string, mulai: string, selesai: string, slot: string}> slot hanya berisi untuk jenis "sesi"
     */
    public static function susun(array $slots, ?array $atur): array
    {
        usort($slots, static fn (string $a, string $b): int => strcmp($a, $b));
        $antre     = array_values($atur ?? []);
        $istirahat = static fn (string $m, string $s): array => ['jenis' => 'istirahat', 'mulai' => $m, 'selesai' => $s, 'slot' => ''];
        $baris     = [];
        $akhir     = null; // jam selesai terbesar sejauh ini → celah = istirahat otomatis
        foreach ($slots as $slot) {
            [$mulai, $selesai] = explode('|', $slot);
            if ($atur === null) {
                if ($akhir !== null && $mulai !== '' && $mulai > $akhir) {
                    $baris[] = $istirahat($akhir, $mulai);
                }
            } else {
                while ($antre !== [] && $mulai !== '' && $antre[0]['mulai'] <= $mulai) {
                    $i       = array_shift($antre);
                    $baris[] = $istirahat($i['mulai'], $i['selesai']);
                }
            }
            $baris[] = ['jenis' => 'sesi', 'mulai' => $mulai, 'selesai' => $selesai, 'slot' => $slot];
            if ($selesai !== '' && ($akhir === null || $selesai > $akhir)) {
                $akhir = $selesai;
            }
        }
        foreach ($antre as $i) { // diatur sesudah ujian terakhir (ujian berikutnya mungkin belum diisi)
            $baris[] = $istirahat($i['mulai'], $i['selesai']);
        }

        return $baris;
    }

    /**
     * Ringkasan per shift + hari untuk tab Jadwal Ujian. Istirahat diambil dari susunan yang DICETAK (matriks); hari yang jadwalnya
     * belum cocok dengan kelas mana pun (belum tercetak) dihitung dari jadwalnya sendiri dan diberi catatan.
     *
     * @return list<array{shift: string, tanggal: string, hari: string, tgl: string, mode: string, jam: list<array{mulai: string, selesai: string}>, sesi: list<string>, adaJadwal: bool, catatan: string}>
     */
    public static function ringkas(array $periode): array
    {
        $atur   = self::pengaturan((int) $periode['id']);
        $jadwal = self::jadwalHari((int) $periode['id']);
        $out    = [];
        foreach (self::SHIFT as $shift) {
            $cetak = [];
            foreach (JadwalUjianCetak::matriks($periode, $shift)['hari'] as $h) {
                $cetak[$h['tanggal']] = $h['baris'];
            }
            $tanggals = array_unique(array_merge(array_keys($jadwal[$shift]), array_keys($atur[$shift])));
            sort($tanggals);
            foreach ($tanggals as $tanggal) {
                $aturHari  = $atur[$shift][$tanggal] ?? null;
                $adaJadwal = isset($jadwal[$shift][$tanggal]);
                $baris     = $cetak[$tanggal] ?? self::susun($adaJadwal ? self::slots($jadwal[$shift][$tanggal]) : [], $aturHari);
                $jam       = [];
                $sesi      = [];
                foreach ($baris as $b) {
                    if ($b['jenis'] === 'istirahat') {
                        $jam[] = ['mulai' => (string) $b['mulai'], 'selesai' => (string) $b['selesai']];
                    } else {
                        $sesi[] = ['mulai' => (string) $b['mulai'], 'selesai' => (string) $b['selesai']];
                    }
                }
                $out[] = [
                    'shift'     => $shift,
                    'tanggal'   => (string) $tanggal,
                    'hari'      => self::namaHari((string) $tanggal),
                    'tgl'       => date('d/m/Y', strtotime((string) $tanggal)),
                    'mode'      => $aturHari === null ? 'otomatis' : ($aturHari === [] ? 'ditiadakan' : 'diatur'),
                    'jam'       => $jam,
                    'sesi'      => array_map(static fn (array $s): string => JadwalUjianCetak::waktu($s['mulai'], $s['selesai']), $sesi),
                    'adaJadwal' => $adaJadwal,
                    'catatan'   => self::catatan($aturHari, $jam, $sesi, $adaJadwal, isset($cetak[$tanggal])),
                ];
            }
        }

        return $out;
    }

    /** Catatan satu hari (kenapa istirahat tidak ada / apa yang perlu diperiksa); '' = tak ada yang perlu dicatat. */
    private static function catatan(?array $atur, array $jam, array $sesi, bool $adaJadwal, bool $tercetak): string
    {
        if (! $adaJadwal) {
            return 'Tidak ada jadwal ujian di hari ini, jadi pengaturan ini tidak dipakai — boleh dihapus.';
        }
        if (! $tercetak) {
            return 'Jadwal hari ini belum cocok dengan kelas mana pun di Master Kelas (tingkat, jurusan, shift), jadi belum ikut tercetak.';
        }
        if ($atur !== null) {
            foreach ($jam as $i) {
                foreach ($sesi as $s) {
                    if ($s['mulai'] !== '' && UjianJadwalModel::jamBerimpit($i['mulai'], $i['selesai'], $s['mulai'], $s['selesai'])) {
                        return 'Istirahat ' . JadwalUjianCetak::waktu($i['mulai'], $i['selesai']) . ' menabrak ujian ' . JadwalUjianCetak::waktu($s['mulai'], $s['selesai']) . ' — betulkan salah satunya.';
                    }
                }
            }

            return '';
        }
        if ($jam !== []) {
            return '';
        }
        foreach ($sesi as $s) {
            if ($s['mulai'] === '' || $s['selesai'] === '') {
                return 'Ada ujian yang jam mulai/selesainya kosong, jadi jedanya tidak bisa dihitung. Lengkapi jamnya, atau atur istirahat sendiri.';
            }
        }
        if (count($sesi) < 2) {
            return 'Baru ada satu jam ujian di hari ini.';
        }
        foreach ($sesi as $a => $x) {
            foreach (array_slice($sesi, $a + 1) as $y) {
                if ($y['mulai'] < $x['selesai']) {
                    return 'Jam ujiannya bertumpuk: ' . JadwalUjianCetak::waktu($x['mulai'], $x['selesai']) . ' dan ' . JadwalUjianCetak::waktu($y['mulai'], $y['selesai']) . ' — periksa, mungkin ada jam yang salah isi.';
                }
            }
        }

        return 'Jam ujiannya menyambung tanpa jeda. Geser dulu jam ujian sesudahnya, baru istirahat bisa dipasang.';
    }

    // =================================================================
    //  Simpan
    // =================================================================

    /**
     * Simpan pengaturan istirahat.
     *
     * @param string              $tanggal "TTTT-BB-HH" atau "semua" (= semua hari ujian shift itu)
     * @param string              $mode    atur | otomatis | tanpa
     * @param list<array{0: string, 1: string}> $jam pasangan [mulai, selesai] "JJ:MM" (hanya untuk mode atur; pasangan kosong diabaikan)
     *
     * @return array{ok: bool, pesan: string, galat: list<string>, catatan: string}
     */
    public static function simpan(array $periode, string $shift, string $tanggal, string $mode, array $jam = []): array
    {
        $gagal = static fn (string ...$p): array => ['ok' => false, 'pesan' => $p[0], 'galat' => array_values($p), 'catatan' => ''];
        if (! self::siap()) {
            return $gagal('Tabel istirahat belum ada di server ini. Jalankan migrasi dulu (php spark migrate), lalu coba lagi.');
        }
        if (! in_array($shift, self::SHIFT, true) || ! in_array($mode, self::MODE, true)) {
            return $gagal('Pilihan istirahat tidak dikenal. Muat ulang halaman, lalu coba lagi.');
        }
        $pid    = (int) $periode['id'];
        $jadwal = self::jadwalHari($pid)[$shift];
        $atur   = self::pengaturan($pid)[$shift];

        // Hari yang dituju.
        if ($tanggal === 'semua') {
            $tuju = array_keys($jadwal);
            if ($mode === 'otomatis') {
                $tuju = array_values(array_unique(array_merge($tuju, array_keys($atur)))); // ikut membersihkan hari tanpa jadwal
            }
            if ($tuju === []) {
                return $gagal('Belum ada jadwal ujian ' . $shift . '. Isi jadwal ujiannya dulu, baru atur istirahat.');
            }
        } else {
            $sah = $mode === 'otomatis' ? isset($jadwal[$tanggal]) || isset($atur[$tanggal]) : isset($jadwal[$tanggal]);
            if (! $sah) {
                return $gagal('Tidak ada jadwal ujian ' . $shift . ' di hari itu. Muat ulang halaman, lalu pilih harinya lagi.');
            }
            $tuju = [$tanggal];
        }

        $lewat = [];
        if ($mode === 'atur') {
            [$jam, $salah] = self::periksaJam($jam);
            if ($salah !== []) {
                return $gagal(...$salah);
            }
            $boleh = [];
            foreach ($tuju as $t) {
                $sebab = self::sebabTakBisa($jadwal[$t], $jam, $tanggal === 'semua');
                if ($sebab === '') {
                    $boleh[] = $t;
                } else {
                    $lewat[] = self::labelHari($t) . ': ' . $sebab;
                }
            }
            if ($boleh === []) {
                return $tanggal === 'semua'
                    ? $gagal('Istirahat ' . self::teksJam($jam) . ' tidak bisa dipasang di satu hari pun:', ...$lewat)
                    : $gagal('Istirahat belum disimpan. ' . $lewat[0]);
            }
            $tuju = $boleh;
        }

        $db = db_connect();
        $db->transException(true);
        $db->transStart();
        $now = date('Y-m-d H:i:s');
        foreach ($tuju as $t) {
            $db->table(self::TABEL)->where('periode_id', $pid)->where('shift', $shift)->where('tanggal', $t)->delete();
            if ($mode === 'otomatis') {
                continue;
            }
            $baris = $mode === 'tanpa' ? [['mulai' => null, 'selesai' => null]] : $jam;
            $db->table(self::TABEL)->insertBatch(array_map(static fn (array $j): array => [
                'periode_id' => $pid, 'shift' => $shift, 'tanggal' => $t, 'jam_mulai' => $j['mulai'], 'jam_selesai' => $j['selesai'], 'created_at' => $now, 'updated_at' => $now,
            ], $baris));
        }
        $db->transComplete();

        $untuk = count($tuju) === 1 && $tanggal !== 'semua' ? $shift . ' ' . self::labelHari($tuju[0]) : $shift . ' (' . count($tuju) . ' hari ujian)';
        $pesan = match ($mode) {
            'atur'     => 'Istirahat ' . $untuk . ' disimpan: ' . self::teksJam($jam) . '.',
            'tanpa'    => 'Istirahat ' . $untuk . ' dihapus — baris ISTIRAHAT tidak tampil di jadwal.',
            'otomatis' => 'Istirahat ' . $untuk . ' kembali otomatis, dihitung dari jeda jam ujian.',
        };
        if ($mode === 'atur' && $tanggal !== 'semua' && ! self::diAntaraUjian($jadwal[$tuju[0]], $jam)) {
            $pesan .= ' Catatan: istirahat ini tidak berada di antara dua ujian — periksa apakah jadwal hari itu sudah lengkap.';
        }
        // Pengawas yang sudah terisi tidak disentuh; pola "sesudah istirahat pindah ruang" baru mengikuti saat Generate ulang.
        $ada = $db->table('ujian_pengawas_ruang')->where('periode_id', $pid)->where('shift', $shift)->whereIn('tanggal', $tuju)->countAllResults();
        if ($ada > 0) {
            $pesan .= ' Pengawas yang sudah terisi tidak berubah; Generate ulang bila ingin pola pindah ruang mengikuti istirahat ini.';
        }

        return ['ok' => true, 'pesan' => $pesan, 'galat' => $lewat === [] ? [] : array_merge([count($lewat) . ' hari dilewati:'], $lewat),
            'catatan' => ($mode === 'atur' ? self::teksJam($jam) : $mode) . ' → ' . $untuk];
    }

    /**
     * Alasan jadwal ujian ini harus ditolak karena menabrak istirahat yang DIATUR (istirahat otomatis tak pernah menolak); null = boleh.
     *
     * @param array{periode_id: int, shift: string, tanggal: string, jam_mulai: ?string, jam_selesai: ?string} $data
     */
    public static function tolakJadwal(array $data): ?string
    {
        if (empty($data['jam_mulai']) || empty($data['jam_selesai']) || empty($data['tanggal'])) {
            return null;
        }
        $atur = self::pengaturan((int) $data['periode_id']);
        foreach ($data['shift'] === 'semua' ? self::SHIFT : [(string) $data['shift']] as $shift) {
            foreach ($atur[$shift][$data['tanggal']] ?? [] as $i) {
                if (UjianJadwalModel::jamBerimpit($data['jam_mulai'], $data['jam_selesai'], $i['mulai'], $i['selesai'])) {
                    return 'Jam ujian ' . JadwalUjianCetak::waktu($data['jam_mulai'], $data['jam_selesai']) . ' menabrak istirahat ' . $shift . ' '
                        . self::teksJam([$i]) . ' yang diatur untuk ' . self::labelHari((string) $data['tanggal'])
                        . '. Ubah jam ujiannya, atau ubah dulu istirahatnya di bagian Istirahat.';
                }
            }
        }

        return null;
    }

    // =================================================================
    //  Pembantu
    // =================================================================

    /** "Senin, 30/11/2026". */
    public static function labelHari(string $tanggal): string
    {
        return self::namaHari($tanggal) . ', ' . date('d/m/Y', strtotime($tanggal));
    }

    /** "08.30 - 09.10 dan 10.40 - 11.00". @param list<array{mulai: string, selesai: string}> $jam */
    public static function teksJam(array $jam): string
    {
        return implode(' dan ', array_map(static fn (array $j): string => JadwalUjianCetak::waktu($j['mulai'], $j['selesai']), $jam));
    }

    private static function namaHari(string $tanggal): string
    {
        return self::HARI[(int) date('N', strtotime($tanggal))];
    }

    /**
     * Jadwal per shift + tanggal (jadwal lama/API bershift "semua" masuk ke kedua shift yang punya kelas, sama seperti matriks).
     *
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private static function jadwalHari(int $periodeId): array
    {
        $out   = array_fill_keys(self::SHIFT, []);
        $kelas = null;
        foreach (db_connect()->table('ujian_jadwal uj')
            ->select('uj.shift, uj.tingkat, uj.tanggal, uj.jam_mulai, uj.jam_selesai, m.nama_mapel, j.kode AS jurusan_kode')
            ->join('mata_pelajaran m', 'm.id = uj.mapel_id', 'left')
            ->join('jurusan j', 'j.id = uj.jurusan_id', 'left')
            ->where('uj.periode_id', $periodeId)->where('uj.deleted_at', null)->where('uj.tanggal IS NOT NULL', null, false)
            ->orderBy('uj.tanggal', 'ASC')->orderBy('uj.jam_mulai', 'ASC')->orderBy('uj.jam_selesai', 'ASC')
            ->get()->getResultArray() as $r) {
            foreach ($r['shift'] === 'semua' ? ($kelas ??= JadwalUjianCetak::shiftAda()) : [(string) $r['shift']] as $s) {
                if (isset($out[$s])) {
                    $out[$s][(string) $r['tanggal']][] = $r;
                }
            }
        }

        return $out;
    }

    /** Sesi unik "mulai|selesai" satu hari. @return list<string> */
    private static function slots(array $baris): array
    {
        return array_values(array_unique(array_map(static fn (array $r): string => ($r['jam_mulai'] ?? '') . '|' . ($r['jam_selesai'] ?? ''), $baris)));
    }

    /**
     * Rapikan & periksa isian jam: pasangan kosong diabaikan, diurutkan, tidak boleh saling menimpa.
     *
     * @return array{0: list<array{mulai: string, selesai: string}>, 1: list<string>} [jam, daftar kesalahan]
     */
    private static function periksaJam(array $isian): array
    {
        $baca = static function (mixed $v): ?string {
            $v = is_string($v) ? trim($v) : '';

            return preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v, $m) === 1 ? $m[1] . ':' . $m[2] . ':00' : null;
        };
        $jam   = [];
        $salah = [];
        foreach (array_slice(array_values($isian), 0, self::MAKS) as $n => $p) {
            $mentah = [trim((string) (is_array($p) ? ($p[0] ?? '') : '')), trim((string) (is_array($p) ? ($p[1] ?? '') : ''))];
            if ($mentah[0] === '' && $mentah[1] === '') {
                continue;
            }
            $ke      = 'Istirahat ke-' . ($n + 1) . ': ';
            $mulai   = $baca($mentah[0]);
            $selesai = $baca($mentah[1]);
            if ($mulai === null || $selesai === null) {
                $salah[] = $ke . 'jam mulai dan jam selesai harus diisi lengkap.';
            } elseif ($selesai <= $mulai) {
                $salah[] = $ke . 'jam selesai harus lebih besar dari jam mulai.';
            } else {
                $jam[] = ['mulai' => $mulai, 'selesai' => $selesai];
            }
        }
        if ($salah !== []) {
            return [[], $salah];
        }
        if ($jam === []) {
            return [[], ['Isi jam mulai dan jam selesai istirahat.']];
        }
        usort($jam, static fn (array $a, array $b): int => strcmp($a['mulai'], $b['mulai']));
        for ($i = 1; $i < count($jam); $i++) {
            if ($jam[$i]['mulai'] < $jam[$i - 1]['selesai']) {
                return [[], ['Jam istirahat saling menimpa: ' . self::teksJam([$jam[$i - 1], $jam[$i]]) . '.']];
            }
        }

        return [$jam, []];
    }

    /** Kenapa jam istirahat ini tidak bisa dipasang di hari dengan jadwal $baris; '' = bisa. $harusDiAntara: mode "semua hari". */
    private static function sebabTakBisa(array $baris, array $jam, bool $harusDiAntara): string
    {
        foreach ($jam as $i) {
            $tabrak = [];
            foreach ($baris as $r) {
                if (($r['jam_mulai'] ?? '') !== '' && UjianJadwalModel::jamBerimpit($i['mulai'], $i['selesai'], $r['jam_mulai'], $r['jam_selesai'])) {
                    $tabrak[] = $r;
                }
            }
            if ($tabrak !== []) {
                $r = $tabrak[0];

                return 'istirahat ' . self::teksJam([$i]) . ' menabrak ujian ' . JadwalUjianCetak::waktu($r['jam_mulai'], $r['jam_selesai'])
                    . ' (' . ($r['nama_mapel'] ?? 'Tanpa mapel') . ', ' . trim($r['tingkat'] . ' ' . ($r['jurusan_kode'] ?? ''))
                    . (count($tabrak) > 1 ? ', dan ' . (count($tabrak) - 1) . ' jadwal lain' : '') . '). Ubah jam ujiannya dulu.';
            }
        }

        return $harusDiAntara && ! self::diAntaraUjian($baris, $jam) ? 'istirahat tidak berada di antara dua ujian.' : '';
    }

    /** Semua istirahat berada di antara dua ujian (ada ujian sebelum & sesudahnya)? */
    private static function diAntaraUjian(array $baris, array $jam): bool
    {
        foreach ($jam as $i) {
            $sebelum = false;
            $sesudah = false;
            foreach ($baris as $r) {
                $m = (string) ($r['jam_mulai'] ?? '');
                if ($m === '') {
                    continue;
                }
                $sebelum = $sebelum || $m < $i['mulai'];
                $sesudah = $sesudah || $m >= $i['selesai'];
            }
            if (! $sebelum || ! $sesudah) {
                return false;
            }
        }

        return true;
    }
}
