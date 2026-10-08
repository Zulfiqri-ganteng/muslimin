<?php

namespace App\Commands;

use App\Libraries\SiswaResmi8355;
use App\Models\PklPengajuanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Hapus siswa yang sudah KELUAR dari sekolah = siswa di sistem yang TIDAK ada di Daftar Nama Siswa resmi (Excel Format 8355).
 * Dijalankan SETELAH siswa:data-8355 --tulis (supaya semua siswa resmi sudah punya NIS asli).
 *
 *   phpm spark siswa:hapus-keluar                    → LAPORAN SAJA (tidak menghapus apa pun) — selalu jalankan ini dulu
 *   phpm spark siswa:hapus-keluar --tulis            → menghapus (hapus LUNAK: bisa dipulihkan), satu transaksi
 *   phpm spark siswa:hapus-keluar --kecuali 1021,1326 → ID siswa yang JANGAN dihapus
 *   phpm spark siswa:hapus-keluar --paksa 1380        → ID yang ditahan karena "nama mirip" tapi pasti orang lain: tetap dihapus
 *
 * Yang otomatis DITAHAN (tidak dihapus, tertulis di ditahan.csv):
 *   - namanya mirip dengan siswa resmi yang belum punya kelas (kemungkinan orang yang sama, ejaannya beda → gabungkan manual dulu);
 *   - masih punya ajuan PKL aktif.
 * Hapus lunak = kolom deleted_at diisi; data tetap ada di database. Cara memulihkan ada di docs/PANDUAN-DATA-SISWA-8355.md.
 */
class SiswaHapusKeluar extends BaseCommand
{
    private const BATAS_AMAN = 150; // jauh di atas perkiraan (±60); lebih dari ini = ada yang salah (mis. Excel belum diterapkan)

    protected $group       = 'siswa';
    protected $name        = 'siswa:hapus-keluar';
    protected $description = 'Hapus (lunak) siswa yang tidak ada di Excel resmi sekolah = sudah keluar. Tanpa --tulis = laporan saja.';
    protected $usage       = 'siswa:hapus-keluar [--tulis] [--file "path.xlsx"] [--kecuali ID,ID] [--paksa ID,ID] [--db nama_database]';
    protected $options     = [
        '--tulis'   => 'Benar-benar menghapus (tanpa ini hanya laporan).',
        '--file'    => 'Alamat berkas Excel (bawaan: writable/data-sekolah/Data Siswa.xlsx).',
        '--kecuali' => 'ID siswa yang tidak boleh dihapus, pisahkan koma.',
        '--paksa'   => 'ID siswa yang ditahan karena "nama mirip" tetapi pasti BUKAN orang yang sama, tetap dihapus (ajuan PKL aktif tetap ditahan).',
        '--db'      => 'Nama database lain (uji).',
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

        $daftarId = static function (string $opsi) use ($params): ?array {
            $v = $params[$opsi] ?? CLI::getOption($opsi);
            if (! is_string($v) || $v === '') {
                return [];
            }
            $h = [];
            foreach (explode(',', $v) as $id) {
                if (! ctype_digit(trim($id))) {
                    CLI::error('Format --' . $opsi . ' salah: "' . $id . '" (harus angka ID siswa, pisahkan koma).');

                    return null;
                }
                $h[(int) trim($id)] = true;
            }

            return $h;
        };
        $kecuali = $daftarId('kecuali');
        $paksa   = $daftarId('paksa');
        if ($kecuali === null || $paksa === null) {
            return EXIT_ERROR;
        }

        try {
            CLI::write('Berkas    : ' . $file, 'light_gray');
            $baca    = SiswaResmi8355::baca($file);
            $rencana = SiswaResmi8355::rencana($baca['baris'], SiswaResmi8355::muatSiswa($koneksi));
            $aktif   = $rencana['aktif'];
            $excel   = $rencana['excel'];

            // Siswa resmi yang BELUM punya kelas (siswa baru dari Excel): pembanding untuk mendeteksi nama yang sama beda ejaan.
            $baruTanpaKelas = [];
            foreach ($rencana['pasang'] as [$i]) {
                if ($aktif[$i]['kelas_id'] === null) {
                    $baruTanpaKelas[] = $aktif[$i];
                }
            }

            $pkl      = new PklPengajuanModel();
            $hapus    = [];
            $ditahan  = [];
            foreach ($rencana['dbSaja'] as $i) {
                $d = $aktif[$i];
                $alasan = null;
                if (isset($kecuali[(int) $d['id']])) {
                    $alasan = 'dikecualikan lewat --kecuali';
                } elseif ($pkl->aktifMilik((int) $d['id']) !== null) {
                    $alasan = 'masih punya ajuan PKL aktif';
                } elseif (! isset($paksa[(int) $d['id']])) {
                    foreach ($baruTanpaKelas as $b) {
                        if ($this->mirip($d['n'], $b['n'])) {
                            $alasan = 'nama mirip siswa resmi tanpa kelas: ' . $b['nama'] . ' (NIS ' . $b['nis'] . ', id ' . $b['id'] . ') — gabungkan manual dulu';
                            break;
                        }
                    }
                }
                $baris = [$d['id'], $d['nis'], $d['nama'], $d['nama_kelas'] ?? '', $d['status']];
                if ($alasan === null) {
                    $hapus[] = $baris;
                } else {
                    $ditahan[] = array_merge($baris, [$alasan]);
                }
            }

            $dirLap = $dasar . 'laporan-hapus-keluar' . DIRECTORY_SEPARATOR;
            $this->tulisCsv($dirLap, 'akan_dihapus.csv', ['id_siswa', 'nis_sistem', 'nama', 'kelas', 'status'], $hapus);
            $this->tulisCsv($dirLap, 'ditahan.csv', ['id_siswa', 'nis_sistem', 'nama', 'kelas', 'status', 'alasan_ditahan'], $ditahan);

            CLI::newLine();
            CLI::write('===== RINGKASAN ' . ($tulis ? '(MODE TULIS)' : '(LAPORAN, tidak menghapus)') . ' =====', 'yellow');
            CLI::write(sprintf('  %-48s %d', 'Siswa di Excel resmi', count($excel)));
            CLI::write(sprintf('  %-48s %d', 'Siswa di sistem (belum dihapus)', count($aktif)));
            CLI::write(sprintf('  %-48s %d', 'Siswa sistem TIDAK ada di Excel', count($rencana['dbSaja'])));
            CLI::write(sprintf('  %-48s %d', '  → akan dihapus', count($hapus)), 'red');
            CLI::write(sprintf('  %-48s %d', '  → ditahan (lihat ditahan.csv)', count($ditahan)), 'yellow');
            CLI::write(sprintf('  %-48s %d', 'Siswa di sistem setelah dihapus', count($aktif) - count($hapus)), 'green');
            CLI::write('Laporan CSV: ' . $dirLap, 'light_gray');

            if (count($hapus) > self::BATAS_AMAN) {
                CLI::error('BERHENTI: ' . count($hapus) . ' siswa akan dihapus, jauh di atas perkiraan. Pastikan siswa:data-8355 --tulis sudah dijalankan dan Excelnya benar.');

                return EXIT_ERROR;
            }
            if (! $tulis) {
                CLI::newLine();
                CLI::write('Ini LAPORAN SAJA — tidak ada siswa yang dihapus. Periksa akan_dihapus.csv & ditahan.csv, lalu jalankan lagi dengan --tulis.', 'yellow');

                return EXIT_SUCCESS;
            }
            if ($hapus === []) {
                CLI::write('Tidak ada yang perlu dihapus.', 'green');

                return EXIT_SUCCESS;
            }

            // Cadangan daftar (id + NIS + nama) sebelum menghapus.
            $dirCad = $dasar . 'cadangan';
            if (! is_dir($dirCad)) {
                @mkdir($dirCad, 0775, true);
            }
            $this->tulisCsv($dirCad . DIRECTORY_SEPARATOR, 'hapus-keluar-' . date('Ymd-His') . '.csv', ['id_siswa', 'nis_sistem', 'nama', 'kelas', 'status'], $hapus);

            $ids = array_map(static fn ($b) => (int) $b[0], $hapus);
            $now = date('Y-m-d H:i:s');
            $koneksi->transStart();
            $koneksi->table('siswa')->whereIn('id', $ids)->where('deleted_at', null)->update(['deleted_at' => $now, 'updated_at' => $now]);
            $n = $koneksi->affectedRows();
            if ($koneksi->tableExists('audit_log')) {
                $koneksi->table('audit_log')->insert([
                    'admin_id' => null, 'aksi' => 'delete', 'tabel' => 'siswa', 'record_id' => null,
                    'deskripsi' => mb_substr('Siswa yang sudah keluar (tidak ada di data resmi sekolah) dihapus lewat perintah: ' . $n . ' siswa', 0, 255),
                    'ip_address' => null, 'created_at' => $now,
                ]);
            }
            $koneksi->transComplete();
            if (! $koneksi->transStatus()) {
                throw new \RuntimeException('Penghapusan gagal; tidak ada siswa yang dihapus.');
            }

            helper('cache');
            master_data_changed('siswa');
            cache()->delete('stat_siswa');
            cache()->delete('siswa_per_kelas');
            CLI::write('SELESAI: ' . $n . ' siswa dihapus (hapus lunak). Siswa di sistem sekarang: ' . (count($aktif) - $n) . '.', 'green');
            CLI::write('Daftar yang dihapus tersimpan di folder cadangan (hapus-keluar-*.csv).', 'light_gray');

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('GAGAL: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }

    /** Dua nama (sudah dinormalkan) kemungkinan orang yang sama: sangat mirip, atau yang pendek = singkatan/inisial dari yang panjang. */
    private function mirip(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        [$p, $l] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];

        return SiswaResmi8355::skor($a, $b) >= 80 || SiswaResmi8355::tokenCocok($p, $l);
    }

    /** @param list<string> $judul @param list<array<int, mixed>> $baris */
    private function tulisCsv(string $dir, string $nama, array $judul, array $baris): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $f = fopen($dir . $nama, 'wb');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, $judul, ';');
        foreach ($baris as $b) {
            fputcsv($f, array_map(static fn ($v) => $v === null ? '' : (string) $v, $b), ';');
        }
        fclose($f);
    }
}
