<?php

namespace App\Filters;

use App\Libraries\HakAkses;
use App\Models\AdminModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Penjaga seluruh area /admin. Urutan pemeriksaan:
 *
 *   1. sudah login?                         → bila tidak, ke halaman login
 *   2. sesi tidak menganggur > 1 jam?       → bila lewat, sesi dihapus
 *   3. akun MASIH ada & aktif? (dibaca ulang dari database tiap permintaan, jadi
 *      akun yang dinonaktifkan atau diubah perannya langsung berlaku, tanpa
 *      menunggu sesi habis)
 *   4. sandi sementara sudah diganti?       → bila belum, hanya Profil yang terbuka
 *   5. peran boleh membuka alamat ini?      → Config\Peran via Libraries\HakAkses
 *
 * Penolakan di langkah 4–5 TIDAK menjalankan apa pun (POST pun tak diproses).
 */
class AuthFilter implements FilterInterface
{
    /** Batas tidak ada aktivitas sebelum sesi dianggap kadaluarsa (detik). */
    private const IDLE_TIMEOUT = 3600; // 1 jam

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('isLoggedIn')) {
            $session->setFlashdata('error', 'Silakan login terlebih dahulu.');
            return redirect()->to(site_url('admin/login'));
        }

        // Auto-logout bila 1 jam tanpa aktivitas.
        $last = (int) $session->get('lastActivity');
        if ($last > 0 && (time() - $last) > self::IDLE_TIMEOUT) {
            return $this->keluarkan('Sesi berakhir karena tidak ada aktivitas selama 1 jam. Silakan login kembali.');
        }

        // Baca ulang akun dari database (satu kueri berindeks per permintaan).
        $admin = (new AdminModel())->find((int) (((array) $session->get('admin'))['id'] ?? 0));
        if (! $admin || (int) ($admin['aktif'] ?? 1) !== 1) {
            return $this->keluarkan('Akun Anda sudah tidak aktif. Hubungi admin sekolah.');
        }
        unset($admin['password']);
        $session->set('admin', $admin); // nama/peran/foto selalu segar di menu & halaman

        // Perbarui stempel aktivitas.
        $session->set('lastActivity', time());

        $alamat = uri_string();
        $peran  = (string) ($admin['role'] ?? '');

        // Sandi sementara dari admin: wajib diganti dulu, hanya Profil yang terbuka.
        if ((int) ($admin['wajib_ganti_sandi'] ?? 0) === 1 && ! HakAkses::cocokAwalan($alamat, 'admin/profile')) {
            return $this->tolak(
                $request,
                'Demi keamanan, ganti kata sandi sementara Anda lebih dulu (bagian "Ganti Password").',
                'admin/profile'
            );
        }

        if (! HakAkses::boleh($peran, $alamat)) {
            $beranda = HakAkses::beranda($peran);

            // Jaga dari putaran tanpa ujung bila beranda peran sendiri tak boleh dibuka.
            if (HakAkses::cocokAwalan($alamat, $beranda) || ! HakAkses::boleh($peran, $beranda)) {
                return service('response')->setStatusCode(403)
                    ->setBody('Akses ditolak. Peran akun Anda tidak diizinkan membuka halaman ini.');
            }

            return $this->tolak($request, 'Halaman itu tidak tersedia untuk peran ' . HakAkses::label($peran) . '.', $beranda);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // tidak ada aksi
    }

    /**
     * Akhiri sesi login lalu kirim ke halaman login DENGAN pesan.
     * session()->destroy() membuang pesan flash (sesinya sudah tak ada), jadi
     * data login dicopot dan id sesi diganti baru (id lama dibuang → tak bisa
     * dipakai ulang), baru pesan disimpan.
     */
    private function keluarkan(string $pesan)
    {
        $session = session();
        $session->remove(['isLoggedIn', 'admin', 'lastActivity', 'loginAt']);
        $session->regenerate(true);
        $session->setFlashdata('error', $pesan);

        return redirect()->to(site_url('admin/login'));
    }

    /** Permintaan AJAX/JSON dijawab 403; selebihnya dialihkan dengan pesan. */
    private function tolak(RequestInterface $request, string $pesan, string $tujuan)
    {
        $json = $request instanceof IncomingRequest
            && ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json'));

        if ($json) {
            return service('response')->setStatusCode(403)
                ->setJSON(['status' => 'error', 'ok' => false, 'message' => $pesan, 'data' => null]);
        }

        return redirect()->to(site_url($tujuan))->with('error', $pesan);
    }
}
