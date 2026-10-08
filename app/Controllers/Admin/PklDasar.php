<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Dasar controller halaman staf PKL tambahan (biaya, laporan, hak akses): data umum tampilan (tab navigasi, hak
 * peran), konteks pencatat riwayat, dan pembantu respons. Aturan hak diputuskan HakAkses/PklHak — controller turunan
 * tetap memeriksa lagi hak yang dituntut aksinya (pagar kedua setelah filter rute).
 */
abstract class PklDasar extends BaseController
{
    protected PklPengajuanModel $model;
    protected array $p = [];

    public function __construct()
    {
        $this->model = new PklPengajuanModel();
        $this->p     = (new PklPengaturanModel())->ambil();
    }

    protected function peranSaya(): string
    {
        return (string) (session('admin')['role'] ?? '');
    }

    /** Data umum semua halaman PKL (dipakai _nav dan tombol-tombol bersyarat). */
    protected function dasar(string $judul, string $tab): array
    {
        $peran = $this->peranSaya();

        return [
            'title'           => $judul,
            'tab'             => $tab,
            'peran'           => $peran,
            'peranLabel'      => HakAkses::label($peran),
            'p'               => $this->p,
            'bolehPengaturan' => HakAkses::boleh($peran, 'admin/pkl/pengaturan'),
            'bolehHapus'      => HakAkses::boleh($peran, 'admin/pkl/hapus'),
            'bolehAcc'        => HakAkses::bolehAcc($peran),
            'bolehTtd'        => HakAkses::boleh($peran, 'admin/pkl/ttd'),
            'bolehSurat'      => HakAkses::bolehPkl($peran, 'surat'),
            'bolehLaporan'    => HakAkses::bolehPkl($peran, 'laporan'),
            'batasHari'       => PklPengaturanModel::batasHari($this->p),
            'hitungTab'       => $this->model->hitungStatus(),
        ];
    }

    /** Konteks pencatat riwayat: siapa, perannya, dari IP mana. */
    protected function konteks(array $tambah = []): array
    {
        $a = (array) session('admin');

        return $tambah + [
            'oleh'     => (string) ($a['full_name'] ?? 'Staf'),
            'admin_id' => ((int) ($a['id'] ?? 0)) ?: null,
            'peran'    => (string) ($a['role'] ?? ''),
            'ip'       => $this->request->getIPAddress(),
        ];
    }

    protected function ke(string $alamat, string $jenis, string $pesan): RedirectResponse
    {
        return redirect()->to(site_url($alamat))->with($jenis, $pesan);
    }

    /** Balasan JSON tanpa cache. Untuk POST: sertakan token CSRF baru (token diganti tiap permintaan POST). */
    protected function json(array $isi, int $kode = 200, bool $dengancsrf = false): ResponseInterface
    {
        if ($dengancsrf) {
            $isi['csrf'] = csrf_hash();
        }

        return $this->response->setStatusCode($kode)->setHeader('Cache-Control', 'no-store')->setJSON($isi);
    }

    /** Tolak bila peran saya tak memegang hak $hak (pagar kedua; filter rute sudah menjaga lebih dulu). */
    protected function wajibHak(string $hak): ?ResponseInterface
    {
        if (HakAkses::bolehPkl($this->peranSaya(), $hak)) {
            return null;
        }

        return $this->json(['ok' => false, 'message' => 'Akun ' . HakAkses::label($this->peranSaya()) . ' tidak punya hak untuk aksi ini. Admin bisa mengaturnya di PKL → Hak Akses.'], 403);
    }
}
