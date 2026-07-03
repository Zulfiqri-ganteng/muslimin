<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SubmissionModel;
use App\Models\SettingModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Export extends BaseController
{
    /** Surat pernyataan kesediaan (PDF) untuk 1 guru. */
    public function surat($id)
    {
        $model = new SubmissionModel();
        $row   = $model->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/submissions'))->with('error', 'Data tidak ditemukan.');
        }

        $setting = (new SettingModel())->get();
        $html    = view('pdf/surat', [
            'row'     => SubmissionModel::decode($row),
            'setting' => $setting,
        ]);

        $this->streamPdf($html, 'Surat-Kesediaan-' . $this->slug($row['nama_lengkap']), 'A4', 'portrait');
    }

    /** Rekap seluruh kesediaan (PDF, landscape). */
    public function recapPdf()
    {
        $model   = new SubmissionModel();
        $status  = $this->request->getGet('status');
        if (in_array($status, ['PNS', 'PPPK', 'GTY', 'GTT'], true)) {
            $model->where('status_kepegawaian', $status);
        }
        $rows    = $model->orderBy('nama_lengkap', 'ASC')->findAll();
        $setting = (new SettingModel())->get();

        $html = view('pdf/rekap', [
            'rows'    => SubmissionModel::decodeAll($rows),
            'setting' => $setting,
            'status'  => $status,
        ]);

        $this->streamPdf($html, 'Rekap-Kesediaan-Guru', 'A4', 'landscape');
    }

    /** Rekap seluruh kesediaan (Excel). */
    public function excel()
    {
        $model  = new SubmissionModel();
        $status = $this->request->getGet('status');
        if (in_array($status, ['PNS', 'PPPK', 'GTY', 'GTT'], true)) {
            $model->where('status_kepegawaian', $status);
        }
        $rows    = SubmissionModel::decodeAll($model->orderBy('nama_lengkap', 'ASC')->findAll());
        $setting = (new SettingModel())->get();

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Kesediaan Guru');

        // Judul
        $sheet->mergeCells('A1:J1')->setCellValue('A1', 'REKAP KESEDIAAN GURU MENGAJAR');
        $sheet->mergeCells('A2:J2')->setCellValue('A2', 'Tahun Pelajaran ' . $setting['academic_year']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header
        $headers = ['No', 'Nama Lengkap', 'Guru Mapel', 'Status', 'No. HP',
            'Mata Pelajaran Diampu', 'Tugas Tambahan', 'Hari (Pagi/Siang)', 'Kesediaan', 'Tgl Isi'];
        $col = 'A'; $r = 4;
        foreach ($headers as $h) {
            $sheet->setCellValue($col . $r, $h);
            $col++;
        }
        $sheet->getStyle('A4:J4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:J4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
        $sheet->getStyle('A4:J4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data
        $r = 5;
        foreach ($rows as $i => $d) {
            $mapel = implode(", ", array_map(fn ($m) => $m['mapel'] . ($m['kelas'] ? " ({$m['kelas']})" : ''), $d['mapel_diampu'] ?: []));
            $tugas = ! empty($d['tugas_tambahan']) ? 'Bersedia' : '-';
            $hari  = [];
            foreach (($d['ketersediaan_hari'] ?: []) as $h => $s) {
                if ($s) $hari[] = $h . ': ' . implode('/', (array) $s);
            }
            $hariStr = $hari ? implode(", ", $hari) : '-';

            $sheet->fromArray([
                $i + 1, $d['nama_lengkap'], $d['guru_mapel'], $d['status_kepegawaian'],
                "'" . $d['nomor_hp'], $mapel, $tugas, $hariStr,
                empty($d['bersedia_mengajar']) ? 'TIDAK BERSEDIA' : 'Bersedia',
                date('d-m-Y', strtotime($d['created_at'])),
            ], null, 'A' . $r, true);
            $r++;
        }

        // Border & lebar kolom
        $last = $r - 1;
        if ($last >= 5) {
            $sheet->getStyle('A4:J' . $last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A5:J' . $last)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        }
        foreach (['A'=>5,'B'=>28,'C'=>18,'D'=>8,'E'=>15,'F'=>40,'G'=>12,'H'=>30,'I'=>16,'J'=>12] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        kop_excel_prepend($sheet, 'J');

        $filename = 'Rekap-Kesediaan-Guru-' . date('Ymd-His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($ss);
        $writer->save('php://output');
        exit;
    }

    // ---------------------------------------------------------------
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

    private function slug(string $text): string
    {
        return preg_replace('/[^a-z0-9]+/i', '-', trim($text));
    }
}
