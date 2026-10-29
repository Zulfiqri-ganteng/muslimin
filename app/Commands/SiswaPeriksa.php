<?php

namespace App\Commands;

use App\Libraries\PeriksaSiswa;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Laporan Periksa Data Siswa di Terminal (sama dengan Master Siswa → Periksa data). Hanya membaca.
 *
 *   phpm spark siswa:periksa
 */
class SiswaPeriksa extends BaseCommand
{
    protected $group       = 'siswa';
    protected $name        = 'siswa:periksa';
    protected $description = 'Laporan data siswa: kemungkinan ganda, data tidak sesuai, penempatan kelas otomatis (hanya membaca).';

    public function run(array $params)
    {
        $h = PeriksaSiswa::hitung();
        foreach ($h['jumlah'] as $k => $n) {
            CLI::write(sprintf('  %-18s %d', $k, $n));
        }
        CLI::newLine();
        CLI::write('== Kemungkinan data ganda ==', 'yellow');
        foreach ($h['ganda'] as $g) {
            $f = static fn (array $s): string => '#' . $s['id'] . ' ' . $s['nama'] . ' [' . ($s['kelas'] ?? '-') . '] NIS ' . $s['nis'] . ' NISN ' . ($s['nisn'] ?? '-') . ' lahir ' . ($s['tanggal_lahir'] ?? '-');
            CLI::write('  ' . $f($g['a']) . '  <->  ' . $f($g['b']) . '  (' . $g['alasan'] . ')');
        }
        CLI::write('== Data tidak sesuai ==', 'yellow');
        foreach ($h['tidak_sesuai'] as $s) {
            CLI::write('  #' . $s['id'] . ' ' . $s['nama'] . ' [' . ($s['kelas'] ?? '-') . '] NIS ' . $s['nis'] . ' NISN ' . ($s['nisn'] ?? '-') . ' — ' . implode(', ', $s['masalah']));
        }

        return EXIT_SUCCESS;
    }
}
