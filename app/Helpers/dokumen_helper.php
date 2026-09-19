<?php

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * =====================================================================
 * Helper Manajemen Dokumen (SIMDOK)
 * =====================================================================
 * Beda mendasar dari labimage_helper & ukkdoc_helper:
 *
 *   1. Berkas disimpan di WRITEPATH (writable/uploads/dokumen/YYYY/MM/),
 *      DI LUAR webroot — bukan di public/uploads/. Modul ini memegang
 *      dokumen kesiswaan, jadi tak boleh ada yang bisa dibuka cuma karena
 *      tahu URL-nya. Semua akses wajib lewat controller yang cek izin.
 *   2. Berkas TIDAK dikonversi. Apa pun yang diunggah disimpan apa adanya
 *      supaya utuh saat diunduh. Yang dibuat hanyalah THUMBNAIL kecil
 *      untuk gambar.
 *   3. Modul ini TIDAK menolak jenis berkas — kecuali video, yang menurut
 *      keputusan user ditempel sebagai tautan YouTube/Drive (saklar
 *      settings.dok_izinkan_video).
 *
 * Dipakai: helper('dokumen'); $meta = dokumen_save($file, $err);
 */

// =====================================================================
//  Lokasi & path
// =====================================================================

if (! function_exists('dokumen_root')) {
    /** Folder induk penyimpanan dokumen (dibuat + dikunci bila belum ada). */
    function dokumen_root(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'dokumen' . DIRECTORY_SEPARATOR;

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Lapis pertahanan kedua: seandainya folder writable/ suatu saat
        // ter-ekspos oleh salah konfigurasi server, .htaccess ini tetap menolak.
        $ht = $dir . '.htaccess';
        if (! is_file($ht)) {
            @file_put_contents($ht, "<IfModule authz_core_module>\n\tRequire all denied\n</IfModule>\n<IfModule !authz_core_module>\n\tDeny from all\n</IfModule>\n");
        }

        return $dir;
    }
}

if (! function_exists('dokumen_dir_bulan')) {
    /** Folder penyimpanan bulan berjalan (mis. .../dokumen/2026/09/). */
    function dokumen_dir_bulan(?string $tanggal = null): string
    {
        $ts  = $tanggal !== null ? strtotime($tanggal) : time();
        $sub = date('Y', $ts) . DIRECTORY_SEPARATOR . date('m', $ts) . DIRECTORY_SEPARATOR;
        $dir = dokumen_root() . $sub;

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}

if (! function_exists('dokumen_path')) {
    /**
     * Ubah path relatif dari DB ("2026/09/abc.pdf") jadi path absolut,
     * SETELAH dipastikan benar-benar berada di dalam folder dokumen.
     *
     * Ini penjaga path traversal: nilai dari DB pun tetap diverifikasi,
     * karena berkas ini dipakai untuk mengirim isi file ke pengguna.
     */
    function dokumen_path(?string $pathRel): ?string
    {
        $pathRel = trim((string) $pathRel);
        if ($pathRel === '' || str_contains($pathRel, "\0")) {
            return null;
        }

        $root  = realpath(dokumen_root());
        $penuh = realpath(dokumen_root() . str_replace(['\\', '..'], ['/', ''], $pathRel));

        if ($root === false || $penuh === false) {
            return null;
        }
        if (! str_starts_with($penuh, $root)) {
            return null;   // mencoba keluar dari folder dokumen
        }

        return is_file($penuh) ? $penuh : null;
    }
}

if (! function_exists('dokumen_delete')) {
    /** Hapus berkas fisik + thumbnail-nya (aman bila sudah tak ada). */
    function dokumen_delete(?string $pathRel, ?string $thumb = null): void
    {
        $p = dokumen_path($pathRel);
        if ($p !== null) {
            @unlink($p);
        }

        $t = dokumen_path($thumb);
        if ($t !== null) {
            @unlink($t);
        }
    }
}

// =====================================================================
//  Pengaturan (dibaca dari tabel settings)
// =====================================================================

if (! function_exists('dokumen_setting')) {
    /** Ambil satu nilai pengaturan dokumen dengan nilai bawaan yang aman. */
    function dokumen_setting(string $kunci, int $bawaan): int
    {
        static $cache = null;

        if ($cache === null) {
            try {
                $cache = (new \App\Models\SettingModel())->get();
            } catch (\Throwable $e) {
                $cache = [];
            }
        }

        $nilai = $cache[$kunci] ?? null;

        return $nilai === null || $nilai === '' ? $bawaan : (int) $nilai;
    }
}

if (! function_exists('dokumen_maks_bytes')) {
    /** Batas ukuran satu berkas dalam byte (dari settings.dok_maks_mb). */
    function dokumen_maks_bytes(): int
    {
        return max(1, dokumen_setting('dok_maks_mb', 25)) * 1024 * 1024;
    }
}

if (! function_exists('dokumen_izinkan_video')) {
    /** Apakah unggah berkas video diizinkan? (default: tidak) */
    function dokumen_izinkan_video(): bool
    {
        return dokumen_setting('dok_izinkan_video', 0) === 1;
    }
}

// =====================================================================
//  Pengenalan jenis berkas
// =====================================================================

if (! function_exists('dokumen_mime_map')) {
    /**
     * Peta ekstensi → MIME resmi.
     *
     * PENTING: finfo membaca .docx/.xlsx/.pptx sebagai application/zip
     * (memang benar — OOXML itu wadah zip). Jadi untuk berkas Office,
     * ekstensilah yang memberi arti, sementara finfo dipakai memastikan
     * isinya memang wadah zip, bukan sesuatu yang lain.
     *
     * @return array<string, string>
     */
    function dokumen_mime_map(): array
    {
        return [
            // dokumen
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'odt'  => 'application/vnd.oasis.opendocument.text',
            'rtf'  => 'application/rtf',
            'txt'  => 'text/plain',
            'md'   => 'text/markdown',
            // spreadsheet
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
            'csv'  => 'text/csv',
            // presentasi
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odp'  => 'application/vnd.oasis.opendocument.presentation',
            // gambar
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'avif' => 'image/avif',
            'svg'  => 'image/svg+xml',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            // audio
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'm4a'  => 'audio/mp4',
            'aac'  => 'audio/aac',
            // video
            'mp4'  => 'video/mp4',
            'mov'  => 'video/quicktime',
            'mkv'  => 'video/x-matroska',
            'avi'  => 'video/x-msvideo',
            'webm' => 'video/webm',
            '3gp'  => 'video/3gpp',
            // arsip
            'zip'  => 'application/zip',
            'rar'  => 'application/vnd.rar',
            '7z'   => 'application/x-7z-compressed',
            'tar'  => 'application/x-tar',
            'gz'   => 'application/gzip',
        ];
    }
}

if (! function_exists('dokumen_kategori')) {
    /**
     * Tentukan kategori dari ekstensi (utama) dengan MIME sebagai cadangan.
     * Ekstensi didahulukan justru karena Office/OOXML tak bisa dibedakan
     * dari zip biasa lewat isinya saja.
     */
    function dokumen_kategori(string $ekstensi, string $mime = ''): string
    {
        $ekstensi = strtolower(ltrim($ekstensi, '.'));

        $peta = [
            'pdf'         => ['pdf'],
            'dokumen'     => ['doc', 'docx', 'odt', 'rtf', 'txt', 'md'],
            'spreadsheet' => ['xls', 'xlsx', 'ods', 'csv'],
            'presentasi'  => ['ppt', 'pptx', 'odp'],
            'gambar'      => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'svg', 'heic', 'heif'],
            'audio'       => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'],
            'video'       => ['mp4', 'mov', 'mkv', 'avi', 'webm', '3gp', 'm4v', 'wmv'],
            'arsip'       => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'],
        ];

        foreach ($peta as $kategori => $daftar) {
            if (in_array($ekstensi, $daftar, true)) {
                return $kategori;
            }
        }

        // Cadangan: tebak dari MIME bila ekstensinya asing.
        $mime = strtolower($mime);
        if (str_starts_with($mime, 'image/')) {
            return 'gambar';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'text/')) {
            return 'dokumen';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        return 'lainnya';
    }
}

if (! function_exists('dokumen_ekstensi_bersih')) {
    /** Ambil ekstensi dari nama berkas, dibersihkan & dibatasi 20 karakter. */
    function dokumen_ekstensi_bersih(string $namaAsli): string
    {
        $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';

        return substr($ext, 0, 20);
    }
}

if (! function_exists('dokumen_nama_aman')) {
    /**
     * Bersihkan nama berkas asli untuk DISIMPAN & DITAMPILKAN.
     * Nama ini tak pernah dipakai sebagai path di disk (nama disk diacak),
     * tapi tetap dibersihkan karena akan muncul di header unduhan.
     */
    function dokumen_nama_aman(string $nama): string
    {
        $nama = str_replace(["\r", "\n", "\0", '"', '\\', '/'], ' ', $nama);
        $nama = preg_replace('/[\x00-\x1F\x7F]/u', '', $nama) ?? $nama;
        $nama = trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama);

        return $nama === '' ? 'berkas' : mb_substr($nama, 0, 255);
    }
}

if (! function_exists('dokumen_ukuran_manusia')) {
    /** 1536 → "1,5 KB" (format Indonesia). */
    function dokumen_ukuran_manusia(?int $byte): string
    {
        $byte = max(0, (int) $byte);
        if ($byte < 1024) {
            return $byte . ' B';
        }

        $satuan = ['KB', 'MB', 'GB', 'TB'];
        $nilai  = $byte / 1024;
        $i      = 0;

        while ($nilai >= 1024 && $i < count($satuan) - 1) {
            $nilai /= 1024;
            $i++;
        }

        return number_format($nilai, $nilai < 10 ? 1 : 0, ',', '.') . ' ' . $satuan[$i];
    }
}

if (! function_exists('dokumen_bisa_pratinjau')) {
    /**
     * Apakah kategori ini punya pratinjau di dalam aplikasi web?
     * (presentasi sengaja TIDAK — tak ada mesin render pptx di browser)
     */
    function dokumen_bisa_pratinjau(string $kategori): bool
    {
        return in_array($kategori, ['pdf', 'gambar', 'audio', 'spreadsheet', 'dokumen'], true);
    }
}

// =====================================================================
//  Penyimpanan berkas
// =====================================================================

if (! function_exists('dokumen_save')) {
    /**
     * Simpan satu berkas unggahan APA ADANYA (tanpa konversi).
     *
     * @param UploadedFile $file  berkas dari $this->request->getFile()
     * @param string|null  $error diisi alasan bila gagal
     *
     * @return array<string, mixed>|null metadata siap masuk DokumenModel:
     *         nama_asli, nama_file, path_rel, ekstensi, mime, ukuran,
     *         hash_sha256, kategori, thumb
     */
    function dokumen_save(UploadedFile $file, ?string &$error = null): ?array
    {
        $error = null;

        if (! $file->isValid()) {
            $error = 'Berkas tidak valid: ' . $file->getErrorString();

            return null;
        }

        $maks = dokumen_maks_bytes();
        if ($file->getSize() > $maks) {
            $error = 'Ukuran berkas ' . dokumen_ukuran_manusia((int) $file->getSize())
                . ' melebihi batas ' . dokumen_ukuran_manusia($maks) . '.';

            return null;
        }

        $namaAsli = dokumen_nama_aman($file->getClientName());
        $ekstensi = dokumen_ekstensi_bersih($namaAsli);

        // MIME dari ISI berkas — bukan label kiriman klien. Aplikasi Flutter
        // mengirim application/octet-stream untuk semua berkas, jadi label
        // klien memang tak bisa dipercaya (pelajaran bug foto profil).
        $mimeIsi = strtolower((string) $file->getMimeType());
        $mimePeta = dokumen_mime_map()[$ekstensi] ?? null;

        // Untuk Office/OOXML, finfo wajar membaca 'application/zip' —
        // pakai MIME resmi dari ekstensi supaya tersimpan bermakna.
        $mime = $mimePeta ?? ($mimeIsi !== '' ? $mimeIsi : 'application/octet-stream');
        if ($mimePeta === null && $mimeIsi === '') {
            $mime = 'application/octet-stream';
        }

        $kategori = dokumen_kategori($ekstensi, $mimeIsi);

        if ($kategori === 'video' && ! dokumen_izinkan_video()) {
            $error = 'Berkas video tidak disimpan di server. Pakai tombol '
                . '"Tambah Tautan" untuk menempelkan tautan YouTube atau Google Drive.';

            return null;
        }

        // Nama di disk diacak; nama asli hidup di database.
        $namaFile = date('Ymd') . '_' . bin2hex(random_bytes(8)) . ($ekstensi !== '' ? '.' . $ekstensi : '');
        $dir      = dokumen_dir_bulan();
        $pathRel  = date('Y') . '/' . date('m') . '/' . $namaFile;

        // Sidik jari isi dihitung SEBELUM dipindah (berkas sementara masih ada).
        $hash = @hash_file('sha256', $file->getTempName()) ?: null;

        if (! $file->move($dir, $namaFile, true)) {
            $error = 'Gagal menyimpan berkas ke penyimpanan server.';

            return null;
        }

        $tujuan = $dir . $namaFile;

        return [
            'nama_asli'   => $namaAsli,
            'nama_file'   => $namaFile,
            'path_rel'    => $pathRel,
            'ekstensi'    => $ekstensi,
            'mime'        => $mime,
            'ukuran'      => is_file($tujuan) ? (int) filesize($tujuan) : (int) $file->getSize(),
            'hash_sha256' => $hash,
            'kategori'    => $kategori,
            'thumb'       => $kategori === 'gambar' ? dokumen_thumb_buat($tujuan, $pathRel) : null,
        ];
    }
}

if (! function_exists('dokumen_thumb_buat')) {
    /**
     * Buat thumbnail kecil untuk berkas gambar. Mengembalikan path relatif
     * thumbnail, atau null bila tak bisa dibuat (mis. HEIC tanpa Imagick).
     *
     * Kegagalan di sini BUKAN kegagalan unggah — berkas aslinya tetap utuh,
     * yang hilang hanya pratinjau kecilnya.
     */
    function dokumen_thumb_buat(string $pathAbsolut, string $pathRel, int $maksDim = 480): ?string
    {
        if (! is_file($pathAbsolut)) {
            return null;
        }

        $thumbRel = preg_replace('/(\.[^.]+)?$/', '', $pathRel, 1) . '_thumb.webp';
        $thumbAbs = dokumen_root() . str_replace('/', DIRECTORY_SEPARATOR, $thumbRel);
        $webp     = function_exists('imagewebp');
        if (! $webp) {
            $thumbRel = preg_replace('/\.webp$/', '.jpg', $thumbRel) ?? $thumbRel;
            $thumbAbs = preg_replace('/\.webp$/', '.jpg', $thumbAbs) ?? $thumbAbs;
        }

        // ── Jalur 1: Imagick bila tersedia (cakupan terluas, termasuk HEIC) ──
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $im = new \Imagick($pathAbsolut);
                $im->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                if (method_exists($im, 'autoOrient')) {
                    $im->autoOrient();
                }
                $im->thumbnailImage($maksDim, $maksDim, true);
                $im->setImageFormat($webp ? 'webp' : 'jpeg');
                $im->setImageCompressionQuality(80);
                $im->stripImage();
                $ok = $im->writeImage($thumbAbs);
                $im->clear();

                if ($ok) {
                    return $thumbRel;
                }
            } catch (\Throwable $e) {
                log_message('debug', 'Thumbnail Imagick gagal: ' . $e->getMessage());
            }
        }

        // ── Jalur 2: GD ──
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $info = @getimagesize($pathAbsolut);
        if ($info === false) {
            return null;   // bukan format yang dikenali GD (mis. HEIC, SVG)
        }

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($pathAbsolut),
            IMAGETYPE_PNG  => @imagecreatefrompng($pathAbsolut),
            IMAGETYPE_GIF  => @imagecreatefromgif($pathAbsolut),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($pathAbsolut) : null,
            IMAGETYPE_BMP  => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($pathAbsolut) : null,
            IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? @imagecreatefromavif($pathAbsolut) : null,
            default        => null,
        };

        if (! $src) {
            return null;
        }

        // Perbaiki orientasi foto dari kamera/HP sebelum diperkecil.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($pathAbsolut);
            $rot  = match ((int) ($exif['Orientation'] ?? 0)) {
                3       => 180,
                6       => -90,
                8       => 90,
                default => 0,
            };
            if ($rot !== 0) {
                $putar = @imagerotate($src, $rot, 0);
                if ($putar) {
                    imagedestroy($src);
                    $src = $putar;
                }
            }
        }

        $lebar  = imagesx($src);
        $tinggi = imagesy($src);
        $skala  = min(1, $maksDim / max($lebar, $tinggi));
        $lBaru  = max(1, (int) round($lebar * $skala));
        $tBaru  = max(1, (int) round($tinggi * $skala));

        $dst = imagecreatetruecolor($lBaru, $tBaru);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $lBaru, $tBaru, $lebar, $tinggi);

        $ok = $webp ? @imagewebp($dst, $thumbAbs, 80) : @imagejpeg($dst, $thumbAbs, 82);

        imagedestroy($src);
        imagedestroy($dst);

        return $ok ? $thumbRel : null;
    }
}

// =====================================================================
//  Tautan eksternal (video YouTube/Drive & sejenisnya)
// =====================================================================

if (! function_exists('dokumen_penyedia')) {
    /** Kenali penyedia dari URL: youtube / drive / lainnya. */
    function dokumen_penyedia(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
            return 'youtube';
        }
        if (str_contains($host, 'drive.google.com') || str_contains($host, 'docs.google.com')) {
            return 'drive';
        }

        return 'lainnya';
    }
}

if (! function_exists('dokumen_youtube_id')) {
    /** Ambil id video YouTube dari berbagai bentuk URL (null bila bukan). */
    function dokumen_youtube_id(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (str_contains($host, 'youtu.be')) {
            $id = trim((string) parse_url($url, PHP_URL_PATH), '/');

            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : null;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $id = (string) ($q['v'] ?? '');

            if ($id === '' && preg_match('#/(embed|shorts|live)/([A-Za-z0-9_-]{6,20})#', $url, $m)) {
                $id = $m[2];
            }

            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : null;
        }

        return null;
    }
}

if (! function_exists('dokumen_thumb_url_eksternal')) {
    /** Gambar sampul untuk dokumen bertipe tautan (khusus YouTube). */
    function dokumen_thumb_url_eksternal(string $url): ?string
    {
        $id = dokumen_youtube_id($url);

        return $id === null ? null : 'https://img.youtube.com/vi/' . $id . '/mqdefault.jpg';
    }
}
