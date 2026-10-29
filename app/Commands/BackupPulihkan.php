<?php

namespace App\Commands;

use App\Libraries\BackupSistem;
use App\Libraries\ModeMaintenance;
use App\Libraries\PulihkanBackup;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Restore lewat Terminal cPanel — jalan darurat (web tidak bisa dibuka) atau berkas sangat besar (docs/DESAIN-ALAT-SISTEM.md F4).
 *
 *   phpm spark backup:pulihkan                                  → daftar backup database
 *   phpm spark backup:pulihkan db-20261029-010000-otomatis.sql.gz --ya
 *   phpm spark backup:pulihkan /home/kank9494/ekspor.sql --ya     (berkas .sql/.sql.gz di mana saja)
 *
 * Mode Maintenance dinyalakan otomatis bila belum (dan DIBIARKAN menyala — matikan sesudah memeriksa data).
 */
class BackupPulihkan extends BaseCommand
{
    protected $group       = 'Sistem';
    protected $name        = 'backup:pulihkan';
    protected $description = 'Pulihkan database dari backup (tanpa argumen = daftar backup). Wajib --ya.';
    protected $usage       = 'backup:pulihkan [kunci_backup|path_berkas] --ya';
    protected $options     = ['--ya' => 'Konfirmasi: SEMUA data database sekarang diganti isi backup'];

    public function run(array $params)
    {
        $sumber = (string) ($params[0] ?? '');
        if ($sumber === '') {
            foreach (BackupSistem::daftar() as $b) {
                if ($b['isi'] === 'db') {
                    CLI::write($b['kunci'] . '  ' . $b['waktu'] . '  ' . number_format($b['ukuran'] / 1024, 0, ',', '.') . ' KB  ' . $b['label_jenis']);
                }
            }

            return EXIT_SUCCESS;
        }
        $path = BackupSistem::path($sumber) ?? (is_file($sumber) ? $sumber : null);
        if ($path === null) {
            CLI::error('Berkas tidak ditemukan: ' . $sumber);

            return EXIT_ERROR;
        }
        if (CLI::getOption('ya') === null) {
            CLI::error('SEMUA data database sekarang akan diganti isi ' . basename($path) . '. Ulangi dengan --ya untuk melanjutkan.');

            return EXIT_ERROR;
        }
        if (! ModeMaintenance::aktif()) {
            ModeMaintenance::simpan(true, '', '', 'Terminal (spark) — restore');
            CLI::write('Mode Maintenance dinyalakan.', 'yellow');
        }
        $t = PulihkanBackup::mulai($path, basename($path), 'Terminal (spark)');
        if (isset($t['galat'])) {
            CLI::error($t['galat']);

            return EXIT_ERROR;
        }
        CLI::write('Memulihkan ' . basename($path) . ' …');
        $t = PulihkanBackup::sampaiHabis($t['id']);
        foreach ($t['catatan'] ?? [] as $c) {
            CLI::write('  ' . $c);
        }
        if (($t['tahap'] ?? '') !== 'selesai') {
            CLI::error('Restore GAGAL: ' . ($t['galat'] ?? '?'));

            return EXIT_ERROR;
        }
        CLI::write('Restore selesai: ' . $t['perintah'] . ' perintah. Periksa data, lalu: phpm spark maintenance off', 'green');
        try {
            (new \App\Models\AuditModel())->record('update', 'backup', null, 'Restore SELESAI lewat perintah dari ' . basename($path) . ' (' . $t['perintah'] . ' perintah)');
        } catch (\Throwable) {
        }

        return EXIT_SUCCESS;
    }
}
