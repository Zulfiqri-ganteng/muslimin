<?php

namespace App\Commands;

use App\Libraries\BackupSistem;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Backup lewat Terminal (docs/DESAIN-ALAT-SISTEM.md F3). Keputusan user 2026-10-29: TANPA cron — backup dibuat Admin lewat tombol
 * di Sistem → Backup & Restore; perintah ini untuk keadaan darurat / Terminal.
 *
 *   phpm spark backup:buat                 → backup database jenis "manual"
 *   phpm spark backup:buat --berkas        → + backup berkas unggahan (.zip)
 *   phpm spark backup:buat --otomatis      → jenis "otomatis" (HANYA bila kelak dipasang di cron; simpan 7 terakhir)
 */
class BackupBuat extends BaseCommand
{
    protected $group       = 'Sistem';
    protected $name        = 'backup:buat';
    protected $description = 'Buat backup database (+ berkas unggahan dengan --berkas). Bawaan jenis "manual".';
    protected $usage       = 'backup:buat [--berkas] [--otomatis]';
    protected $options     = ['--berkas' => 'Ikut backup berkas unggahan (.zip)', '--otomatis' => 'Catat sebagai backup otomatis (untuk cron, bila kelak dipakai)'];

    public function run(array $params)
    {
        $jenis = CLI::getOption('otomatis') !== null ? 'otomatis' : 'manual';
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
