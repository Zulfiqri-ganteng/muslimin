<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji render seluruh view master data dengan data contoh.
 * Menangkap error runtime view (undefined variable/array key, dsb.)
 * tanpa perlu login lewat browser.
 *
 * Jalankan:  php spark dev:smoke-views
 */
class SmokeViews extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:smoke-views';
    protected $description = 'Render semua view master data dengan data contoh untuk menangkap error variabel.';

    public function run(array $params)
    {
        try {
            return $this->doRun();
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');

            return EXIT_ERROR;
        }
    }

    private function doRun()
    {
        // csrf_field() butuh IncomingRequest; spark memberi CLIRequest → suntik mock.
        $request = new \CodeIgniter\HTTP\IncomingRequest(
            config('App'),
            new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/master/guru'),
            null,
            new \CodeIgniter\HTTP\UserAgent()
        );
        \Config\Services::injectMock('request', $request);

        // Notice/warning ikut dianggap gagal agar tidak ada "undefined variable" lolos.
        set_error_handler(static function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        service('pager')->store('default', 1, 10, 1);
        $pager = service('pager');

        $guruRow = [
            'id' => 1, 'nip' => '198700001', 'kode_guru' => '27', 'nama' => "Muslimin, S.Kom",
            'jenis_kelamin' => 'L', 'status_guru' => 'GTY', 'max_beban' => 24, 'keterangan' => null,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => null, 'deleted_at' => null,
        ];
        $statusList = ['PNS', 'PPPK', 'GTY', 'GTT'];

        $cases = [
            'admin/master/guru' => [
                'title' => 'Master Guru', 'rows' => [$guruRow], 'pager' => $pager,
                'q' => '', 'status' => '', 'per' => 10, 'total' => 1, 'statusList' => $statusList,
            ],
            'admin/master/mapel' => [
                'title' => 'Master Mapel',
                'rows'  => [[
                    'id' => 1, 'kode_mapel' => 'PD', 'nama_mapel' => 'Pemrograman Dasar',
                    'kelompok' => 'Kejuruan', 'jp_default' => 8, 'deleted_at' => null,
                ]],
                'pager' => $pager, 'q' => '', 'kelompok' => '', 'per' => 10, 'total' => 1,
                'allGuru' => [1 => '27 - Muslimin, S.Kom'], 'kompMap' => [1 => [1]],
                'kelompokList' => ['Umum', 'Kejuruan'],
            ],
            'admin/master/kelas' => [
                'title' => 'Master Kelas',
                'rows'  => [[
                    'id' => 1, 'nama_kelas' => 'X TKJT 1', 'tingkat' => 'X', 'shift' => 'pagi',
                    'jurusan_id' => 1, 'wali_kelas_id' => 1, 'jurusan_kode' => 'TKJT', 'wali_nama' => 'Muslimin',
                ]],
                'pager' => $pager, 'q' => '', 'tingkat' => '', 'shift' => '', 'per' => 10, 'total' => 1,
                'jurusanOpts' => [1 => 'TKJT - Teknik Komputer'], 'guruOpts' => [1 => '27 - Muslimin'],
            ],
            'admin/master/pengampu' => [
                'title' => 'Penugasan', 'kelasOpts' => [1 => 'X TKJT 1'], 'kelasId' => 1,
                'rows'  => [[
                    'id' => 1, 'kode_mapel' => 'PD', 'nama_mapel' => 'Pemrograman Dasar',
                    'kode_guru' => '27', 'guru_nama' => 'Muslimin', 'jp' => 8, 'jp_default' => 8, 'guru_id' => 1,
                ]],
                'totalJp' => 8, 'mapelOpts' => [1 => 'PD - Pemrograman Dasar'],
                'mapelJp' => [1 => 8], 'guruOpts' => [1 => '27 - Muslimin'], 'kompMap' => [1 => [1]],
            ],
            'admin/master/ketersediaan' => [
                'title' => 'Ketersediaan', 'guruOpts' => [1 => '27 - Muslimin'], 'guruId' => 1, 'shift' => 'pagi',
                'hari'  => [['id' => 1, 'nama' => 'Senin', 'urutan' => 1, 'aktif' => 1]],
                'jam'   => [['id' => 1, 'shift' => 'pagi', 'jam_ke' => 1, 'waktu_mulai' => '07:00:00', 'waktu_selesai' => '07:45:00', 'durasi' => 45, 'is_istirahat' => 0]],
                'unavailable' => ['1-1' => true],
            ],
            'admin/master/jurusan' => [
                'title' => 'Master Jurusan',
                'rows'  => [['id' => 1, 'kode' => 'TKJT', 'nama' => 'Teknik Komputer', 'deleted_at' => null]],
                'pager' => $pager, 'q' => '', 'per' => 10, 'total' => 1,
            ],
            'admin/master/hari' => [
                'title' => 'Master Hari',
                'rows'  => [['id' => 1, 'nama' => 'Senin', 'urutan' => 1, 'aktif' => 1]],
                'pager' => $pager, 'q' => '', 'status' => '', 'per' => 10, 'total' => 1,
            ],
            'admin/master/jam' => [
                'title' => 'Master Jam', 'shift' => 'pagi',
                'rows'  => [
                    ['id' => 1, 'shift' => 'pagi', 'jam_ke' => 1, 'waktu_mulai' => '07:00:00', 'waktu_selesai' => '07:45:00', 'durasi' => 45, 'is_istirahat' => 0],
                    ['id' => 2, 'shift' => 'pagi', 'jam_ke' => 2, 'waktu_mulai' => '09:00:00', 'waktu_selesai' => '09:15:00', 'durasi' => 15, 'is_istirahat' => 1],
                ],
            ],
            'admin/jadwal/guru' => [
                'title'    => 'Jadwal Guru',
                'guruOpts' => [1 => '27 - Muslimin, S.Kom'],
                'guruId'   => 1,
                'guru'     => $guruRow,
                'hari'     => [['id' => 1, 'nama' => 'Senin', 'urutan' => 1, 'aktif' => 1]],
                'jam'      => [
                    ['id' => 1, 'shift' => 'pagi', 'jam_ke' => 1, 'waktu_mulai' => '07:00:00', 'waktu_selesai' => '07:45:00', 'durasi' => 45, 'is_istirahat' => 0],
                    ['id' => 2, 'shift' => 'pagi', 'jam_ke' => 2, 'waktu_mulai' => '09:00:00', 'waktu_selesai' => '09:15:00', 'durasi' => 15, 'is_istirahat' => 1],
                    ['id' => 3, 'shift' => 'siang', 'jam_ke' => 1, 'waktu_mulai' => '13:00:00', 'waktu_selesai' => '13:45:00', 'durasi' => 45, 'is_istirahat' => 0],
                ],
                'grid'     => ['1-1' => ['hari_id' => 1, 'jam_id' => 1, 'shift' => 'pagi', 'kode_mapel' => 'PD', 'nama_mapel' => 'Pemrograman Dasar', 'nama_kelas' => 'X TKJT 1']],
            ],
            'pdf/jadwal_grid' => [
                'title'   => 'JADWAL PELAJARAN',
                'label'   => 'X TKJT 1',
                'setting' => ['school_name' => 'SMK Uji', 'academic_year' => '2026/2027'],
                'hari'    => [['id' => 1, 'nama' => 'Senin', 'urutan' => 1, 'aktif' => 1]],
                'jam'     => [
                    ['id' => 1, 'shift' => 'pagi', 'jam_ke' => 1, 'waktu_mulai' => '07:00:00', 'waktu_selesai' => '07:45:00', 'durasi' => 45, 'is_istirahat' => 0],
                    ['id' => 2, 'shift' => 'pagi', 'jam_ke' => 2, 'waktu_mulai' => '09:00:00', 'waktu_selesai' => '09:15:00', 'durasi' => 15, 'is_istirahat' => 1],
                ],
                'grid'    => ['1-1' => ['nama_mapel' => 'Pemrograman Dasar', 'guru_nama' => 'Muslimin', 'nama_kelas' => 'X TKJT 1', 'kode_mapel' => 'PD']],
                'mode'    => 'kelas',
            ],
            'pdf/rekap_beban' => [
                'setting' => ['school_name' => 'SMK Uji', 'academic_year' => '2026/2027'],
                'grouped' => [[
                    'kode_guru' => '27', 'guru_nama' => 'Muslimin', 'total' => 10, 'max_beban' => 24,
                    'items'     => [['mapel' => 'Pemrograman Dasar', 'kelas' => 'X TKJT 1', 'jp' => 4]],
                ]],
            ],
            'pdf/rekap_absensi' => [
                'setting' => ['school_name' => 'SMK Uji', 'academic_year' => '2026/2027'],
                'dari'    => '2026-07-01',
                'sampai'  => '2026-07-31',
                'rows'    => [['kode' => '27', 'nama' => 'Muslimin', 'total' => 12, 'hadir' => 11, 'telat' => 0, 'izin' => 1, 'sakit' => 0, 'alpa' => 0]],
            ],
            'admin/master/import_preview' => [
                'title'    => 'Pratinjau Impor', 'subtitle' => 'Master Guru',
                'cols'     => [
                    ['key' => 'kode', 'label' => 'Kode', 'type' => 'text', 'required' => true, 'width' => 100],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['PNS'], 'width' => 100],
                    ['key' => 'kelompok', 'label' => 'Kelompok', 'type' => 'datalist', 'options' => ['Umum'], 'width' => 100],
                ],
                'rows'     => [['kode' => '27', 'status' => 'GTY', 'kelompok' => '', '_status' => 'baru']],
                'commitUrl' => site_url('admin/master/guru/import-commit'),
                'backUrl'   => site_url('admin/master/guru'),
            ],
        ];

        $fail = false;
        foreach ($cases as $viewName => $data) {
            try {
                $html = view($viewName, $data, ['saveData' => false]);
                if (strlen($html) < 500) {
                    throw new \RuntimeException('Output terlalu pendek, kemungkinan render gagal.');
                }
                CLI::write('OK   ' . $viewName, 'green');
            } catch (\Throwable $e) {
                $fail = true;
                CLI::error('FAIL ' . $viewName . ' → ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            }
        }

        // Uji nyata: kop_excel_prepend harus menghasilkan file xlsx yang valid.
        try {
            $ss    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $ss->getActiveSheet();
            $sheet->fromArray(['No', 'Nama'], null, 'A1', true);
            $sheet->setCellValue('A2', 1);
            $sheet->setCellValue('B2', 'Uji');
            $sheet->setCellValue('B3', '=SUM(A2:A2)');
            kop_excel_prepend($sheet, 'B');

            $tmp = WRITEPATH . 'cache/smoke_kop.xlsx';
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($tmp);
            $reloaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
            // header tabel harus bergeser ke baris 6 (5 baris KOP di atasnya)
            if ((string) $reloaded->getCell('A6')->getValue() !== 'No') {
                throw new \RuntimeException('Isi sheet tidak bergeser 5 baris seperti seharusnya.');
            }
            @unlink($tmp);
            CLI::write('OK   kop_excel_prepend (xlsx valid, isi bergeser benar)', 'green');
        } catch (\Throwable $e) {
            $fail = true;
            CLI::error('FAIL kop_excel_prepend → ' . $e->getMessage());
        }

        restore_error_handler();
        CLI::write($fail ? 'Ada view yang gagal dirender.' : 'Semua view master berhasil dirender tanpa error.', $fail ? 'red' : 'green');

        return $fail ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
