<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\PengampuModel;
use App\Models\SettingModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Cetak extends BaseController
{
    private function setting(): array
    {
        return (new SettingModel())->get() ?: ['school_name' => 'SEKOLAH', 'academic_year' => ''];
    }

    // ===================== JADWAL PER KELAS =====================
    public function jadwalKelas($id, $format = 'pdf')
    {
        $id    = (int) $id;
        $kelas = (new KelasModel())->find($id);
        if (! $kelas) {
            return redirect()->to(site_url('admin/jadwal'))->with('error', 'Kelas tidak ditemukan.');
        }
        $hari = (new HariModel())->aktifUrut();
        $jam  = (new JamPelajaranModel())->where('shift', $kelas['shift'])->orderBy('jam_ke', 'ASC')->findAll();
        $grid = (new JadwalModel())->gridForKelas($id);

        $title    = 'JADWAL KBM — ' . $kelas['nama_kelas'];
        $filename = 'Jadwal-' . $this->slug($kelas['nama_kelas']);

        if ($format === 'excel') {
            return $this->gridExcel($title, $hari, $jam, $grid, 'kelas', $filename);
        }
        return $this->gridPdf($title, $hari, $jam, $grid, 'kelas', $filename);
    }

    // ===================== JADWAL PER GURU =====================
    public function jadwalGuru($id, $format = 'pdf')
    {
        $id   = (int) $id;
        $guru = (new GuruModel())->find($id);
        if (! $guru) {
            return redirect()->to(site_url('admin/kurikulum/rekap'))->with('error', 'Guru tidak ditemukan.');
        }
        $hari = (new HariModel())->aktifUrut();
        // jam dua shift (tanpa istirahat) — guru bisa mengajar pagi & siang
        $jam  = (new JamPelajaranModel())->where('is_istirahat', 0)
            ->orderBy('shift', 'ASC')->orderBy('jam_ke', 'ASC')->findAll();
        $grid = (new JadwalModel())->gridForGuru($id);

        $title    = 'JADWAL MENGAJAR — ' . $guru['kode_guru'] . ' · ' . $guru['nama'];
        $filename = 'Jadwal-Guru-' . $this->slug($guru['nama']);

        if ($format === 'excel') {
            return $this->gridExcel($title, $hari, $jam, $grid, 'guru', $filename);
        }
        return $this->gridPdf($title, $hari, $jam, $grid, 'guru', $filename);
    }

    // ===================== REKAP BEBAN =====================
    public function rekap($format = 'pdf')
    {
        $grouped = $this->rekapGrouped();
        if ($format === 'excel') {
            return $this->rekapExcel($grouped);
        }
        $setting = $this->setting();
        $html = view('pdf/rekap_beban', ['grouped' => $grouped, 'setting' => $setting]);
        $this->streamPdf($html, 'Rekap-Beban-Mengajar', 'A4', 'portrait');
    }

    // ---------------------------------------------------------------
    // RENDER GRID (PDF)
    // ---------------------------------------------------------------
    private function gridPdf(string $title, array $hari, array $jam, array $grid, string $mode, string $filename): void
    {
        $html = view('pdf/jadwal_grid', [
            'title'   => $title,
            'setting' => $this->setting(),
            'hari'    => $hari,
            'jam'     => $jam,
            'grid'    => $grid,
            'mode'    => $mode,
        ]);
        $this->streamPdf($html, $filename, 'A4', 'landscape');
    }

    // RENDER GRID (EXCEL, nilai jadi)
    private function gridExcel(string $title, array $hari, array $jam, array $grid, string $mode, string $filename)
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Jadwal');

        $colCount = count($hari) + 1;
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        $sheet->mergeCells("A1:{$lastCol}1")->setCellValue('A1', $title);
        $sheet->mergeCells("A2:{$lastCol}2")->setCellValue('A2', strtoupper($this->setting()['school_name']) . ' — T.P. ' . $this->setting()['academic_year']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // header
        $r = 4;
        $sheet->setCellValue('A' . $r, 'Jam');
        $c = 2;
        foreach ($hari as $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $r, $h['nama']);
        }
        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // body
        $r = 5;
        foreach ($jam as $j) {
            if (! empty($j['is_istirahat'])) {
                $sheet->mergeCells("A{$r}:{$lastCol}{$r}")
                    ->setCellValue('A' . $r, 'ISTIRAHAT (' . substr($j['waktu_mulai'], 0, 5) . '-' . substr($j['waktu_selesai'], 0, 5) . ')');
                $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
                $r++;
                continue;
            }
            $label = ($mode === 'guru' ? ucfirst($j['shift']) . ' ' : '') . 'Jam ' . $j['jam_ke']
                . ' (' . substr($j['waktu_mulai'], 0, 5) . '-' . substr($j['waktu_selesai'], 0, 5) . ')';
            $sheet->setCellValue('A' . $r, $label);
            $c = 2;
            foreach ($hari as $h) {
                $cell = $grid[$h['id'] . '-' . $j['id']] ?? null;
                $val  = '';
                if ($cell) {
                    $val = $mode === 'kelas'
                        ? $cell['kode_mapel'] . " / " . $cell['kode_guru']
                        : $cell['nama_kelas'] . " / " . $cell['kode_mapel'];
                }
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++) . $r, $val);
            }
            $r++;
        }

        $sheet->getStyle("A4:{$lastCol}" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A4:{$lastCol}" . ($r - 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getColumnDimension('A')->setWidth(24);
        for ($i = 2; $i <= $colCount; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(18);
        }

        return $this->streamXlsx($ss, $filename);
    }

    // ---------------------------------------------------------------
    // REKAP (EXCEL, formula hidup: SUM + VLOOKUP + sheet Master Kode)
    // ---------------------------------------------------------------
    private function rekapExcel(array $grouped)
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Beban');

        $sheet->mergeCells('A1:F1')->setCellValue('A1', 'REKAP BEBAN MENGAJAR');
        $sheet->mergeCells('A2:F2')->setCellValue('A2', strtoupper($this->setting()['school_name']) . ' — T.P. ' . $this->setting()['academic_year']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $head = ['No', 'Kode Guru', 'Nama Guru (VLOOKUP)', 'Mata Pelajaran', 'Kelas', 'JP'];
        $sheet->fromArray($head, null, 'A4', true);
        $sheet->getStyle('A4:F4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A4:F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r  = 5;
        $no = 1;
        foreach ($grouped as $g) {
            $startRow = $r;
            foreach ($g['items'] as $it) {
                $sheet->setCellValue("A{$r}", $no);
                $sheet->setCellValue("B{$r}", $g['kode_guru']);
                // VLOOKUP hidup: ambil nama dari sheet Master Kode berdasar kode
                $sheet->setCellValue("C{$r}", "=VLOOKUP(B{$r},'Master Kode'!\$A:\$B,2,FALSE)");
                $sheet->setCellValue("D{$r}", $it['mapel']);
                $sheet->setCellValue("E{$r}", $it['kelas']);
                $sheet->setCellValue("F{$r}", $it['jp']);
                $r++;
            }
            $endRow = $r - 1;
            // baris subtotal dengan SUM hidup
            $sheet->setCellValue("E{$r}", 'TOTAL ' . $g['kode_guru']);
            if ($endRow >= $startRow) {
                $sheet->setCellValue("F{$r}", "=SUM(F{$startRow}:F{$endRow})");
            } else {
                $sheet->setCellValue("F{$r}", 0);
            }
            $sheet->getStyle("A{$r}:F{$r}")->getFont()->setBold(true);
            $sheet->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
            $r++;
            $no++;
        }

        $sheet->getStyle("A4:F" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (['A' => 5, 'B' => 12, 'C' => 28, 'D' => 30, 'E' => 18, 'F' => 8] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // ---- Sheet referensi "Master Kode" (target VLOOKUP) ----
        $ref = $ss->createSheet();
        $ref->setTitle('Master Kode');
        $ref->fromArray(['Kode Guru', 'Nama Guru'], null, 'A1', true);
        $ref->getStyle('A1:B1')->getFont()->setBold(true);
        $rr = 2;
        foreach ((new GuruModel())->orderBy('nama', 'ASC')->findAll() as $gr) {
            $ref->setCellValue("A{$rr}", $gr['kode_guru']);
            $ref->setCellValue("B{$rr}", $gr['nama']);
            $rr++;
        }
        $ref->getColumnDimension('A')->setWidth(12);
        $ref->getColumnDimension('B')->setWidth(30);

        $ss->setActiveSheetIndex(0);
        return $this->streamXlsx($ss, 'Rekap-Beban-Mengajar');
    }

    // ---------------------------------------------------------------
    private function rekapGrouped(): array
    {
        $rows    = (new PengampuModel())->rekapRows();
        $grouped = [];
        foreach ($rows as $r) {
            $gid = (int) $r['guru_id'];
            if (! isset($grouped[$gid])) {
                $grouped[$gid] = [
                    'kode_guru' => $r['kode_guru'], 'guru_nama' => $r['guru_nama'],
                    'max_beban' => (int) $r['max_beban'], 'items' => [], 'total' => 0,
                ];
            }
            $grouped[$gid]['items'][] = ['mapel' => $r['nama_mapel'], 'kelas' => $r['nama_kelas'], 'jp' => (int) $r['jp']];
            $grouped[$gid]['total'] += (int) $r['jp'];
        }
        return array_values($grouped);
    }

    private function streamPdf(string $html, string $filename, string $size, string $orientation): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($size, $orientation);
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

    private function slug(string $text): string
    {
        return preg_replace('/[^a-z0-9]+/i', '-', trim($text));
    }
}
