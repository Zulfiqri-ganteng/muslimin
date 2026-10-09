<?php

namespace App\Commands;

use App\Libraries\SiswaResmi8355;
use App\Models\AuditModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Tempatkan siswa AKTIF yang belum punya kelas (mis. siswa baru dari impor Daftar Nama Siswa resmi Format 8355, yang hanya
 * mengelompokkan per tingkat + jurusan tanpa nomor rombel).
 *
 *   phpm spark siswa:tempatkan-kelas                       → LAPORAN SAJA (tidak menulis apa pun) — selalu jalankan ini dulu
 *   phpm spark siswa:tempatkan-kelas --tulis               → menempatkan HANYA yang pasti
 *   phpm spark siswa:tempatkan-kelas --tulis --keluarkan NIS,NIS --status pindah|keluar
 *                                                          → siswa aktif yang tidak ada di daftar resmi dinyatakan mutasi/keluar
 *
 * ATURAN PASTI: kelompok (tingkat + jurusan, dari Excel resmi) ditempatkan ke SATU kelas bila di tingkat+jurusan itu
 * hanya ada tepat satu kelas yang masih 0 siswa aktif. Selain itu nomor rombel tidak bisa disimpulkan, jadi hanya
 * dilaporkan (CSV) untuk ditempatkan manual — tidak pernah menebak.
 *
 * Berkas Excel dibaca dari writable/data-sekolah/Data Siswa.xlsx di SERVER (bukan dari repo: berisi data pribadi, repo
 * publik; karena itu penempatan tidak ditulis sebagai migrasi berisi nama siswa). Laporan CSV ada di
 * writable/data-sekolah/laporan-kelas/. Aman dijalankan ulang (yang sudah berkelas tidak disentuh).
 */
class SiswaTempatkanKelas extends BaseCommand
{
    protected $group       = 'siswa';
    protected $name        = 'siswa:tempatkan-kelas';
    protected $description = 'Tempatkan siswa aktif yang belum berkelas (hanya yang pasti). Tanpa --tulis = laporan saja.';
    protected $usage       = 'siswa:tempatkan-kelas [--tulis] [--file "path.xlsx"] [--keluarkan NIS,NIS --status pindah|keluar] [--db nama_database]';
    protected $options     = [
        '--tulis'     => 'Benar-benar menulis ke database (tanpa ini hanya laporan).',
        '--file'      => 'Alamat berkas Excel (bawaan: writable/data-sekolah/Data Siswa.xlsx).',
        '--keluarkan' => 'NIS siswa aktif yang TIDAK ada di daftar resmi, pisahkan koma → status diubah (wajib bersama --status).',
        '--status'    => 'pindah (mutasi) atau keluar — dipakai bersama --keluarkan.',
        '--db'        => 'Nama database lain (uji).',
    ];

    public function run(array $params)
    {
        $dbNama = $params['db'] ?? CLI::getOption('db');
        if (is_string($dbNama) && $dbNama !== '') {
            config('Database')->default['database'] = $dbNama;
        }
        $db    = db_connect();
        $tulis = array_key_exists('tulis', $params) || CLI::getOption('tulis') !== null;
        CLI::write('Database : ' . $db->getDatabase(), 'light_gray');

        $file = $params['file'] ?? CLI::getOption('file');
        if (! is_string($file) || $file === '') {
            $file = WRITEPATH . 'data-sekolah/Data Siswa.xlsx';
            if (! is_file($file) && is_file(ROOTPATH . 'formatdatasekolah/Data Siswa.xlsx')) {
                $file = ROOTPATH . 'formatdatasekolah/Data Siswa.xlsx';
            }
        }
        if (! is_file($file)) {
            CLI::error('Berkas Excel tidak ditemukan: ' . $file . ' — unggah Data Siswa.xlsx ke writable/data-sekolah/ lewat cPanel File Manager.');

            return EXIT_ERROR;
        }

        $keluarkan = [];
        $status    = '';
        $k = $params['keluarkan'] ?? CLI::getOption('keluarkan');
        if (is_string($k) && $k !== '') {
            $keluarkan = array_values(array_filter(array_map('trim', explode(',', $k)), static fn (string $x): bool => $x !== ''));
            $status    = (string) ($params['status'] ?? CLI::getOption('status') ?? '');
            if (! in_array($status, ['pindah', 'keluar'], true)) {
                CLI::error('Sertakan --status pindah (mutasi) atau --status keluar bersama --keluarkan.');

                return EXIT_ERROR;
            }
        }

        try {
            $excel = SiswaResmi8355::baca($file);
        } catch (Throwable $e) {
            CLI::error('Gagal membaca Excel: ' . $e->getMessage());

            return EXIT_ERROR;
        }
        $perNis = [];
        foreach ($excel['baris'] as $r) {
            $perNis[(string) $r['nis']] = $r;
        }

        $tanpa = $db->query("SELECT s.id, s.nis, s.nama, s.status FROM siswa s WHERE s.kelas_id IS NULL AND s.deleted_at IS NULL ORDER BY s.nis")->getResultArray();
        $kelas = $db->query("SELECT k.id, k.nama_kelas, k.tingkat, j.kode AS jk, j.nama AS jn,
                                    (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.status = 'aktif' AND s.deleted_at IS NULL) AS n
                             FROM kelas k LEFT JOIN jurusan j ON j.id = k.jurusan_id WHERE k.deleted_at IS NULL")->getResultArray();
        $kosongPer = [];
        foreach ($kelas as $kl) {
            if ((int) $kl['n'] === 0) {
                $kosongPer[strtoupper((string) $kl['tingkat']) . '|' . SiswaResmi8355::jurusanDb($kl['jk'], $kl['jn'])][] = $kl;
            }
        }

        $kelompok = [];   // kunci => siswa aktif tanpa kelas
        $takAda   = [];   // aktif, tak ada di Excel
        $lain     = [];   // non-aktif tanpa kelas
        foreach ($tanpa as $s) {
            if ($s['status'] !== 'aktif') {
                $lain[] = $s;
                continue;
            }
            $e = $perNis[(string) $s['nis']] ?? null;
            if ($e === null || $e['tingkat'] === '' || $e['jurusan'] === '') {
                $takAda[] = $s;
                continue;
            }
            $kelompok[$e['tingkat'] . '|' . $e['jurusan']][] = $s;
        }

        // keputusan per kelompok
        $rencana = [];  // kelas_id => [siswa ids]
        $baris   = [];  // untuk CSV
        CLI::newLine();
        CLI::write('Siswa aktif tanpa kelas: ' . (count($tanpa) - count($lain)) . ' (di Excel resmi: ' . array_sum(array_map('count', $kelompok)) . ', tidak ada di Excel: ' . count($takAda) . '); non-aktif tanpa kelas: ' . count($lain), 'white');
        ksort($kelompok);
        foreach ($kelompok as $kunci => $daftar) {
            $cand = $kosongPer[$kunci] ?? [];
            if (count($cand) === 1) {
                $rencana[(int) $cand[0]['id']] = array_merge($rencana[(int) $cand[0]['id']] ?? [], array_column($daftar, 'id'));
                CLI::write(sprintf('  %-10s %3d siswa → %s  (satu-satunya kelas kosong: PASTI)', str_replace('|', ' ', $kunci), count($daftar), $cand[0]['nama_kelas']), 'green');
                foreach ($daftar as $s) {
                    $baris[] = [$s['id'], $s['nis'], $s['nama'], str_replace('|', ' ', $kunci), $cand[0]['nama_kelas'], 'DITEMPATKAN OTOMATIS (satu-satunya kelas kosong)'];
                }
            } else {
                $alasan = $cand === [] ? 'tidak ada kelas kosong — nomor rombel tidak diketahui dari Excel' : count($cand) . ' kelas kosong — tidak bisa dipilih otomatis';
                CLI::write(sprintf('  %-10s %3d siswa → TEMPATKAN MANUAL (%s)', str_replace('|', ' ', $kunci), count($daftar), $alasan), 'yellow');
                foreach ($daftar as $s) {
                    $baris[] = [$s['id'], $s['nis'], $s['nama'], str_replace('|', ' ', $kunci), '', 'MANUAL: ' . $alasan];
                }
            }
        }
        foreach ($takAda as $s) {
            $baris[] = [$s['id'], $s['nis'], $s['nama'], '', '', 'PERIKSA: aktif tetapi TIDAK ada di daftar resmi (mutasi/keluar? atau siswa baru yang belum masuk daftar)'];
        }
        if ($takAda !== []) {
            CLI::write('  ' . count($takAda) . ' siswa aktif tidak ada di Excel resmi: ' . implode(', ', array_map(static fn (array $s): string => $s['nis'] . ' ' . $s['nama'], array_slice($takAda, 0, 4))) . (count($takAda) > 4 ? ', …' : '') . ' → periksa; bila sudah mutasi/keluar pakai --keluarkan NIS --status pindah|keluar', 'yellow');
        }

        $dir = WRITEPATH . 'data-sekolah/laporan-kelas/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $csv = $dir . 'belum-berkelas.csv';
        if (($h = @fopen($csv, 'wb')) !== false) {
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['id_siswa', 'nis', 'nama', 'tingkat_jurusan_excel', 'kelas_tujuan', 'keputusan'], ';');
            foreach ($baris as $b) {
                fputcsv($h, $b, ';');
            }
            fclose($h);
            CLI::write('Laporan CSV: ' . $csv, 'light_gray');
        }

        // yang akan dikeluarkan
        $keluarId = [];
        foreach ($keluarkan as $nis) {
            $s = null;
            foreach ($takAda as $x) {
                if ((string) $x['nis'] === $nis) {
                    $s = $x;
                    break;
                }
            }
            if ($s === null) {
                CLI::write('  --keluarkan ' . $nis . ': DILEWATI (bukan siswa aktif tanpa kelas yang tidak ada di Excel resmi).', 'red');
                continue;
            }
            $keluarId[(int) $s['id']] = $s;
            CLI::write('  --keluarkan ' . $nis . ' ' . $s['nama'] . ' → status ' . $status, 'cyan');
        }

        if (! $tulis) {
            CLI::newLine();
            CLI::write('Ini LAPORAN SAJA — tidak ada data yang diubah. Periksa di atas, lalu jalankan lagi dengan --tulis.', 'yellow');

            return EXIT_SUCCESS;
        }

        $n = 0;
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        foreach ($rencana as $kelasId => $ids) {
            $db->table('siswa')->whereIn('id', array_map('intval', $ids))->where('kelas_id', null)->where('status', 'aktif')->update(['kelas_id' => $kelasId, 'updated_at' => $now]);
            $n += $db->affectedRows();
        }
        $m = 0;
        foreach ($keluarId as $id => $s) {
            $ket = trim((string) ($db->table('siswa')->select('keterangan')->where('id', $id)->get()->getRow()->keterangan ?? ''));
            $baru = ($ket !== '' ? $ket . ' | ' : '') . 'Tidak ada di daftar resmi 8355 → ' . ($status === 'pindah' ? 'mutasi (pindah)' : 'keluar') . ' (' . date('d/m/Y') . ')';
            $db->table('siswa')->where('id', $id)->where('status', 'aktif')->update(['status' => $status, 'keterangan' => mb_substr($baru, 0, 250), 'updated_at' => $now]);
            $m += $db->affectedRows();
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            CLI::error('Gagal menulis. Tidak ada yang berubah.');

            return EXIT_ERROR;
        }
        helper('cache');
        master_data_changed('siswa');
        cache()->delete('stat_siswa');
        cache()->delete('siswa_per_kelas');
        if ($n > 0 || $m > 0) {
            (new AuditModel())->record('update', 'siswa', null, "Tempatkan siswa (siswa:tempatkan-kelas): $n siswa diberi kelas, $m siswa dinyatakan " . ($status !== '' ? $status : '-'));
        }
        CLI::newLine();
        CLI::write("SELESAI: $n siswa ditempatkan ke kelas, $m siswa diubah statusnya.", 'green');
        CLI::write('Jalankan perintah ini sekali lagi tanpa --tulis: kelompok yang pasti tinggal 0.', 'light_gray');

        return EXIT_SUCCESS;
    }
}
