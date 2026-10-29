<?php

namespace App\Commands;

use App\Libraries\ModeMaintenance;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Mode Maintenance lewat Terminal cPanel — jalan darurat bila Admin tidak bisa masuk web (docs/DESAIN-ALAT-SISTEM.md F2).
 *
 *   phpm spark maintenance                                     → lihat status
 *   phpm spark maintenance off                                 → matikan
 *   phpm spark maintenance on                                  → aktifkan (pesan bawaan / pesan terakhir)
 *   phpm spark maintenance on --pesan "…" --selesai "2026-10-30 22:00"
 */
class Maintenance extends BaseCommand
{
    protected $group       = 'Sistem';
    protected $name        = 'maintenance';
    protected $description = 'Lihat, aktifkan (on), atau matikan (off) Mode Maintenance.';
    protected $usage       = 'maintenance [on|off] [--pesan "teks"] [--selesai "Y-m-d H:i"]';
    protected $arguments   = ['aksi' => 'on = aktifkan / perbarui, off = matikan, kosong = lihat status'];
    protected $options     = ['--pesan' => 'Pesan untuk pengunjung', '--selesai' => 'Perkiraan selesai, mis. "2026-10-30 22:00"'];

    public function run(array $params)
    {
        $aksi = strtolower((string) ($params[0] ?? ''));
        if (in_array($aksi, ['on', 'off'], true)) {
            $s = ModeMaintenance::status();
            $r = ModeMaintenance::simpan($aksi === 'on', (string) (CLI::getOption('pesan') ?? $s['pesan']), (string) (CLI::getOption('selesai') ?? ''), 'Terminal (spark)');
            CLI::write($r['pesan'], $r['ok'] ? 'green' : 'red');
            if (! $r['ok']) {
                return EXIT_ERROR;
            }
        } elseif ($aksi !== '') {
            CLI::error('Aksi tidak dikenal: ' . $aksi . ' (pakai on atau off).');

            return EXIT_ERROR;
        }

        $s = ModeMaintenance::status();
        CLI::write('Mode Maintenance: ' . ($s['aktif'] ? 'AKTIF' : 'mati'), $s['aktif'] ? 'red' : 'green');
        if ($s['aktif']) {
            CLI::write('  Sejak    : ' . $s['mulai'] . ' oleh ' . $s['oleh']);
            CLI::write('  Selesai  : ' . (ModeMaintenance::labelWaktu($s['selesai']) ?: '(tanpa perkiraan)'));
        }
        CLI::write('  Pesan    : ' . $s['pesan']);
        CLI::write('  Berkas   : ' . ModeMaintenance::berkas());

        return EXIT_SUCCESS;
    }
}
