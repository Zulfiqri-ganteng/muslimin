<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LogGalat;
use App\Models\AuditModel;

/**
 * Sistem → Log Error Hosting (khusus Admin — MenuAdmin entri 'log_error'; docs/DESAIN-ALAT-SISTEM.md F1): log galat asli server
 * dibaca dari menu Admin, tanpa membuka cPanel. Unduh & bersihkan dicatat di Audit Log.
 */
class LogError extends BaseController
{
    public function index()
    {
        $lib     = new LogGalat();
        $berkas  = $lib->berkas();
        $kunci   = (string) $this->request->getGet('berkas');
        $kunci   = isset($berkas[$kunci]) ? $kunci : (string) LogGalat::terbaru($berkas);
        $tingkat = (string) $this->request->getGet('tingkat');
        $tingkat = isset(LogGalat::TINGKAT[$tingkat]) ? $tingkat : '';
        $q       = trim((string) $this->request->getGet('q'));

        $hitung = [];
        foreach ($berkas as $k => $b) {
            $hitung[$k] = $lib->hitung($b);
        }
        $isi       = $kunci !== '' ? $lib->baca($kunci) : null;
        $tersaring = $isi !== null ? LogGalat::saring($isi['entri'], $tingkat, $q) : [];

        return view('admin/sistem/log_error', [
            'title'     => 'Log Error Hosting',
            'berkas'    => $berkas,
            'hitung'    => $hitung,
            'kunci'     => $kunci,
            'tingkat'   => $tingkat,
            'q'         => $q,
            'kelompok'  => LogGalat::kelompok($tersaring),
            'jumlah'    => count($tersaring),
            'terpotong' => (bool) ($isi['terpotong'] ?? false),
        ]);
    }

    /** GET admin/sistem/log-error/unduh?berkas=<kunci> — berkas log asli apa adanya. */
    public function unduh()
    {
        $kunci = (string) $this->request->getGet('berkas');
        $path  = (new LogGalat())->path($kunci);
        if ($path === null) {
            return redirect()->to(site_url('admin/sistem/log-error'))->with('error', 'Berkas log tidak ditemukan (mungkin sudah dibersihkan).');
        }
        (new AuditModel())->record('update', 'log_error', null, 'Log error diunduh: ' . $kunci);
        $nama = str_starts_with($kunci, 'php-') ? $kunci . '-error_log.txt' : $kunci;

        return $this->response->download($path, null)->setFileName($nama);
    }

    /** POST admin/sistem/log-error/bersihkan — hapus log aplikasi lebih dari LogGalat::SIMPAN_HARI hari. */
    public function bersihkan()
    {
        $n = (new LogGalat())->bersihkan();
        (new AuditModel())->record('delete', 'log_error', null, 'Bersihkan log error lebih dari ' . LogGalat::SIMPAN_HARI . ' hari: ' . $n . ' berkas');

        return redirect()->to(site_url('admin/sistem/log-error'))
            ->with('success', $n > 0 ? $n . ' berkas log lama (lebih dari ' . LogGalat::SIMPAN_HARI . ' hari) dihapus.' : 'Tidak ada log yang lebih tua dari ' . LogGalat::SIMPAN_HARI . ' hari.');
    }
}
