<?php

namespace App\Commands;

use App\Libraries\Fcm;
use App\Models\NotifAturanModel;
use App\Models\NotifPerangkatModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Periksa pemasangan notifikasi Firebase di server, lalu cetak baris CRON
 * yang siap ditempel (path PHP diambil dari PHP yang menjalankan perintah ini).
 *
 *   cd ~/kangmuslim && phpm spark notif:cek
 *
 * Aman dijalankan kapan saja: hanya membaca & meminta izin ke Google, tidak
 * mengirim notifikasi apa pun.
 */
class NotifCek extends BaseCommand
{
    protected $group       = 'notif';
    protected $name        = 'notif:cek';
    protected $description = 'Periksa pemasangan Firebase di server + tampilkan baris cron.';

    public function run(array $params)
    {
        $ok = true;
        $baris = static function (bool $lulus, string $teks) use (&$ok): void {
            $ok = $ok && $lulus;
            CLI::write(($lulus ? '  [OK]    ' : '  [GAGAL] ') . $teks, $lulus ? 'green' : 'red');
        };

        CLI::write('Pemeriksaan notifikasi jadwal guru', 'yellow');
        $baris(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION . ' (' . PHP_BINARY . ')'
            . (PHP_VERSION_ID >= 80100 ? '' : ' — terlalu lama: jalankan dengan phpm (PHP 8.3), bukan php'));
        $baris(extension_loaded('openssl'), 'ekstensi openssl (tanda tangan JWT)');
        $baris(extension_loaded('curl'), 'ekstensi curl (koneksi ke Google)');

        $path = Fcm::pathKunci();
        if ($path === '') {
            $baris(false, 'FCM_KEY_PATH belum diisi di .env — contoh:  FCM_KEY_PATH = /home/kank9494/rahasia/firebase-muslimin.json');
        } elseif (! is_file($path)) {
            $baris(false, 'berkas kunci tidak ditemukan: ' . $path);
        } elseif (! is_readable($path)) {
            $baris(false, 'berkas kunci tidak bisa dibaca PHP (cek izin berkas): ' . $path);
        } elseif (! Fcm::siap()) {
            $baris(false, 'isi berkas bukan kunci service account Firebase yang valid: ' . $path);
        } else {
            $baris(true, 'berkas kunci: ' . $path);
            $baris(true, 'project Firebase: ' . Fcm::projectId());
            $galat = Fcm::cekIzin();
            $baris($galat === null, $galat === null ? 'izin Google diterima (siap mengirim notifikasi)' : 'izin Google ditolak: ' . $galat);
        }

        try {
            $hp     = (new NotifPerangkatModel())->where('terima', 1)->countAllResults();
            $aturan = (new NotifAturanModel())->where('aktif', 1)->countAllResults();
            CLI::write('  [INFO]  HP penerima: ' . $hp . ' · aturan aktif: ' . $aturan, 'light_gray');
        } catch (Throwable $e) {
            $baris(false, 'tabel notif belum ada — jalankan dulu:  phpm spark migrate');
        }

        CLI::newLine();
        CLI::write('Baris CRON (cPanel → Cron Jobs → Once Per Minute), salin persis:', 'yellow');
        CLI::write(PHP_BINARY . ' ' . ROOTPATH . 'spark notif:kirim >/dev/null 2>&1');
        CLI::newLine();
        CLI::write($ok ? 'SEMUA OK.' : 'Masih ada yang perlu dibereskan (lihat [GAGAL] di atas).', $ok ? 'green' : 'red');

        return $ok ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
