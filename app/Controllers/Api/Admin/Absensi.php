<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\AbsensiKerjaModel;
use App\Models\AuditModel;
use App\Models\GuruJabatanModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JabatanModel;
use App\Models\JadwalModel;
use App\Models\SettingModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Absensi guru (API). Cermin App\Controllers\Admin\Absensi (tanpa PDF/Excel).
 * Prinsip "hanya simpan pengecualian": tak ada baris = HADIR.
 *
 *   GET  /api/v1/admin/absensi?tanggal=            → sesi hari itu (untuk input)
 *   POST /api/v1/admin/absensi/save               → simpan absensi 1 tanggal
 *   POST /api/v1/admin/absensi/unrecord           → batalkan pencatatan 1 tanggal
 *   GET  /api/v1/admin/absensi/rekap?dari=&sampai= → rekap per guru
 *   GET  /api/v1/admin/absensi/rekap/{guruId}?dari=&sampai= → rincian 1 guru
 */
class Absensi extends BaseApiController
{
    private const HARI_NAMA = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    // ===================== INPUT HARIAN =====================
    public function index()
    {
        $tanggal = $this->normalTanggal($this->request->getGet('tanggal'));
        $ts      = strtotime($tanggal);

        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $namaHari  = $hari['nama'] ?? (self::HARI_NAMA[(int) date('N', $ts)] ?? '');
        $hariAktif = $hari && (int) $hari['aktif'] === 1;

        $sessions = $hariAktif ? (new JadwalModel())->sessionsForHari((int) $hari['id']) : [];
        $absen    = (new AbsensiGuruModel())->forDate($tanggal);
        $recorded = (new AbsensiHariModel())->isRecorded($tanggal);

        $grup = [];
        foreach ($sessions as $s) {
            $ex     = $absen[$s['kelas_id'] . '-' . $s['jam_id']] ?? null;
            $status = $ex['status'] ?? 'hadir';

            $gid = (int) $s['guru_id'];
            if (! isset($grup[$gid])) {
                $grup[$gid] = ['guru_id' => $gid, 'nama' => $s['guru_nama'], 'kode_guru' => $s['kode_guru'] ?? null, 'sesi' => []];
            }
            $grup[$gid]['sesi'][] = [
                // identitas sesi (dikirim balik saat save)
                'jadwal_id'     => (int) ($s['jadwal_id'] ?? 0),
                'kelas_id'      => (int) $s['kelas_id'],
                'jam_id'        => (int) $s['jam_id'],
                'guru_id'       => $gid,
                'hari_id'       => (int) $s['hari_id'],
                'mapel_id'      => (int) ($s['mapel_id'] ?? 0),
                // tampilan
                'kelas'         => $s['nama_kelas'],
                'mapel'         => $s['nama_mapel'],
                'jam_ke'        => (int) $s['jam_ke'],
                'waktu_mulai'   => substr((string) $s['waktu_mulai'], 0, 5),
                'waktu_selesai' => substr((string) $s['waktu_selesai'], 0, 5),
                // status saat ini
                'status'        => $status,
                'jam_masuk'     => $ex && $ex['jam_masuk'] ? substr($ex['jam_masuk'], 0, 5) : null,
                'keterangan'    => $ex['keterangan'] ?? null,
            ];
        }

        // Ringkasan PER GURU (status harian = terburuk dari sesi-sesinya);
        // hanya dihitung bila hari sudah tercatat (di-save).
        $ringkas = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($grup as &$g) {
            $harian = 'hadir';
            foreach ($g['sesi'] as $s) {
                $harian = AbsensiGuruModel::worst($harian, $s['status']);
            }
            $g['status_harian'] = $harian;
            if ($recorded) {
                $ringkas[$harian] = ($ringkas[$harian] ?? 0) + 1;
            }
        }
        unset($g);

        // Kehadiran kerja (di luar jadwal) + daftar guru untuk dropdown "tambah".
        $kerja       = (new AbsensiKerjaModel())->forDate($tanggal);
        $guruOptions = array_map(static fn ($g) => [
            'id'        => (int) $g['id'],
            'nama'      => $g['nama'],
            'kode_guru' => $g['kode_guru'] ?? null,
        ], (new GuruModel())->select('id, kode_guru, nama')->orderBy('nama', 'ASC')->findAll());

        // Guru berjabatan struktural (wakil kepala dsb.) wajib hadir walau hari
        // itu tanpa jadwal KBM. Dikirim sebagai SARAN untuk mengisi panel
        // Kehadiran Kerja — belum tersimpan, klien wajib memanggil save-kerja.
        // Setelah tanggal tercatat, saran dikosongkan agar guru yang sengaja
        // dihapus admin tidak muncul lagi (perilaku sama persis dengan web).
        $guruJabatan = new GuruJabatanModel();
        $jabatanMap  = $guruJabatan->mapByGuru();
        $saran       = [];
        if (! $recorded) {
            $sudahAda = array_column($kerja, 'guru_id');
            foreach ($guruJabatan->guruStrukturalIds() as $gid) {
                if (in_array($gid, $sudahAda, true) || isset($grup[$gid])) {
                    continue; // sudah dicatat, atau sudah punya sesi mengajar
                }
                foreach ($guruOptions as $g) {
                    if ($g['id'] === $gid) {
                        $saran[] = [
                            'guru_id'    => $gid,
                            'nama'       => $g['nama'],
                            'kode_guru'  => $g['kode_guru'],
                            'jabatan'    => implode(', ', array_column($jabatanMap[$gid] ?? [], 'nama')),
                            'status'     => 'hadir',
                            'jam_masuk'  => '',
                            'keterangan' => '',
                        ];
                        break;
                    }
                }
            }
        }

        return $this->ok([
            'tanggal'         => $tanggal,
            'hari_nama'       => $namaHari,
            'hari_aktif'      => $hariAktif,
            'recorded'        => $recorded,
            'total'           => count($sessions),
            'total_guru'      => count($grup),
            'ringkas'         => $ringkas,
            'guru'            => array_values($grup),
            'kehadiran_kerja' => $kerja,
            'guru_options'    => $guruOptions,
            'saran_kerja'     => $saran,
        ]);
    }

    public function save()
    {
        $in      = $this->body();
        $tanggal = $this->normalTanggal($in['tanggal'] ?? null);
        $rows    = is_array($in['rows'] ?? null) ? $in['rows'] : [];
        $adminId = $this->adminId();

        (new AbsensiGuruModel())->syncDate($tanggal, $rows, $adminId);
        (new AbsensiHariModel())->mark($tanggal, $adminId);
        (new AuditModel())->record('update', 'absensi_guru', null, 'Simpan absensi ' . $tanggal . ' (via mobile)');

        return $this->ok(['tanggal' => $tanggal], 'Absensi tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    /**
     * POST /api/v1/admin/absensi/save-kerja
     * Simpan kehadiran kerja (di luar jadwal) satu tanggal. Body: {tanggal, rows:[
     * {guru_id, status, jam_masuk, keterangan}]}. Sinkron ke daftar yang dikirim.
     */
    public function saveKerja()
    {
        $in      = $this->body();
        $tanggal = $this->normalTanggal($in['tanggal'] ?? null);
        $rows    = is_array($in['rows'] ?? null) ? $in['rows'] : [];
        $adminId = $this->adminId();

        (new AbsensiKerjaModel())->syncDate($tanggal, $rows, $adminId);
        (new AbsensiHariModel())->mark($tanggal, $adminId);
        (new AuditModel())->record('update', 'absensi_kerja', null, 'Simpan kehadiran kerja ' . $tanggal . ' (via mobile)');

        return $this->ok(['tanggal' => $tanggal], 'Kehadiran kerja tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    public function unrecord()
    {
        $tanggal = $this->normalTanggal($this->body()['tanggal'] ?? null);

        (new AbsensiGuruModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiKerjaModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiHariModel())->unmark($tanggal);
        (new AuditModel())->record('delete', 'absensi_hari', null, 'Batal catat absensi ' . $tanggal . ' (via mobile)');

        return $this->ok(['tanggal' => $tanggal], 'Pencatatan absensi tanggal ' . $tanggal . ' dibatalkan.');
    }

    // ===================== REKAP =====================
    public function rekap()
    {
        [$dari, $sampai] = $this->rentang();
        $rows            = $this->rekapData($dari, $sampai);

        // Filter jabatan (mis. hanya wakil kepala). Jumlah dihitung SETELAH
        // difilter agar total selalu cocok dengan daftar yang dikirim.
        $jabatanId = (int) $this->request->getGet('jabatan_id');
        if ($jabatanId > 0) {
            $rows = array_values(array_filter(
                $rows,
                static fn ($r) => in_array($jabatanId, $r['jabatan_ids'], true)
            ));
        }

        $sum = ['total' => 0, 'hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($rows as $r) {
            foreach ($sum as $k => $_) {
                $sum[$k] += $r[$k];
            }
        }

        return $this->ok([
            'dari'          => $dari,
            'sampai'        => $sampai,
            'hari_tercatat' => count((new AbsensiHariModel())->datesInRange($dari, $sampai)),
            'jabatan_id'    => $jabatanId ?: null,
            'jabatan_nama'  => $jabatanId > 0 ? ((new JabatanModel())->find($jabatanId)['nama'] ?? null) : null,
            'sum'           => $sum,
            'items'         => $rows,
        ]);
    }

    /** Rincian ketidakhadiran satu guru pada rentang. */
    public function rekapGuru($id = 0)
    {
        [$dari, $sampai] = $this->rentang();
        $guru            = (new GuruModel())->find((int) $id);
        if (! $guru) {
            return $this->missing('Guru tidak ditemukan.');
        }

        // Rincian: sesi mengajar (pengecualian) + kehadiran kerja (di luar jadwal).
        $detail = array_map(static fn ($d) => [
            'tanggal'         => $d['tanggal'],
            'status'          => $d['status'],
            'jam_masuk'       => $d['jam_masuk'] ? substr($d['jam_masuk'], 0, 5) : null,
            'keterangan'      => $d['keterangan'] ?? null,
            'kelas'           => $d['nama_kelas'] ?? null,
            'mapel'           => $d['nama_mapel'] ?? null,
            'jam_ke'          => isset($d['jam_ke']) ? (int) $d['jam_ke'] : null,
            'waktu_mulai'     => $d['waktu_mulai'] ? substr((string) $d['waktu_mulai'], 0, 5) : null,
            'waktu_selesai'   => $d['waktu_selesai'] ? substr((string) $d['waktu_selesai'], 0, 5) : null,
            'kehadiran_kerja' => false,
        ], (new AbsensiGuruModel())->detailForGuru((int) $id, $dari, $sampai));
        foreach ((new AbsensiKerjaModel())->detailForGuru((int) $id, $dari, $sampai) as $k) {
            $detail[] = [
                'tanggal'         => $k['tanggal'],
                'status'          => $k['status'],
                'jam_masuk'       => $k['jam_masuk'],
                'keterangan'      => $k['keterangan'],
                'kelas'           => null,
                'mapel'           => null,
                'jam_ke'          => null,
                'waktu_mulai'     => null,
                'waktu_selesai'   => null,
                'kehadiran_kerja' => true,
            ];
        }
        usort($detail, static fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

        // Ringkas per HARI dari status harian gabungan (mengajar + kerja).
        $perTgl = $this->dailyStatus($dari, $sampai)[(int) $id] ?? [];
        $total  = count($perTgl);
        $cnt    = ['telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($perTgl as $st) {
            if (isset($cnt[$st])) {
                $cnt[$st]++;
            }
        }
        $ringkas = ['total' => $total, 'hadir' => max(0, $total - array_sum($cnt))] + $cnt;

        return $this->ok([
            'guru'    => ['id' => (int) $guru['id'], 'kode_guru' => $guru['kode_guru'] ?? null, 'nama' => $guru['nama']],
            'dari'    => $dari,
            'sampai'  => $sampai,
            'ringkas' => $ringkas,
            'detail'  => $detail,
        ]);
    }

    /**
     * GET /api/v1/admin/absensi/rekap/export/{pdf|excel}?dari=&sampai=
     * Unduh laporan rekap (dipakai tombol download di aplikasi Android).
     * Format & isi sama persis dengan export web.
     */
    public function rekapExport($format = 'pdf')
    {
        [$dari, $sampai] = $this->rentang();
        $rows            = $this->rekapData($dari, $sampai);
        $setting         = (new SettingModel())->get();

        if ($format === 'excel') {
            return $this->rekapExcel($rows, $dari, $sampai, $setting);
        }

        $html = view('pdf/rekap_absensi', ['rows' => $rows, 'dari' => $dari, 'sampai' => $sampai, 'setting' => $setting]);
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('Rekap-Absensi-' . $dari . '_' . $sampai . '.pdf', ['Attachment' => true]);
        exit;
    }

    /** Excel rekap (salinan builder web — kolom hitungan HARI). */
    private function rekapExcel(array $rows, string $dari, string $sampai, array $setting)
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');

        $sheet->mergeCells('A1:I1')->setCellValue('A1', 'REKAP ABSENSI GURU');
        $sheet->mergeCells('A2:I2')->setCellValue('A2', 'Tahun Pelajaran ' . ($setting['academic_year'] ?? ''));
        $sheet->mergeCells('A3:I3')->setCellValue('A3', 'Periode ' . $dari . ' s/d ' . $sampai);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $head = ['No', 'Kode', 'Nama Guru', 'Total Hari', 'Hadir', 'Telat', 'Izin', 'Sakit', 'Alpa'];
        $sheet->fromArray($head, null, 'A5', true);
        $sheet->getStyle('A5:I5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A5:I5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r  = 6;
        $no = 1;
        $firstRow = $r;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$r}", $no++);
            $sheet->setCellValueExplicit("B{$r}", (string) $row['kode'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("C{$r}", $row['nama']);
            $sheet->setCellValue("D{$r}", $row['total']);
            $sheet->setCellValue("E{$r}", $row['hadir']);
            $sheet->setCellValue("F{$r}", $row['telat']);
            $sheet->setCellValue("G{$r}", $row['izin']);
            $sheet->setCellValue("H{$r}", $row['sakit']);
            $sheet->setCellValue("I{$r}", $row['alpa']);
            $r++;
        }
        $lastRow = $r - 1;

        $sheet->setCellValue("C{$r}", 'TOTAL');
        if ($lastRow >= $firstRow) {
            foreach (['D', 'E', 'F', 'G', 'H', 'I'] as $col) {
                $sheet->setCellValue("{$col}{$r}", "=SUM({$col}{$firstRow}:{$col}{$lastRow})");
            }
        }
        $sheet->getStyle("A{$r}:I{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}:I{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');

        $sheet->getStyle("A5:I{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A6:A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D6:I{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['A' => 5, 'B' => 10, 'C' => 30, 'D' => 11, 'E' => 9, 'F' => 9, 'G' => 9, 'H' => 9, 'I' => 9] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        kop_excel_prepend($sheet, 'I');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Rekap-Absensi-' . $dari . '_' . $sampai . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    // ===================== HELPER (mirror web) =====================

    /**
     * Status harian GABUNGAN per guru pada rentang: [gid][tanggal] => status
     * terburuk dari (1) sesi mengajar terjadwal + (2) kehadiran kerja di luar
     * jadwal. Hanya tanggal tercatat. Satu tanggal = satu hari. Cermin web.
     */
    private function dailyStatus(string $dari, string $sampai): array
    {
        $dates = (new AbsensiHariModel())->datesInRange($dari, $sampai);
        if (empty($dates)) {
            return [];
        }
        $dateSet = array_flip($dates);

        $counts    = (new JadwalModel())->countPerGuruHari();
        $teachExc  = (new AbsensiGuruModel())->dayStatusPerGuru($dari, $sampai);
        $workStat  = (new AbsensiKerjaModel())->dayStatusPerGuru($dari, $sampai);
        $hariModel = new HariModel();

        $weekdayHari = [];
        $daily       = [];

        foreach ($dates as $tgl) {
            $n = (int) date('N', strtotime($tgl));
            if (! array_key_exists($n, $weekdayHari)) {
                $h               = $hariModel->byWeekday($n);
                $weekdayHari[$n] = ($h && (int) $h['aktif'] === 1) ? (int) $h['id'] : 0;
            }
            $hid = $weekdayHari[$n];
            if ($hid) {
                foreach ($counts as $gid => $byHari) {
                    if (isset($byHari[$hid])) {
                        $st                = $teachExc[$gid][$tgl] ?? 'hadir';
                        $daily[$gid][$tgl] = AbsensiGuruModel::worst($daily[$gid][$tgl] ?? 'hadir', $st);
                    }
                }
            }
        }

        foreach ($workStat as $gid => $perTgl) {
            foreach ($perTgl as $tgl => $st) {
                if (! isset($dateSet[$tgl])) {
                    continue;
                }
                $daily[$gid][$tgl] = AbsensiGuruModel::worst($daily[$gid][$tgl] ?? 'hadir', $st);
            }
        }

        return $daily;
    }

    /** Rekap HARI per guru (mengajar + kerja): hadir = total hari − bermasalah. */
    private function rekapData(string $dari, string $sampai): array
    {
        $daily = $this->dailyStatus($dari, $sampai);

        $guruMap = [];
        foreach ((new GuruModel())->select('id, kode_guru, nama')->findAll() as $gr) {
            $guruMap[(int) $gr['id']] = $gr;
        }
        // Jabatan seluruh guru dalam 1 query (kolom & filter Jabatan).
        $jabatanMap = (new GuruJabatanModel())->mapByGuru();

        $rows = [];
        foreach ($daily as $gid => $perTgl) {
            if (! isset($guruMap[$gid])) {
                continue;
            }
            $cnt = ['telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
            foreach ($perTgl as $st) {
                if (isset($cnt[$st])) {
                    $cnt[$st]++;
                }
            }
            $total = count($perTgl);
            $hadir = max(0, $total - array_sum($cnt));

            $jbt = $jabatanMap[(int) $gid] ?? [];

            $rows[] = [
                'id'    => (int) $gid,
                'kode'  => $guruMap[$gid]['kode_guru'],
                'nama'  => $guruMap[$gid]['nama'],
                // Jabatan utama untuk kolom ringkas; daftar id untuk memfilter.
                'jabatan'     => $jbt !== [] ? $jbt[0]['nama'] : null,
                'jabatan_all' => implode(', ', array_column($jbt, 'nama')),
                'jabatan_ids' => array_map('intval', array_column($jbt, 'id')),
                'struktural'  => $jbt !== [] && (bool) $jbt[0]['is_struktural'],
                'total' => $total, 'hadir' => $hadir,
                'telat' => $cnt['telat'], 'izin' => $cnt['izin'], 'sakit' => $cnt['sakit'], 'alpa' => $cnt['alpa'],
            ];
        }
        usort($rows, static fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));

        return $rows;
    }

    private function rentang(): array
    {
        $dari   = $this->normalTanggal($this->request->getGet('dari') ?: date('Y-m-01'));
        $sampai = $this->normalTanggal($this->request->getGet('sampai') ?: date('Y-m-t'));
        if (strtotime($sampai) < strtotime($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        return [$dari, $sampai];
    }

    private function normalTanggal(?string $raw): string
    {
        $raw = trim((string) $raw);
        $ts  = $raw !== '' ? strtotime($raw) : false;
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
