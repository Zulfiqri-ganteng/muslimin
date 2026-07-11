<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\AbsensiKerjaModel;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
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
 * Absensi manual guru per sesi mengajar (berbasis jadwal KBM).
 * Pilih tanggal → sistem tampilkan sesi hari itu (default HADIR) → admin
 * tandai yang telat/izin/sakit/alpa → simpan. Hanya pengecualian disimpan.
 * Rekap merangkum kehadiran per guru pada rentang tanggal (untuk gaji).
 */
class Absensi extends BaseController
{
    /** date('N') 1..7 → nama hari (fallback tampilan). */
    private const HARI_NAMA = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    /**
     * Ringkasan absensi guru satu tanggal — dipakai highlight dashboard web
     * & API (pola sama dengan Kurikulum::dashboardData()). Status harian per
     * guru = terburuk dari sesi-sesinya; tanpa baris pengecualian = hadir.
     */
    public static function ringkasHarian(string $tanggal): array
    {
        $ts        = strtotime($tanggal);
        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $namaHari  = $hari['nama'] ?? (self::HARI_NAMA[(int) date('N', $ts)] ?? '');
        $hariAktif = $hari && (int) $hari['aktif'] === 1;

        $sessions = $hariAktif ? (new JadwalModel())->sessionsForHari((int) $hari['id']) : [];
        $absen    = (new AbsensiGuruModel())->forDate($tanggal);
        $recorded = (new AbsensiHariModel())->isRecorded($tanggal);

        $harian = [];
        foreach ($sessions as $s) {
            $gid          = (int) $s['guru_id'];
            $st           = $absen[$s['kelas_id'] . '-' . $s['jam_id']]['status'] ?? 'hadir';
            $harian[$gid] = AbsensiGuruModel::worst($harian[$gid] ?? 'hadir', $st);
        }
        // Kehadiran kerja (di luar jadwal) ikut jadi status harian guru.
        foreach ((new AbsensiKerjaModel())->forDate($tanggal) as $k) {
            $gid          = (int) $k['guru_id'];
            $harian[$gid] = AbsensiGuruModel::worst($harian[$gid] ?? 'hadir', $k['status']);
        }

        $ringkas = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        if ($recorded) {
            foreach ($harian as $st) {
                $ringkas[$st] = ($ringkas[$st] ?? 0) + 1;
            }
        }

        return [
            'tanggal'    => $tanggal,
            'hari_nama'  => $namaHari,
            'hari_aktif' => $hariAktif,
            'recorded'   => $recorded,
            'total_sesi' => count($sessions),
            'total_guru' => count($harian),
            'ringkas'    => $ringkas,
        ];
    }

    // ===================== INPUT HARIAN =====================
    public function index()
    {
        $tanggal = $this->normalTanggal($this->request->getGet('tanggal'));
        $ts      = strtotime($tanggal);

        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $namaHari  = $hari['nama'] ?? (self::HARI_NAMA[(int) date('N', $ts)] ?? '');
        $hariAktif = $hari && (int) $hari['aktif'] === 1;

        $sessions = $hariAktif
            ? (new JadwalModel())->sessionsForHari((int) $hari['id'])
            : [];
        $absen    = (new AbsensiGuruModel())->forDate($tanggal);
        $recorded = (new AbsensiHariModel())->isRecorded($tanggal);

        $grup = [];
        foreach ($sessions as $s) {
            $ex              = $absen[$s['kelas_id'] . '-' . $s['jam_id']] ?? null;
            $s['status']     = $ex['status'] ?? 'hadir';
            $s['jam_masuk']  = $ex && $ex['jam_masuk'] ? substr($ex['jam_masuk'], 0, 5) : '';
            $s['keterangan'] = $ex['keterangan'] ?? '';

            $gid = (int) $s['guru_id'];
            if (! isset($grup[$gid])) {
                $grup[$gid] = ['guru_id' => $gid, 'nama' => $s['guru_nama'], 'kode' => $s['kode_guru'], 'sesi' => []];
            }
            $grup[$gid]['sesi'][] = $s;
        }

        // Ringkasan PER GURU (status harian = terburuk dari sesi-sesinya);
        // hanya dihitung bila hari ini SUDAH tercatat (di-save).
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
        $guruOptions = (new GuruModel())->select('id, kode_guru, nama')->orderBy('nama', 'ASC')->findAll();

        return view('admin/absensi/index', [
            'title'       => 'Absensi Guru',
            'tanggal'     => $tanggal,
            'namaHari'    => $namaHari,
            'hariAktif'   => $hariAktif,
            'grup'        => array_values($grup),
            'ringkas'     => $ringkas,
            'total'       => count($sessions),
            'sekolah'     => (new SettingModel())->get()['school_name'] ?? '',
            'recorded'    => $recorded,
            'kerja'       => $kerja,
            'guruOptions' => $guruOptions,
        ]);
    }

    public function save()
    {
        $tanggal = $this->normalTanggal($this->request->getPost('tanggal'));
        $rows    = $this->request->getPost('rows');
        $rows    = is_array($rows) ? $rows : [];
        $adminId = session('admin')['id'] ?? null;

        (new AbsensiGuruModel())->syncDate($tanggal, $rows, $adminId);
        // Tandai hari ini sudah diabsen agar masuk hitungan rekap.
        (new AbsensiHariModel())->mark($tanggal, $adminId);
        (new AuditModel())->record('update', 'absensi_guru', null, 'Simpan absensi ' . $tanggal);

        return redirect()->to(site_url('admin/absensi') . '?tanggal=' . $tanggal)
            ->with('success', 'Absensi tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    /**
     * Simpan KEHADIRAN KERJA (di luar jadwal) satu tanggal. Sinkron ke daftar
     * yang dikirim (guru yang dihapus dari daftar → catatannya dibuang). Tanggal
     * ikut ditandai tercatat agar masuk rekap.
     */
    public function saveKerja()
    {
        $tanggal = $this->normalTanggal($this->request->getPost('tanggal'));
        $rows    = $this->request->getPost('kerja');
        $rows    = is_array($rows) ? $rows : [];
        $adminId = session('admin')['id'] ?? null;

        (new AbsensiKerjaModel())->syncDate($tanggal, $rows, $adminId);
        (new AbsensiHariModel())->mark($tanggal, $adminId);
        (new AuditModel())->record('update', 'absensi_kerja', null, 'Simpan kehadiran kerja ' . $tanggal);

        return redirect()->to(site_url('admin/absensi') . '?tanggal=' . $tanggal)
            ->with('success', 'Kehadiran kerja tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    /** Batalkan pencatatan satu hari: hapus registry + semua tandaan hari itu. */
    public function unrecord()
    {
        $tanggal = $this->normalTanggal($this->request->getPost('tanggal'));

        (new AbsensiGuruModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiKerjaModel())->where('tanggal', $tanggal)->delete();
        (new AbsensiHariModel())->unmark($tanggal);
        (new AuditModel())->record('delete', 'absensi_hari', null, 'Batal catat absensi ' . $tanggal);

        return redirect()->to(site_url('admin/absensi') . '?tanggal=' . $tanggal)
            ->with('success', 'Pencatatan absensi tanggal ' . $tanggal . ' dibatalkan. Hari ini kembali "belum diabsen".');
    }

    // ===================== REKAP =====================
    public function rekap($format = 'html')
    {
        [$dari, $sampai] = $this->rentang();
        $rows            = $this->rekapData($dari, $sampai);
        $setting         = (new SettingModel())->get();

        if ($format === 'pdf') {
            $html = view('pdf/rekap_absensi', ['rows' => $rows, 'dari' => $dari, 'sampai' => $sampai, 'setting' => $setting]);
            $this->streamPdf($html, 'Rekap-Absensi-' . $dari . '_' . $sampai);
            return null;
        }
        if ($format === 'excel') {
            return $this->rekapExcel($rows, $dari, $sampai, $setting);
        }

        $sum = ['total' => 0, 'hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($rows as $r) {
            foreach ($sum as $k => $_) {
                $sum[$k] += $r[$k];
            }
        }

        return view('admin/absensi/rekap', [
            'title'       => 'Rekap Absensi Guru',
            'rows'        => $rows, 'dari' => $dari, 'sampai' => $sampai, 'sum' => $sum,
            'hariTercatat' => count((new AbsensiHariModel())->datesInRange($dari, $sampai)),
        ]);
    }

    /** Rincian ketidakhadiran satu guru pada rentang. */
    public function rekapGuru($id = 0)
    {
        [$dari, $sampai] = $this->rentang();
        $guru            = (new GuruModel())->find((int) $id);
        if (! $guru) {
            return redirect()->to(site_url('admin/absensi/rekap'))->with('error', 'Guru tidak ditemukan.');
        }

        // Rincian: sesi mengajar (pengecualian) + kehadiran kerja (di luar jadwal).
        $detail = (new AbsensiGuruModel())->detailForGuru((int) $id, $dari, $sampai);
        foreach ((new AbsensiKerjaModel())->detailForGuru((int) $id, $dari, $sampai) as $k) {
            $detail[] = [
                'tanggal'         => $k['tanggal'],
                'status'          => $k['status'],
                'jam_masuk'       => $k['jam_masuk'],
                'keterangan'      => $k['keterangan'],
                'nama_kelas'      => null,
                'nama_mapel'      => null,
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
        $ringkas = [
            'total' => $total,
            'hadir' => max(0, $total - array_sum($cnt)),
        ] + $cnt;

        return view('admin/absensi/rekap_guru', [
            'title'   => 'Rincian Absensi — ' . $guru['nama'],
            'guru'    => $guru, 'detail' => $detail, 'ringkas' => $ringkas,
            'dari'    => $dari, 'sampai' => $sampai,
        ]);
    }

    /**
     * Status harian GABUNGAN per guru pada rentang: [gid][tanggal] => status
     * terburuk hari itu dari DUA sumber — (1) sesi mengajar terjadwal (default
     * hadir kecuali ada pengecualian di absensi_guru) dan (2) kehadiran kerja
     * di luar jadwal (absensi_kerja). Hanya tanggal yang tercatat (registry
     * absensi_hari) yang dihitung. Satu tanggal = satu hari (bukan per sesi).
     *
     * Ini fondasi rekap: total hari per guru = jumlah tanggal di petanya, dan
     * hadir = total − hari (telat+izin+sakit+alpa).
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

        // Sumber 1: hari mengajar terjadwal (default hadir; pengecualian menimpa).
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
                        $st                 = $teachExc[$gid][$tgl] ?? 'hadir';
                        $daily[$gid][$tgl]  = AbsensiGuruModel::worst($daily[$gid][$tgl] ?? 'hadir', $st);
                    }
                }
            }
        }

        // Sumber 2: kehadiran kerja di luar jadwal (tanggal harus tercatat).
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

    /**
     * Rekap HARI per guru pada rentang tanggal. Hadir = total hari − hari
     * bermasalah. Menggabungkan hari mengajar + hari masuk kerja.
     *
     * @return list<array{id:int,kode:string,nama:string,total:int,hadir:int,telat:int,izin:int,sakit:int,alpa:int}>
     */
    private function rekapData(string $dari, string $sampai): array
    {
        $daily = $this->dailyStatus($dari, $sampai);

        // Nama guru.
        $guruMap = [];
        foreach ((new GuruModel())->select('id, kode_guru, nama')->findAll() as $gr) {
            $guruMap[(int) $gr['id']] = $gr;
        }

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

            $rows[] = [
                'id'    => (int) $gid,
                'kode'  => $guruMap[$gid]['kode_guru'], 'nama' => $guruMap[$gid]['nama'],
                'total' => $total, 'hadir' => $hadir,
                'telat' => $cnt['telat'], 'izin' => $cnt['izin'], 'sakit' => $cnt['sakit'], 'alpa' => $cnt['alpa'],
            ];
        }
        usort($rows, static fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));

        return $rows;
    }

    /** Excel rekap absensi (nilai jadi + baris total). */
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

        // Baris TOTAL dengan SUM hidup.
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

        $this->streamXlsx($ss, 'Rekap-Absensi-' . $dari . '_' . $sampai);
        return null;
    }

    // ===================== HELPER =====================
    /** Ambil rentang tanggal dari GET; default bulan berjalan; tukar bila terbalik. */
    private function rentang(): array
    {
        $dari   = $this->normalTanggal($this->request->getGet('dari') ?: date('Y-m-01'));
        $sampai = $this->normalTanggal($this->request->getGet('sampai') ?: date('Y-m-t'));
        if (strtotime($sampai) < strtotime($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        return [$dari, $sampai];
    }

    /** Validasi/normalisasi tanggal → Y-m-d; fallback hari ini. */
    private function normalTanggal(?string $raw): string
    {
        $raw = trim((string) $raw);
        $ts  = $raw !== '' ? strtotime($raw) : false;
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function streamPdf(string $html, string $filename): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf', ['Attachment' => false]);
        exit;
    }

    private function streamXlsx(Spreadsheet $ss, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }
}
