<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UkkReport;
use App\Models\SettingModel;
use App\Models\TahunAjaranModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Rekap hasil UKK — agregasi peserta per status, per paket soal, per
 * jurusan. Filter opsional per tahun ajaran. Hitungannya di Library bersama
 * App\Libraries\UkkReport (dipakai juga oleh API Api\Admin\LaporanUkk).
 */
class LaporanUkk extends BaseController
{
    public function index()
    {
        $tahunId = (int) $this->request->getGet('tahun_ajaran_id');

        return view('admin/laporan_ukk/index', [
            'title'     => 'Rekap Hasil UKK',
            'tahunId'   => $tahunId,
            'tahunOpts' => (new TahunAjaranModel())->options(),
        ] + UkkReport::hitung($tahunId));
    }

    public function pdf()
    {
        $tahunId = (int) $this->request->getGet('tahun_ajaran_id');
        $html = view('pdf/laporan_ukk', [
            'tahunId'   => $tahunId,
            'tahunOpts' => (new TahunAjaranModel())->options(),
            'setting'   => (new SettingModel())->get(),
        ] + UkkReport::hitung($tahunId));

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('Rekap-UKK-' . date('Ymd-His') . '.pdf', ['Attachment' => false]);
        exit;
    }

    public function excel()
    {
        $tahunId = (int) $this->request->getGet('tahun_ajaran_id');
        $d = UkkReport::hitung($tahunId);

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap UKK');

        $r = 1;
        $judul = function (string $teks) use ($sheet, &$r) {
            $sheet->setCellValue('A' . $r, $teks);
            $sheet->getStyle('A' . $r . ':E' . $r)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A' . $r . ':E' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
            $r++;
        };
        $baris = function (string $label, $nilai) use ($sheet, &$r) {
            $sheet->setCellValue('A' . $r, $label);
            $sheet->setCellValue('B' . $r, $nilai);
            $r++;
        };

        $judul('RINGKASAN PESERTA');
        $baris('Total Peserta', $d['totalPeserta']);
        foreach ($d['perStatus'] as $k => $v) {
            $baris(ucfirst(str_replace('_', ' ', $k)), $v);
        }
        $baris('Rata-rata Nilai Akhir', $d['rataNilai'] !== null ? $d['rataNilai'] : '—');
        $r++;

        $judul('REKAP PER PAKET SOAL');
        $sheet->fromArray(['Paket Soal', 'Total', 'Lulus', 'Tidak Lulus', 'Rata-rata Nilai'], null, 'A' . $r, true);
        $sheet->getStyle('A' . $r . ':E' . $r)->getFont()->setBold(true);
        $r++;
        foreach ($d['perPaket'] as $row) {
            $sheet->fromArray([
                $row['paket_kode'] . ' - ' . $row['paket_nama'], $row['total'], $row['lulus'], $row['tidak_lulus'],
                $row['rata_nilai'] !== null ? $row['rata_nilai'] : '—',
            ], null, 'A' . $r, true);
            $r++;
        }
        $r++;

        $judul('REKAP PER JURUSAN');
        $sheet->fromArray(['Jurusan', 'Total', 'Lulus', 'Tidak Lulus'], null, 'A' . $r, true);
        $sheet->getStyle('A' . $r . ':D' . $r)->getFont()->setBold(true);
        $r++;
        foreach ($d['perJurusan'] as $row) {
            $sheet->fromArray([
                $row['jurusan_nama'] ?? '(tanpa jurusan)', $row['total'], $row['lulus'], $row['tidak_lulus'],
            ], null, 'A' . $r, true);
            $r++;
        }

        $sheet->getColumnDimension('A')->setWidth(34);
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        kop_excel_prepend($sheet, 'E');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Rekap-UKK-' . date('Ymd-His') . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }
}
