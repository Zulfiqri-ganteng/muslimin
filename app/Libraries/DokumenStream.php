<?php

namespace App\Libraries;

/**
 * =====================================================================
 *  Penyaji berkas dokumen
 * =====================================================================
 *  Berkas SIMDOK disimpan di luar webroot, jadi Apache tak bisa
 *  melayaninya langsung — PHP yang harus mengirimkannya setelah izin
 *  diperiksa. Kelas ini yang melakukannya, dan dipakai bersama oleh:
 *    - Admin\DokumenFile   (admin yang sudah login)
 *    - Publik\Berbagi      (tamu yang memegang tautan bertoken)
 *    - Api\Admin\Dokumen   (aplikasi Android)
 *  Pola satu-library-dipakai-bersama meniru App\Libraries\LabReport.
 *
 *  Yang ditangani:
 *    - HTTP Range  → berkas besar bisa dilompati (seek) & unduhan
 *                    yang putus bisa dilanjutkan
 *    - ETag / Last-Modified + 304 → hemat kuota, berkas yang sama tak
 *                    diunduh dua kali oleh perangkat yang sama
 *    - Dikirim berpotongan 8 KB → memori server tetap kecil walau
 *                    berkasnya puluhan MB (JANGAN file_get_contents)
 *    - Pengamanan tipe berbahaya (lihat catatan di bawah)
 */
class DokumenStream
{
    /** Besar satu potongan saat mengirim (8 KB). */
    private const POTONG = 8192;

    /**
     * Tipe yang BOLEH ditampilkan langsung di dalam browser.
     *
     * Ini daftar putih, bukan daftar hitam, dan itu disengaja: berkas
     * diunggah pengguna dan disajikan dari domain sekolah sendiri. Kalau
     * HTML/SVG/XML dibuka langsung, skrip di dalamnya berjalan DI DOMAIN
     * KITA dan bisa mencuri sesi admin. Apa pun yang tidak ada di daftar
     * ini dipaksa jadi unduhan — aman, dan pengguna tetap dapat berkasnya.
     */
    private const INLINE_AMAN = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp',
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac',
        'video/mp4', 'video/webm', 'video/ogg',
        'text/plain', 'text/csv', 'text/markdown',
    ];

    /**
     * Kirim sebuah berkas ke pengguna lalu HENTIKAN eksekusi.
     *
     * @param string               $path  path absolut yang SUDAH divalidasi
     *                                    lewat dokumen_path()
     * @param array<string, mixed> $opsi  nama, mime, inline, hash, publik
     */
    public static function kirim(string $path, array $opsi = []): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            self::tolak(404, 'Berkas tidak ditemukan.');
        }

        $ukuran = (int) filesize($path);
        $mtime  = (int) filemtime($path);

        $mime   = self::bersihkanHeader((string) ($opsi['mime'] ?? 'application/octet-stream')) ?: 'application/octet-stream';
        $nama   = (string) ($opsi['nama'] ?? basename($path));
        $publik = (bool) ($opsi['publik'] ?? false);

        // Inline hanya untuk tipe yang aman dibuka di browser; sisanya unduh.
        $inline = (bool) ($opsi['inline'] ?? false) && in_array(strtolower($mime), self::INLINE_AMAN, true);

        // Sidik jari isi (bila ada) jadi ETag paling akurat; kalau tidak,
        // gabungan ukuran+waktu ubah sudah cukup untuk menandai versi.
        $hash = trim((string) ($opsi['hash'] ?? ''));
        $etag = '"' . ($hash !== '' ? substr($hash, 0, 32) : md5($path . '|' . $mtime . '|' . $ukuran)) . '"';

        if (self::tidakBerubah($etag, $mtime)) {
            self::kepala304($etag, $mtime, $publik);

            exit;
        }

        [$mulai, $akhir, $parsial] = self::hitungRange($ukuran);

        self::bersihkanBuffer();

        // ── Header ──
        http_response_code($parsial ? 206 : 200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . ($akhir - $mulai + 1));
        header('Accept-Ranges: bytes');
        header('Content-Disposition: ' . self::disposisi($inline ? 'inline' : 'attachment', $nama));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('ETag: ' . $etag);
        header('Cache-Control: ' . ($publik ? 'public, max-age=3600' : 'private, max-age=0, must-revalidate'));

        // Jangan biarkan browser menebak-nebak tipe berkas.
        header('X-Content-Type-Options: nosniff');

        // Berkas tetap dikurung `default-src 'none'` sehingga tak bisa
        // memanggil apa pun dari luar. Arahan `sandbox` HANYA dipasang pada
        // berkas yang diunduh: pada tampilan inline, sandbox penuh membuat
        // penampil PDF bawaan peramban ikut dilumpuhkan. Itu aman karena
        // yang boleh inline sudah dibatasi daftar putih di atas.
        $csp = "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'";
        header('Content-Security-Policy: ' . $csp . ($inline ? '' : '; sandbox'));
        header('X-Frame-Options: SAMEORIGIN');

        if ($parsial) {
            header('Content-Range: bytes ' . $mulai . '-' . $akhir . '/' . $ukuran);
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }

        // Baru DI SINI berkas benar-benar dikirim utuh. Pemanggil menitipkan
        // pencatatan lewat 'onKirim' supaya penghitung tidak ikut naik saat
        // permintaan HEAD (sekadar menanyakan ukuran), jawaban 304 (berkas
        // sudah ada di cache perangkat), atau potongan Range (satu pemutaran
        // video bisa mengirim puluhan permintaan untuk berkas yang sama).
        if (! $parsial && isset($opsi['onKirim']) && is_callable($opsi['onKirim'])) {
            ($opsi['onKirim'])();
        }

        self::alirkan($path, $mulai, $akhir);

        exit;
    }

    /**
     * Baca header Range dan tentukan potongan mana yang diminta.
     * Range ganda ("bytes=0-10,20-30") sengaja tidak didukung — jarang
     * dipakai dan menambah rumit; permintaan seperti itu dilayani utuh.
     *
     * @return array{0:int, 1:int, 2:bool} [mulai, akhir, parsial]
     */
    private static function hitungRange(int $ukuran): array
    {
        $mulai = 0;
        $akhir = max(0, $ukuran - 1);

        $header = (string) ($_SERVER['HTTP_RANGE'] ?? '');
        if ($header === '' || $ukuran === 0) {
            return [$mulai, $akhir, false];
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/i', trim($header), $m)) {
            return [$mulai, $akhir, false];   // termasuk range ganda
        }

        $dari  = $m[1];
        $ke    = $m[2];

        if ($dari === '' && $ke === '') {
            return [$mulai, $akhir, false];
        }

        if ($dari === '') {
            // "bytes=-500" = 500 byte TERAKHIR
            $panjang = (int) $ke;
            if ($panjang <= 0) {
                return [$mulai, $akhir, false];
            }
            $mulai = max(0, $ukuran - $panjang);
        } else {
            $mulai = (int) $dari;
            if ($ke !== '') {
                $akhir = min((int) $ke, $ukuran - 1);
            }
        }

        // Permintaan di luar jangkauan wajib dijawab 416, bukan diam-diam
        // dipotong — pemutar video mengandalkan jawaban ini.
        if ($mulai > $akhir || $mulai >= $ukuran) {
            self::bersihkanBuffer();
            http_response_code(416);
            header('Content-Range: bytes */' . $ukuran);

            exit;
        }

        return [$mulai, $akhir, true];
    }

    /** Apakah berkas tak berubah sejak permintaan sebelumnya? */
    private static function tidakBerubah(string $etag, int $mtime): bool
    {
        $etagKlien = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($etagKlien !== '') {
            foreach (explode(',', $etagKlien) as $satu) {
                $satu = trim(str_replace('W/', '', $satu));
                if ($satu === $etag || $satu === '*') {
                    return true;
                }
            }
        }

        $sejak = trim((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
        if ($sejak !== '') {
            $ts = strtotime($sejak);
            if ($ts !== false && $ts >= $mtime) {
                return true;
            }
        }

        return false;
    }

    private static function kepala304(string $etag, int $mtime, bool $publik): void
    {
        self::bersihkanBuffer();
        http_response_code(304);
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: ' . ($publik ? 'public, max-age=3600' : 'private, max-age=0, must-revalidate'));
    }

    /**
     * Kirim isi berkas potongan demi potongan.
     * Berhenti sendiri bila pengguna membatalkan unduhan.
     */
    private static function alirkan(string $path, int $mulai, int $akhir): void
    {
        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return;
        }

        if ($mulai > 0) {
            fseek($fp, $mulai);
        }

        $sisa = $akhir - $mulai + 1;

        // Batas waktu dilonggarkan: berkas besar lewat koneksi lambat bisa
        // memakan waktu, dan pengiriman ini tak membebani CPU.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        ignore_user_abort(false);

        while ($sisa > 0 && ! feof($fp)) {
            $baca = (int) min(self::POTONG, $sisa);
            $isi  = fread($fp, $baca);

            if ($isi === false || $isi === '') {
                break;
            }

            echo $isi;
            $sisa -= strlen($isi);

            if (connection_aborted() === 1) {
                break;
            }

            flush();
        }

        fclose($fp);
    }

    /**
     * Susun header Content-Disposition yang tahan nama berkas berbahasa
     * Indonesia/emoji: versi ASCII untuk peramban lama + RFC 5987 untuk
     * yang modern.
     */
    private static function disposisi(string $jenis, string $nama): string
    {
        $nama = str_replace(["\r", "\n", '"', '\\'], '', $nama);
        $nama = $nama === '' ? 'berkas' : $nama;

        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $nama) ?? 'berkas';
        $utf8  = rawurlencode($nama);

        return $jenis . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . $utf8;
    }

    /** Buang karakter yang bisa menyuntikkan header baru. */
    private static function bersihkanHeader(string $nilai): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $nilai));
    }

    /**
     * Matikan semua buffer keluaran sebelum mengirim isi berkas — kalau
     * tidak, seluruh berkas menumpuk di memori sebelum terkirim dan
     * memory_limit shared hosting jebol.
     */
    private static function bersihkanBuffer(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    private static function tolak(int $kode, string $pesan): void
    {
        self::bersihkanBuffer();
        http_response_code($kode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $pesan;

        exit;
    }
}
