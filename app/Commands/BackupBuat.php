<?php

namespace App\Commands;

use App\Libraries\BackupSistem;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Backup lewat Terminal / cron cPanel (docs/DESAIN-ALAT-SISTEM.md F3, docs/PANDUAN-DEPLOY-ALAT-SISTEM.md).
 *
 *   phpm spark backup:buat                 → backup database jenis "otomatis" (untuk cron harian; simpan 7 terakhir)
 *   phpm spark backup:buat --berkas        → + backup berkas unggahan (.zip)
 *   phpm spark backup:buat --manual        → jenis "manual"
 *
 * Cron (path lengkap PHP 8.3 — alias phpm tidak berlaku di cron), tiap jam 01.00:
 *   0 1 * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/kank9494/kangmuslim/spark backup:buat >/dev/null 2>&1
 */
class BackupBuat extends BaseCommand
{
    protected $group       = 'Sistem';
    protected $name        = 'backup:buat';
    protected $description = 'Buat backup database (+ berkas unggahan dengan --berkas). Bawaan jenis "otomatis" untuk cron.';
    protected $usage       = 'backup:buat [--berkas] [--manual]';
    protected $options     = ['--berkas' => 'Ikut backup berkas unggahan (.zip)', '--manual' => 'Catat sebagai backup manual'];

    public function run(array $params)
    {
        $jenis = CLI::getOption('manual') !== null ? 'manual' : 'otomatis';
        try {
            $b = BackupSistem::buatDb($jenis, 'Terminal (spark)');
            CLI::write('Backup database: ' . $b['kunci'] . ' (' . number_format($b['ukuran'] / 1024, 0, ',', '.') . ' KB, ' . $b['tabel'] . ' tabel)', 'green');
            if (CLI::getOption('berkas') !== null) {
                $f = BackupSistem::buatBerkas($jenis, 'Terminal (spark)');
                CLI::write('Backup berkas  : ' . $f['kunci'] . ' (' . number_format($f['ukuran'] / 1024, 0, ',', '.') . ' KB)', 'green');
            }
        } catch (\Throwable $e) {
            log_message('error', '[Backup] backup:buat gagal: ' . $e->getMessage());
            CLI::error('Backup gagal: ' . $e->getMessage());

            return EXIT_ERROR;
        }
        try {
            (new \App\Models\AuditModel())->record('create', 'backup', null, 'Backup ' . $jenis . ' dibuat lewat perintah: ' . $b['kunci']);
        } catch (\Throwable) {
        }

        return EXIT_SUCCESS;
    }
}
