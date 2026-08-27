<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
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
 * jurusan. Filter opsional per tahun ajaran. Agregasi pakai RAW query
 * builder dengan deleted_at DIKUALIFIKASI (pola sama SiswaModel::statistik
 * / LabReport) supaya aman saat JOIN.
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
        ] + $this->hitung($tahunId));
    }

    public function pdf()
    {
        $tahunId = (int) $this->request->getGet('tahun_ajaran_id');
        $html = view('pdf/laporan_ukk', [
            'tahunId'   => $tahunId,
            'tahunOpts' => (new TahunAjaranModel())->options(),
            'setting'   => (new SettingModel())->get(),
        ] + $this->hitung($tahunId));

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
        $d = $this->hitung($tahunId);

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

    // =================================================================

    private function hitung(int $tahunId): array
    {
        $db = db_connect();

        $base = static fn () => $db->table('peserta_ukk')->where('peserta_ukk.deleted_at', null);
        $withTahun = function ($b) use ($tahunId) {
            return $tahunId > 0 ? $b->where('peserta_ukk.tahun_ajaran_id', $tahunId) : $b;
        };

        $totalPeserta = (int) $withTahun($base())->countAllResults();

        $perStatus = ['terdaftar' => 0, 'hadir' => 0, 'tidak_hadir' => 0, 'lulus' => 0, 'tidak_lulus' => 0];
        foreach ($withTahun($base())->select('status, COUNT(*) AS jumlah')->groupBy('status')->get()->getResultArray() as $r) {
            if (isset($perStatus[$r['status']])) {
                $perStatus[$r['status']] = (int) $r['jumlah'];
            }
        }

        $rataRow = $withTahun($base())->select('AVG(nilai_akhir) AS rata')->where('nilai_akhir IS NOT NULL')->get()->getRowArray();
        $rataNilai = $rataRow && $rataRow['rata'] !== null ? round((float) $rataRow['rata'], 2) : null;

        // Per paket soal.
        $perPaket = [];
        $rows = $withTahun(
            $base()->select(
                'paket_soal_ukk.kode AS paket_kode, paket_soal_ukk.nama AS paket_nama,'
                . ' COUNT(*) AS total,'
                . ' SUM(CASE WHEN peserta_ukk.status = "lulus" THEN 1 ELSE 0 END) AS lulus,'
                . ' SUM(CASE WHEN peserta_ukk.status = "tidak_lulus" THEN 1 ELSE 0 END) AS tidak_lulus,'
                . ' AVG(peserta_ukk.nilai_akhir) AS rata_nilai'
            )->join('paket_soal_ukk', 'paket_soal_ukk.id = peserta_ukk.paket_soal_id AND paket_soal_ukk.deleted_at IS NULL', 'left')
        )->groupBy('peserta_ukk.paket_soal_id')->orderBy('paket_soal_ukk.nama', 'ASC')->get()->getResultArray();
        foreach ($rows as $r) {
            $perPaket[] = [
                'paket_kode'  => $r['paket_kode'] ?? '-',
                'paket_nama'  => $r['paket_nama'] ?? '(paket dihapus)',
                'total'       => (int) $r['total'],
                'lulus'       => (int) $r['lulus'],
                'tidak_lulus' => (int) $r['tidak_lulus'],
                'rata_nilai'  => $r['rata_nilai'] !== null ? round((float) $r['rata_nilai'], 2) : null,
            ];
        }

        // Per jurusan (lewat paket_soal_ukk.jurusan_id).
        $perJurusan = [];
        $rows = $withTahun(
            $base()->select(
                'jurusan.nama AS jurusan_nama,'
                . ' COUNT(*) AS total,'
                . ' SUM(CASE WHEN peserta_ukk.status = "lulus" THEN 1 ELSE 0 END) AS lulus,'
                . ' SUM(CASE WHEN peserta_ukk.status = "tidak_lulus" THEN 1 ELSE 0 END) AS tidak_lulus'
            )->join('paket_soal_ukk', 'paket_soal_ukk.id = peserta_ukk.paket_soal_id AND paket_soal_ukk.deleted_at IS NULL', 'left')
            ->join('jurusan', 'jurusan.id = paket_soal_ukk.jurusan_id', 'left')
        )->groupBy('jurusan.id')->orderBy('total', 'DESC')->get()->getResultArray();
        foreach ($rows as $r) {
            $perJurusan[] = [
                'jurusan_nama' => $r['jurusan_nama'],
                'total'        => (int) $r['total'],
                'lulus'        => (int) $r['lulus'],
                'tidak_lulus'  => (int) $r['tidak_lulus'],
            ];
        }

        return [
            'totalPeserta' => $totalPeserta,
            'perStatus'    => $perStatus,
            'rataNilai'    => $rataNilai,
            'perPaket'     => $perPaket,
            'perJurusan'   => $perJurusan,
        ];
    }
}
