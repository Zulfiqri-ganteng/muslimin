<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UjianReport;
use App\Models\SettingModel;
use App\Models\UjianJadwalModel;
use App\Models\UjianPengawasModel;
use App\Models\UjianPeriodeModel;
use App\Models\UjianSusulanModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Cetakan & laporan modul Ujian.
 *
 * Controller sendiri, mengikuti pola `Admin\LaporanLab` / `Admin\LaporanUkk`
 * — sekaligus menjaga `Admin\Ujian` (yang mengurus tampilan tab) tidak
 * membengkak lagi. Agregasi angkanya ada di `App\Libraries\UjianReport`
 * supaya tab Rekap dan cetakan ini memakai perhitungan yang SAMA.
 *
 * Tiga keluaran:
 *  1. Daftar Hadir per sesi (PDF)  — kolom tanda tangan dibiarkan kosong
 *  2. Berita Acara per sesi (PDF)  — narasi + daftar tidak hadir + ttd pengawas
 *  3. Rekap periode (PDF & Excel)  — ringkasan + rekap per kelas/mapel/tanggal
 */
class LaporanUjian extends BaseController
{
    protected UjianPeriodeModel $model;

    public function __construct()
    {
        $this->model = new UjianPeriodeModel();
    }

    // =================================================================
    // Rekap satu periode
    // =================================================================

    public function pdf(string $slug = '')
    {
        $ctx = $this->konteks($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode] = $ctx;

        $html = view('pdf/ujian_rekap', [
            'periode' => $periode,
            'label'   => $this->model->label($periode),
            'panjang' => UjianPeriodeModel::JENIS_PANJANG[$periode['jenis']] ?? '',
            'setting' => (new SettingModel())->get(),
        ] + UjianReport::hitung((int) $periode['id']));

        $this->kirimPdf($html, 'Rekap-Ujian-' . strtoupper($slug) . '-' . date('Ymd-His'));
    }

    public function excel(string $slug = '')
    {
        $ctx = $this->konteks($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode] = $ctx;

        $d     = UjianReport::hitung((int) $periode['id']);
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Ujian');

        $baris = 1;
        $judul = function (string $teks) use ($sheet, &$baris) {
            $sheet->setCellValue('A' . $baris, $teks);
            $sheet->getStyle('A' . $baris)->getFont()->setBold(true)->setSize(12);
            $baris += 1;
        };
        $kepala = function (array $kolom) use ($sheet, &$baris) {
            $sheet->fromArray($kolom, null, 'A' . $baris, true);
            $akhir = chr(ord('A') + count($kolom) - 1);
            $range = 'A' . $baris . ':' . $akhir . $baris;
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A6B');
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $baris += 1;
        };
        $isi = function (array $data) use ($sheet, &$baris) {
            if ($data !== []) {
                $sheet->fromArray($data, null, 'A' . $baris, true);
                $baris += count($data);
            }
            $baris += 1;
        };

        $judul($this->model->label($periode));
        $sheet->setCellValue('A' . $baris, 'Semester ' . $periode['semester']
            . ' · Pelaksanaan ' . $this->tgl($periode['tanggal_mulai']) . ' s.d. ' . $this->tgl($periode['tanggal_selesai']));
        $baris += 2;

        $judul('RINGKASAN');
        $kepala(['Keterangan', 'Jumlah']);
        $isi([
            ['Sesi ujian terjadwal', $d['jadwalTotal']],
            ['Penugasan pengawas', $d['pengawasTotal']],
            ['Siswa tidak hadir', $d['takHadirTotal']],
            ['Susulan belum dijadwalkan', $d['perStatus']['belum']],
            ['Susulan sudah dijadwalkan', $d['perStatus']['dijadwalkan']],
            ['Susulan selesai', $d['perStatus']['selesai']],
            ['Susulan batal', $d['perStatus']['batal']],
            ['Alasan: sakit', $d['perAlasan']['sakit']],
            ['Alasan: izin', $d['perAlasan']['izin']],
            ['Alasan: alpa', $d['perAlasan']['alpa']],
            ['Alasan: lainnya', $d['perAlasan']['lainnya']],
        ]);

        $judul('REKAP PER KELAS');
        $kepala(['Kelas', 'Tingkat', 'Tidak Hadir', 'Belum', 'Dijadwalkan', 'Selesai', 'Batal']);
        $isi(array_map(static fn ($r) => [
            $r['nama_kelas'] ?? '—', $r['tingkat'] ?? '—',
            (int) $r['total'], (int) $r['belum'], (int) $r['dijadwalkan'], (int) $r['selesai'], (int) $r['batal'],
        ], $d['perKelas']));

        $judul('REKAP PER MATA PELAJARAN');
        $kepala(['Kode', 'Mata Pelajaran', 'Tidak Hadir', 'Belum', 'Dijadwalkan', 'Selesai', 'Batal']);
        $isi(array_map(static fn ($r) => [
            $r['kode_mapel'] ?? '—', $r['nama_mapel'] ?? '—',
            (int) $r['total'], (int) $r['belum'], (int) $r['dijadwalkan'], (int) $r['selesai'], (int) $r['batal'],
        ], $d['perMapel']));

        $judul('DAFTAR SISWA TIDAK HADIR & SUSULANNYA');
        $kepala(['NIS', 'Nama', 'Kelas', 'Mapel', 'Tgl Ujian', 'Alasan', 'Status', 'Tgl Susulan', 'Ruang', 'Pengawas']);
        $isi(array_map(fn ($r) => [
            $r['nis'] ?? '—',
            $r['siswa_nama'] ?? '—',
            $r['nama_kelas'] ?? '—',
            $r['nama_mapel'] ?? '—',
            $this->tgl($r['tanggal_ujian']),
            $r['alasan'],
            UjianSusulanModel::STATUS_LABEL[$r['status']] ?? $r['status'],
            $this->tgl($r['tanggal_susulan']),
            $r['ruang_susulan'] ?? '—',
            $r['pengawas_nama'] ?? '—',
        ], $this->daftarSusulan((int) $periode['id'])));

        foreach (range('A', 'J') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }
        kop_excel_prepend($sheet, 'J');

        $this->kirimXlsx($ss, 'Rekap-Ujian-' . strtoupper($slug) . '-' . date('Ymd-His'));
    }

    // =================================================================
    // Cetakan per sesi ujian
    // =================================================================

    /** Daftar hadir peserta satu sesi — kolom tanda tangan dibiarkan kosong. */
    public function daftarHadir(string $slug = '', $jadwalId = 0)
    {
        $ctx = $this->konteksSesi($slug, (int) $jadwalId);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode, $jadwal] = $ctx;

        $jadwalModel = new UjianJadwalModel();
        $kelompok    = UjianReport::pesertaSesi($jadwalModel->kelasSasaran($jadwal));

        $html = view('pdf/ujian_daftar_hadir', [
            'periode'  => $periode,
            'label'    => $this->model->label($periode),
            'jadwal'   => $jadwal,
            'kelompok' => $kelompok,
            'pengawas' => (new UjianPengawasModel())->untukJadwal((int) $jadwal['id']),
        ]);

        $this->kirimPdf($html, 'Daftar-Hadir-' . strtoupper($slug) . '-' . $jadwal['id']);
    }

    /** Berita acara pelaksanaan satu sesi ujian. */
    public function beritaAcara(string $slug = '', $jadwalId = 0)
    {
        $ctx = $this->konteksSesi($slug, (int) $jadwalId);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode, $jadwal] = $ctx;

        $jadwalModel = new UjianJadwalModel();
        $sasaran     = $jadwalModel->kelasSasaran($jadwal);
        $kelompok    = UjianReport::pesertaSesi($sasaran);

        $jumlahPeserta = 0;
        foreach ($kelompok as $k) {
            $jumlahPeserta += count($k['siswa']);
        }

        $takHadir = (new UjianSusulanModel())->withRelations()
            ->where('ujian_susulan.jadwal_id', (int) $jadwal['id'])
            ->orderBy('kelas.nama_kelas', 'ASC')->orderBy('siswa.nama', 'ASC')
            ->findAll();

        $html = view('pdf/ujian_berita_acara', [
            'periode'       => $periode,
            'label'         => $this->model->label($periode),
            'panjang'       => UjianPeriodeModel::JENIS_PANJANG[$periode['jenis']] ?? '',
            'jadwal'        => $jadwal,
            'sasaran'       => $sasaran,
            'jumlahPeserta' => $jumlahPeserta,
            'takHadir'      => $takHadir,
            'pengawas'      => (new UjianPengawasModel())->untukJadwal((int) $jadwal['id']),
            // Nomor DITURUNKAN dari data (tidak disimpan) supaya stabil tiap
            // kali dicetak tanpa menambah tabel penomoran.
            'nomor'         => 'BA/' . strtoupper($periode['jenis']) . '/'
                . str_replace('/', '-', $periode['tahun_ajaran']) . '/'
                . str_pad((string) $jadwal['id'], 4, '0', STR_PAD_LEFT),
            'setting'       => (new SettingModel())->get(),
        ]);

        $this->kirimPdf($html, 'Berita-Acara-' . strtoupper($slug) . '-' . $jadwal['id']);
    }

    // =================================================================
    // Util internal
    // =================================================================

    /** Daftar susulan satu periode, lengkap dengan relasinya. */
    private function daftarSusulan(int $periodeId): array
    {
        return (new UjianSusulanModel())->withRelations()
            ->where('ujian_susulan.periode_id', $periodeId)
            ->orderBy('kelas.nama_kelas', 'ASC')
            ->orderBy('siswa.nama', 'ASC')
            ->orderBy('ujian_susulan.tanggal_ujian', 'ASC')
            ->findAll();
    }

    /**
     * Selesaikan slug + periode (aturan tahunnya ada di model, dipakai
     * bersama web & API).
     *
     * @return array{0:string,1:array}|\CodeIgniter\HTTP\RedirectResponse
     */
    private function konteks(string $slug)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Jenis ujian tidak dikenal.');
        }
        $slug = UjianPeriodeModel::keSlug($jenis);

        $periode = $this->model->untukTahun($jenis, $this->request->getGet('tp'));

        if ($periode === null) {
            return redirect()->to(site_url('admin/ujian/' . $slug))
                ->with('error', 'Periode ujian tidak ditemukan.');
        }

        return [$slug, $periode];
    }

    /**
     * Seperti konteks(), plus satu sesi ujian yang wajib milik periode itu.
     *
     * @return array{0:string,1:array,2:array}|\CodeIgniter\HTTP\RedirectResponse
     */
    private function konteksSesi(string $slug, int $jadwalId)
    {
        $ctx = $this->konteks($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode] = $ctx;

        $kembali = site_url('admin/ujian/' . $slug . '/jadwal')
            . '?tp=' . rawurlencode($periode['tahun_ajaran']);

        $jadwal = $jadwalId > 0
            ? (new UjianJadwalModel())->withRelations()->where('ujian_jadwal.id', $jadwalId)->first()
            : null;

        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return redirect()->to($kembali)->with('error', 'Sesi ujian tidak ditemukan.');
        }

        return [$slug, $periode, $jadwal];
    }

    private function tgl(?string $d): string
    {
        return $d ? date('d/m/Y', strtotime($d)) : '—';
    }

    /** Render HTML jadi PDF A4 portrait (pola LaporanLab / Cetak). */
    private function kirimPdf(string $html, string $namaBerkas): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($namaBerkas . '.pdf', ['Attachment' => false]);
        exit;
    }

    private function kirimXlsx(Spreadsheet $ss, string $namaBerkas): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $namaBerkas . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }
}
