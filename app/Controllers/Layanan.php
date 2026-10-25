<?php

namespace App\Controllers;

use App\Libraries\LayananPublik;
use App\Libraries\LayananSiswa;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * LAYANAN SISWA (publik, tanpa akun) — satu link bagi siswa: pilih layanan → (untuk surat) kenali diri dengan NISN atau tanggal
 * lahir → lihat surat apa yang bisa diminta dan status permintaannya. Aturan: Libraries\LayananSiswa. Rancangan:
 * docs/DESAIN-SURAT-SEKOLAH.md bagian 10.
 *
 * Alamat: GET layanan · GET layanan/masuk?tujuan= · POST layanan/masuk · GET layanan/siswa?kelas_id= (JSON nama) ·
 * GET layanan/saya · POST layanan/keluar. Di subdomain PKL/Biodata alamat ini dialihkan ke domain utama (SubdomainHostFilter).
 */
class Layanan extends BaseController
{
    private array $setting;
    private array $p;
    private LayananSiswa $lib;

    public function __construct()
    {
        $this->setting = (new SettingModel())->get();
        $this->p       = (new PklPengaturanModel())->ambil();
        $this->lib     = new LayananSiswa();
    }

    /** Beranda Layanan Siswa: layanan isian (Biodata, PKL, Lapor) + layanan surat. */
    public function index()
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        $semua = LayananPublik::semua($this->setting);
        $masuk = LayananSiswa::siswaMasuk();

        return view('layanan/index', [
            'setting' => $this->setting,
            'title'   => 'Layanan Siswa',
            'isian'   => array_values(array_filter([$semua['biodata'] ?? null, $semua['pkl'] ?? null, $semua['lapor_pkl'] ?? null])),
            'siswa'   => $masuk !== null ? $this->lib->profil($masuk) : null,
        ]);
    }

    /** Langkah kenali diri (pilih kelas → nama → NISN / tanggal lahir). */
    public function masuk()
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        $tujuan = LayananSiswa::tujuanSah((string) $this->request->getGet('tujuan'));
        if (LayananSiswa::siswaMasuk() !== null) {
            return redirect()->to($this->alamatSaya($tujuan));
        }

        return view('layanan/masuk', [
            'setting' => $this->setting,
            'title'   => 'Masuk Layanan Siswa',
            'kelas'   => $this->lib->kelas($this->p, $tujuan),
            'tujuan'  => $tujuan,
            'pesan'   => (string) (session()->getFlashdata('layanan_galat') ?? ''),
        ]);
    }

    public function prosesMasuk(): RedirectResponse
    {
        $tujuan = LayananSiswa::tujuanSah((string) $this->request->getPost('tujuan'));
        $r      = $this->lib->masuk(
            (int) $this->request->getPost('siswa_id'),
            (string) $this->request->getPost('nisn'),
            (string) $this->request->getPost('tanggal_lahir'),
            $this->request->getIPAddress(),
            $this->p
        );
        if (! $r['ok']) {
            return redirect()->to(site_url('layanan/masuk') . ($tujuan !== '' ? '?tujuan=' . $tujuan : ''))->withInput()->with('layanan_galat', $r['pesan']);
        }

        return redirect()->to($this->alamatSaya($tujuan));
    }

    /** GET layanan/siswa?kelas_id= — nama siswa aktif satu kelas (JSON). */
    public function siswa(): ResponseInterface
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Pilih kelas terlebih dahulu.']);
        }
        $data = $this->lib->siswaKelas($kelasId, $this->p);

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON($data === []
            ? ['ok' => false, 'message' => 'Tidak ada siswa di kelas ini. Pilih kelas lain.']
            : ['ok' => true, 'data' => $data]);
    }

    /** Halaman siswa yang sudah masuk: surat yang bisa diminta + status permintaan. */
    public function saya()
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        $id = LayananSiswa::siswaMasuk();
        $s  = $id !== null ? $this->lib->profil($id) : null;
        if ($s === null) {
            LayananSiswa::keluar();

            return redirect()->to(site_url('layanan/masuk'));
        }
        $semua = LayananPublik::semua($this->setting);

        return view('layanan/saya', [
            'setting'    => $this->setting,
            'title'      => 'Layanan Siswa',
            'siswa'      => $s,
            'kelayakan'  => $this->lib->kelayakan($s, $this->p),
            'permintaan' => $this->lib->permintaan($id),
            'isian'      => array_values(array_filter([$semua['biodata'] ?? null, $semua['pkl'] ?? null, $semua['lapor_pkl'] ?? null])),
            'sukses'     => (string) (session()->getFlashdata('layanan_sukses') ?? ''),
            'galat'      => (string) (session()->getFlashdata('layanan_galat') ?? ''),
        ]);
    }

    /** GET layanan/surat/{kunci} — formulir permintaan surat (T3: izin_ujian, izin_tka). */
    public function surat(string $kunci = '')
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        [$s, $alih] = $this->siswaUntukSurat($kunci);
        if ($alih !== null) {
            return $alih;
        }
        $dasar = [
            'setting' => $this->setting,
            'title'   => LayananSiswa::SURAT[$kunci][0],
            'kunci'   => $kunci,
            'siswa'   => $s,
            'pesan'   => (string) (session()->getFlashdata('layanan_galat') ?? ''),
            'galat'   => (array) (session()->getFlashdata('layanan_galat_kolom') ?? []),
        ];

        return match ($kunci) {
            'pernyataan' => view('layanan/surat_pernyataan', $dasar + ['form' => $this->lib->formPernyataan($s)]),
            'penarikan'  => view('layanan/surat_penarikan', $dasar + ['form' => $this->lib->formPenarikan($s)]),
            'izin_tka'   => view('layanan/surat_tka', $dasar + ['form' => $this->lib->formTka($s, $this->lib->kelayakan($s, $this->p))]),
            default      => view('layanan/surat_izin', $dasar + ['form' => $this->lib->formIzin($s, $kunci, $this->lib->kelayakan($s, $this->p))]),
        };
    }

    /** POST layanan/surat/{kunci} — kirim permintaan. */
    public function ajukan(string $kunci = ''): RedirectResponse
    {
        [$s, $alih] = $this->siswaUntukSurat($kunci);
        if ($alih !== null) {
            return $alih;
        }
        $ip = $this->request->getIPAddress();
        $in = (array) $this->request->getPost();
        $r  = match ($kunci) {
            'pernyataan' => $this->lib->ajukanPernyataan($s['id'], $in, $ip, $this->p),
            'penarikan'  => $this->lib->ajukanPenarikan($s['id'], $in, $ip, $this->p),
            'izin_tka'   => $this->lib->ajukanTka($s['id'], $in, $ip, $this->p),
            default      => $this->lib->ajukanIzin($s['id'], $kunci, (int) ($in['jadwal_id'] ?? 0), $ip, $this->p),
        };
        if (! $r['ok']) {
            return redirect()->to(site_url('layanan/surat/' . $kunci))->withInput()
                ->with('layanan_galat', $r['pesan'])->with('layanan_galat_kolom', $r['galat'] ?? []);
        }

        return redirect()->to(site_url('layanan/saya') . '#status')->with('layanan_sukses', $r['pesan']);
    }

    /**
     * Siswa yang masuk + layanan surat yang formulirnya ada dan boleh dipakai siswa ini; selain itu pengalihan dengan pesan.
     *
     * @return array{0: array<string, mixed>|null, 1: RedirectResponse|null}
     */
    private function siswaUntukSurat(string $kunci): array
    {
        if (! isset(LayananSiswa::SURAT[$kunci]) || ! in_array($kunci, LayananSiswa::SIAP, true)) {
            return [null, redirect()->to(site_url('layanan'))];
        }
        $id = LayananSiswa::siswaMasuk();
        $s  = $id !== null ? $this->lib->profil($id) : null;
        if ($s === null) {
            return [null, redirect()->to(site_url('layanan/masuk') . '?tujuan=' . $kunci)];
        }
        $k = $this->lib->kelayakan($s, $this->p)[$kunci];
        if (! $k['boleh']) {
            return [null, redirect()->to(site_url('layanan/saya') . '#' . $kunci)->with('layanan_galat', LayananSiswa::SURAT[$kunci][0] . ' belum bisa diajukan: ' . $k['alasan'])];
        }

        return [$s, null];
    }

    public function keluar(): RedirectResponse
    {
        LayananSiswa::keluar();

        return redirect()->to(site_url('layanan'))->with('layanan_sukses', 'Kamu sudah keluar. Sampai jumpa!');
    }

    /** Setelah masuk: langsung ke formulir surat tujuan (bila sudah ada), selain itu halaman siswa. */
    private function alamatSaya(string $tujuan): string
    {
        if (in_array($tujuan, LayananSiswa::SIAP, true)) {
            return site_url('layanan/surat/' . $tujuan);
        }

        return site_url('layanan/saya') . ($tujuan !== '' ? '#' . $tujuan : '');
    }
}
