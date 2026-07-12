<?php

namespace App\Libraries;

/**
 * ApkManager — kelola APK rilis untuk fitur update otomatis (OTA) aplikasi
 * Flutter (fluter-muslimin).
 *
 * File APK disimpan sebagai FILE STATIS di public/downloads/apk/ (langsung
 * disajikan web server, cacheable). Karena batas upload PHP di shared hosting
 * kecil, APK TIDAK diunggah lewat PHP — melainkan ditaruh via FTP / File Manager,
 * lalu cukup DIDAFTARKAN metadata-nya (body kecil → lolos limit).
 *
 * Berbeda dari project galajuara, muslimin TIDAK punya tabel key-value
 * (platform_setting). Karena versi/build/ukuran APK bisa dibaca LANGSUNG dari
 * file APK (parsing AndroidManifest biner), satu-satunya nilai yang perlu
 * disimpan adalah `min_build` (penanda update WAJIB) + catatan rilis. Keduanya
 * disimpan di berkas kecil public/downloads/apk/meta.json.
 *
 * Dipakai oleh: Api\AppController::bootstrap / register.
 */
class ApkManager
{
    /** Folder file statis APK (di dalam public/ → disajikan langsung web server). */
    public static function dir(): string
    {
        return FCPATH . 'downloads/apk/';
    }

    /** Berkas metadata rilis (min_build/notes). Sengaja di folder yang sama. */
    public static function metaPath(): string
    {
        return self::dir() . 'meta.json';
    }

    /** Baca metadata rilis (min_build, notes). Default aman bila belum ada. */
    public static function readMeta(): array
    {
        $path = self::metaPath();
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $j   = $raw ? json_decode($raw, true) : null;
            if (is_array($j)) {
                return $j + ['min_build' => 1, 'notes' => '', 'uploaded_at' => null];
            }
        }
        return ['min_build' => 1, 'notes' => '', 'uploaded_at' => null];
    }

    /** Tulis metadata rilis. */
    public static function writeMeta(array $meta): bool
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        return @file_put_contents(self::metaPath(), json_encode($meta, JSON_PRETTY_PRINT)) !== false;
    }

    /** Daftar nama file .apk yang ADA di folder, terbaru dulu. */
    public static function listApks(): array
    {
        $files = glob(self::dir() . '*.apk') ?: [];
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map('basename', $files);
    }

    /**
     * Baca versionCode (build) & versionName (versi) langsung dari APK,
     * dengan parsing biner AndroidManifest.xml (AXML). Menghilangkan kesalahan
     * isi-manual yang bikin "update tersedia terus".
     *
     * @return array{version_code:int, version_name:string}|null  null bila gagal baca.
     */
    public static function readApkInfo(string $path): ?array
    {
        if (!class_exists('ZipArchive') || !is_file($path)) {
            return null;
        }
        try {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return null;
            }
            $axml = $zip->getFromName('AndroidManifest.xml');
            $zip->close();
            if ($axml === false || strlen($axml) < 8) {
                return null;
            }
            return self::parseManifestAxml($axml);
        } catch (\Throwable $e) {
            log_message('error', '[APK INFO] gagal baca: ' . $e->getMessage());
            return null;
        }
    }

    /** Parser minimal AXML: ambil versionCode & versionName dari elemen <manifest>. */
    private static function parseManifestAxml(string $d): ?array
    {
        $len = strlen($d);
        if ($len < 8 || (ord($d[0]) | (ord($d[1]) << 8)) !== 0x0003) {
            return null; // bukan RES_XML
        }

        $u16 = static fn (int $o) => ord($d[$o]) | (ord($d[$o + 1]) << 8);
        $u32 = static fn (int $o) => ord($d[$o]) | (ord($d[$o + 1]) << 8) | (ord($d[$o + 2]) << 16) | (ord($d[$o + 3]) << 24);

        $strings = [];
        $off     = 8;

        while ($off + 8 <= $len) {
            $type  = $u16($off);
            $csize = $u32($off + 4);
            if ($csize < 8 || $off + $csize > $len) break;

            if ($type === 0x0001) { // STRING POOL
                $strings = self::readAxmlStrings($d, $off, $u16, $u32);
            } elseif ($type === 0x0102) { // START ELEMENT
                $nameIdx = $u32($off + 20);
                $name    = $strings[$nameIdx] ?? '';
                if ($name === 'manifest') {
                    $attrStart = $u16($off + 24);
                    $attrSize  = $u16($off + 26) ?: 20;
                    $attrCount = $u16($off + 28);
                    $base      = $off + 16 + $attrStart;

                    $vCode = null; $vName = null;
                    for ($i = 0; $i < $attrCount; $i++) {
                        $a       = $base + $i * $attrSize;
                        if ($a + 20 > $len) break;
                        $aName   = $strings[$u32($a + 4)] ?? '';
                        $rawVal  = $u32($a + 8);
                        $dataTyp = ord($d[$a + 15]);
                        $data    = $u32($a + 16);

                        if ($aName === 'versionCode') {
                            $vCode = $data; // tipe int → nilai langsung
                        } elseif ($aName === 'versionName') {
                            if ($rawVal !== 0xFFFFFFFF && isset($strings[$rawVal])) {
                                $vName = $strings[$rawVal];
                            } elseif ($dataTyp === 0x03 && isset($strings[$data])) {
                                $vName = $strings[$data];
                            }
                        }
                    }

                    if ($vCode !== null && $vCode >= 1) {
                        return ['version_code' => (int) $vCode, 'version_name' => (string) ($vName ?? '')];
                    }
                    return null;
                }
            }
            $off += $csize;
        }
        return null;
    }

    /** Baca array string dari chunk STRING POOL AXML. */
    private static function readAxmlStrings(string $d, int $poolOff, callable $u16, callable $u32): array
    {
        $headerSize   = $u16($poolOff + 2);
        $stringCount  = $u32($poolOff + 8);
        $flags        = $u32($poolOff + 16);
        $stringsStart = $u32($poolOff + 20);
        $isUtf8       = ($flags & 0x100) !== 0;
        $offsetsBase  = $poolOff + $headerSize;
        $dataBase     = $poolOff + $stringsStart;
        $len          = strlen($d);

        $out = [];
        for ($i = 0; $i < $stringCount; $i++) {
            $oPos = $offsetsBase + $i * 4;
            if ($oPos + 4 > $len) break;
            $p = $dataBase + $u32($oPos);
            if ($p >= $len) { $out[$i] = ''; continue; }

            if ($isUtf8) {
                // lewati char-len (u8 + ekstensi), lalu baca byte-len
                $b = ord($d[$p]); $p++;
                if ($b & 0x80) { $p++; }
                $bl = ord($d[$p]); $p++;
                if ($bl & 0x80) { $bl = (($bl & 0x7F) << 8) | ord($d[$p]); $p++; }
                $out[$i] = substr($d, $p, $bl);
            } else {
                $cl = $u16($p); $p += 2;
                if ($cl & 0x8000) { $cl = (($cl & 0x7FFF) << 16) | $u16($p); $p += 2; }
                $raw = substr($d, $p, $cl * 2);
                $out[$i] = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
            }
        }
        return $out;
    }

    /** Validasi isi file benar-benar APK: signature ZIP + ada AndroidManifest.xml. */
    public static function validateApk(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        $fh    = @fopen($path, 'rb');
        $magic = $fh ? fread($fh, 4) : '';
        if ($fh) fclose($fh);
        if ($magic !== "PK\x03\x04") {
            return false;
        }
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return false;
            }
            $hasManifest = $zip->locateName('AndroidManifest.xml') !== false;
            $zip->close();
            return $hasManifest;
        }
        return true; // tanpa ekstensi zip: cukup signature
    }

    /**
     * Info APK terbaru yang terpasang di folder (untuk bootstrap). Semua nilai
     * diturunkan LANGSUNG dari file → tak ada isi-manual yang bisa salah.
     *
     * @return array{available:bool, filename:string, url:string, version:string, build:int, size:int}
     */
    public static function current(): array
    {
        $file = self::listApks()[0] ?? '';
        $none = ['available' => false, 'filename' => '', 'url' => '', 'version' => '', 'build' => 0, 'size' => 0];
        if ($file === '') {
            return $none;
        }
        $path = self::dir() . $file;
        if (!is_file($path)) {
            return $none;
        }
        $info = self::readApkInfo($path);
        return [
            'available' => true,
            'filename'  => $file,
            'url'       => base_url('downloads/apk/' . $file),
            'version'   => $info['version_name'] ?? '',
            'build'     => (int) ($info['version_code'] ?? 0),
            'size'      => (int) (@filesize($path) ?: 0),
        ];
    }

    /**
     * Daftarkan APK yang SUDAH ADA di folder downloads/apk → set metadata bootstrap.
     * Bila $filename null → pakai file .apk terbaru di folder (auto-detect).
     * Menghapus file .apk lain agar hanya menyimpan yang terdaftar (hemat disk).
     *
     * @return array{ok:bool, code?:string, message:string, data?:array}
     */
    public static function register(?string $filename, string $version, int $build, string $notes = '', ?int $minBuild = null): array
    {
        $dir      = self::dir();
        $filename = $filename ? basename($filename) : (self::listApks()[0] ?? '');
        if ($filename === '' || !preg_match('/\.apk$/i', $filename)) {
            return ['ok' => false, 'code' => 'invalid_filename', 'message' => 'Tidak ada file .apk di folder. Upload dulu ke public/downloads/apk/ via FTP / File Manager.'];
        }

        $path = $dir . $filename;
        if (!is_file($path)) {
            return ['ok' => false, 'code' => 'apk_not_found', 'message' => 'File ' . $filename . ' tidak ditemukan di downloads/apk/.'];
        }
        if (!self::validateApk($path)) {
            return ['ok' => false, 'code' => 'invalid_apk', 'message' => 'File bukan APK valid (perlu signature ZIP + AndroidManifest.xml).'];
        }

        // ── Auto-detect versi & build LANGSUNG dari APK (sumber kebenaran) ──
        $info = self::readApkInfo($path);
        if ($info) {
            $build   = $info['version_code'];
            $version = $info['version_name'] !== '' ? $info['version_name'] : $version;
        }

        if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) {
            return ['ok' => false, 'code' => 'invalid_version', 'message' => 'Versi harus format semver dasar (mis. 1.0.1).'];
        }
        if ($build < 1) {
            return ['ok' => false, 'code' => 'invalid_build', 'message' => 'Build harus bilangan bulat > 0.'];
        }

        // Simpan hanya yang didaftarkan — hapus .apk lain.
        foreach (glob($dir . '*.apk') ?: [] as $other) {
            if (basename($other) !== $filename) {
                @unlink($other);
            }
        }

        $size = @filesize($path) ?: 0;

        // Simpan metadata (min_build utk update WAJIB + catatan rilis).
        $meta = self::readMeta();
        $meta['notes']       = mb_substr($notes, 0, 500);
        $meta['uploaded_at'] = date('Y-m-d H:i:s');
        $minBuildOut = (int) ($meta['min_build'] ?? 1);
        if ($minBuild !== null && $minBuild >= 1) {
            $meta['min_build'] = $minBuild;
            $minBuildOut       = $minBuild;
        }
        self::writeMeta($meta);

        log_message('info', '[APK REGISTER] ' . $filename . ' v' . $version . ' build ' . $build . ' (' . $size . ' bytes)');

        return ['ok' => true, 'message' => 'APK terdaftar.', 'data' => [
            'apk_url'       => base_url('downloads/apk/' . $filename),
            'apk_version'   => $version,
            'apk_build'     => $build,
            'apk_size'      => $size,
            'min_build'     => $minBuildOut,
            'filename'      => $filename,
            'auto_detected' => $info !== null,
        ]];
    }
}
