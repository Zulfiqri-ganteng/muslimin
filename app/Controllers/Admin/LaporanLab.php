<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LabReport;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Laporan Laboratorium — rekap gabungan (aset, peminjaman, kerusakan/perbaikan,
 * sparepart, pemakaian lab) + export PDF & Excel dengan kop resmi.
 *
 * Agregasi memakai raw query builder dengan kolom deleted_at yang DIKUALIFIKASI
 * (mis. aset.deleted_at) agar aman saat ada JOIN — meniru SiswaModel::statistik.
 */
class LaporanLab extends BaseController
{
    public function index()
    {
        [$dari, $sampai] = $this->rentang();

        return view('admin/laporan_lab/index', [
            'title'  => 'Laporan Laboratorium',
            'dari'   => $dari,
            'sampai' => $sampai,
        ] + LabReport::hitung($dari, $sampai));
    }

    public function pdf()
    {
        [$dari, $sampai] = $this->rentang();
        $html = view('pdf/laporan_lab', [
            'dari'   => $dari,
            'sampai' => $sampai,
        ] + LabReport::hitung($dari, $sampai));

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('Laporan-Lab-' . $dari . '_' . $sampai . '.pdf', ['Attachment' => false]);
        exit;
    }

    public function excel()
    {
        [$dari, $sampai] = $this->rentang();
        $d = LabReport::hitung($dari, $sampai);

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Laporan Lab');

        $r = 1;
        $judul = function (string $teks) use ($sheet, &$r) {
            $sheet->setCellValue('A' . $r, $teks);
            $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
            $sheet->getStyle('A' . $r . ':D' . $r)->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A' . $r . ':D' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
            $r++;
        };
        $baris = function (string $label, $nilai) use ($sheet, &$r) {
            $sheet->setCellValue('A' . $r, $label);
            $sheet->setCellValue('B' . $r, $nilai);
            $r++;
        };

        $sheet->setCellValue('A' . $r, 'Periode: ' . $dari . ' s/d ' . $sampai);
        $r += 2;

        $judul('ASET / INVENTARIS');
        $baris('Total Aset', $d['asetTotal']);
        foreach ($d['asetStatus'] as $k => $v) {
            $baris('Status: ' . ucfirst($k), $v);
        }
        foreach ($d['asetKondisi'] as $k => $v) {
            $baris('Kondisi: ' . ucfirst(str_replace('_', ' ', $k)), $v);
        }
        $r++;
        $judul('ASET PER LAB');
        foreach ($d['asetPerLab'] as $row) {
            $baris($row['lab_nama'] ?? '(tanpa lab)', (int) $row['c']);
        }
        $r++;
        $judul('PEMINJAMAN (periode)');
        $baris('Total peminjaman', $d['pmTotal']);
        foreach ($d['pmStatus'] as $k => $v) {
            $baris(ucfirst($k), $v);
        }
        $baris('Sedang dipinjam (kini)', $d['pmSedang']);
        $baris('Terlambat (kini)', $d['pmTerlambat']);
        $r++;
        $judul('KERUSAKAN & PERBAIKAN (periode)');
        $baris('Total kerusakan', $d['krTotal']);
        foreach ($d['krStatus'] as $k => $v) {
            $baris('Kerusakan ' . str_replace('_', ' ', $k), $v);
        }
        $baris('Total perbaikan', $d['pbTotal']);
        foreach ($d['pbJenis'] as $k => $v) {
            $baris('Perbaikan ' . $k, $v);
        }
        $baris('Total biaya perbaikan (Rp)', $d['pbBiaya']);
        $r++;
        $judul('SPAREPART');
        $baris('Total jenis sparepart', $d['spTotal']);
        $baris('Stok menipis', $d['spMenipisCount']);
        $r++;
        $judul('PEMAKAIAN LAB (jurnal, periode)');
        $baris('Total sesi', $d['jrTotal']);
        foreach ($d['jrPerLab'] as $row) {
            $baris($row['lab_nama'] ?? '(tanpa lab)', (int) $row['c']);
        }

        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        kop_excel_prepend($sheet, 'D');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Laporan-Lab-' . $dari . '_' . $sampai . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    // =================================================================

    /** Rentang tanggal dari query (default bulan berjalan; tukar bila terbalik). */
    private function rentang(): array
    {
        $dari   = trim((string) $this->request->getGet('dari')) ?: date('Y-m-01');
        $sampai = trim((string) $this->request->getGet('sampai')) ?: date('Y-m-t');
        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari, $sampai];
    }

}
