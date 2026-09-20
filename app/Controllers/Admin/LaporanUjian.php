<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UjianCetak;
use App\Models\UjianJadwalModel;
use App\Models\UjianPeriodeModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Cetakan & laporan modul Ujian untuk WEB.
 *
 * Controller sendiri, mengikuti pola `Admin\LaporanLab` / `Admin\LaporanUkk`.
 * Perakitan berkasnya sendiri ada di `App\Libraries\UjianCetak` supaya
 * identik dengan yang diunduh lewat aplikasi Android
 * (`Api\Admin\UjianCetak`); di sini tinggal urusan menyelesaikan periode
 * lalu melempar berkasnya ke browser.
 *
 * Empat keluaran:
 *  1. Daftar Hadir per sesi (PDF)  — kolom tanda tangan dibiarkan kosong
 *  2. Berita Acara per sesi (PDF)  — narasi + daftar tidak hadir + ttd pengawas
 *  3. Rekap periode (PDF & Excel)  — ringkasan + rekap per kelas/mapel
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

        $this->kirim(
            UjianCetak::pdf(UjianCetak::rekapHtml($periode)),
            'Rekap-Ujian-' . strtoupper($slug) . '-' . date('Ymd-His') . '.pdf',
            'application/pdf',
            true
        );
    }

    public function excel(string $slug = '')
    {
        $ctx = $this->konteks($slug);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode] = $ctx;

        $this->kirimXlsx(
            UjianCetak::rekapSpreadsheet($periode),
            'Rekap-Ujian-' . strtoupper($slug) . '-' . date('Ymd-His')
        );
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

        $this->kirim(
            UjianCetak::pdf(UjianCetak::daftarHadirHtml($periode, $jadwal)),
            'Daftar-Hadir-' . strtoupper($slug) . '-' . $jadwal['id'] . '.pdf',
            'application/pdf',
            true
        );
    }

    /** Berita acara pelaksanaan satu sesi ujian. */
    public function beritaAcara(string $slug = '', $jadwalId = 0)
    {
        $ctx = $this->konteksSesi($slug, (int) $jadwalId);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$slug, $periode, $jadwal] = $ctx;

        $this->kirim(
            UjianCetak::pdf(UjianCetak::beritaAcaraHtml($periode, $jadwal)),
            'Berita-Acara-' . strtoupper($slug) . '-' . $jadwal['id'] . '.pdf',
            'application/pdf',
            true
        );
    }

    // =================================================================
    // Util internal
    // =================================================================

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

    /** Lempar berkas ke browser (pola streaming yang sudah dipakai modul lain). */
    private function kirim(string $isi, string $namaBerkas, string $mime, bool $inline = false): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . UjianCetak::namaAman($namaBerkas) . '"');
        header('Content-Length: ' . strlen($isi));
        header('Cache-Control: max-age=0');
        echo $isi;
        exit;
    }

    private function kirimXlsx(Spreadsheet $ss, string $namaBerkas): void
    {
        $this->kirim(
            UjianCetak::xlsx($ss),
            $namaBerkas . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }
}
