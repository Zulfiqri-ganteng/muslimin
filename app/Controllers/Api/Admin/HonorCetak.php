<?php

namespace App\Controllers\Api\Admin;

use App\Libraries\HonorCetak as Cetak;
use App\Libraries\HonorDokumen;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Unduhan rekap & slip Honor Ujian untuk aplikasi Android — KHUSUS ADMIN.
 * Berkas dirakit Libraries\HonorCetak (sumber SAMA dengan unduhan web), jadi PDF/Excel dari HP identik dengan dari browser.
 * Seperti Api\Admin\UjianCetak: respons BERKAS BINER (bukan JSON beramplop); galat tetap JSON beramplop — klien cukup
 * memeriksa Content-Type. `?unduh=1` → header attachment (bawaan inline). Tiap unduhan dicatat di Audit Log.
 *
 *   GET /api/v1/admin/ujian/{slug}/honor/cetak               daftar berkas yang tersedia (JSON)
 *   GET /api/v1/admin/ujian/{slug}/honor/cetak/rekap-pdf     Rekap (F4 landscape)
 *   GET /api/v1/admin/ujian/{slug}/honor/cetak/rekap-xlsx    Excel Rekap + Slip (format rekap sekolah)
 *   GET /api/v1/admin/ujian/{slug}/honor/cetak/slip-pdf      Slip semua penerima; ?baris=ID untuk satu orang
 * Semua menerima ?tp=YYYY/YYYY.
 */
class HonorCetak extends HonorBase
{
    private const MIME_PDF  = 'application/pdf';
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function index(string $slug = ''): ResponseInterface
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $tp    = '?tp=' . rawurlencode((string) $this->periode['tahun_ajaran']);
        $basis = 'admin/ujian/' . UjianPeriodeModel::keSlug((string) $this->periode['jenis']) . '/honor/cetak/';
        $dok   = $this->dokumenAda();
        $m     = $dok !== null ? (new HonorDokumen())->muat((int) $dok['id']) : null;

        return $this->ok([
            'tersedia' => $m !== null && $m['baris'] !== [],
            'status'   => $dok['status'] ?? null,
            'berkas'   => [
                ['kunci' => 'rekap_pdf', 'label' => 'Rekap honor (PDF)', 'mime' => self::MIME_PDF, 'path' => $basis . 'rekap-pdf' . $tp, 'per_penerima' => false],
                ['kunci' => 'rekap_xlsx', 'label' => 'Excel: Rekap + Slip', 'mime' => self::MIME_XLSX, 'path' => $basis . 'rekap-xlsx' . $tp, 'per_penerima' => false],
                ['kunci' => 'slip_pdf', 'label' => 'Slip semua penerima (PDF)', 'mime' => self::MIME_PDF, 'path' => $basis . 'slip-pdf' . $tp, 'per_penerima' => false],
                ['kunci' => 'slip_pdf_satu', 'label' => 'Slip satu penerima (PDF)', 'mime' => self::MIME_PDF, 'path' => $basis . 'slip-pdf' . $tp . '&baris={baris_id}', 'per_penerima' => true],
            ],
            'catatan'  => $dok !== null && $dok['status'] === 'draf' ? 'Honor masih Draf: cetakan bertanda "DRAF". Tandai Final dulu bila siap dibayar.' : null,
        ], 'Daftar berkas cetak honor.');
    }

    public function rekapPdf(string $slug = ''): ResponseInterface
    {
        if (($m = $this->bahan($slug)) instanceof ResponseInterface) {
            return $m;
        }
        $isi = Cetak::pdf(Cetak::rekapHtml($m, $this->periode), 'f4-landscape');
        $this->catat($m, 'PDF rekap (mobile)');

        return $this->berkas($isi, Cetak::namaBerkas($this->periode, 'Rekap', 'pdf'), self::MIME_PDF);
    }

    public function rekapXlsx(string $slug = ''): ResponseInterface
    {
        if (($m = $this->bahan($slug)) instanceof ResponseInterface) {
            return $m;
        }
        $isi = Cetak::xlsx(Cetak::spreadsheet($m, $this->periode));
        $this->catat($m, 'Excel Rekap + Slip (mobile)');

        return $this->berkas($isi, Cetak::namaBerkas($this->periode, 'Rekap-dan-Slip', 'xlsx'), self::MIME_XLSX);
    }

    public function slipPdf(string $slug = ''): ResponseInterface
    {
        if (($m = $this->bahan($slug)) instanceof ResponseInterface) {
            return $m;
        }
        $barisId = $this->request->getGet('baris') !== null ? (int) $this->request->getGet('baris') : null;
        $html    = Cetak::slipHtml($m, $this->periode, $barisId);
        if ($html === null) {
            return $this->missing('Penerima tidak ditemukan pada honor ini.');
        }
        $isi = Cetak::pdf($html, 'a4-portrait');
        $this->catat($m, ($barisId === null ? 'PDF slip semua (mobile)' : 'PDF slip satu penerima (mobile)'));

        return $this->berkas($isi, Cetak::namaBerkas($this->periode, $barisId === null ? 'Slip' : 'Slip-' . $barisId, 'pdf'), self::MIME_PDF);
    }

    // -----------------------------------------------------------------

    /** @return array<string,mixed>|ResponseInterface honor lengkap siap cetak, atau respons galat */
    private function bahan(string $slug)
    {
        if (($e = $this->siap($slug)) !== null) {
            return $e;
        }
        $dok = $this->dokumenAda();
        $m   = $dok !== null ? (new HonorDokumen())->muat((int) $dok['id']) : null;
        if ($m === null) {
            return $this->belumAda();
        }
        if ($m['baris'] === []) {
            return $this->failure('Belum ada penerima, belum ada yang bisa dicetak.', 422);
        }

        return $m;
    }

    private function catat(array $m, string $jenis): void
    {
        (new AuditModel())->record('export', 'honor_dokumen', (int) $m['dokumen']['id'], 'Unduh honor ' . (new UjianPeriodeModel())->label($this->periode) . ' — ' . $jenis . ' (' . count($m['baris']) . ' penerima)');
    }

    private function berkas(string $isi, string $nama, string $mime): ResponseInterface
    {
        $unduh = in_array((string) $this->request->getGet('unduh'), ['1', 'true', 'ya'], true);

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', ($unduh ? 'attachment' : 'inline') . '; filename="' . $nama . '"')
            ->setHeader('Content-Length', (string) strlen($isi))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setBody($isi);
    }
}
