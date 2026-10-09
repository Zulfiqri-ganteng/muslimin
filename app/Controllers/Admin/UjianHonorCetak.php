<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HonorCetak;
use App\Libraries\HonorDokumen;
use App\Models\AuditModel;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Cetak / unduh Honor Ujian: rekap + slip Excel, rekap PDF, slip PDF (semua atau satu orang).
 * KHUSUS ADMIN (data gaji): rute tidak ada di hak Operator/Waka Hubin dan tiap aksi memeriksa ulang peran Admin.
 * Tiap unduhan dicatat di Audit Log. Angka berasal dari Libraries\HonorDokumen/HonorCetak (sama dengan layar).
 */
class UjianHonorCetak extends BaseController
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private ?array $periode = null;
    private ?array $honor   = null;

    /** GET ujian/(:segment)/honor/cetak/rekap-xlsx */
    public function rekapXlsx(string $slug = '')
    {
        if (($tolak = $this->siapkan($slug)) !== null) {
            return $tolak;
        }
        $isi = HonorCetak::xlsx(HonorCetak::spreadsheet($this->honor, $this->periode));
        $this->catat('Excel (REKAP + SLIP)');

        return $this->kirim($isi, HonorCetak::namaBerkas($this->periode, 'Rekap-dan-Slip', 'xlsx'), self::XLSX, false);
    }

    /** GET ujian/(:segment)/honor/cetak/rekap-pdf */
    public function rekapPdf(string $slug = '')
    {
        if (($tolak = $this->siapkan($slug)) !== null) {
            return $tolak;
        }
        $isi = HonorCetak::pdf(HonorCetak::rekapHtml($this->honor, $this->periode), 'f4-landscape');
        $this->catat('PDF rekap');

        return $this->kirim($isi, HonorCetak::namaBerkas($this->periode, 'Rekap', 'pdf'), 'application/pdf', true);
    }

    /** GET ujian/(:segment)/honor/cetak/slip-pdf[?baris=ID] — semua penerima, atau satu orang. */
    public function slipPdf(string $slug = '')
    {
        if (($tolak = $this->siapkan($slug)) !== null) {
            return $tolak;
        }
        $barisId = $this->request->getGet('baris') !== null ? (int) $this->request->getGet('baris') : null;
        $html    = HonorCetak::slipHtml($this->honor, $this->periode, $barisId);
        if ($html === null) {
            return redirect()->to($this->urlHonor())->with('error', 'Penerima tidak ditemukan pada honor ini.');
        }
        $isi = HonorCetak::pdf($html, 'a4-portrait');
        $this->catat($barisId === null ? 'PDF slip (semua penerima)' : 'PDF slip (satu penerima)');

        return $this->kirim($isi, HonorCetak::namaBerkas($this->periode, $barisId === null ? 'Slip' : 'Slip-' . $barisId, 'pdf'), 'application/pdf', true);
    }

    // -----------------------------------------------------------------

    /** Pagar: Admin, jenis & periode (?tp=) dikenal, honor sudah dibuat dan punya penerima. */
    private function siapkan(string $slug): RedirectResponse|ResponseInterface|null
    {
        if ((string) (session('admin')['role'] ?? '') !== 'admin') {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Honor hanya bisa dicetak Admin.');
        }
        $jenis = UjianPeriodeModel::dariSlug($slug);
        $p     = $jenis !== null ? (new UjianPeriodeModel())->untukTahun($jenis, $this->request->getGet('tp')) : null;
        if ($p === null) {
            return redirect()->to(site_url('admin/ujian/asts1'))->with('error', 'Periode ujian tidak ditemukan.');
        }
        $this->periode = $p;
        $lib = new HonorDokumen();
        $dok = $lib->dokumenPeriode((int) $p['id']);
        $m   = $dok !== null ? $lib->muat((int) $dok['id']) : null;
        if ($m === null || $m['baris'] === []) {
            return redirect()->to($this->urlHonor())->with('error', 'Belum ada honor yang bisa dicetak. Buat honor dan tambahkan penerima dulu.');
        }
        $this->honor = $m;

        return null;
    }

    private function urlHonor(): string
    {
        return site_url('admin/ujian/' . UjianPeriodeModel::keSlug((string) $this->periode['jenis']) . '/honor') . '?tp=' . rawurlencode((string) $this->periode['tahun_ajaran']);
    }

    private function catat(string $jenis): void
    {
        (new AuditModel())->record('export', 'honor_dokumen', (int) $this->honor['dokumen']['id'], 'Unduh honor ' . (new UjianPeriodeModel())->label($this->periode) . ' — ' . $jenis . ' (' . count($this->honor['baris']) . ' penerima)');
    }

    private function kirim(string $isi, string $nama, string $mime, bool $inline): ResponseInterface
    {
        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $nama . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($isi);
    }
}
