<?php

namespace App\Libraries;

/**
 * Restore database (Sistem → Backup & Restore; docs/DESAIN-ALAT-SISTEM.md F4). Sumber: backup .sql.gz buatan sistem atau berkas
 * .sql/.sql.gz unggahan (termasuk ekspor phpMyAdmin). Syarat dijaga controller: Mode Maintenance aktif + sandi + ketik PULIHKAN.
 *
 * Alur "tugas" (berkas writable/backup/pulihkan-{id}.json) supaya tidak kena batas waktu hosting:
 *   mulai()   → salin/urai sumber ke pulihkan-{id}.sql, periksa isinya → tahap 'siap'
 *   langkah() → tahap siap: buat backup "sebelum-pulihkan", hapus SEMUA tabel → 'jalan'; tahap jalan: jalankan perintah SQL
 *               sampai ±DETIK_PER_LANGKAH detik, simpan posisi byte → ulangi sampai habis → 'selesai' (atau 'gagal').
 * Setiap langkah = koneksi baru, jadi perintah transaksi (START TRANSACTION/COMMIT/AUTOCOMMIT) & LOCK TABLES dari berkas dilewati
 * dan tiap perintah langsung tersimpan. Bila gagal di tengah, backup "sebelum-pulihkan" bisa dipulihkan dengan cara yang sama.
 */
class PulihkanBackup
{
    public const KATA             = 'PULIHKAN';
    public const DETIK_PER_LANGKAH = 8.0;
    private const BACA            = 1_048_576;
    private const POLA_LEWAT      = '/^(START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK|LOCK\s+TABLES|UNLOCK\s+TABLES|USE\s|CREATE\s+DATABASE|SET\s+(@@SESSION\.|SESSION\s+)?AUTOCOMMIT)\b/i';

    /**
     * Periksa berkas .sql (sudah terurai): harus berisi CREATE TABLE `admins` & `migrations` (database aplikasi ini), tanpa DELIMITER,
     * dan bila buatan sistem harus utuh (ada penanda selesai).
     *
     * @return array{ok: bool, pesan: string, tabel: int, buatan_sistem: bool}
     */
    public static function periksa(string $sql): array
    {
        $fh = @fopen($sql, 'rb');
        if ($fh === false) {
            return ['ok' => false, 'pesan' => 'Berkas tidak bisa dibaca.', 'tabel' => 0, 'buatan_sistem' => false];
        }
        $awal    = (string) fread($fh, 400);
        $sistem  = str_starts_with($awal, BackupSistem::PENANDA_AWAL);
        $tabel   = [];
        $sisa    = '';
        $delim   = false;
        $selesai = false;
        rewind($fh);
        while (! feof($fh)) {
            $blok = $sisa . (string) fread($fh, self::BACA);
            if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $blok, $m)) {
                foreach ($m[1] as $t) {
                    $tabel[strtolower($t)] = true;
                }
            }
            $delim   = $delim || (bool) preg_match('/^\s*DELIMITER\s/mi', $blok);
            $selesai = $selesai || str_contains($blok, BackupSistem::PENANDA_SELESAI);
            $sisa    = substr($blok, -200);
        }
        fclose($fh);

        $n = count($tabel);
        if ($delim) {
            return ['ok' => false, 'pesan' => 'Berkas berisi DELIMITER (prosedur/trigger) — pulihkan lewat phpMyAdmin.', 'tabel' => $n, 'buatan_sistem' => $sistem];
        }
        if (! isset($tabel['admins'], $tabel['migrations'])) {
            return ['ok' => false, 'pesan' => 'Berkas ini bukan backup database aplikasi ini (tabel admins/migrations tidak ditemukan).', 'tabel' => $n, 'buatan_sistem' => $sistem];
        }
        if ($sistem && ! $selesai) {
            return ['ok' => false, 'pesan' => 'Berkas backup tidak utuh (terpotong) — pilih backup lain.', 'tabel' => $n, 'buatan_sistem' => $sistem];
        }

        return ['ok' => true, 'pesan' => 'Berkas sah: ' . $n . ' tabel.', 'tabel' => $n, 'buatan_sistem' => $sistem];
    }

    /**
     * Siapkan tugas restore dari berkas sumber (.sql atau .sql.gz). Sumber tidak diubah.
     *
     * @return array tugas, atau ['galat' => pesan] bila berkas tidak sah / ada restore lain yang sedang berjalan
     */
    public static function mulai(string $sumber, string $namaSumber, string $oleh): array
    {
        self::bersihkanLama();
        foreach (glob(BackupSistem::folder() . 'pulihkan-*.json') ?: [] as $f) {
            $t = json_decode((string) file_get_contents($f), true);
            if (in_array($t['tahap'] ?? '', ['siap', 'jalan'], true) && time() - (int) ($t['diperbarui'] ?? 0) < 600) {
                return ['galat' => 'Masih ada restore lain yang sedang berjalan (dimulai ' . ($t['mulai'] ?? '') . ' oleh ' . ($t['oleh'] ?? '') . ').'];
            }
        }
        $id   = bin2hex(random_bytes(8));
        $kerja = BackupSistem::folder() . 'pulihkan-' . $id . '.sql';
        $in   = str_ends_with(strtolower($namaSumber), '.gz') ? @gzopen($sumber, 'rb') : @fopen($sumber, 'rb');
        $out  = @fopen($kerja, 'wb');
        if ($in === false || $out === false) {
            return ['galat' => 'Berkas sumber tidak bisa dibaca.'];
        }
        $gz = str_ends_with(strtolower($namaSumber), '.gz');
        while (! ($gz ? gzeof($in) : feof($in))) {
            fwrite($out, (string) ($gz ? gzread($in, self::BACA) : fread($in, self::BACA)));
        }
        $gz ? gzclose($in) : fclose($in);
        fclose($out);

        $cek = self::periksa($kerja);
        if (! $cek['ok']) {
            @unlink($kerja);

            return ['galat' => $cek['pesan']];
        }
        $tugas = ['id' => $id, 'sumber' => $namaSumber, 'oleh' => $oleh, 'tahap' => 'siap', 'posisi' => 0, 'total' => (int) filesize($kerja),
            'perintah' => 0, 'tabel_berkas' => $cek['tabel'], 'mulai' => date('Y-m-d H:i:s'), 'diperbarui' => time(), 'selesai' => null,
            'cadangan' => null, 'galat' => null, 'catatan' => []];
        self::simpan($tugas);

        return $tugas;
    }

    public static function tugas(string $id): ?array
    {
        if (! preg_match('/^[a-f0-9]{16}$/', $id) || ! is_file(self::berkasTugas($id))) {
            return null;
        }
        $t = json_decode((string) file_get_contents(self::berkasTugas($id)), true);

        return is_array($t) ? $t : null;
    }

    /** Persen kemajuan 0–100 (tahap siap = 0, selesai = 100). */
    public static function persen(array $t): int
    {
        return $t['tahap'] === 'selesai' ? 100 : ($t['total'] > 0 ? min(99, (int) floor($t['posisi'] * 100 / $t['total'])) : 0);
    }

    /**
     * Jalankan satu langkah (dikunci flock — dua tab tidak bisa menjalankan bersamaan). $batasDetik null = sampai habis (perintah CLI).
     */
    public static function langkah(string $id, ?float $batasDetik = self::DETIK_PER_LANGKAH): array
    {
        $t = self::tugas($id);
        if ($t === null) {
            return ['galat' => 'Tugas restore tidak ditemukan.'];
        }
        if (! in_array($t['tahap'], ['siap', 'jalan'], true)) {
            return $t;
        }
        $kunci = fopen(self::berkasTugas($id) . '.lock', 'c');
        if ($kunci === false || ! flock($kunci, LOCK_EX | LOCK_NB)) {
            return $t; // langkah lain masih berjalan
        }
        @set_time_limit(max(60, (int) ($batasDetik ?? 0) * 4));
        $awalWaktu = microtime(true);
        $db        = BackupSistem::koneksi();
        $db->initialize();
        $m = $db->connID;
        try {
            self::aturSesi($m);
            if ($t['tahap'] === 'siap') {
                $c              = BackupSistem::buatDb('sebelum-pulihkan', $t['oleh']);
                $t['cadangan']  = $c['kunci'];
                $t['catatan'][] = 'Backup sebelum restore dibuat: ' . $c['kunci'];
                self::aturSesi($m);
                $t['catatan'][] = 'Tabel lama dihapus: ' . self::hapusSemuaTabel($m);
                $t['tahap']     = 'jalan';
                self::simpan($t);
            }
            $t = self::jalankan($m, $t, $batasDetik, $awalWaktu);
        } catch (\Throwable $e) {
            $t['tahap'] = 'gagal';
            $t['galat'] = $e->getMessage();
        } finally {
            try {
                $m->query('COMMIT');
            } catch (\Throwable) {
            }
            if ($t['tahap'] === 'selesai') {
                self::sesudahSelesai($t);
            }
            self::simpan($t);
            flock($kunci, LOCK_UN);
            fclose($kunci);
        }

        return $t;
    }

    /** Jalankan semua langkah sekaligus (perintah CLI / uji). */
    public static function sampaiHabis(string $id): array
    {
        do {
            $t = self::langkah($id, null);
        } while (in_array($t['tahap'] ?? '', ['siap', 'jalan'], true));

        return $t;
    }

    /**
     * Pecah teks SQL menjadi perintah utuh. Paham kutip '…' "…" `…` (dengan \ escape), komentar "--", "#", dan blok garis-miring-
     * bintang (komentar bersyarat garis-miring-bintang-seru MySQL tetap bagian perintah). Bila $akhir false, sisa yang belum ditutup ; tidak diambil. Bila $akhir true, sisa tanpa ;
     * diambil sebagai perintah terakhir — kecuali kutip/komentar belum tertutup (berkas terpotong): sisa itu TIDAK terpakai.
     *
     * @return array{0: list<string>, 1: int, 2: list<int>} [perintah, byte terpakai, byte sesudah tiap perintah]
     */
    public static function pecah(string $s, bool $akhir): array
    {
        $out   = [];
        $ujung = [];
        $n     = strlen($s);
        $i     = 0;
        $mulai = 0;
        $pakai = 0;
        while ($i < $n) {
            $i += strcspn($s, "'\"`;-#/", $i);
            if ($i >= $n) {
                break;
            }
            $c = $s[$i];
            if ($c === ';') {
                $p = trim(substr($s, $mulai, $i - $mulai));
                $i++;
                if ($p !== '') {
                    $out[]   = $p;
                    $ujung[] = $i;
                }
                $mulai = $pakai = $i;
            } elseif ($c === "'" || $c === '"' || $c === '`') {
                $j = $i + 1;
                while (true) {
                    $j += strcspn($s, $c === '`' ? '`' : $c . '\\', $j);
                    if ($j >= $n) {
                        break 2; // kutip belum tertutup di blok ini
                    }
                    if ($s[$j] === '\\') {
                        $j += 2;
                        continue;
                    }
                    break;
                }
                $i = $j + 1;
            } elseif ($c === '#' || ($c === '-' && substr($s, $i, 2) === '--' && ($i + 2 >= $n || ctype_space($s[$i + 2])))) {
                $e = strpos($s, "\n", $i);
                if ($e === false) {
                    if (! $akhir) {
                        break;
                    }
                    $e = $n;
                }
                $s = substr_replace($s, str_repeat(' ', $e - $i), $i, $e - $i); // komentar → spasi (panjang tetap)
                $i = $e;
            } elseif ($c === '/' && ($s[$i + 1] ?? '') === '*') {
                $e = strpos($s, '*/', $i + 2);
                if ($e === false) {
                    break;
                }
                if (($s[$i + 2] ?? '') !== '!') {
                    $s = substr_replace($s, str_repeat(' ', $e + 2 - $i), $i, $e + 2 - $i);
                }
                $i = $e + 2;
            } else {
                $i++;
            }
        }
        if ($akhir && $i >= $n) {
            $p = trim(substr($s, $mulai));
            if ($p !== '') {
                $out[]   = $p;
                $ujung[] = $n;
            }
            $pakai = $n;
        }

        return [$out, $pakai, $ujung];
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private static function jalankan(\mysqli $m, array $t, ?float $batasDetik, float $awal): array
    {
        $kerja = BackupSistem::folder() . 'pulihkan-' . $t['id'] . '.sql';
        $fh    = fopen($kerja, 'rb');
        fseek($fh, $t['posisi']);
        $buf   = '';
        $dasar = $t['posisi'];
        while (true) {
            $baca  = (string) fread($fh, self::BACA);
            $akhir = feof($fh);
            $buf  .= $baca;
            [$perintah, $pakai, $ujung] = self::pecah($buf, $akhir);
            if ($perintah === [] && ! $akhir) {
                continue; // satu perintah lebih besar dari blok baca — tambah blok
            }
            if ($akhir && trim(substr($buf, $pakai)) !== '') {
                fclose($fh);
                $t['tahap'] = 'gagal';
                $t['galat'] = 'Berkas terpotong: perintah terakhir tidak utuh (kutip/komentar tidak tertutup). Data sampai perintah ke-'
                    . $t['perintah'] . ' sudah masuk; pulihkan backup "sebelum restore" bila perlu.';

                return $t;
            }
            $habisWaktu = false;
            foreach ($perintah as $k => $p) {
                if (! preg_match(self::POLA_LEWAT, $p)) {
                    try {
                        $r = $m->query($p);
                        if ($r instanceof \mysqli_result) {
                            $r->free();
                        } elseif ($r === false) {
                            throw new \RuntimeException($m->error);
                        }
                    } catch (\Throwable $e) {
                        fclose($fh);
                        $t['tahap'] = 'gagal';
                        $t['galat'] = 'Perintah ke-' . ($t['perintah'] + 1) . ' gagal: ' . $e->getMessage() . ' — ' . mb_strimwidth($p, 0, 160, '…');

                        return $t;
                    }
                }
                $t['perintah']++;
                if ($batasDetik !== null && microtime(true) - $awal > $batasDetik) {
                    $habisWaktu = true;
                    break;
                }
            }
            if ($habisWaktu) {
                $t['posisi'] = $dasar + $ujung[$k]; // tepat sesudah perintah terakhir yang dijalankan
                break;
            }
            $dasar += $pakai;
            $buf    = substr($buf, $pakai);
            $t['posisi'] = $dasar;
            if ($akhir) {
                $t['posisi'] = $t['total'];
                $t['tahap']  = 'selesai';
                break;
            }
        }
        fclose($fh);
        $t['diperbarui'] = time();

        return $t;
    }

    private static function aturSesi(\mysqli $m): void
    {
        foreach (['SET NAMES utf8mb4', 'SET FOREIGN_KEY_CHECKS = 0', 'SET UNIQUE_CHECKS = 0', "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'",
            "SET time_zone = '+00:00'", 'SET autocommit = 1'] as $q) {
            $m->query($q);
        }
    }

    /** Hapus semua tabel & view di database tujuan. @return int jumlah */
    private static function hapusSemuaTabel(\mysqli $m): int
    {
        $n   = 0;
        $res = $m->query('SHOW FULL TABLES');
        $obj = [];
        while ($r = $res->fetch_row()) {
            $obj[] = [(string) $r[0], (string) $r[1]];
        }
        $res->free();
        foreach ($obj as [$nama, $jenis]) {
            $m->query(($jenis === 'VIEW' ? 'DROP VIEW IF EXISTS ' : 'DROP TABLE IF EXISTS ') . '`' . str_replace('`', '``', $nama) . '`');
            $n++;
        }

        return $n;
    }

    /** Sesudah selesai: hapus berkas kerja, kosongkan cache aplikasi, catat migrasi yang belum dijalankan. */
    private static function sesudahSelesai(array &$t): void
    {
        $t['selesai'] = date('Y-m-d H:i:s');
        @unlink(BackupSistem::folder() . 'pulihkan-' . $t['id'] . '.sql');
        try {
            cache()->clean();
        } catch (\Throwable) {
        }
        $belum = self::migrasiBelum();
        if ($belum > 0) {
            $t['catatan'][] = $belum . ' migrasi kode belum ada di backup ini — jalankan `phpm spark migrate` di Terminal cPanel.';
        }
        $t['migrasi_belum'] = $belum;
    }

    /** Jumlah berkas migrasi aplikasi yang versinya belum tercatat di tabel migrations (database tujuan). */
    public static function migrasiBelum(): int
    {
        try {
            $ada = array_column(BackupSistem::koneksi()->table('migrations')->select('version')->get()->getResultArray(), 'version');
            $n   = 0;
            foreach (glob(APPPATH . 'Database/Migrations/*.php') ?: [] as $f) {
                if (preg_match('/^(\d{4}-\d{2}-\d{2}-\d{6})_/', basename($f), $m) && ! in_array($m[1], $ada, true)) {
                    $n++;
                }
            }

            return $n;
        } catch (\Throwable) {
            return -1;
        }
    }

    private static function berkasTugas(string $id): string
    {
        return BackupSistem::folder() . 'pulihkan-' . $id . '.json';
    }

    private static function simpan(array $t): void
    {
        $t['diperbarui'] = time();
        file_put_contents(self::berkasTugas($t['id']), json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /** Berkas tugas & kerja yang lebih dari 2 hari dihapus. */
    private static function bersihkanLama(): void
    {
        foreach (glob(BackupSistem::folder() . 'pulihkan-*') ?: [] as $f) {
            if (time() - (int) filemtime($f) > 172800) {
                @unlink($f);
            }
        }
    }
}
