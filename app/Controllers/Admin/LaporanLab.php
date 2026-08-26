<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
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
        ] + $this->hitung($dari, $sampai));
    }

    public function pdf()
    {
        [$dari, $sampai] = $this->rentang();
        $html = view('pdf/laporan_lab', [
            'dari'   => $dari,
            'sampai' => $sampai,
        ] + $this->hitung($dari, $sampai));

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
        $d = $this->hitung($dari, $sampai);

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

    private function petakan(array $rows, string $key, array $default): array
    {
        $out = $default;
        foreach ($rows as $row) {
            if (array_key_exists($row[$key], $out)) {
                $out[$row[$key]] = (int) $row['c'];
            }
        }

        return $out;
    }

    /** Hitung seluruh agregat laporan. */
    private function hitung(string $dari, string $sampai): array
    {
        $db    = db_connect();
        $today = date('Y-m-d');

        // ---- Aset (snapshot) ----
        $asetTotal   = (int) $db->table('aset')->where('aset.deleted_at', null)->countAllResults();
        $asetKondisi = $this->petakan(
            $db->table('aset')->select('kondisi, COUNT(*) c')->where('deleted_at', null)->groupBy('kondisi')->get()->getResultArray(),
            'kondisi',
            ['baik' => 0, 'rusak_ringan' => 0, 'rusak_berat' => 0]
        );
        $asetStatus = $this->petakan(
            $db->table('aset')->select('status, COUNT(*) c')->where('deleted_at', null)->groupBy('status')->get()->getResultArray(),
            'status',
            ['tersedia' => 0, 'dipinjam' => 0, 'perbaikan' => 0, 'dihapus' => 0]
        );
        $asetPerLab = $db->table('aset')
            ->select('lab.nama AS lab_nama, COUNT(*) c')
            ->join('lab', 'lab.id = aset.lab_id', 'left')
            ->where('aset.deleted_at', null)
            ->groupBy('aset.lab_id')->orderBy('c', 'DESC')->get()->getResultArray();

        // ---- Peminjaman ----
        $pmRange = static fn ($b) => $b->where('peminjaman.deleted_at', null)
            ->where('tanggal_pinjam >=', $dari)->where('tanggal_pinjam <=', $sampai);
        $pmTotal  = (int) $pmRange($db->table('peminjaman'))->countAllResults();
        $pmStatus = $this->petakan(
            $pmRange($db->table('peminjaman'))->select('status, COUNT(*) c')->groupBy('status')->get()->getResultArray(),
            'status',
            ['dipinjam' => 0, 'dikembalikan' => 0, 'terlambat' => 0, 'hilang' => 0]
        );
        $pmSedang    = (int) $db->table('peminjaman')->where('deleted_at', null)->where('status', 'dipinjam')->countAllResults();
        $pmTerlambat = (int) $db->table('peminjaman')->where('deleted_at', null)->where('status', 'dipinjam')
            ->where('tanggal_kembali_rencana IS NOT NULL')->where('tanggal_kembali_rencana <', $today)->countAllResults();

        // ---- Kerusakan ----
        $krRange = static fn ($b) => $b->where('kerusakan.deleted_at', null)
            ->where('tanggal_lapor >=', $dari)->where('tanggal_lapor <=', $sampai);
        $krTotal  = (int) $krRange($db->table('kerusakan'))->countAllResults();
        $krStatus = $this->petakan(
            $krRange($db->table('kerusakan'))->select('status, COUNT(*) c')->groupBy('status')->get()->getResultArray(),
            'status',
            ['dilaporkan' => 0, 'diproses' => 0, 'selesai' => 0, 'tak_teratasi' => 0]
        );

        // ---- Perbaikan ----
        $pbRange = static fn ($b) => $b->where('perbaikan.deleted_at', null)
            ->where('tanggal >=', $dari)->where('tanggal <=', $sampai);
        $pbTotal = (int) $pbRange($db->table('perbaikan'))->countAllResults();
        $pbJenis = $this->petakan(
            $pbRange($db->table('perbaikan'))->select('jenis, COUNT(*) c')->groupBy('jenis')->get()->getResultArray(),
            'jenis',
            ['perbaikan' => 0, 'maintenance' => 0, 'penggantian' => 0]
        );
        $pbBiaya = (float) ($pbRange($db->table('perbaikan'))->selectSum('biaya', 't')->get()->getRow()->t ?? 0);

        // ---- Sparepart (snapshot) ----
        $spTotal   = (int) $db->table('sparepart')->where('deleted_at', null)->countAllResults();
        $spMenipis = $db->table('sparepart')->select('kode, nama, stok, stok_minimum, satuan')
            ->where('deleted_at', null)->where('stok <= stok_minimum', null, false)
            ->orderBy('nama', 'ASC')->get()->getResultArray();

        // ---- Jurnal pemakaian ----
        $jrRange = static fn ($b) => $b->where('jurnal_lab.deleted_at', null)
            ->where('tanggal >=', $dari)->where('tanggal <=', $sampai);
        $jrTotal  = (int) $jrRange($db->table('jurnal_lab'))->countAllResults();
        $jrPerLab = $jrRange($db->table('jurnal_lab'))
            ->select('lab.nama AS lab_nama, COUNT(*) c')
            ->join('lab', 'lab.id = jurnal_lab.lab_id', 'left')
            ->groupBy('jurnal_lab.lab_id')->orderBy('c', 'DESC')->get()->getResultArray();

        return compact(
            'asetTotal', 'asetKondisi', 'asetStatus', 'asetPerLab',
            'pmTotal', 'pmStatus', 'pmSedang', 'pmTerlambat',
            'krTotal', 'krStatus', 'pbTotal', 'pbJenis', 'pbBiaya',
            'spTotal', 'spMenipis', 'jrTotal', 'jrPerLab'
        ) + ['spMenipisCount' => count($spMenipis)];
    }
}
