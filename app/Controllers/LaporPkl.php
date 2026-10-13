<?php

namespace App\Controllers;

use App\Libraries\PklLapor;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Form LAPOR DITERIMA PKL — PUBLIK, tanpa login (docs/DESAIN-HAK-AKSES.md bagian 4). Siswa yang ajuan PKL-nya sudah DISETUJUI
 * melapor bahwa perusahaan (DUDI) sudah menerimanya + periode PKL, supaya sekolah membuatkan Surat Balasan.
 *
 * Alamat (di bawah awalan pkl/ supaya juga terbuka di subdomain PKL — SubdomainHostFilter hanya melayani "/" dan "pkl/*"):
 *   GET  pkl/lapor                 halaman form (tutup bila saklar Pengaturan PKL dimatikan)
 *   GET  pkl/lapor/siswa?kelas_id= daftar nama + status lapor (tanpa data perusahaan)
 *   POST pkl/lapor/cek             bukti diri dengan nomor HP → ringkasan ajuan & laporan
 *   POST pkl/lapor/kirim           kirim / perbarui laporan
 *   GET  pkl/lapor/selesai         halaman terima kasih
 * Pengaman sama dengan form pengajuan PKL: bukti nomor HP (dibatasi LoginThrottle), izin kirim berumur 1 jam di sesi, jebakan bot.
 * Aturan & penyimpanan: Libraries\PklLapor.
 */
class LaporPkl extends BaseController
{
    private array $setting;
    private array $p;
    private PklLapor $lib;

    public function __construct()
    {
        $this->setting = (new SettingModel())->get();
        $this->p       = (new PklPengaturanModel())->ambil();
        $this->lib     = new PklLapor();
    }

    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        if (! PklLapor::terbuka($this->p)) {
            return view('pkl/lapor_tutup', ['setting' => $this->setting, 'title' => 'Lapor Diterima PKL']);
        }

        return view('pkl/lapor', [
            'setting' => $this->setting,
            'title'   => 'Lapor Diterima PKL',
            'kelas'   => $this->lib->kelas(),
            'p'       => $this->p,
        ]);
    }

    /** GET pkl/lapor/siswa?kelas_id= */
    public function siswa(): ResponseInterface
    {
        if (! PklLapor::terbuka($this->p)) {
            return $this->json(['ok' => false, 'message' => 'Form lapor diterima sedang ditutup.'], 403);
        }
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId <= 0) {
            return $this->json(['ok' => false, 'message' => 'Pilih kelas terlebih dahulu.'], 422);
        }

        return $this->json(['ok' => true, 'data' => $this->lib->siswaKelas($kelasId)]);
    }

    /** POST pkl/lapor/cek — siswa_id, hp */
    public function cek(): ResponseInterface
    {
        if (! PklLapor::terbuka($this->p)) {
            return $this->json(['ok' => false, 'message' => 'Form lapor diterima sedang ditutup.'], 403);
        }
        $r = $this->lib->cek((int) $this->request->getPost('siswa_id'), trim((string) $this->request->getPost('hp')), $this->request->getIPAddress());

        return $this->json(['ok' => $r['ok'], 'message' => $r['pesan'], 'data' => $r['data'] ?? null], $r['http']);
    }

    /** POST pkl/lapor/kirim — ajuan_id, tanggal_mulai, tanggal_selesai, alamat, catatan (+ jebakan bot "website") */
    public function kirim(): ResponseInterface
    {
        if (! PklLapor::terbuka($this->p)) {
            return $this->json(['ok' => false, 'message' => 'Maaf, form lapor diterima sudah ditutup.'], 403);
        }
        if (trim((string) $this->request->getPost('website')) !== '') {
            return $this->json(['ok' => true, 'redirect' => site_url('pkl/lapor/selesai')]); // bot: pura-pura berhasil
        }
        $r = $this->lib->kirim((array) $this->request->getPost(), $this->request->getIPAddress());
        if (! $r['ok']) {
            return $this->json(['ok' => false, 'message' => $r['pesan'], 'errors' => $r['galat'] ?? []], $r['http']);
        }
        session()->setFlashdata('pkl_lapor_selesai', $r['data']);

        return $this->json(['ok' => true, 'redirect' => site_url('pkl/lapor/selesai')]);
    }

    public function selesai()
    {
        $info = session('pkl_lapor_selesai');
        if (! is_array($info)) {
            return redirect()->to(site_url('pkl/lapor'));
        }

        return view('pkl/lapor_selesai', ['setting' => $this->setting, 'title' => 'Laporan Terkirim', 'info' => $info]);
    }

    private function json(array $isi, int $kode = 200): ResponseInterface
    {
        return $this->response->setStatusCode($kode)->setHeader('Cache-Control', 'no-store')->setJSON($isi);
    }
}
