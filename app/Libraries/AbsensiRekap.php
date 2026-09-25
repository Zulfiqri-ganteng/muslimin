<?php

namespace App\Libraries;

use App\Models\GuruJabatanModel;
use App\Models\GuruModel;
use App\Models\JabatanModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Rekap absensi guru — SATU sumber logika untuk web (Admin\Absensi) dan API
 * (Api\Admin\Absensi). Sebelumnya logika ini disalin di dua controller dan
 * sempat berbeda (export API tanpa kolom & filter Jabatan); kini keduanya
 * memanggil kelas ini agar hasil web dan Android selalu identik.
 */
class AbsensiRekap
{
    /** Kolom hitungan yang dijumlahkan di baris TOTAL. */
    public const SUM_KEYS = ['total', 'hadir', 'telat', 'izin', 'sakit', 'alpa'];

    /**
     * Status harian per ORANG pada rentang: [orang][tanggal] => status terburuk
     * hari itu. Diturunkan dari matriks laporan bulanan (AbsensiLaporan) —
     * sesi mengajar (salinan jadwal saat disimpan), kehadiran kerja, dan daftar
     * belum hadir yang tak diselesaikan (= alpa) — agar rekap di layar, export
     * rekap, dan laporan format sekolah SELALU sama. Hanya tanggal tercatat &
     * orang yang punya tugas hari itu yang dihitung. Satu tanggal = satu hari.
     *
     * Ini fondasi rekap: total hari per guru = jumlah tanggal di petanya, dan
     * hadir = total − hari (telat+izin+sakit+alpa).
     */
    public static function dailyStatus(string $dari, string $sampai): array
    {
        return AbsensiLaporan::statusHarian(AbsensiLaporan::matriks($dari, $sampai));
    }

    /**
     * Rekap HARI per guru pada rentang tanggal. Hadir = total hari − hari
     * bermasalah. Menggabungkan hari mengajar + hari masuk kerja. Bila
     * $jabatanId > 0 hanya guru penyandang jabatan itu yang dikembalikan.
     *
     * @return list<array{id:int,kode:string,nama:string,jabatan:string,jabatan_all:string,jabatan_ids:list<int>,struktural:bool,total:int,hadir:int,telat:int,izin:int,sakit:int,alpa:int}>
     */
    public static function rekapData(string $dari, string $sampai, int $jabatanId = 0): array
    {
        $daily = self::dailyStatus($dari, $sampai);

        $guruMap = [];
        foreach ((new GuruModel())->select('id, kode_guru, nama')->findAll() as $gr) {
            $guruMap[(int) $gr['id']] = $gr;
        }
        // Jabatan seluruh guru dalam 1 query (dipakai kolom & filter Jabatan).
        $jabatanMap = (new GuruJabatanModel())->mapByGuru();

        $rows = [];
        foreach ($daily as $gid => $perTgl) {
            if (! isset($guruMap[$gid])) {
                continue;
            }
            $jbt = $jabatanMap[(int) $gid] ?? [];
            $cnt = ['telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
            foreach ($perTgl as $st) {
                if (isset($cnt[$st])) {
                    $cnt[$st]++;
                }
            }
            $total = count($perTgl);
            $hadir = max(0, $total - array_sum($cnt));

            $rows[] = [
                'id'   => (int) $gid,
                'kode' => $guruMap[$gid]['kode_guru'],
                'nama' => $guruMap[$gid]['nama'],
                // Jabatan utama tampil ringkas; daftar id dipakai untuk memfilter.
                'jabatan'     => $jbt !== [] ? $jbt[0]['nama'] : '',
                'jabatan_all' => implode(', ', array_column($jbt, 'nama')),
                'jabatan_ids' => array_map('intval', array_column($jbt, 'id')),
                'struktural'  => $jbt !== [] && (bool) $jbt[0]['is_struktural'],
                'total' => $total, 'hadir' => $hadir,
                'telat' => $cnt['telat'], 'izin' => $cnt['izin'], 'sakit' => $cnt['sakit'], 'alpa' => $cnt['alpa'],
            ];
        }
        usort($rows, static fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));

        if ($jabatanId > 0) {
            $rows = array_values(array_filter(
                $rows,
                static fn ($r) => in_array($jabatanId, $r['jabatan_ids'], true)
            ));
        }

        return $rows;
    }

    /** Jumlah tiap kolom hitungan — dihitung SETELAH filter agar cocok dengan daftar. */
    public static function sum(array $rows): array
    {
        $sum = array_fill_keys(self::SUM_KEYS, 0);
        foreach ($rows as $r) {
            foreach (self::SUM_KEYS as $k) {
                $sum[$k] += (int) $r[$k];
            }
        }

        return $sum;
    }

    /** Nama jabatan untuk judul berkas export ('' bila tanpa filter). */
    public static function labelJabatan(int $jabatanId): string
    {
        return $jabatanId > 0 ? ((new JabatanModel())->find($jabatanId)['nama'] ?? '') : '';
    }

    /** Nama berkas export tanpa ekstensi. */
    public static function namaBerkas(string $dari, string $sampai): string
    {
        return 'Rekap-Absensi-' . $dari . '_' . $sampai;
    }

    /**
     * Spreadsheet rekap absensi (nilai jadi + baris total dengan SUM hidup).
     * Kolom: A No, B Kode, C Nama, D Jabatan, E Total, F Hadir, G Telat,
     * H Izin, I Sakit, J Alpa — kolom angka mulai E s/d J.
     */
    public static function excel(array $rows, string $dari, string $sampai, array $setting, string $labelJabatan = ''): Spreadsheet
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');

        $sheet->mergeCells('A1:J1')->setCellValue('A1', 'REKAP ABSENSI GURU');
        $sheet->mergeCells('A2:J2')->setCellValue('A2', 'Tahun Pelajaran ' . ($setting['academic_year'] ?? ''));
        $sheet->mergeCells('A3:J3')->setCellValue(
            'A3',
            'Periode ' . $dari . ' s/d ' . $sampai
                . ($labelJabatan !== '' ? ' — Jabatan: ' . $labelJabatan : '')
        );
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $head = ['No', 'Kode', 'Nama Guru', 'Jabatan', 'Total Hari', 'Hadir', 'Telat', 'Izin', 'Sakit', 'Alpa'];
        $sheet->fromArray($head, null, 'A5', true);
        $sheet->getStyle('A5:J5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A5:J5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A5:J5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r        = 6;
        $no       = 1;
        $firstRow = $r;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$r}", $no++);
            $sheet->setCellValueExplicit("B{$r}", (string) $row['kode'], DataType::TYPE_STRING);
            $sheet->setCellValue("C{$r}", $row['nama']);
            $sheet->setCellValue("D{$r}", $row['jabatan_all'] ?? '');
            $sheet->setCellValue("E{$r}", $row['total']);
            $sheet->setCellValue("F{$r}", $row['hadir']);
            $sheet->setCellValue("G{$r}", $row['telat']);
            $sheet->setCellValue("H{$r}", $row['izin']);
            $sheet->setCellValue("I{$r}", $row['sakit']);
            $sheet->setCellValue("J{$r}", $row['alpa']);
            $r++;
        }
        $lastRow = $r - 1;

        // Baris TOTAL dengan SUM hidup.
        $sheet->setCellValue("C{$r}", 'TOTAL');
        if ($lastRow >= $firstRow) {
            foreach (['E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $sheet->setCellValue("{$col}{$r}", "=SUM({$col}{$firstRow}:{$col}{$lastRow})");
            }
        }
        $sheet->getStyle("A{$r}:J{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}:J{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');

        $sheet->getStyle("A5:J{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A6:A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E6:J{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['A' => 5, 'B' => 10, 'C' => 30, 'D' => 28, 'E' => 11, 'F' => 9, 'G' => 9, 'H' => 9, 'I' => 9, 'J' => 9] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        kop_excel_prepend($sheet, 'J');

        return $ss;
    }

    /** HTML rekap untuk Dompdf (view pdf/rekap_absensi). */
    public static function pdfHtml(array $rows, string $dari, string $sampai, array $setting, string $labelJabatan = ''): string
    {
        return view('pdf/rekap_absensi', [
            'rows'         => $rows,
            'dari'         => $dari,
            'sampai'       => $sampai,
            'setting'      => $setting,
            'labelJabatan' => $labelJabatan,
        ]);
    }
}
