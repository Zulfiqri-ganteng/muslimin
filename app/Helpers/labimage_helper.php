<?php

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * =====================================================================
 * Helper Gambar Lab — auto-konversi ke WEBP (kecil & seragam).
 * =====================================================================
 * Menerima banyak format (JPG/PNG/WEBP/GIF/BMP/AVIF; HEIC bila server punya
 * Imagick), memperkecil dimensi, memperbaiki orientasi EXIF, lalu MENYIMPAN
 * sebagai .webp di public/uploads/lab/. Maksimal 5 MB per berkas.
 *
 * Dipakai web & API: helper('labimage'); $nama = labimage_save($file, $err);
 */

if (! function_exists('labimage_dir')) {
    /** Path absolut folder penyimpanan (dibuat bila belum ada). */
    function labimage_dir(): string
    {
        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'lab' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}

if (! function_exists('labimage_url')) {
    /** URL publik sebuah berkas gambar lab (atau null bila kosong). */
    function labimage_url(?string $file): ?string
    {
        $file = trim((string) $file);

        return $file === '' ? null : base_url('uploads/lab/' . $file);
    }
}

if (! function_exists('labimage_delete')) {
    /** Hapus berkas fisik gambar lab (aman bila tak ada). */
    function labimage_delete(?string $file): void
    {
        $file = basename(trim((string) $file));
        if ($file === '') {
            return;
        }
        $path = labimage_dir() . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (! function_exists('labimage_save')) {
    /**
     * Simpan satu unggahan gambar sebagai WEBP. Mengembalikan nama berkas
     * (mis. "a1b2c3.webp") atau null bila gagal (alasan diisi di $error).
     *
     * @param int $maxDim Sisi terpanjang maksimum (px); gambar lebih besar diperkecil.
     */
    function labimage_save(UploadedFile $file, ?string &$error = null, int $maxDim = 1600, int $quality = 80): ?string
    {
        $error = null;
        if (! $file->isValid()) {
            $error = 'Berkas tidak valid: ' . $file->getErrorString();

            return null;
        }
        // Batas 5 MB.
        if ($file->getSize() > 5 * 1024 * 1024) {
            $error = 'Ukuran gambar melebihi 5 MB.';

            return null;
        }

        $tmp  = $file->getTempName();
        $mime = strtolower((string) $file->getMimeType()); // dari isi berkas (finfo), bukan label klien

        $target = labimage_dir() . uniqid('lab_', true) . '.webp';

        // ── Jalur 1: Imagick (bila ada) — cakupan terluas termasuk HEIC/HEIF ──
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick($tmp);
                $im->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                if (method_exists($im, 'autoOrient')) {
                    $im->autoOrient();
                }
                $w = $im->getImageWidth();
                $h = $im->getImageHeight();
                if ($w > $maxDim || $h > $maxDim) {
                    $im->resizeImage(
                        $w >= $h ? $maxDim : 0,
                        $h > $w ? $maxDim : 0,
                        \Imagick::FILTER_LANCZOS,
                        1,
                        true
                    );
                }
                $im->setImageFormat('webp');
                $im->setImageCompressionQuality($quality);
                $im->writeImage($target);
                $im->clear();
                $im->destroy();

                return basename($target);
            } catch (\Throwable $e) {
                // Jatuh ke GD.
            }
        }

        // ── Jalur 2: GD — JPG/PNG/WEBP/GIF/BMP/AVIF ──
        $src = labimage_gd_decode($tmp, $mime);
        if ($src === null) {
            $error = 'Format gambar tidak didukung server ini (' . $mime . '). '
                . 'Untuk HEIC, unggah lewat aplikasi HP (otomatis dikonversi).';

            return null;
        }

        // Perbaiki orientasi dari EXIF (khusus JPEG).
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $src = labimage_fix_orientation($src, $tmp);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $maxDim / max($w, $h));
        if ($scale < 1.0) {
            $nw  = max(1, (int) round($w * $scale));
            $nh  = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        } else {
            imagepalettetotruecolor($src);
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }

        $ok = imagewebp($src, $target, $quality);
        imagedestroy($src);
        if (! $ok) {
            $error = 'Gagal mengonversi gambar ke WEBP.';

            return null;
        }

        return basename($target);
    }
}

if (! function_exists('labimage_gd_decode')) {
    /** Buat resource GD dari berkas sesuai mime. Null bila tak didukung. */
    function labimage_gd_decode(string $path, string $mime)
    {
        $img = null;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $img = @imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $img = @imagecreatefrompng($path);
                break;
            case 'image/webp':
                $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
                break;
            case 'image/gif':
                $img = @imagecreatefromgif($path);
                break;
            case 'image/bmp':
            case 'image/x-ms-bmp':
                $img = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : null;
                break;
            case 'image/avif':
                $img = function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : null;
                break;
        }

        return $img ?: null;
    }
}

if (! function_exists('labimage_fix_orientation')) {
    /** Putar gambar mengikuti tag EXIF Orientation (kamera HP sering miring). */
    function labimage_fix_orientation($img, string $path)
    {
        try {
            $exif = @exif_read_data($path);
        } catch (\Throwable) {
            return $img;
        }
        $o = (int) ($exif['Orientation'] ?? 0);
        $deg = match ($o) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($deg !== 0) {
            $rot = imagerotate($img, $deg, 0);
            if ($rot !== false) {
                imagedestroy($img);

                return $rot;
            }
        }

        return $img;
    }
}
