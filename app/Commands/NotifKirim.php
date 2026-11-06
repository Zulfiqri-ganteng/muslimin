<?php

namespace App\Commands;

use App\Libraries\NotifJadwal;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Kirim notifikasi jadwal guru yang waktunya tiba — dipanggil CRON TIAP MENIT:
 *
 *   * * * * * /path/php8.3 /home/kank9494/kangmuslim/spark notif:kirim >/dev/null 2>&1
 *
 * (Path PHP 8.3 = hasil `alias phpm` di Terminal cPanel; alias tidak berlaku
 * di cron. JANGAN pakai `php` biasa — di hosting itu PHP 7.4.)
 *
 * Simulasi tanpa mengirim:
 *   php spark notif:kirim --sekarang "2026-09-29 06:55" --kering
 */
class NotifKirim extends BaseCommand
{
    protected $group       = 'notif';
    protected $name        = 'notif:kirim';
    protected $description = 'Kirim notifikasi jadwal guru yang waktunya tiba (cron tiap menit).';
    protected $usage       = 'notif:kirim [--sekarang "YYYY-MM-DD HH:MM"] [--kering] [--db nama_database]';
    protected $options     = [
        '--sekarang' => 'Anggap jam sekarang = nilai ini (uji/simulasi).',
        '--kering'   => 'Hanya tampilkan yang akan dikirim; tidak mengirim & tidak mencatat.',
        '--db'       => 'Nama database lain (uji).',
    ];

    public function run(array $params)
    {
        $db = $params['db'] ?? CLI::getOption('db');
        if (is_string($db) && $db !== '') {
            config('Database')->default['database'] = $db;
        }

        $sekarang = $params['sekarang'] ?? CLI::getOption('sekarang');
        $ts       = is_string($sekarang) && $sekarang !== '' ? strtotime($sekarang) : null;
        if ($ts === false) {
            CLI::error('Format --sekarang tidak dikenal. Contoh: "2026-09-29 06:55"');

            return EXIT_ERROR;
        }
        $kering = array_key_exists('kering', $params) || CLI::getOption('kering') !== null;

        // Detak cron: jam jalan terakhir (dibaca notif:cek & API) — bukti cron hidup
        // walau tak ada notif yang dikirim. Simulasi (--sekarang/--kering) tak dihitung.
        if ($ts === null && ! $kering) {
            cache()->save(NotifJadwal::CACHE_DETAK, time(), 7 * 86400);
        }

        // Database hosting kadang sesaat tak tersedia (restart pemeliharaan sekitar 00.02 — log 30 Okt, 2 & 6 Nov 2026, 1× dari
        // 1.440 putaran/hari). Periksa dulu lewat sambungan polos (tanpa jejak panjang CI di log) dan coba lagi sampai ±30 detik.
        // Gangguan SESAAT tidak lagi ditulis ke log (2026-11-07: bukan galat, hanya membuat Log Error Hosting ramai); yang ditulis
        // hanya bila database tetap mati GAGAL_BERTURUT putaran (menit) berturut-turut = masalah sungguhan. Tak ada notifikasi
        // yang hilang: putaran berikutnya menyusul dalam jendela NotifJadwal::TOLERANSI_MENIT.
        if (! self::dbSiapDenganUlang()) {
            $gagal = (int) (cache()->get(self::CACHE_GAGAL) ?? 0) + 1;
            cache()->save(self::CACHE_GAGAL, $gagal, 3600);
            if ($gagal >= self::GAGAL_BERTURUT) {
                log_message('error', 'notif:kirim: database hosting tidak tersedia ' . $gagal . ' menit berturut-turut; notifikasi jadwal tertunda sampai database pulih. Bila berlanjut, cek cPanel / tanya Rumahweb.');
            }
            CLI::error('Database belum tersedia — putaran ini dilewati (' . $gagal . 'x berturut-turut).');

            return EXIT_ERROR;
        }
        if (cache()->get(self::CACHE_GAGAL) !== null) {
            cache()->delete(self::CACHE_GAGAL);
        }

        $h = NotifJadwal::jalankan($ts, $kering);

        // Diam bila tidak ada apa-apa (cron tiap menit tidak perlu keluaran).
        if ($h['kelompok'] === 0 && $h['catatan'] === [] && ! $kering) {
            return EXIT_SUCCESS;
        }
        CLI::write($h['tanggal'] . ' ' . $h['jam'] . ($kering ? ' (kering)' : '') . ' — '
            . $h['kelompok'] . ' notif, ' . $h['berhasil'] . ' ok, ' . $h['gagal'] . ' gagal');
        foreach ($h['catatan'] as $c) {
            CLI::write('  ' . str_replace("\n", "\n    ", $c));
        }

        return EXIT_SUCCESS;
    }

    /** Jeda (detik) sebelum mencoba lagi bila database belum tersedia — total ±30 detik, aman untuk cron tiap menit. */
    private const JEDA_ULANG = [5, 10, 15];

    /** Database mati sebanyak ini putaran berturut-turut baru ditulis ke log (sekali kedip tengah malam diabaikan). */
    public const GAGAL_BERTURUT = 3;

    /** Kunci cache penghitung putaran gagal berturut-turut. */
    public const CACHE_GAGAL = 'notif_kirim_db_gagal';

    private static function dbSiapDenganUlang(): bool
    {
        if (self::dbSiap()) {
            return true;
        }
        foreach (self::JEDA_ULANG as $detik) {
            sleep($detik);
            if (self::dbSiap()) {
                return true;
            }
        }

        return false;
    }

    /** Sambungan uji ke database TANPA lewat CI — gagal di sini tidak menulis jejak panjang ke log. */
    private static function dbSiap(): bool
    {
        $c = config('Database')->default;
        mysqli_report(MYSQLI_REPORT_OFF); // CI menyetel ulang mode laporannya sendiri saat menyambung
        $m  = mysqli_init();
        $ok = $m !== false && @$m->real_connect((string) $c['hostname'], (string) $c['username'], (string) $c['password'], (string) $c['database'], (int) ($c['port'] ?? 3306));
        if ($ok) {
            $m->close();
        }

        return (bool) $ok;
    }
}
