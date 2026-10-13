<?php

namespace App\Libraries;

/**
 * Perapi gambar tanda tangan / stempel unggahan (Waka Hubin, Kepala Sekolah), supaya gambar UKURAN BERAPA PUN — foto HP,
 * hasil pindai, gambar bertepi lebar — tercetak rapi dan seragam di surat Word:
 *   1. orientasi foto JPEG dibetulkan (EXIF), sisi terpanjang diperkecil ke ≤ SISI_KERJA px (cepat & hemat memori);
 *   2. warna latar ditaksir: kecerahan persentil-90 (pada gambar tanda tangan, sebagian besar piksel adalah kertas);
 *   3. tepi kosong dipotong ke kotak "tinta" (piksel yang jelas lebih gelap dari latar) + sedikit ruang;
 *   4. latar dibuat transparan bertahap (tepi tinta tetap halus) — latar abu-abu/kekuningan foto kertas ikut hilang;
 *   5. disimpan PNG. Setelah itu PklSurat memuatnya ke kotak tanda tangan dengan perbandingan asli.
 * Latar gelap (bukan kertas) atau tanpa tinta terdeteksi: gambar hanya diperkecil, tidak dipotong.
 */
final class GambarTtd
{
    /** Sisi terpanjang hasil (px) — cukup tajam untuk dicetak ±5 cm (≥ 600 dpi), berkas Word tetap kecil. */
    public const SISI_KERJA = 1200;

    /** Piksel yang ≥ sekian tingkat (0–255) lebih gelap dari latar = tinta. */
    private const BEDA_TINTA = 45;

    /** Latar yang lebih gelap dari ini dianggap bukan kertas → tidak dipotong / tidak dibuat transparan. */
    private const LATAR_MIN = 150;

    /** Ruang di sekeliling tinta setelah dipotong: 4 % sisi terpanjang tinta, minimal 3 px. */
    private const RUANG = 0.04;

    /**
     * Rapikan $sumber (PNG/JPG) → $tujuan (PNG).
     *
     * @return array{ok: bool, pesan: string, lebar?: int, tinggi?: int, dipotong?: bool}
     */
    public static function rapikan(string $sumber, string $tujuan): array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return ['ok' => false, 'pesan' => 'Pustaka gambar (GD) tidak tersedia di server.'];
        }
        $info = @getimagesize($sumber);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $info[0] < 1 || $info[1] < 1) {
            return ['ok' => false, 'pesan' => 'Berkas bukan gambar PNG/JPG yang sah.'];
        }
        if (! self::cukupMemori((int) $info[0] * (int) $info[1])) {
            return ['ok' => false, 'pesan' => 'Gambar terlalu besar untuk dirapikan server.'];
        }
        $im = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($sumber) : @imagecreatefromjpeg($sumber);
        if ($im === false) {
            return ['ok' => false, 'pesan' => 'Gambar tidak bisa dibaca.'];
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            helper('labimage');
            $im = labimage_fix_orientation($im, $sumber);
        }

        $im    = self::perkecil($im);
        $w     = imagesx($im);
        $h     = imagesy($im);
        $latar = self::latar($im, $w, $h);
        $kotak = $latar >= self::LATAR_MIN ? self::kotakTinta($im, $w, $h, $latar - self::BEDA_TINTA) : null;
        if ($kotak !== null) {
            [$x0, $y0, $x1, $y1] = $kotak;
            $ruang = max(3, (int) round(max($x1 - $x0, $y1 - $y0) * self::RUANG));
            $x0    = max(0, $x0 - $ruang);
            $y0    = max(0, $y0 - $ruang);
            $x1    = min($w - 1, $x1 + $ruang);
            $y1    = min($h - 1, $y1 + $ruang);
            $pw    = $x1 - $x0 + 1;
            $ph    = $y1 - $y0 + 1;
            $potong = self::kanvas($pw, $ph);
            imagecopy($potong, $im, 0, 0, $x0, $y0, $pw, $ph);
            imagedestroy($im);
            [$im, $w, $h] = [$potong, $pw, $ph];
            self::latarTransparan($im, $w, $h, $latar);
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);
        $ok = @imagepng($im, $tujuan, 6);
        imagedestroy($im);

        return $ok
            ? ['ok' => true, 'pesan' => '', 'lebar' => $w, 'tinggi' => $h, 'dipotong' => $kotak !== null]
            : ['ok' => false, 'pesan' => 'Gambar hasil rapian gagal disimpan.'];
    }

    // =================================================================

    /** Kanvas truecolor transparan (alpha dipertahankan). */
    private static function kanvas(int $w, int $h): \GdImage
    {
        $im = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));

        return $im;
    }

    /** Truecolor + sisi terpanjang ≤ SISI_KERJA. */
    private static function perkecil(\GdImage $im): \GdImage
    {
        $w     = imagesx($im);
        $h     = imagesy($im);
        $skala = min(1.0, self::SISI_KERJA / max($w, $h));
        if ($skala >= 1.0) {
            imagepalettetotruecolor($im);
            imagealphablending($im, false);
            imagesavealpha($im, true);

            return $im;
        }
        $nw  = max(1, (int) round($w * $skala));
        $nh  = max(1, (int) round($h * $skala));
        $kcl = self::kanvas($nw, $nh);
        imagecopyresampled($kcl, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);

        return $kcl;
    }

    /** Kecerahan 0–255 (bobot mata manusia). */
    private static function terang(int $c): int
    {
        return (int) ((($c >> 16 & 0xFF) * 299 + ($c >> 8 & 0xFF) * 587 + ($c & 0xFF) * 114) / 1000);
    }

    /**
     * Taksiran kecerahan latar: persentil-90 piksel yang tak transparan (sampel kisi). Gambar yang sebagian besar transparan
     * (PNG tanda tangan tanpa latar) → latar dianggap putih.
     */
    private static function latar(\GdImage $im, int $w, int $h): int
    {
        $langkah = max(1, intdiv(max($w, $h), 300));
        $hist    = array_fill(0, 256, 0);
        $tampak  = 0;
        $semua   = 0;
        for ($y = 0; $y < $h; $y += $langkah) {
            for ($x = 0; $x < $w; $x += $langkah) {
                $c = imagecolorat($im, $x, $y);
                $semua++;
                if (($c >> 24 & 0x7F) > 100) {
                    continue; // (hampir) transparan
                }
                $hist[self::terang($c)]++;
                $tampak++;
            }
        }
        if ($tampak === 0 || ($semua - $tampak) > $semua * 0.3) {
            return 255;
        }
        $batas = (int) ceil($tampak * 0.9);
        $jumlah = 0;
        foreach ($hist as $l => $n) {
            $jumlah += $n;
            if ($jumlah >= $batas) {
                return $l;
            }
        }

        return 255;
    }

    /**
     * Kotak tinta [x0, y0, x1, y1]: piksel tak transparan yang lebih gelap dari $ambang. null bila tak ada / terlalu kecil.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    private static function kotakTinta(\GdImage $im, int $w, int $h, int $ambang): ?array
    {
        $x0 = $w;
        $y0 = $h;
        $x1 = -1;
        $y1 = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                if (($c >> 24 & 0x7F) < 64 && self::terang($c) < $ambang) {
                    if ($x < $x0) {
                        $x0 = $x;
                    }
                    if ($x > $x1) {
                        $x1 = $x;
                    }
                    if ($y < $y0) {
                        $y0 = $y;
                    }
                    $y1 = $y;
                }
            }
        }

        return ($x1 - $x0) >= 4 && ($y1 - $y0) >= 4 ? [$x0, $y0, $x1, $y1] : null;
    }

    /**
     * Latar → transparan bertahap: kecerahan ≥ latar−15 transparan penuh, ≤ latar−60 tetap utuh, di antaranya setengah
     * transparan (tepi tinta halus). Warna tinta tidak diubah.
     */
    private static function latarTransparan(\GdImage $im, int $w, int $h, int $latar): void
    {
        imagealphablending($im, false);
        $mulai = $latar - 60;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $t = (self::terang($c) - $mulai) / 45;
                if ($t <= 0) {
                    continue;
                }
                $a = max($c >> 24 & 0x7F, (int) round(127 * min(1.0, $t)));
                imagesetpixel($im, $x, $y, ($a << 24) | ($c & 0xFFFFFF));
            }
        }
    }

    /** Cukup memori untuk memuat gambar $piksel px (GD ±5 byte/piksel + salinan kerja)? Batas dinaikkan bila perlu & diizinkan. */
    private static function cukupMemori(int $piksel): bool
    {
        $batas = self::byte((string) ini_get('memory_limit'));
        if ($batas <= 0) {
            return true; // tanpa batas
        }
        $perlu = $piksel * 5 + self::SISI_KERJA * self::SISI_KERJA * 10 + 16 * 1048576;
        if (memory_get_usage(true) + $perlu <= $batas) {
            return true;
        }
        $baru = memory_get_usage(true) + $perlu + 16 * 1048576;

        return @ini_set('memory_limit', (string) $baru) !== false && self::byte((string) ini_get('memory_limit')) >= $baru;
    }

    private static function byte(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g'     => $n * 1073741824,
            'm'     => $n * 1048576,
            'k'     => $n * 1024,
            default => $n,
        };
    }
}
