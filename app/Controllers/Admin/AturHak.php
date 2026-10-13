<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\HakPeran;
use App\Libraries\MenuAdmin;
use App\Libraries\PklHak;
use App\Libraries\SuratJenis;
use App\Models\AuditModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Sistem → Hak Akses (KHUSUS ADMIN): Admin mengatur SIAPA BOLEH APA tanpa perlu koding (docs/DESAIN-HAK-AKSES.md).
 *
 * Tiga bagian (?bagian=):
 *   - menu  : tiap peran × tiap menu → Tidak / Lihat (buka & unduh saja) / Kelola (semua aksi); modul PKL & Surat → Boleh.
 *   - pkl   : hak rinci PKL (ACC ajuan, unduh Surat Izin PKL, laporan, tanda tangan, …) + Surat Sekolah per jenis (Buat / ACC /
 *             Unduh) + jenis yang wajib ACC.
 *   - peran : tambah / ubah / hapus peran (mis. Waka Kesiswaan, Kurikulum, TU) dan halaman awalnya.
 * Admin selalu penuh dan tidak bisa diubah. Halaman ini & Kelola Akun tidak bisa diberikan ke peran lain (mencegah peran menaikkan
 * haknya sendiri). Semua perubahan langsung berlaku dan tercatat di Audit Log. Penjaga rute sudah menolak non-Admin; tiap aksi
 * memeriksa lagi (pagar kedua).
 */
class AturHak extends BaseController
{
    private const BAGIAN = ['menu', 'pkl', 'peran'];

    private function peranSaya(): string
    {
        return (string) (session('admin')['role'] ?? '');
    }

    private function konteks(): array
    {
        $a = (array) session('admin');

        return ['oleh' => (string) ($a['full_name'] ?? 'Admin'), 'admin_id' => ((int) ($a['id'] ?? 0)) ?: null, 'peran' => (string) ($a['role'] ?? ''), 'ip' => $this->request->getIPAddress()];
    }

    /** Pagar kedua: hanya yang boleh membuka Hak Akses (= Admin). */
    private function pagar(): ?RedirectResponse
    {
        if (HakAkses::boleh($this->peranSaya(), 'admin/hak-akses', (string) $this->request->getMethod())) {
            return null;
        }

        return redirect()->to(site_url(HakAkses::beranda($this->peranSaya())))->with('error', 'Hak Akses hanya bisa diatur Admin.');
    }

    private function balik(string $bagian, string $jenis, string $pesan, array $tambah = []): RedirectResponse
    {
        $r = redirect()->to(site_url('admin/hak-akses') . '?bagian=' . $bagian)->with($jenis, $pesan);
        foreach ($tambah as $k => $v) {
            $r = $r->with($k, $v);
        }

        return $r;
    }

    // =================================================================
    // Halaman
    // =================================================================

    public function index()
    {
        if (($t = $this->pagar()) !== null) {
            return $t;
        }
        $bagian = (string) $this->request->getGet('bagian');
        if (! in_array($bagian, self::BAGIAN, true)) {
            $bagian = 'menu';
        }
        $semua = HakPeran::semua();
        $atur  = HakPeran::peranAtur();
        $akun  = [];
        foreach (db_connect()->table('admins')->select('role, COUNT(*) n, SUM(aktif) aktif')->groupBy('role')->get()->getResultArray() as $r) {
            $akun[(string) $r['role']] = ['n' => (int) $r['n'], 'aktif' => (int) $r['aktif']];
        }

        // Pilihan halaman awal per peran = item menu samping yang boleh dibuka peran itu.
        $berandaPilihan = [];
        foreach ($atur as $kode) {
            foreach (MenuAdmin::sidebar() as $g) {
                foreach ($g['items'] as $it) {
                    if (empty($it[4]) && HakPeran::boleh($kode, (string) $it[0])) {
                        $berandaPilihan[$kode][(string) $it[0]] = ($g['title'] !== null ? $g['title'] . ' › ' : '') . $it[1];
                    }
                }
            }
        }

        // Peran yang punya hak rinci PKL/Surat tetapi belum diberi modulnya (hak belum berlaku) → diberi catatan di tampilan.
        $tanpaModul = [];
        foreach ($atur as $kode) {
            $tanpaModul[$kode] = [
                'pkl'   => HakPeran::level($kode, (array) MenuAdmin::entri('pkl')) === null,
                'surat' => HakPeran::level($kode, (array) MenuAdmin::entri('surat')) === null,
            ];
        }

        return view('admin/hak_akses/index', [
            'title'          => 'Hak Akses',
            'bagian'         => $bagian,
            'tabelAda'       => HakPeran::tabelAda(),
            'semua'          => $semua,
            'atur'           => $atur,
            'akun'           => $akun,
            'grupMenu'       => MenuAdmin::untukMatriks(),
            'hakPkl'         => PklHak::HAK,
            'aksiSurat'      => PklHak::AKSI_SURAT,
            'matriksPkl'     => PklHak::matriks(),
            'matriksSurat'   => PklHak::matriksSurat(),
            'bawaanPkl'      => PklHak::bawaanLengkap(),
            'accSurat'       => SuratJenis::petaAcc((new PklPengaturanModel())->ambil()),
            'peringatan'     => PklHak::peringatan(),
            'berandaPilihan' => $berandaPilihan,
            'tanpaModul'     => $tanpaModul,
        ]);
    }

    // =================================================================
    // Simpan
    // =================================================================

    /** POST admin/hak-akses/menu — menu[peran][kunci] = '' | lihat | kelola | boleh. */
    public function simpanMenu(): RedirectResponse
    {
        if (($t = $this->pagar()) !== null) {
            return $t;
        }
        if (! HakPeran::tabelAda()) {
            return $this->balik('menu', 'error', 'Tabel peran belum ada. Jalankan "php spark migrate" di server lebih dulu.');
        }
        $kirim = (array) $this->request->getPost('menu');
        $lama  = [];
        $baru  = [];
        foreach (HakPeran::peranAtur() as $kode) {
            $lama[$kode] = (array) (HakPeran::peran($kode)['menu'] ?? []);
            $baru[$kode] = HakPeran::rapikanMenu(array_filter((array) ($kirim[$kode] ?? []), static fn ($v): bool => is_string($v) && $v !== ''));
        }
        if (! HakPeran::simpanMenu($baru)) {
            return $this->balik('menu', 'error', 'Menu gagal disimpan. Coba lagi.');
        }

        $ubahan = [];
        $label  = ['lihat' => 'Lihat', 'kelola' => 'Kelola', 'boleh' => 'Boleh'];
        foreach ($baru as $kode => $menu) {
            foreach (array_unique(array_merge(array_keys($lama[$kode]), array_keys($menu))) as $k) {
                $dulu = $lama[$kode][$k] ?? null;
                $kini = $menu[$k] ?? null;
                if ($dulu !== $kini) {
                    $nama     = (string) (MenuAdmin::entri($k)['label'] ?? $k);
                    $ubahan[] = HakPeran::label($kode) . ': ' . $nama . ' ' . ($dulu !== null ? $label[$dulu] ?? $dulu : 'Tidak') . ' → ' . ($kini !== null ? $label[$kini] ?? $kini : 'Tidak');
                }
            }
        }
        (new AuditModel())->record('update', 'peran', null, mb_substr('Menu per peran diubah oleh ' . $this->konteks()['oleh'] . ': ' . ($ubahan === [] ? '(tanpa perubahan)' : implode('; ', $ubahan)), 0, 255));

        // Peran yang kini tak bisa membuka menu apa pun (hanya Profil) — boleh, tapi diberi tahu.
        $peringatan = [];
        foreach ($baru as $kode => $menu) {
            if ($menu === [] && (int) db_connect()->table('admins')->where('role', $kode)->where('aktif', 1)->countAllResults() > 0) {
                $peringatan[] = HakPeran::label($kode) . ' kini tidak punya menu apa pun (akunnya hanya bisa membuka Profil).';
            }
        }

        return $this->balik('menu', 'success', $ubahan === [] ? 'Tidak ada perubahan menu.' : 'Menu per peran disimpan (' . count($ubahan) . ' perubahan). Berlaku langsung.', $peringatan !== [] ? ['peringatan_hak' => $peringatan] : []);
    }

    /** POST admin/hak-akses/pkl — hak[peran][] = kunci (PKL & surat per jenis), acc[] = jenis yang wajib ACC. */
    public function simpanPkl(): RedirectResponse
    {
        if (($t = $this->pagar()) !== null) {
            return $t;
        }
        $hasil = PklHak::simpan((array) $this->request->getPost('hak'), $this->konteks(), true);
        if (! $hasil['ok']) {
            return $this->balik('pkl', 'error', $hasil['pesan']);
        }
        $acc   = SuratJenis::simpanAcc(array_map('strval', (array) $this->request->getPost('acc')), $this->konteks());
        $pesan = $hasil['pesan'] . ($acc['ubah'] !== [] ? ' Aturan wajib ACC: ' . implode('; ', $acc['ubah']) . '.' : '');
        $peringatan = PklHak::peringatan(); // dihitung SESUDAH aturan wajib ACC tersimpan

        return $this->balik('pkl', 'success', $pesan, $peringatan !== [] ? ['peringatan_hak' => $peringatan] : []);
    }

    /** POST admin/hak-akses/bawaan — bagian=menu|pkl: kembalikan Operator & Waka Hubin ke aturan bawaan sekolah. */
    public function bawaan(): RedirectResponse
    {
        if (($t = $this->pagar()) !== null) {
            return $t;
        }
        $bagian = (string) $this->request->getPost('bagian') === 'menu' ? 'menu' : 'pkl';
        $bw     = HakPeran::bawaan();
        if ($bagian === 'menu') {
            $peta = [];
            foreach (HakPeran::peranAtur() as $kode) {
                if (isset($bw[$kode]) && empty($bw[$kode]['penuh'])) {
                    $peta[$kode] = $bw[$kode]['menu'];
                }
            }
            $ok = HakPeran::simpanMenu($peta);
            (new AuditModel())->record('update', 'peran', null, 'Menu Operator & Waka Hubin dikembalikan ke bawaan oleh ' . $this->konteks()['oleh']);

            return $this->balik('menu', $ok ? 'success' : 'error', $ok ? 'Menu Operator Sekolah & Waka Hubin dikembalikan ke bawaan sekolah. Peran buatan tidak diubah.' : 'Gagal menyimpan.');
        }
        $kirim = [];
        foreach (HakPeran::peranAtur() as $kode) {
            $kirim[$kode] = PklHak::bawaanLengkap()[$kode] ?? HakPeran::hakDaftar($kode); // peran buatan dibiarkan
        }
        $hasil = PklHak::simpan($kirim, $this->konteks(), true);

        return $this->balik('pkl', $hasil['ok'] ? 'success' : 'error', $hasil['ok'] ? 'Hak PKL & Surat Operator Sekolah & Waka Hubin dikembalikan ke bawaan sekolah (Waka Hubin = ACC; Operator = surat & biaya). Peran buatan tidak diubah.' : $hasil['pesan']);
    }

    // =================================================================
    // Peran
    // =================================================================

    public function tambahPeran(): RedirectResponse
    {
        if (($t = $this->pagar()) !== null) {
            return $t;
        }
        $hasil = HakPeran::tambahPeran([
            'label'   => (string) $this->request->getPost('label'),
            'ringkas' => (string) $this->request->getPost('ringkas'),
            'api'     => (string) $this->request->getPost('api') === '1',
            'salin'   => (string) $this->request->getPost('salin'),
        ], $this->konteks());

        return $this->balik($hasil['ok'] ? 'menu' : 'peran', $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    /** @param string $kode */
    public function ubahPeran($kode): RedirectResponse
    {
        if (($t = $this->pagar()) !== null) {
            return $t;
        }
        $hasil = HakPeran::ubahPeran((string) $kode, [
            'label'   => (string) $this->request->getPost('label'),
            'ringkas' => (string) $this->request->getPost('ringkas'),
            'beranda' => (string) $this->request->getPost('beranda'),
            'api'     => (string) $this->request->getPost('api') === '1',
        ], $this->konteks());

        return $this->balik('peran', $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    /** @param string $kode */
    public function hapusPeran($kode): RedirectResponse
    {
        if (($t = $this->pagar()) !== null) {
            return $t;
        }
        $hasil = HakPeran::hapusPeran((string) $kode, $this->konteks());

        return $this->balik('peran', $hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }
}
