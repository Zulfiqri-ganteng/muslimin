<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Backup (Sistem → Backup & Restore; docs/DESAIN-ALAT-SISTEM.md F3). Cadangan database dengan PHP murni (tanpa mysqldump/exec —
 * hosting bersama sering memblokirnya) ke .sql.gz, dan cadangan berkas unggahan ke .zip. Disimpan di writable/backup/ (di luar
 * folder publik). Nama berkas = sumber kebenaran daftar: {db|berkas}-YYYYmmdd-HHiiss-{jenis}.{sql.gz|zip}.
 * Berkas .sql.gz memakai DROP TABLE + CREATE TABLE + INSERT, ditutup baris penanda "-- Backup selesai" (berkas terpotong = tidak sah).
 */
class BackupSistem
{
    /** Jenis → [label, jumlah yang disimpan per isi (yang lebih lama dihapus otomatis)]. */
    public const JENIS = [
        'manual'           => ['Manual', 10],
        'otomatis'         => ['Otomatis (tiap malam)', 7],
        'sebelum-pulihkan' => ['Otomatis sebelum restore', 3],
    ];

    public const PENANDA_AWAL    = '-- Backup database aplikasi muslimin';
    public const PENANDA_SELESAI = '-- Backup selesai:';

    /** Folder berkas unggahan yang ikut backup berkas (label → path). */
    public static function folderBerkas(): array
    {
        return [
            'public/uploads'         => FCPATH . 'uploads',
            'writable/uploads'       => WRITEPATH . 'uploads',
            'writable/pkl'           => WRITEPATH . 'pkl',
            'writable/data-sekolah'  => WRITEPATH . 'data-sekolah',
        ];
    }

    private const POLA_NAMA = '/^(db|berkas)-(\d{8})-(\d{6})-(manual|otomatis|sebelum-pulihkan)\.(sql\.gz|zip)$/';
    private const BARIS_INSERT = 200;       // baris per perintah INSERT
    private const BYTE_INSERT  = 1_000_000; // atau sampai ±1 MB

    private static ?string $folderUji = null;
    private static ?BaseConnection $dbUji = null;

    /** Uji: ganti folder backup / koneksi database. null = kembali ke bawaan. */
    public static function pakaiFolder(?string $folder): void
    {
        self::$folderUji = $folder;
    }

    public static function pakaiKoneksi(?BaseConnection $db): void
    {
        self::$dbUji = $db;
    }

    public static function koneksi(): BaseConnection
    {
        return self::$dbUji ?? db_connect();
    }

    /** Folder backup (dibuat bila belum ada, berisi index.html kosong seperti folder writable lain). */
    public static function folder(): string
    {
        $f = rtrim(self::$folderUji ?? WRITEPATH . 'backup', '/\\') . DIRECTORY_SEPARATOR;
        if (! is_dir($f)) {
            @mkdir($f, 0755, true);
        }
        if (! is_file($f . 'index.html')) {
            @file_put_contents($f . 'index.html', '');
        }

        return $f;
    }

    /**
     * Daftar backup, terbaru di atas.
     *
     * @return list<array{kunci: string, isi: string, jenis: string, label_jenis: string, waktu: string, ukuran: int, tabel: ?int, baris: ?int, oleh: ?string}>
     */
    public static function daftar(): array
    {
        $out = [];
        foreach (scandir(self::folder()) ?: [] as $nama) {
            if (! preg_match(self::POLA_NAMA, $nama, $m)) {
                continue;
            }
            $path = self::folder() . $nama;
            $w    = \DateTimeImmutable::createFromFormat('YmdHis', $m[2] . $m[3]);
            $info = ['kunci' => $nama, 'isi' => $m[1], 'jenis' => $m[4], 'label_jenis' => self::JENIS[$m[4]][0],
                'waktu' => $w ? $w->format('Y-m-d H:i:s') : '', 'ukuran' => (int) filesize($path), 'tabel' => null, 'baris' => null, 'oleh' => null];
            if ($m[1] === 'db') {
                $info = array_merge($info, self::kepala($path));
            }
            $out[] = $info;
        }
        usort($out,static fn (array $a, array $b): int => strcmp($b['waktu'], $a['waktu']) ?: strcmp($a['isi'], $b['isi']));

        return $out;
    }

    /** Path backup dari kuncinya (hanya nama yang cocok pola & ada di folder), atau null. */
    public static function path(string $kunci): ?string
    {
        return preg_match(self::POLA_NAMA, $kunci) && is_file(self::folder() . $kunci) ? self::folder() . $kunci : null;
    }

    public static function totalUkuran(): int
    {
        return array_sum(array_column(self::daftar(), 'ukuran'));
    }

    /**
     * Buat backup database. Ditulis ke berkas sementara lalu diganti nama (tidak pernah ada berkas setengah jadi di daftar).
     *
     * @return array info backup (bentuk daftar())
     */
    public static function buatDb(string $jenis, string $oleh): array
    {
        $jenis = isset(self::JENIS[$jenis]) ? $jenis : 'manual';
        $db    = self::koneksi();
        $db->initialize();
        $m     = $db->connID;
        @set_time_limit(600);
        $nama  = 'db-' . date('Ymd-His') . '-' . $jenis . '.sql.gz';
        $tmp   = self::folder() . $nama . '.tmp';
        $gz    = gzopen($tmp, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('Berkas backup tidak bisa ditulis di ' . self::folder());
        }
        $tzLama = (string) $m->query('SELECT @@session.time_zone AS tz')->fetch_assoc()['tz'];
        $m->query("SET time_zone = '+00:00'");
        try {
            $tabel = [];
            $res   = $m->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            while ($r = $res->fetch_row()) {
                $tabel[] = (string) $r[0];
            }
            $res->free();
            sort($tabel, SORT_STRING);
            $migrasi = '';
            if (in_array('migrations', $tabel, true)) {
                $migrasi = (string) ($m->query('SELECT MAX(version) AS v FROM `migrations`')->fetch_assoc()['v'] ?? '');
            }

            gzwrite($gz, self::PENANDA_AWAL . " (Sistem → Backup & Restore)\n"
                . '-- Dibuat: ' . date('Y-m-d H:i:s') . ' WIB oleh ' . str_replace(["\n", "\r"], ' ', $oleh) . ' (' . $jenis . ")\n"
                . '-- Database: ' . $db->getDatabase() . ' · Tabel: ' . count($tabel) . ' · Migrasi terakhir: ' . $migrasi . "\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");

            $total = 0;
            foreach ($tabel as $t) {
                $total += self::tulisTabel($m, $gz, $t);
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n" . self::PENANDA_SELESAI . ' ' . count($tabel) . ' tabel, ' . $total . " baris\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($tmp);

            throw $e;
        } finally {
            $m->query("SET time_zone = '" . $m->real_escape_string($tzLama) . "'");
        }
        gzclose($gz);
        if (! @rename($tmp, self::folder() . $nama)) {
            @unlink($tmp);

            throw new \RuntimeException('Berkas backup tidak bisa disimpan.');
        }
        self::rapikan();

        return self::info($nama);
    }

    /** Backup berkas unggahan (foto, logo, arsip dokumen, TTD, data sekolah) ke .zip. */
    public static function buatBerkas(string $jenis, string $oleh): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Ekstensi ZIP PHP tidak tersedia di server ini.');
        }
        $jenis = isset(self::JENIS[$jenis]) ? $jenis : 'manual';
        @set_time_limit(600);
        $nama = 'berkas-' . date('Ymd-His') . '-' . $jenis . '.zip';
        $tmp  = self::folder() . $nama . '.tmp';
        $zip  = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Berkas backup tidak bisa ditulis di ' . self::folder());
        }
        $n = 0;
        foreach (self::folderBerkas() as $label => $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $zip->addFile($f->getPathname(), $label . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1)));
                    $n++;
                }
            }
        }
        $zip->setArchiveComment('Backup berkas unggahan — ' . date('Y-m-d H:i:s') . ' WIB oleh ' . $oleh . ' (' . $jenis . '), ' . $n . ' berkas');
        if ($n === 0) {
            $zip->addFromString('KOSONG.txt', 'Tidak ada berkas unggahan saat backup dibuat.');
        }
        $zip->close();
        if (! @rename($tmp, self::folder() . $nama)) {
            @unlink($tmp);

            throw new \RuntimeException('Berkas backup tidak bisa disimpan.');
        }
        self::rapikan();

        return self::info($nama);
    }

    public static function hapus(string $kunci): bool
    {
        $p = self::path($kunci);

        return $p !== null && @unlink($p);
    }

    /** Simpan N terbaru per (isi, jenis) sesuai JENIS; sisanya dihapus. @return int jumlah terhapus */
    public static function rapikan(): int
    {
        $grup = [];
        foreach (self::daftar() as $b) {
            $grup[$b['isi'] . '|' . $b['jenis']][] = $b;
        }
        $n = 0;
        foreach ($grup as $k => $isi) {
            $batas = self::JENIS[explode('|', $k)[1]][1];
            foreach (array_slice($isi, $batas) as $b) {
                $n += (int) @unlink(self::folder() . $b['kunci']);
            }
        }

        return $n;
    }

    /** Info satu backup (bentuk daftar()). */
    public static function info(string $kunci): array
    {
        foreach (self::daftar() as $b) {
            if ($b['kunci'] === $kunci) {
                return $b;
            }
        }

        throw new \RuntimeException('Backup tidak ditemukan: ' . $kunci);
    }

    // =================================================================
    // Pembantu
    // =================================================================

    /** Isi kepala backup buatan sistem: jumlah tabel, baris (dari penanda selesai bila terbaca di ujung), oleh. */
    private static function kepala(string $path): array
    {
        $out = ['tabel' => null, 'baris' => null, 'oleh' => null];
        $gz  = @gzopen($path, 'rb');
        if ($gz === false) {
            return $out;
        }
        $kepala = (string) gzread($gz, 600);
        gzclose($gz);
        if (preg_match('/^-- Dibuat: [^\n]* oleh (.+) \((?:manual|otomatis|sebelum-pulihkan)\)$/m', $kepala, $m)) {
            $out['oleh'] = $m[1];
        }
        if (preg_match('/· Tabel: (\d+)/u', $kepala, $m)) {
            $out['tabel'] = (int) $m[1];
        }

        return $out;
    }

    /** Tulis satu tabel: DROP + CREATE + INSERT bertahap (kolom generated dilewati, biner sebagai 0x…). @return int jumlah baris */
    private static function tulisTabel(\mysqli $m, $gz, string $t): int
    {
        $q      = '`' . str_replace('`', '``', $t) . '`';
        $create = (string) ($m->query('SHOW CREATE TABLE ' . $q)->fetch_row()[1] ?? '');
        $kolom  = [];
        $res    = $m->query('SHOW COLUMNS FROM ' . $q);
        while ($c = $res->fetch_assoc()) {
            if (! preg_match('/GENERATED/i', (string) $c['Extra'])) {
                $kolom[] = '`' . str_replace('`', '``', (string) $c['Field']) . '`';
            }
        }
        $res->free();
        gzwrite($gz, "-- Tabel {$q}\nDROP TABLE IF EXISTS {$q};\n{$create};\n");

        $daftarKolom = implode(', ', $kolom);
        $res = $m->query('SELECT ' . $daftarKolom . ' FROM ' . $q, MYSQLI_USE_RESULT);
        $jenis = array_map(static fn (object $f): array => [$f->type, $f->charsetnr], $res->fetch_fields());
        $n     = 0;
        $buf   = [];
        $byte  = 0;
        $tulis = static function () use (&$buf, &$byte, $gz, $q, $daftarKolom): void {
            if ($buf !== []) {
                gzwrite($gz, 'INSERT INTO ' . $q . ' (' . $daftarKolom . ") VALUES\n" . implode(",\n", $buf) . ";\n");
                $buf  = [];
                $byte = 0;
            }
        };
        while ($r = $res->fetch_row()) {
            $nilai = [];
            foreach ($r as $i => $v) {
                $nilai[] = self::nilai($m, $v, $jenis[$i][0], $jenis[$i][1]);
            }
            $baris = '(' . implode(',', $nilai) . ')';
            $buf[] = $baris;
            $byte += strlen($baris);
            $n++;
            if (count($buf) >= self::BARIS_INSERT || $byte >= self::BYTE_INSERT) {
                $tulis();
            }
        }
        $res->free();
        $tulis();
        gzwrite($gz, "\n");

        return $n;
    }

    /** Nilai SQL satu sel: NULL, angka apa adanya, biner/BIT sebagai 0x…, selebihnya string ber-escape. */
    private static function nilai(\mysqli $m, ?string $v, int $tipe, int $charset): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (in_array($tipe, [MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24, MYSQLI_TYPE_FLOAT,
            MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_NEWDECIMAL, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_YEAR], true) && is_numeric($v)) {
            return $v;
        }
        if ($tipe === MYSQLI_TYPE_BIT || ($charset === 63 && in_array($tipe, [MYSQLI_TYPE_TINY_BLOB, MYSQLI_TYPE_MEDIUM_BLOB, MYSQLI_TYPE_LONG_BLOB,
            MYSQLI_TYPE_BLOB, MYSQLI_TYPE_STRING, MYSQLI_TYPE_VAR_STRING], true))) {
            return $v === '' ? "''" : '0x' . bin2hex($v);
        }

        return "'" . $m->real_escape_string($v) . "'";
    }
}
