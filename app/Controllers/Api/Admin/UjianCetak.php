<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\UjianCetak as Cetak;
use App\Models\UjianJadwalModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Unduhan berkas cetak modul Ujian untuk aplikasi Android.
 *
 * Berkasnya dirakit `App\Libraries\UjianCetak` — sumber yang SAMA dengan
 * cetakan web (`Admin\LaporanUjian` / `Admin\UjianBerkas`), jadi berkas yang
 * diunduh dari HP identik dengan yang diunduh dari browser.
 *
 * Berbeda dengan endpoint lain di modul ini, respons di sini **bukan JSON
 * beramplop** melainkan berkas biner — pola yang sudah dipakai
 * `Api\Admin\Dokumen::berkas/unduh`. Galat tetap dikembalikan sebagai JSON
 * beramplop biasa, jadi klien cukup memeriksa Content-Type: bila
 * `application/json` berarti gagal.
 *
 * Rute (semua GET, butuh Bearer token):
 *   /admin/ujian/{slug}/cetak/rekap-pdf
 *   /admin/ujian/{slug}/cetak/rekap-excel
 *   /admin/ujian/{slug}/cetak/jadwal-excel
 *   /admin/ujian/{slug}/cetak/daftar-hadir/{jadwalId}
 *   /admin/ujian/{slug}/cetak/berita-acara/{jadwalId}
 *
 * Semua menerima `?tp=YYYY/YYYY`. Tambahkan `?unduh=1` bila ingin header
 * `attachment` (bawaannya `inline`, supaya PDF bisa langsung dipratinjau).
 */
class UjianCetak extends BaseApiController
{
    private const MIME_PDF  = 'application/pdf';
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    protected UjianPeriodeModel $model;

    public function __construct()
    {
        $this->model = new UjianPeriodeModel();
    }

    /** Daftar berkas yang tersedia — memudahkan klien menyusun menu cetak. */
    public function index(string $slug = ''): ResponseInterface
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }
        $slug  = UjianPeriodeModel::keSlug($periode['jenis']);
        $tp    = '?tp=' . rawurlencode($periode['tahun_ajaran']);
        $basis = 'admin/ujian/' . $slug . '/cetak/';

        return $this->ok([
            'periode' => ['id' => (int) $periode['id'], 'label' => $this->model->label($periode)],
            'berkas'  => [
                ['kunci' => 'rekap_pdf', 'label' => 'Rekap (PDF)', 'mime' => self::MIME_PDF, 'path' => $basis . 'rekap-pdf' . $tp, 'per_sesi' => false],
                ['kunci' => 'rekap_excel', 'label' => 'Rekap (Excel)', 'mime' => self::MIME_XLSX, 'path' => $basis . 'rekap-excel' . $tp, 'per_sesi' => false],
                ['kunci' => 'jadwal_excel', 'label' => 'Jadwal Ujian (Excel)', 'mime' => self::MIME_XLSX, 'path' => $basis . 'jadwal-excel' . $tp, 'per_sesi' => false],
                ['kunci' => 'daftar_hadir', 'label' => 'Daftar Hadir (PDF)', 'mime' => self::MIME_PDF, 'path' => $basis . 'daftar-hadir/{jadwal_id}' . $tp, 'per_sesi' => true],
                ['kunci' => 'berita_acara', 'label' => 'Berita Acara (PDF)', 'mime' => self::MIME_PDF, 'path' => $basis . 'berita-acara/{jadwal_id}' . $tp, 'per_sesi' => true],
            ],
        ], 'Daftar berkas cetak.');
    }

    // ================= Rekap periode =================

    public function rekapPdf(string $slug = '')
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        return $this->berkas(
            Cetak::pdf(Cetak::rekapHtml($periode)),
            'Rekap-Ujian-' . strtoupper($periode['jenis']) . '-' . $this->tahunBerkas($periode) . '.pdf',
            self::MIME_PDF
        );
    }

    public function rekapExcel(string $slug = '')
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        return $this->berkas(
            Cetak::xlsx(Cetak::rekapSpreadsheet($periode)),
            'Rekap-Ujian-' . strtoupper($periode['jenis']) . '-' . $this->tahunBerkas($periode) . '.xlsx',
            self::MIME_XLSX
        );
    }

    /** Ekspor jadwal satu periode (ber-kop, sama dengan tombol Export di web). */
    public function jadwalExcel(string $slug = '')
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $ss = Cetak::jadwalSpreadsheet($periode);
        kop_excel_prepend($ss->getActiveSheet(), 'I');

        return $this->berkas(
            Cetak::xlsx($ss),
            'Jadwal-Ujian-' . strtoupper($periode['jenis']) . '-' . $this->tahunBerkas($periode) . '.xlsx',
            self::MIME_XLSX
        );
    }

    // ================= Cetakan per sesi =================

    public function daftarHadir(string $slug = '', $jadwalId = null)
    {
        $ctx = $this->sesi($slug, (int) $jadwalId);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$periode, $jadwal] = $ctx;

        return $this->berkas(
            Cetak::pdf(Cetak::daftarHadirHtml($periode, $jadwal)),
            'Daftar-Hadir-' . strtoupper($periode['jenis']) . '-' . (int) $jadwal['id'] . '.pdf',
            self::MIME_PDF
        );
    }

    public function beritaAcara(string $slug = '', $jadwalId = null)
    {
        $ctx = $this->sesi($slug, (int) $jadwalId);
        if (! is_array($ctx)) {
            return $ctx;
        }
        [$periode, $jadwal] = $ctx;

        return $this->berkas(
            Cetak::pdf(Cetak::beritaAcaraHtml($periode, $jadwal)),
            'Berita-Acara-' . strtoupper($periode['jenis']) . '-' . (int) $jadwal['id'] . '.pdf',
            self::MIME_PDF
        );
    }

    // ================= Util =================

    /**
     * Balas berkas biner.
     *
     * Bawaannya `inline` supaya PDF bisa langsung dipratinjau di aplikasi;
     * kirim `?unduh=1` untuk memaksa `attachment`.
     */
    private function berkas(string $isi, string $nama, string $mime): ResponseInterface
    {
        $unduh    = in_array((string) $this->request->getGet('unduh'), ['1', 'true', 'ya'], true);
        $disposisi = ($unduh ? 'attachment' : 'inline') . '; filename="' . Cetak::namaAman($nama) . '"';

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', $disposisi)
            ->setHeader('Content-Length', (string) strlen($isi))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setBody($isi);
    }

    /** Tahun pelajaran yang aman dipakai di nama berkas (2026/2027 → 2026-2027). */
    private function tahunBerkas(array $periode): string
    {
        return str_replace('/', '-', (string) $periode['tahun_ajaran']);
    }

    /** @return array|ResponseInterface */
    private function periode(string $slug)
    {
        $jenis = UjianPeriodeModel::dariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis ujian tidak dikenal.');
        }

        $periode = $this->model->untukTahun($jenis, $this->request->getGet('tp'));

        return $periode ?? $this->missing('Periode ujian tidak ditemukan.');
    }

    /**
     * Periode + satu sesi yang WAJIB milik periode itu.
     *
     * @return array{0:array,1:array}|ResponseInterface
     */
    private function sesi(string $slug, int $jadwalId)
    {
        $periode = $this->periode($slug);
        if (! is_array($periode)) {
            return $periode;
        }

        $jadwal = $jadwalId > 0
            ? (new UjianJadwalModel())->withRelations()->where('ujian_jadwal.id', $jadwalId)->first()
            : null;

        if (! $jadwal || (int) $jadwal['periode_id'] !== (int) $periode['id']) {
            return $this->missing('Sesi ujian tidak ditemukan.');
        }

        return [$periode, $jadwal];
    }
}
