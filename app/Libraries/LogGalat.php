<?php

namespace App\Libraries;

/**
 * Log Error Hosting (Sistem → Log Error Hosting; docs/DESAIN-ALAT-SISTEM.md F1): membaca log galat ASLI server tanpa masuk
 * cPanel — log aplikasi CodeIgniter (writable/logs/log-YYYY-MM-DD.log) dan error_log PHP buatan cPanel (bila ada).
 * Hanya membaca; satu-satunya pengubah = bersihkan log aplikasi lama. Berkas dipilih lewat KUNCI dari daftar tetap
 * (bukan path kiriman pengguna), jadi tidak bisa dipakai membaca berkas lain di server.
 */
class LogGalat
{
    /** Bagian akhir berkas yang dibaca (byte): log besar cukup dibaca ujungnya agar hosting tetap ringan. */
    public const MAKS_BACA = 2_000_000;

    /** Log aplikasi yang lebih tua dari ini (hari) boleh dibersihkan. */
    public const SIMPAN_HARI = 30;

    /** Tingkat tampilan → [label, kelas lencana], urut dari yang paling gawat. */
    public const TINGKAT = [
        'berat'      => ['Galat berat', 'lencana-merah'],
        'galat'      => ['Galat', 'lencana-pink'],
        'peringatan' => ['Peringatan', 'lencana-kuning'],
        'info'       => ['Info', 'lencana-abu'],
    ];

    /** Kejadian per kelompok yang jamnya disimpan untuk ditampilkan (yang terbaru). */
    public const MAKS_JAM = 30;

    private const POLA_CI   = '/^(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG) - (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --> (.*)$/';
    private const POLA_PHP  = '/^\[(\d{1,2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}(?: [^\]]*)?)\] (.*)$/';
    private const NAMA_CI   = '/^log-(\d{4}-\d{2}-\d{2})\.log$/';
    private const LANJUT_PHP = '/^PHP\s+(?:Stack trace:|\d+\.)/'; // baris jejak error_log PHP = sambungan galat sebelumnya

    private const TINGKAT_CI = [
        'EMERGENCY' => 'berat', 'ALERT' => 'berat', 'CRITICAL' => 'berat', 'ERROR' => 'galat',
        'WARNING' => 'peringatan', 'NOTICE' => 'peringatan', 'INFO' => 'info', 'DEBUG' => 'info',
    ];

    /**
     * Penjelasan bahasa awam untuk galat yang sudah dikenal: pola (regex tanpa pembatas, tanpa beda huruf besar/kecil) dicocokkan
     * ke pesan + awal jejak. Pola pertama yang cocok dipakai, jadi yang lebih khusus ditaruh di atas.
     */
    private const PENJELASAN = [
        'too many connections'
            => 'Batas koneksi database hosting penuh (banyak pengunjung bersamaan). Biasanya hilang sendiri; bila sering terjadi, cek cPanel → Resource Usage atau tanya Rumahweb.',
        'mysql server has gone away|lost connection to (?:mysql|server)|real_connect|unable to connect to the database'
            => 'Database hosting sesaat mati atau sedang dimulai ulang. Bukan kesalahan aplikasi; halaman atau perintah berikutnya biasanya berhasil lagi.',
        'disk quota|no space left'
            => 'Ruang penyimpanan hosting penuh. Hapus berkas lama (log, backup) atau tambah kuota hosting.',
        'allowed memory size'
            => 'Memori PHP habis karena data yang diproses terlalu besar (mis. laporan atau Excel sangat panjang).',
        'maximum execution time'
            => 'Proses terlalu lama sehingga dihentikan hosting (batas waktu). Coba lagi dengan data lebih sedikit.',
        'duplicate entry'
            => 'Data kembar ditolak database — biasanya tombol simpan tertekan dua kali, atau data yang sama sudah ada. Data lama tetap aman.',
        'foreign key constraint fails'
            => 'Data yang dirujuk sudah tidak ada (mis. siswa atau surat sudah dihapus), jadi simpanan dibatalkan. Data lain tetap aman.',
        'the action you requested is not allowed'
            => 'Token keamanan halaman kedaluwarsa (halaman dibiarkan terbuka terlalu lama). Cukup muat ulang halaman lalu ulangi.',
        'could not resolve host|curl error|operation timed out|connection timed out'
            => 'Gagal menghubungi layanan luar (mis. notifikasi Firebase) — jaringan hosting atau layanan itu sedang bermasalah.',
        'permission denied|is not writable|unable to write|failed to open stream'
            => 'Server tidak bisa membaca atau menulis sebuah berkas/folder (izin berkas di hosting, atau berkas tidak ada).',
        'call to undefined|undefined (?:variable|array key|index|offset|method|property)|typeerror|argumentcounterror|parse error|syntax error|class "[^"]+" not found|must be of type'
            => 'Kesalahan di kode aplikasi. Kirim isi galat ini (tombol Unduh) ke pengembang.',
    ];

    private string $folderLog;

    /** @var array<string, array{0: string, 1: string}> kunci => [label, path] error_log PHP yang diperiksa */
    private array $logPhp;

    /**
     * @param array<string, array{0: string, 1: string}>|null $logPhp
     */
    public function __construct(?string $folderLog = null, ?array $logPhp = null)
    {
        $this->folderLog = rtrim($folderLog ?? WRITEPATH . 'logs', '/\\') . DIRECTORY_SEPARATOR;
        $this->logPhp    = $logPhp ?? [
            'php-public' => ['error_log PHP (folder public)', FCPATH . 'error_log'],
            'php-proyek' => ['error_log PHP (folder proyek)', ROOTPATH . 'error_log'],
        ];
    }

    /**
     * Semua berkas log yang ada: error_log PHP dulu, lalu log aplikasi terbaru di atas.
     *
     * @return array<string, array{kunci: string, jenis: string, label: string, tanggal: ?string, path: string, ukuran: int, diubah: int}>
     */
    public function berkas(): array
    {
        $out = [];
        foreach ($this->logPhp as $kunci => [$label, $path]) {
            if (is_file($path)) {
                $out[$kunci] = $this->info($kunci, 'php', $label, null, $path);
            }
        }
        $ci = [];
        foreach (glob($this->folderLog . 'log-*.log') ?: [] as $path) {
            $nama = basename($path);
            if (preg_match(self::NAMA_CI, $nama, $m) && is_file($path)) {
                $ci[$nama] = $this->info($nama, 'aplikasi', 'Log aplikasi ' . IsianBantu::tanggalIndo($m[1]), $m[1], $path);
            }
        }
        krsort($ci, SORT_STRING);

        return $out + $ci;
    }

    /** Kunci berkas yang paling baru diubah (pilihan awal halaman), atau null bila belum ada log sama sekali. */
    public static function terbaru(array $berkas): ?string
    {
        $pilih = null;
        foreach ($berkas as $k => $b) {
            if ($pilih === null || $b['diubah'] > $berkas[$pilih]['diubah']) {
                $pilih = $k;
            }
        }

        return $pilih;
    }

    /**
     * Jumlah kejadian per tingkat dalam satu berkas (dari bagian yang dibaca).
     *
     * @return array<string, int> tingkat => jumlah
     */
    public function hitung(array $b): array
    {
        $n = array_fill_keys(array_keys(self::TINGKAT), 0);
        foreach ($this->urai($b) as $e) {
            $n[$e['tingkat']]++;
        }

        return $n;
    }

    /**
     * Isi satu berkas: daftar kejadian (urut waktu tulis) + tanda bila hanya ujung berkas yang dibaca.
     *
     * @return array{entri: list<array{tingkat: string, waktu: string, pesan: string, jejak: string, asal: string}>, terpotong: bool}|null
     */
    public function baca(string $kunci): ?array
    {
        $b = $this->berkas()[$kunci] ?? null;
        if ($b === null) {
            return null;
        }

        return ['entri' => $this->urai($b), 'terpotong' => $b['ukuran'] > self::MAKS_BACA];
    }

    /** Path berkas untuk diunduh (hanya kunci dari daftar), atau null. */
    public function path(string $kunci): ?string
    {
        return $this->berkas()[$kunci]['path'] ?? null;
    }

    /**
     * Saring kejadian menurut tingkat ('' = semua) dan kata (pesan atau jejak, tanpa beda huruf besar/kecil).
     *
     * @param list<array> $entri
     *
     * @return list<array>
     */
    public static function saring(array $entri, string $tingkat, string $q): array
    {
        $q = mb_strtolower(trim($q));

        return array_values(array_filter($entri, static fn (array $e): bool => ($tingkat === '' || $e['tingkat'] === $tingkat)
            && ($q === '' || str_contains(mb_strtolower($e['pesan'] . "\n" . $e['jejak']), $q))));
    }

    /**
     * Galat yang SAMA digabung (angka diabaikan saat membandingkan): berapa kali, jam pertama/terakhir, asal, contoh jejak
     * (kejadian terakhir), penjelasan bahasa awam. Urut kejadian terakhir paling baru di atas.
     *
     * @param list<array> $entri
     *
     * @return list<array{tingkat: string, pesan: string, jejak: string, jumlah: int, pertama: string, terakhir: string, asal: list<string>, jam: list<string>, penjelasan: ?string}>
     */
    public static function kelompok(array $entri): array
    {
        $g = [];
        foreach ($entri as $e) {
            $k = $e['tingkat'] . '|' . preg_replace(['/\d+/', '/\s+/'], ['#', ' '], mb_substr($e['pesan'], 0, 300));
            if (! isset($g[$k])) {
                $g[$k] = ['tingkat' => $e['tingkat'], 'pesan' => $e['pesan'], 'jejak' => '', 'jumlah' => 0, 'pertama' => $e['waktu'],
                    'terakhir' => $e['waktu'], 'asal' => [], 'jam' => [], 'penjelasan' => self::penjelasan($e['pesan'] . "\n" . $e['jejak'])];
            }
            $x = &$g[$k];
            $x['jumlah']++;
            if ($e['waktu'] >= $x['terakhir']) {
                $x['terakhir'] = $e['waktu'];
                $x['pesan']    = $e['pesan'];
                $x['jejak']    = $e['jejak'];
            }
            if ($e['waktu'] < $x['pertama']) {
                $x['pertama'] = $e['waktu'];
            }
            $x['asal'][$e['asal']] = true;
            $x['jam'][]            = $e['waktu'];
            unset($x);
        }
        foreach ($g as &$x) {
            $x['asal'] = array_keys($x['asal']);
            rsort($x['jam'], SORT_STRING);
            $x['jam'] = array_slice($x['jam'], 0, self::MAKS_JAM);
        }
        unset($x);
        usort($g, static fn (array $a, array $b): int => strcmp($b['terakhir'], $a['terakhir']));

        return $g;
    }

    /** Penjelasan bahasa awam untuk galat yang dikenal, atau null. */
    public static function penjelasan(string $teks): ?string
    {
        $teks = mb_substr($teks, 0, 3000);
        foreach (self::PENJELASAN as $pola => $arti) {
            if (preg_match('~' . $pola . '~i', $teks)) {
                return $arti;
            }
        }

        return null;
    }

    /**
     * Hapus log aplikasi yang lebih tua dari $hari (dari tanggal di namanya). Log hari ini dan error_log PHP tidak pernah dihapus.
     *
     * @return int jumlah berkas yang terhapus
     */
    public function bersihkan(int $hari = self::SIMPAN_HARI, ?string $hariIni = null): int
    {
        $batas = date('Y-m-d', strtotime(($hariIni ?? date('Y-m-d')) . ' -' . max(1, $hari) . ' days'));
        $n     = 0;
        foreach ($this->berkas() as $b) {
            if ($b['jenis'] === 'aplikasi' && $b['tanggal'] < $batas && @unlink($b['path'])) {
                $n++;
            }
        }

        return $n;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private function info(string $kunci, string $jenis, string $label, ?string $tanggal, string $path): array
    {
        return ['kunci' => $kunci, 'jenis' => $jenis, 'label' => $label, 'tanggal' => $tanggal, 'path' => $path,
            'ukuran' => (int) filesize($path), 'diubah' => (int) filemtime($path)];
    }

    /** Ujung berkas (paling banyak MAKS_BACA byte, mulai dari awal baris utuh), UTF-8 sah. */
    private function ekor(array $b): string
    {
        $fh = @fopen($b['path'], 'rb');
        if ($fh === false) {
            return '';
        }
        if ($b['ukuran'] > self::MAKS_BACA) {
            fseek($fh, -self::MAKS_BACA, SEEK_END);
            fgets($fh); // buang baris yang terpotong di tengah
        }
        $isi = (string) stream_get_contents($fh);
        fclose($fh);

        return mb_convert_encoding($isi, 'UTF-8', 'UTF-8');
    }

    /** @return list<array{tingkat: string, waktu: string, pesan: string, jejak: string, asal: string}> */
    private function urai(array $b): array
    {
        $baris = preg_split('/\r\n|\n|\r/', $this->ekor($b)) ?: [];

        return $b['jenis'] === 'php' ? $this->uraiPhp($baris) : $this->uraiCi($baris);
    }

    /** Log CodeIgniter: "ERROR - 2026-10-28 08:16:05 --> pesan", baris sesudahnya (jejak) sampai entri berikut. */
    private function uraiCi(array $baris): array
    {
        $out = [];
        $cur = null;
        foreach ($baris as $l) {
            if (preg_match(self::POLA_CI, $l, $m)) {
                if ($cur !== null) {
                    $out[] = $this->tutup($cur);
                }
                $cur = ['tingkat' => self::TINGKAT_CI[$m[1]], 'waktu' => $m[2], 'pesan' => trim($m[3]), 'jejak' => []];
            } elseif ($cur !== null && trim($l) !== '') {
                $cur['jejak'][] = $l;
            }
        }
        if ($cur !== null) {
            $out[] = $this->tutup($cur);
        }

        return $out;
    }

    /** error_log PHP: "[29-Oct-2026 06:10:11 UTC] PHP Fatal error:  …"; baris "PHP Stack trace:" / "PHP   1. …" = jejak. */
    private function uraiPhp(array $baris): array
    {
        $out = [];
        $cur = null;
        foreach ($baris as $l) {
            if (preg_match(self::POLA_PHP, $l, $m)) {
                if ($cur !== null && preg_match(self::LANJUT_PHP, $m[2])) {
                    $cur['jejak'][] = $m[2];
                    continue;
                }
                if ($cur !== null) {
                    $out[] = $this->tutup($cur);
                }
                $t   = strtotime($m[1]);
                $cur = ['tingkat' => self::tingkatPhp($m[2]), 'waktu' => $t !== false ? date('Y-m-d H:i:s', $t) : '',
                    'pesan' => trim($m[2]), 'jejak' => []];
            } elseif ($cur !== null && trim($l) !== '') {
                $cur['jejak'][] = $l;
            }
        }
        if ($cur !== null) {
            $out[] = $this->tutup($cur);
        }

        return $out;
    }

    private static function tingkatPhp(string $pesan): string
    {
        return match (true) {
            (bool) preg_match('/^PHP (?:Fatal|Parse|Compile|Core) error/i', $pesan) => 'berat',
            (bool) preg_match('/^PHP Warning/i', $pesan)                             => 'peringatan',
            (bool) preg_match('/^PHP (?:Notice|Deprecated|Strict)/i', $pesan)        => 'info',
            default                                                                  => 'galat',
        };
    }

    /** Rapikan satu kejadian: jejak jadi teks, asal = 'perintah' bila dijalankan lewat spark (cron), selain itu 'web'. */
    private function tutup(array $e): array
    {
        $e['jejak'] = implode("\n", $e['jejak']);
        $e['asal']  = preg_match('/bootSpark|CLI\\\\Console|CLI\\\\Commands/', $e['jejak']) ? 'perintah' : 'web';

        return $e;
    }
}
