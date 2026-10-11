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

        // Database hosting kadang sesaat tak tersedia (restart malam/subuh — terlihat di log 30 Sep–10 Okt 2026, 1× dari
        // 1.440 putaran/hari). Periksa dulu lewat sambungan polos (tanpa jejak panjang CI di log), coba sekali lagi setelah
        // 5 detik, dan bila tetap gagal catat SATU baris. Tak ada notifikasi yang hilang: putaran berikutnya menyusul
        // dalam jendela NotifJadwal::TOLERANSI_MENIT.
        if (! self::dbSiap()) {
            sleep(5);
            if (! self::dbSiap()) {
                log_message('error', 'notif:kirim dilewati: database sedang tidak tersedia; notifikasi menyusul pada putaran berikutnya (jendela ' . NotifJadwal::TOLERANSI_MENIT . ' menit).');
                CLI::error('Database belum tersedia — putaran ini dilewati.');

                return EXIT_ERROR;
            }
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
