<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Data siswa RESMI sekolah (Daftar Nama Siswa, Format 8355) → Master Siswa.
 * Rancangan & temuan: docs/RENCANA-DATA-SISWA-8355.md. Dipakai perintah `siswa:data-8355`.
 *
 * PRIVASI: berkas Excel berisi data pribadi ±1.700 siswa dan repo ini PUBLIK, jadi berkasnya TIDAK ada di repo;
 * dibaca dari server (writable/data-sekolah/), diunggah manual.
 *
 * Tiga tahap, masing-masing fungsi terpisah supaya bisa diuji:
 *   baca()      Excel → baris bersih (+ daftar anomali di berkas)
 *   rencana()   baris Excel × siswa di database → pasangan, siswa baru, yang ditahan, bentrok  (TANPA menulis)
 *   terapkan()  tulis rencana dalam SATU transaksi (+ cadangan nilai lama)
 *
 * Kunci pencocokan: BUKAN nomor urut (nomor urut Excel ≠ NIS sementara di sistem). Urutan percobaan:
 *   0. NIS (hanya NIS asli ≥ 5 digit; NIS sementara di sistem cuma nomor urut 1…1718)
 *   1. nama persis (huruf kecil, tanda baca dibuang) + tingkat + jurusan; nama kembar dipasangkan menurut urutan
 *   2. nama mirip dalam tingkat+jurusan yang sama: salah ketik, singkatan/inisial, atau nama lebih panjang
 *      → HANYA yang yakin diterapkan; yang meragukan ditahan di laporan (bisa dipaksa dengan --gabung)
 * Excel tidak memuat rombel, jadi kelas siswa lama TIDAK diubah; siswa baru masuk tanpa kelas.
 */
final class SiswaResmi8355
{
    /** Kolom yang boleh diisi siswa sendiri lewat Isian Biodata: bila siswa sudah punya biodata disahkan, nilai lamanya dipertahankan. */
    public const KOLOM_BIODATA = ['tempat_lahir', 'tanggal_lahir', 'agama', 'ortu_alamat'];

    /** Kolom yang selalu ditulis Excel (data resmi sekolah). */
    public const KOLOM_RESMI = ['nis', 'nisn', 'nama', 'jenis_kelamin', 'nama_orang_tua', 'sttb_nomor', 'sttb_tahun', 'tahun_masuk'];

    private const AGAMA = [
        'islam' => 'Islam', 'kristen' => 'Kristen', 'protestan' => 'Kristen', 'katolik' => 'Katolik', 'katholik' => 'Katolik',
        'katholic' => 'Katolik', 'hindu' => 'Hindu', 'budha' => 'Buddha', 'buddha' => 'Buddha', 'konghucu' => 'Konghucu', 'khonghucu' => 'Konghucu',
    ];

    // ================================================================= 1. BACA EXCEL

    /**
     * @return array{baris: list<array<string, mixed>>, anomali: list<array{string, string, string}>, tp: ?int}
     *         anomali = [jenis, rujukan, keterangan]; tp = tahun awal tahun pelajaran dari judul (mis. 2026)
     */
    public static function baca(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException('Berkas tidak ditemukan: ' . $path);
        }
        $ws   = IOFactory::load($path)->getSheet(0);
        $maks = (int) $ws->getHighestDataRow();
        $tp   = preg_match('/(\d{4})\s*\/\s*(\d{4})/', (string) $ws->getCell('A1')->getValue(), $m) ? (int) $m[1] : null;

        $baris   = [];
        $anomali = [];
        $bagian  = '';
        for ($r = 1; $r <= $maks; $r++) {
            $a = trim((string) $ws->getCell([1, $r])->getFormattedValue());
            if ($a !== '' && preg_match('/^Kelas\s+/i', $a)) {
                $bagian = $a;

                continue;
            }
            $nis = trim((string) $ws->getCell([2, $r])->getFormattedValue());
            if (! ($a !== '' && ctype_digit($a) && $nis !== '')) {
                continue; // tajuk ulang, tanda tangan, ringkasan
            }
            $rujuk = 'baris ' . $r . ' (No. ' . $a . ')';
            $nisn  = trim((string) $ws->getCell([3, $r])->getFormattedValue());
            if (ctype_digit($nisn) && strlen($nisn) < 10) {
                $nisn = str_pad($nisn, 10, '0', STR_PAD_LEFT);
            }
            if ($nisn !== '' && ! preg_match('/^\d{10}$/', $nisn)) {
                $anomali[] = ['nisn_tak_wajar', $rujuk, 'NISN "' . $nisn . '" bukan 10 digit'];
            }
            if (! ctype_digit($nis)) {
                $anomali[] = ['nis_bukan_angka', $rujuk, 'NIS "' . $nis . '"'];
            }

            [$tingkat, $jurusan] = self::bagian($bagian);
            $nama = self::rapikan((string) $ws->getCell([4, $r])->getFormattedValue());
            $jk   = strtoupper(trim((string) $ws->getCell([5, $r])->getFormattedValue()));
            $tgl  = self::tanggal($ws->getCell([7, $r]), $anomali, $rujuk . ' ' . $nama);

            $agamaAsal = trim((string) $ws->getCell([8, $r])->getFormattedValue());
            $agama     = self::AGAMA[mb_strtolower($agamaAsal)] ?? IsianBantu::judul($agamaAsal);
            if ($agamaAsal !== '' && ! isset(self::AGAMA[mb_strtolower($agamaAsal)])) {
                $anomali[] = ['agama_tak_dikenal', $rujuk . ' ' . $nama, $agamaAsal];
            }

            $row = [
                'baris' => $r, 'no' => (int) $a, 'bagian' => $bagian, 'tingkat' => $tingkat, 'jurusan' => $jurusan,
                'nis' => $nis, 'nisn' => $nisn !== '' ? $nisn : null, 'nama' => IsianBantu::judul($nama),
                'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
                'tempat_lahir' => IsianBantu::judul(self::rapikan((string) $ws->getCell([6, $r])->getFormattedValue())) ?: null,
                'tanggal_lahir' => $tgl, 'agama' => $agama ?: null,
                'nama_orang_tua' => IsianBantu::judul(self::rapikan((string) $ws->getCell([9, $r])->getFormattedValue())) ?: null,
                'ortu_alamat' => self::rapikan((string) $ws->getCell([10, $r])->getFormattedValue()) ?: null,
                'sttb_nomor' => self::rapikan((string) $ws->getCell([11, $r])->getFormattedValue()) ?: null,
                'sttb_tahun' => ctype_digit(trim((string) $ws->getCell([12, $r])->getFormattedValue())) ? (int) trim((string) $ws->getCell([12, $r])->getFormattedValue()) : null,
            ];
            $row['tahun_masuk'] = self::tahunMasuk($nis);

            if ($jk !== '' && $row['jenis_kelamin'] === null) {
                $anomali[] = ['jk_tak_dikenal', $rujuk . ' ' . $nama, $jk];
            }
            if (strlen($nis) !== 9) {
                $anomali[] = ['nis_bukan_9_digit', $rujuk . ' ' . $nama, 'NIS "' . $nis . '" (biasanya 9 digit). Dipakai apa adanya.'];
            } elseif ($row['tahun_masuk'] !== null && $tp !== null && $tingkat !== '') {
                $harus = ['X' => $tp, 'XI' => $tp - 1, 'XII' => $tp - 2][$tingkat] ?? null;
                if ($harus !== null && $row['tahun_masuk'] !== $harus) {
                    $anomali[] = ['nis_tak_sesuai_tingkat', $rujuk . ' ' . $nama, 'NIS ' . $nis . ' = angkatan ' . $row['tahun_masuk'] . ' tetapi tercatat di bagian "' . $bagian . '"'];
                }
            }
            if ($row['ortu_alamat'] === null) {
                $anomali[] = ['alamat_ortu_kosong', $rujuk . ' ' . $nama, 'alamat orang tua kosong'];
            }
            $baris[] = $row;
        }

        // NIS / NISN ganda di dalam berkas
        foreach (['nis', 'nisn'] as $k) {
            $hitung = array_count_values(array_filter(array_column($baris, $k), static fn ($v) => $v !== null && $v !== ''));
            foreach (array_filter($hitung, static fn ($n) => $n > 1) as $nilai => $n) {
                $anomali[] = [$k . '_ganda', (string) $nilai, 'muncul ' . $n . ' kali di berkas'];
            }
        }

        return ['baris' => $baris, 'anomali' => $anomali, 'tp' => $tp];
    }

    /** @return array{string, string} [tingkat, jurusan(TKJ|AKL|MP)] dari judul bagian "Kelas X TEKNIK KOMPUTER DAN JARINGAN". */
    private static function bagian(string $judul): array
    {
        $t = preg_match('/^Kelas\s+(XII|XI|X)\b/i', $judul, $m) ? strtoupper($m[1]) : '';
        $u = strtoupper($judul);

        return [$t, str_contains($u, 'KOMPUTER') ? 'TKJ' : (str_contains($u, 'AKUNTANSI') ? 'AKL' : (str_contains($u, 'PERKANTORAN') ? 'MP' : ''))];
    }

    private static function rapikan(string $s): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return in_array($s, ['-', '--', '—', '–'], true) ? '' : $s; // tanda "-" di berkas sekolah = kosong
    }

    /** Tanggal lahir: teks Y-m-d, d/m/Y, atau angka serial Excel → Y-m-d (atau NULL + anomali). */
    private static function tanggal($sel, array &$anomali, string $rujuk): ?string
    {
        $mentah = $sel->getValue();
        if (is_numeric($mentah) && (float) $mentah > 1000 && (float) $mentah < 80000 && $sel->getDataType() === 'n') {
            $iso = ExcelDate::excelToDateTimeObject((float) $mentah)->format('Y-m-d');
            $anomali[] = ['tgl_angka_serial_diperbaiki', $rujuk, 'angka Excel ' . $mentah . ' → ' . $iso];

            return $iso;
        }
        $t = trim((string) $sel->getFormattedValue());
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $f) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $f, $t);
            $er = \DateTimeImmutable::getLastErrors();
            if ($dt !== false && (! $er || ($er['warning_count'] === 0 && $er['error_count'] === 0))) {
                return $dt->format('Y-m-d');
            }
        }
        $anomali[] = ['tgl_tak_terbaca', $rujuk, 'nilai "' . $t . '" — dikosongkan'];

        return null;
    }

    /** NIS 9 digit [TT][TT+1]10[urut3] → tahun masuk (mis. 262710001 → 2026), selain itu NULL. */
    public static function tahunMasuk(string $nis): ?int
    {
        return preg_match('/^(\d{2})(\d{2})10\d{3}$/', $nis, $m) && (int) $m[2] === (int) $m[1] + 1 ? 2000 + (int) $m[1] : null;
    }

    // ================================================================= 2. RENCANA (tanpa menulis)

    public static function kunciNama(string $s): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s))));
    }

    /** Jurusan database (kode/nama kolom jurusan) → TKJ | AKL | MP | ''. */
    public static function jurusanDb(?string $kode, ?string $nama): string
    {
        $u = strtoupper(trim(($kode ?? '') . ' ' . ($nama ?? '')));

        return str_contains($u, 'TKJ') || str_contains($u, 'KOMPUTER') ? 'TKJ'
            : (str_contains($u, 'AKL') || str_contains($u, 'AKUNTANSI') ? 'AKL'
            : (str_contains($u, 'MPLB') || str_contains($u, 'PERKANTORAN') || preg_match('/\bMP\b/', $u) ? 'MP' : ''));
    }

    /** Semua baris siswa di database (termasuk yang terhapus lunak, untuk memeriksa bentrok NIS/NISN). @return list<array<string, mixed>> */
    public static function muatSiswa(BaseConnection $db): array
    {
        return $db->query(
            'SELECT s.id, s.nis, s.nisn, s.nama, s.jenis_kelamin, s.tempat_lahir, s.tanggal_lahir, s.agama, s.ortu_alamat, s.nama_orang_tua,'
            . ' s.sttb_nomor, s.sttb_tahun, s.tahun_masuk, s.biodata_at, s.kelas_id, s.status, s.deleted_at,'
            . ' k.nama_kelas, k.tingkat, j.kode AS jur_kode, j.nama AS jur_nama'
            . ' FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id LEFT JOIN jurusan j ON j.id = k.jurusan_id ORDER BY s.id'
        )->getResultArray();
    }

    /**
     * @param list<array<string, mixed>> $excel  hasil baca()['baris']
     * @param list<array<string, mixed>> $db     hasil muatSiswa()
     * @param array<string, int>         $paksa  [NIS Excel => id siswa] — pasangan yang dipaksa user (--gabung)
     *
     * @return array<string, mixed>
     */
    public static function rencana(array $excel, array $db, array $paksa = []): array
    {
        // --- siapkan
        $aktif = [];
        foreach ($db as $i => $d) {
            if ($d['deleted_at'] === null) {
                $aktif[$i] = $d + ['n' => self::kunciNama((string) $d['nama']), 'k' => ($d['tingkat'] ?? '') !== '' ? $d['tingkat'] . '|' . self::jurusanDb($d['jur_kode'], $d['jur_nama']) : ''];
            }
        }
        foreach ($excel as $j => &$e) {
            $e['n'] = self::kunciNama((string) $e['nama']);
            $e['k'] = $e['tingkat'] . '|' . $e['jurusan'];
        }
        unset($e);

        $pasang   = []; // list [i(db), j(excel), cara, skor]
        $dipakaiD = [];
        $dipakaiE = [];
        $ikat     = static function (int $i, int $j, string $cara, ?float $skor = null) use (&$pasang, &$dipakaiD, &$dipakaiE): void {
            $pasang[] = [$i, $j, $cara, $skor];
            $dipakaiD[$i] = true;
            $dipakaiE[$j] = true;
        };
        $konflikNis = [];

        $idKeIndeks = [];
        foreach ($aktif as $i => $d) {
            $idKeIndeks[(int) $d['id']] = $i;
        }
        $nisKeExcel = [];
        foreach ($excel as $j => $e) {
            $nisKeExcel[$e['nis']] = $j;
        }

        // --- P. dipaksa user
        foreach ($paksa as $nis => $id) {
            if (isset($nisKeExcel[(string) $nis], $idKeIndeks[(int) $id]) && ! isset($dipakaiE[$nisKeExcel[(string) $nis]]) && ! isset($dipakaiD[$idKeIndeks[(int) $id]])) {
                $ikat($idKeIndeks[(int) $id], $nisKeExcel[(string) $nis], 'dipaksa_user');
            }
        }

        // --- 0. NIS asli yang sama
        foreach ($aktif as $i => $d) {
            if (isset($dipakaiD[$i]) || strlen((string) $d['nis']) < 5 || ! isset($nisKeExcel[(string) $d['nis']])) {
                continue;
            }
            $j = $nisKeExcel[(string) $d['nis']];
            if (isset($dipakaiE[$j])) {
                continue;
            }
            if ($d['n'] === $excel[$j]['n'] || self::skor($d['n'], $excel[$j]['n']) >= 70 || self::tokenCocok(...self::urutPendek($d['n'], $excel[$j]['n']))) {
                $ikat($i, $j, 'nis');
            } else {
                $konflikNis[] = [$i, $j];
            }
        }

        // --- 1. nama persis dalam tingkat+jurusan (kembar → menurut urutan)
        $gD = [];
        foreach ($aktif as $i => $d) {
            if (! isset($dipakaiD[$i]) && $d['k'] !== '') {
                $gD[$d['k'] . '#' . $d['n']][] = $i;
            }
        }
        $gE = [];
        foreach ($excel as $j => $e) {
            if (! isset($dipakaiE[$j])) {
                $gE[$e['k'] . '#' . $e['n']][] = $j;
            }
        }
        foreach ($gD as $kunci => $ids) {
            if (isset($gE[$kunci])) {
                for ($q = 0, $m = min(count($ids), count($gE[$kunci])); $q < $m; $q++) {
                    $ikat($ids[$q], $gE[$kunci][$q], count($ids) === 1 && count($gE[$kunci]) === 1 ? 'nama_persis' : 'nama_kembar_urut');
                }
            }
        }

        // --- 1b. siswa tanpa kelas: nama persis, harus unik di kedua sisi
        $sisaE = [];
        foreach ($excel as $j => $e) {
            if (! isset($dipakaiE[$j])) {
                $sisaE[$e['n']][] = $j;
            }
        }
        foreach ($aktif as $i => $d) {
            if (isset($dipakaiD[$i]) || $d['k'] !== '' || ! isset($sisaE[$d['n']]) || count($sisaE[$d['n']]) !== 1) {
                continue;
            }
            $ikat($i, $sisaE[$d['n']][0], 'nama_persis_tanpa_kelas');
        }

        // --- 2. nama mirip dalam tingkat+jurusan yang sama
        $kandidat = [];
        foreach ($aktif as $i => $d) {
            if (isset($dipakaiD[$i]) || $d['k'] === '') {
                continue;
            }
            foreach ($excel as $j => $e) {
                if (isset($dipakaiE[$j]) || $e['k'] !== $d['k']) {
                    continue;
                }
                $s = self::skor($d['n'], $e['n']);
                [$pendek, $panjang] = self::urutPendek($d['n'], $e['n']);
                $jenis = null;
                if (count(explode(' ', $d['n'])) === count(explode(' ', $e['n'])) && $s >= 85) {
                    $jenis = 'mirip_salah_ketik';
                } elseif (self::tokenCocok($pendek, $panjang) && (count(explode(' ', $pendek)) >= 2 || strlen($pendek) >= 6)) {
                    $jenis = 'mirip_nama_lebih_panjang_atau_singkatan';
                } elseif ($s >= 90) {
                    $jenis = 'mirip_salah_ketik';
                } elseif ($s >= 60) {
                    $jenis = 'lemah';
                }
                if ($jenis !== null) {
                    $kandidat[] = [$jenis === 'lemah' ? $s : 100 + $s, $jenis, $i, $j, $s];
                }
            }
        }
        usort($kandidat, static fn ($p, $q) => $q[0] <=> $p[0]);
        $tinjau = [];
        foreach ($kandidat as [, $jenis, $i, $j, $s]) {
            if (isset($dipakaiD[$i]) || isset($dipakaiE[$j])) {
                continue;
            }
            $jkBeda = $aktif[$i]['jenis_kelamin'] !== null && $excel[$j]['jenis_kelamin'] !== null && $aktif[$i]['jenis_kelamin'] !== $excel[$j]['jenis_kelamin'];
            if ($jenis === 'lemah' || $jkBeda) {
                $tinjau[] = [$i, $j, round($s), $jkBeda ? 'jenis kelamin berbeda' : 'nama kurang mirip'];
                $dipakaiD[$i] = $dipakaiE[$j] = 'tinjau'; // tak dipasangkan lagi dengan yang lain, TAPI tidak diterapkan
            } else {
                $ikat($i, $j, $jenis, $s);
            }
        }

        // --- 2b. Excel hanya menulis NAMA DEPAN ("MONA" untuk "Mona Septya"): hanya bila satu-satunya calon di tingkat+jurusan
        //         itu pada KEDUA sisi, jenis kelamin sama, dan nama depan cukup khas (≥ 4 huruf).
        $calonD = [];
        foreach ($aktif as $i => $d) {
            if (! isset($dipakaiD[$i]) && $d['k'] !== '') {
                $calonD[$d['k'] . '#' . explode(' ', $d['n'])[0]][] = $i;
            }
        }
        $calonE = [];
        foreach ($excel as $j => $e) {
            if (! isset($dipakaiE[$j]) && ! str_contains($e['n'], ' ') && strlen($e['n']) >= 4) {
                $calonE[$e['k'] . '#' . $e['n']][] = $j;
            }
        }
        foreach ($calonE as $kunci => $js) {
            if (count($js) !== 1 || ! isset($calonD[$kunci]) || count($calonD[$kunci]) !== 1) {
                continue;
            }
            $i = $calonD[$kunci][0];
            $j = $js[0];
            if (isset($dipakaiD[$i]) || isset($dipakaiE[$j]) || str_contains($aktif[$i]['n'], ' ') === false) {
                continue;
            }
            if ($aktif[$i]['jenis_kelamin'] === null || $excel[$j]['jenis_kelamin'] === null || $aktif[$i]['jenis_kelamin'] === $excel[$j]['jenis_kelamin']) {
                $ikat($i, $j, 'mirip_nama_depan_saja', 80.0);
            }
        }

        // --- hasil pencocokan
        $baru = [];
        $dbSaja = [];
        foreach ($excel as $j => $e) {
            if (! isset($dipakaiE[$j]) || $dipakaiE[$j] === 'tinjau') {
                $baru[] = $j;
            }
        }
        foreach ($aktif as $i => $d) {
            if (! isset($dipakaiD[$i]) || $dipakaiD[$i] === 'tinjau') {
                $dbSaja[] = $i;
            }
        }

        return self::susunPerubahan($excel, $db, $aktif, $pasang, $tinjau, $baru, $dbSaja, $konflikNis);
    }

    /** @return array{string, string} [lebih pendek, lebih panjang] */
    private static function urutPendek(string $a, string $b): array
    {
        return strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];
    }

    /** Skor kemiripan 0–100 (yang terbesar dari similar_text dan jarak Levenshtein). */
    public static function skor(string $a, string $b): float
    {
        similar_text($a, $b, $p);
        $l = levenshtein(substr($a, 0, 200), substr($b, 0, 200));

        return max($p, 100 * (1 - $l / max(1, max(strlen($a), strlen($b)))));
    }

    /**
     * Tiap kata nama pendek punya pasangan unik di nama panjang, dan SETIDAKNYA SATU kata persis sama.
     * Kata tunggal di nama pendek dianggap inisial: "m" cocok "muhamad", "r" cocok "ramadhani".
     * (Syarat satu kata persis mencegah "mawardah" dianggap cocok dengan "m fauji al farid" lewat huruf "m".)
     */
    public static function tokenCocok(string $pendek, string $panjang): bool
    {
        $tp    = explode(' ', $pendek);
        $tl    = explode(' ', $panjang);
        $pakai = [];
        $persis = 0;
        foreach ($tp as $t) {
            $ok = false;
            // Utamakan kata persis sama, baru inisial.
            foreach ($tl as $j => $u) {
                if (! isset($pakai[$j]) && $t === $u) {
                    $pakai[$j] = true;
                    $ok        = true;
                    $persis++;
                    break;
                }
            }
            if (! $ok && strlen($t) === 1) {
                foreach ($tl as $j => $u) {
                    if (! isset($pakai[$j]) && str_starts_with($u, $t)) {
                        $pakai[$j] = true;
                        $ok        = true;
                        break;
                    }
                }
            }
            if (! $ok) {
                return false;
            }
        }

        return $persis >= 1;
    }

    /**
     * Ubah pasangan menjadi perubahan kolom konkret + semua daftar laporan, periksa bentrok NIS/NISN.
     *
     * @return array<string, mixed>
     */
    private static function susunPerubahan(array $excel, array $db, array $aktif, array $pasang, array $tinjau, array $baru, array $dbSaja, array $konflikNis): array
    {
        // Semua NIS/NISN yang SUDAH dipakai (termasuk siswa terhapus lunak — UNIQUE index tetap berlaku).
        $pakaiNis  = [];
        $pakaiNisn = [];
        foreach ($db as $d) {
            $pakaiNis[(string) $d['nis']] = (int) $d['id'];
            if ($d['nisn'] !== null && $d['nisn'] !== '') {
                $pakaiNisn[(string) $d['nisn']] = (int) $d['id'];
            }
        }

        $ubah = [];
        $bentrok = [];
        $konflikJk = [];
        $bedaBiodata = [];
        $tanpaPerubahan = 0;
        foreach ($pasang as [$i, $j, $cara, $skor]) {
            $d = $aktif[$i];
            $e = $excel[$j];
            $id = (int) $d['id'];
            $set = [];
            $lama = [];

            // NIS — bila sudah dipakai siswa LAIN, seluruh baris ini dilewati.
            if ((string) $d['nis'] !== $e['nis']) {
                if (isset($pakaiNis[$e['nis']]) && $pakaiNis[$e['nis']] !== $id) {
                    $bentrok[] = ['nis', $e['nis'], 'siswa id ' . $id . ' (' . $d['nama'] . ') tidak diubah: NIS sudah dipakai siswa id ' . $pakaiNis[$e['nis']]];

                    continue;
                }
                $set['nis']  = $e['nis'];
                $lama['nis'] = $d['nis'];
            }
            // NISN — bila bentrok, hanya NISN yang dilewati.
            if ($e['nisn'] !== null && (string) ($d['nisn'] ?? '') !== $e['nisn']) {
                if (isset($pakaiNisn[$e['nisn']]) && $pakaiNisn[$e['nisn']] !== $id) {
                    $bentrok[] = ['nisn', $e['nisn'], 'siswa id ' . $id . ' (' . $d['nama'] . '): NISN sudah dipakai siswa id ' . $pakaiNisn[$e['nisn']] . ' — NISN dilewati, kolom lain tetap diisi'];
                } else {
                    $set['nisn']  = $e['nisn'];
                    $lama['nisn'] = $d['nisn'];
                }
            }
            // Kolom resmi lain: Excel menang — KECUALI nama: bila nama di Excel hanya versi LEBIH PENDEK dari nama di sistem
            // (mis. "MONA" untuk "Mona Septya", "M. ILHAM" untuk "Muhamad Ilham"), nama sistem yang lebih lengkap dipertahankan.
            $namaExcelLebihPendek = strlen($e['n']) < strlen($d['n']) && self::tokenCocok($e['n'], $d['n']);
            foreach (['nama', 'jenis_kelamin', 'nama_orang_tua', 'sttb_nomor', 'sttb_tahun', 'tahun_masuk'] as $k) {
                if ($k === 'nama' && $namaExcelLebihPendek) {
                    continue;
                }
                if ($e[$k] !== null && (string) ($d[$k] ?? '') !== (string) $e[$k]) {
                    $set[$k]  = $e[$k];
                    $lama[$k] = $d[$k];
                }
            }
            if ($e['jenis_kelamin'] !== null && $d['jenis_kelamin'] !== null && $d['jenis_kelamin'] !== $e['jenis_kelamin']) {
                $konflikJk[] = [$id, $d['nama'], $d['nama_kelas'] ?? '', $d['jenis_kelamin'], $e['jenis_kelamin'], $e['nis']];
            }
            // Kolom milik Biodata: bila siswa sudah punya biodata disahkan dan nilainya terisi, nilai siswa dipertahankan.
            foreach (self::KOLOM_BIODATA as $k) {
                if ($e[$k] === null || (string) ($d[$k] ?? '') === (string) $e[$k]) {
                    continue;
                }
                if ($d['biodata_at'] !== null && ($d[$k] ?? '') !== '' && $d[$k] !== null) {
                    $bedaBiodata[] = [$id, $d['nama'], $k, (string) $d[$k], (string) $e[$k]];

                    continue;
                }
                $set[$k]  = $e[$k];
                $lama[$k] = $d[$k];
            }

            if ($set === []) {
                $tanpaPerubahan++;

                continue;
            }
            if (isset($set['nis'])) {
                $pakaiNis[$set['nis']] = $id;
            }
            if (isset($set['nisn'])) {
                $pakaiNisn[$set['nisn']] = $id;
            }
            $ubah[] = ['id' => $id, 'set' => $set, 'lama' => $lama, 'cara' => $cara, 'skor' => $skor, 'excel_j' => $j, 'db_i' => $i];
        }

        // Siswa baru (Excel yang tak punya pasangan): tanpa kelas, keterangan memuat tingkat & jurusan resmi.
        $tambah = [];
        foreach ($baru as $j) {
            $e = $excel[$j];
            if (isset($pakaiNis[$e['nis']])) {
                $bentrok[] = ['siswa_baru', $e['nis'], $e['nama'] . ' tidak ditambahkan: NIS sudah dipakai siswa id ' . $pakaiNis[$e['nis']]];

                continue;
            }
            $nisn = $e['nisn'];
            if ($nisn !== null && isset($pakaiNisn[$nisn])) {
                $bentrok[] = ['siswa_baru_nisn', $nisn, $e['nama'] . ': NISN sudah dipakai siswa id ' . $pakaiNisn[$nisn] . ' — ditambahkan tanpa NISN'];
                $nisn      = null;
            }
            $pakaiNis[$e['nis']] = 0;
            if ($nisn !== null) {
                $pakaiNisn[$nisn] = 0;
            }
            $tambah[] = ['excel_j' => $j, 'nisn' => $nisn];
        }

        return compact('excel', 'db', 'aktif', 'pasang', 'tinjau', 'dbSaja', 'konflikNis', 'ubah', 'tambah', 'bentrok', 'konflikJk', 'bedaBiodata', 'tanpaPerubahan');
    }

    // ================================================================= 3. TERAPKAN

    /**
     * Tulis rencana dalam SATU transaksi (gagal = tidak ada yang berubah). $dir = folder cadangan nilai lama.
     *
     * @return array{diubah: int, baru: int, cadangan: ?string}
     */
    public static function terapkan(BaseConnection $db, array $rencana, string $dirCadangan): array
    {
        $sekarang = date('Y-m-d H:i:s');
        $cadangan = null;
        if ($rencana['ubah'] !== []) {
            if (! is_dir($dirCadangan)) {
                @mkdir($dirCadangan, 0775, true);
            }
            $cadangan = rtrim($dirCadangan, '/\\') . DIRECTORY_SEPARATOR . 'cadangan-' . date('Ymd-His') . '.csv';
            $f        = fopen($cadangan, 'wb');
            fwrite($f, "\xEF\xBB\xBF");
            $kolom = ['id', 'nis', 'nisn', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'ortu_alamat', 'nama_orang_tua', 'sttb_nomor', 'sttb_tahun', 'tahun_masuk'];
            fputcsv($f, $kolom, ';');
            foreach ($rencana['ubah'] as $u) {
                $d = $rencana['aktif'][$u['db_i']];
                fputcsv($f, array_map(static fn ($k) => $d[$k] ?? '', $kolom), ';');
            }
            fclose($f);
        }

        $diubah = 0;
        $baru   = 0;
        $db->transStart();
        foreach ($rencana['ubah'] as $u) {
            $db->table('siswa')->where('id', $u['id'])->update($u['set'] + ['updated_at' => $sekarang]);
            $diubah++;
        }
        foreach ($rencana['tambah'] as $t) {
            $e = $rencana['excel'][$t['excel_j']];
            $db->table('siswa')->insert([
                'nis' => $e['nis'], 'nisn' => $t['nisn'], 'nama' => $e['nama'], 'jenis_kelamin' => $e['jenis_kelamin'],
                'tempat_lahir' => $e['tempat_lahir'], 'tanggal_lahir' => $e['tanggal_lahir'], 'agama' => $e['agama'],
                'nama_orang_tua' => $e['nama_orang_tua'], 'ortu_alamat' => $e['ortu_alamat'],
                'sttb_nomor' => $e['sttb_nomor'], 'sttb_tahun' => $e['sttb_tahun'], 'tahun_masuk' => $e['tahun_masuk'],
                'status' => 'aktif', 'kelas_id' => null,
                'keterangan' => 'Perlu penempatan kelas (data resmi sekolah: ' . trim($e['tingkat'] . ' ' . $e['jurusan']) . ')',
                'created_at' => $sekarang, 'updated_at' => $sekarang,
            ]);
            $baru++;
        }
        if ($db->tableExists('audit_log')) {
            $db->table('audit_log')->insert([
                'admin_id' => null, 'aksi' => 'update', 'tabel' => 'siswa', 'record_id' => null,
                'deskripsi' => mb_substr('Data siswa resmi (Format 8355) diisi lewat perintah: ' . $diubah . ' diperbarui, ' . $baru . ' siswa baru', 0, 255),
                'ip_address' => null, 'created_at' => $sekarang,
            ]);
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new \RuntimeException('Penulisan data siswa gagal; tidak ada data yang berubah.');
        }

        return ['diubah' => $diubah, 'baru' => $baru, 'cadangan' => $cadangan];
    }
}
