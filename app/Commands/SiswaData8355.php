<?php

namespace App\Commands;

use App\Libraries\SiswaResmi8355;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Isi Master Siswa dari Daftar Nama Siswa resmi sekolah (Format 8355). Rancangan: docs/RENCANA-DATA-SISWA-8355.md.
 *
 *   phpm spark siswa:data-8355                  → LAPORAN SAJA (tidak menulis apa pun) — selalu jalankan ini dulu
 *   phpm spark siswa:data-8355 --tulis          → menulis, dalam satu transaksi, + cadangan nilai lama
 *
 * Berkas Excel dibaca dari writable/data-sekolah/Data Siswa.xlsx di SERVER (bukan dari repo: berisi data pribadi,
 * repo publik). Unggah lewat cPanel File Manager. Laporan CSV ada di writable/data-sekolah/laporan-8355/.
 * Aman dijalankan ulang: siswa yang sudah terisi dikenali dari NIS-nya, jadi tidak ada duplikat.
 */
class SiswaData8355 extends BaseCommand
{
    protected $group       = 'siswa';
    protected $name        = 'siswa:data-8355';
    protected $description = 'Isi data siswa resmi sekolah (Format 8355) ke Master Siswa. Tanpa --tulis = laporan saja.';
    protected $usage       = 'siswa:data-8355 [--tulis] [--file "path.xlsx"] [--gabung NIS:ID,NIS:ID] [--db nama_database]';
    protected $options     = [
        '--tulis'  => 'Benar-benar menulis ke database (tanpa ini hanya laporan).',
        '--file'   => 'Alamat berkas Excel (bawaan: writable/data-sekolah/Data Siswa.xlsx).',
        '--gabung' => 'Paksa pasangan nama meragukan: NIS-Excel:id-siswa, pisahkan koma (lihat tinjau_mirip.csv).',
        '--db'     => 'Nama database lain (uji).',
    ];

    public function run(array $params)
    {
        $db = $params['db'] ?? CLI::getOption('db');
        if (is_string($db) && $db !== '') {
            config('Database')->default['database'] = $db;
        }
        $koneksi = db_connect();
        CLI::write('Database : ' . $koneksi->getDatabase(), 'light_gray');

        $dasar = WRITEPATH . 'data-sekolah' . DIRECTORY_SEPARATOR;
        $file  = $params['file'] ?? CLI::getOption('file');
        $file  = is_string($file) && $file !== '' ? $file : $dasar . 'Data Siswa.xlsx';
        $tulis = array_key_exists('tulis', $params) || CLI::getOption('tulis') !== null;

        $paksa = [];
        $g     = $params['gabung'] ?? CLI::getOption('gabung');
        if (is_string($g) && $g !== '') {
            foreach (explode(',', $g) as $p) {
                $b = explode(':', trim($p));
                if (count($b) === 2 && ctype_digit($b[0]) && ctype_digit($b[1])) {
                    $paksa[$b[0]] = (int) $b[1];
                } else {
                    CLI::error('Format --gabung salah: "' . $p . '" (harus NIS:ID, contoh 262710285:1234).');

                    return EXIT_ERROR;
                }
            }
        }

        try {
            CLI::write('Berkas    : ' . $file, 'light_gray');
            $baca = SiswaResmi8355::baca($file);
            CLI::write('Dibaca   : ' . count($baca['baris']) . ' siswa dari Excel (' . count($baca['anomali']) . ' catatan anomali di berkas)', 'light_gray');
            $siswa   = SiswaResmi8355::muatSiswa($koneksi);
            $rencana = SiswaResmi8355::rencana($baca['baris'], $siswa, $paksa);
            $dirLap  = $dasar . 'laporan-8355' . DIRECTORY_SEPARATOR;
            $this->tulisLaporan($dirLap, $baca, $rencana);
            $this->cetakRingkasan($rencana, $baca, $dirLap, $tulis);

            if (! $tulis) {
                CLI::newLine();
                CLI::write('Ini LAPORAN SAJA — tidak ada data yang diubah. Periksa CSV di atas, lalu jalankan lagi dengan --tulis.', 'yellow');

                return EXIT_SUCCESS;
            }

            CLI::newLine();
            CLI::write('Menulis…', 'yellow');
            $h = SiswaResmi8355::terapkan($koneksi, $rencana, $dasar . 'cadangan');
            helper('cache');
            master_data_changed('siswa');
            cache()->delete('stat_siswa');
            cache()->delete('siswa_per_kelas');
            CLI::write('SELESAI: ' . $h['diubah'] . ' siswa diperbarui, ' . $h['baru'] . ' siswa baru ditambahkan.', 'green');
            if ($h['cadangan'] !== null) {
                CLI::write('Nilai lama disimpan di: ' . $h['cadangan'], 'light_gray');
            }
            CLI::write('Jalankan perintah ini sekali lagi tanpa --tulis: seharusnya 0 perubahan & 0 siswa baru.', 'light_gray');

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('GAGAL: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }

    /** @param array<string, mixed> $baca @param array<string, mixed> $r */
    private function tulisLaporan(string $dir, array $baca, array $r): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $csv = static function (string $nama, array $judul, array $baris) use ($dir): void {
            $f = fopen($dir . $nama, 'wb');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $judul, ';');
            foreach ($baris as $b) {
                fputcsv($f, array_map(static fn ($v) => $v === null ? '' : (string) $v, $b), ';');
            }
            fclose($f);
        };
        $ex   = $r['excel'];
        $aktf = $r['aktif'];

        $csv('anomali_excel.csv', ['jenis', 'rujukan', 'keterangan'], $baca['anomali']);
        $csv('tinjau_mirip.csv', ['nis_excel', 'nama_excel', 'bagian_excel', 'id_siswa', 'nama_sistem', 'kelas_sistem', 'skor', 'alasan_ditahan', 'untuk_menggabungkan_pakai'],
            array_map(static fn ($t) => [$ex[$t[1]]['nis'], $ex[$t[1]]['nama'], $ex[$t[1]]['bagian'], $aktf[$t[0]]['id'], $aktf[$t[0]]['nama'], $aktf[$t[0]]['nama_kelas'], $t[2], $t[3], '--gabung ' . $ex[$t[1]]['nis'] . ':' . $aktf[$t[0]]['id']], $r['tinjau']));
        $csv('pasangan_mirip_otomatis.csv', ['id_siswa', 'nama_sistem', 'kelas_sistem', 'nama_excel', 'nis_excel', 'cara', 'skor'],
            array_map(static fn ($p) => [$aktf[$p[0]]['id'], $aktf[$p[0]]['nama'], $aktf[$p[0]]['nama_kelas'], $ex[$p[1]]['nama'], $ex[$p[1]]['nis'], $p[2], $p[3] !== null ? round($p[3]) : ''],
                array_values(array_filter($r['pasang'], static fn ($p) => str_starts_with($p[2], 'mirip') || $p[2] === 'dipaksa_user'))));
        $csv('konflik_jenis_kelamin.csv', ['id_siswa', 'nama', 'kelas', 'jk_sistem_lama', 'jk_excel_(dipakai)', 'nis_excel'], $r['konflikJk']);
        $csv('siswa_baru.csv', ['nis', 'nisn', 'nama', 'bagian_resmi', 'tempat_lahir', 'tanggal_lahir', 'agama', 'nama_orang_tua', 'alamat_orang_tua'],
            array_map(static fn ($t) => [$ex[$t['excel_j']]['nis'], $t['nisn'], $ex[$t['excel_j']]['nama'], $ex[$t['excel_j']]['bagian'], $ex[$t['excel_j']]['tempat_lahir'], $ex[$t['excel_j']]['tanggal_lahir'], $ex[$t['excel_j']]['agama'], $ex[$t['excel_j']]['nama_orang_tua'], $ex[$t['excel_j']]['ortu_alamat']], $r['tambah']));
        $csv('tidak_ada_di_excel.csv', ['id_siswa', 'nis_sistem', 'nama', 'kelas', 'status'],
            array_map(static fn ($i) => [$aktf[$i]['id'], $aktf[$i]['nis'], $aktf[$i]['nama'], $aktf[$i]['nama_kelas'], $aktf[$i]['status']], $r['dbSaja']));
        $bentrok = $r['bentrok'];
        foreach ($r['konflikNis'] as [$i, $j]) {
            $bentrok[] = ['nis_sama_nama_beda', $ex[$j]['nis'], 'Sistem: ' . $aktf[$i]['nama'] . ' (id ' . $aktf[$i]['id'] . ') — Excel: ' . $ex[$j]['nama'] . '. Tidak dipasangkan.'];
        }
        $csv('bentrok.csv', ['jenis', 'nilai', 'keterangan'], $bentrok);
        $csv('beda_dengan_biodata.csv', ['id_siswa', 'nama', 'kolom', 'nilai_siswa_(dipertahankan)', 'nilai_excel'], $r['bedaBiodata']);
    }

    /** @param array<string, mixed> $r @param array<string, mixed> $baca */
    private function cetakRingkasan(array $r, array $baca, string $dirLap, bool $tulis): void
    {
        $cara = [];
        foreach ($r['pasang'] as $p) {
            $cara[$p[2]] = ($cara[$p[2]] ?? 0) + 1;
        }
        $perKolom = [];
        foreach ($r['ubah'] as $u) {
            foreach (array_keys($u['set']) as $k) {
                $perKolom[$k] = ($perKolom[$k] ?? 0) + 1;
            }
        }
        ksort($perKolom);
        $baris = static function (string $label, $nilai, string $warna = 'white'): void {
            CLI::write(sprintf('  %-52s %s', $label, $nilai), $warna);
        };

        CLI::newLine();
        CLI::write('===== RINGKASAN ' . ($tulis ? '(MODE TULIS)' : '(LAPORAN, tidak menulis)') . ' =====', 'yellow');
        $baris('Siswa di Excel resmi', count($r['excel']));
        $baris('Siswa di sistem (belum dihapus)', count($r['aktif']));
        $baris('Dipasangkan (siswa sistem ↔ baris Excel)', count($r['pasang']), 'green');
        foreach ($cara as $k => $n) {
            $baris('    · ' . $k, $n);
        }
        $baris('  dari itu: ada kolom yang berubah', count($r['ubah']));
        $baris('  dari itu: sudah sama (tidak ada yang berubah)', $r['tanpaPerubahan']);
        foreach ($perKolom as $k => $n) {
            $baris('    kolom ' . $k . ' diisi/diubah', $n);
        }
        $baris('Siswa BARU yang akan ditambahkan (tanpa kelas)', count($r['tambah']), 'cyan');
        $baris('Ditahan untuk ditinjau (nama meragukan)', count($r['tinjau']), 'yellow');
        $baris('Siswa sistem TIDAK ada di Excel (tidak diubah)', count($r['dbSaja']), 'yellow');
        $baris('Konflik jenis kelamin (Excel dipakai)', count($r['konflikJk']), 'yellow');
        $baris('Nilai siswa dipertahankan (beda dengan Biodata)', count($r['bedaBiodata']));
        $baris('Bentrok NIS/NISN (dilewati)', count($r['bentrok']) + count($r['konflikNis']), count($r['bentrok']) + count($r['konflikNis']) > 0 ? 'red' : 'white');
        $baris('Catatan anomali di berkas Excel', count($baca['anomali']));
        CLI::newLine();
        CLI::write('Laporan CSV: ' . $dirLap, 'light_gray');
    }
}
