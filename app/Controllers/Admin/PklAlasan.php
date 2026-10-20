<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\PklAlasan as Lib;
use App\Libraries\PklHak;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * PKL → TEMPLATE ALASAN (revisi klien 2026-10-20): kalimat alasan siap klik untuk jendela Tolak, Kembalikan, Batalkan persetujuan,
 * Keluarkan siswa, Ditolak perusahaan, dan Kembalikan laporan. Dikelola pemegang hak "Kelola template alasan" (bawaan Waka Hubin;
 * Admin selalu). Alamat: GET admin/pkl/alasan · POST admin/pkl/alasan (tambah) · POST …/{id} (ubah) · POST …/{id}/hapus ·
 * POST …/{id}/naik · POST …/{id}/turun. Aturan & penyimpanan: Libraries\PklAlasan (dipakai juga API).
 */
class PklAlasan extends PklDasar
{
    public function index()
    {
        $peran = $this->peranSaya();
        if (! Lib::tabelAda()) {
            return $this->ke('admin/pkl', 'error', 'Fitur template alasan belum aktif di server ini: jalankan "php spark migrate".');
        }
        if (! Lib::bolehKelola($peran)) {
            return $this->ke('admin/pkl', 'error', 'Akun ' . HakAkses::label($peran) . ' tidak punya hak mengelola template alasan (sekarang: ' . PklHak::teksPemegang('alasan') . ').');
        }
        $semua = (new Lib())->daftar();
        $grup  = array_fill_keys(array_keys(Lib::JENIS), []);
        foreach ($semua as $r) {
            $grup[$r['jenis']][] = $r;
        }

        return view('admin/pkl/alasan', $this->dasar('Template Alasan', 'alasan') + [
            'grup'  => $grup,
            'galat' => (array) (session()->getFlashdata('galat_alasan') ?? []),
            'ubahId' => (int) (session()->getFlashdata('alasan_ubah') ?? 0),
        ]);
    }

    public function tambah(): RedirectResponse
    {
        return $this->hasil((new Lib())->simpan((array) $this->request->getPost(), null, $this->konteks(['saluran' => 'web'])), null);
    }

    /** @param int|string $id */
    public function ubah($id): RedirectResponse
    {
        return $this->hasil((new Lib())->simpan((array) $this->request->getPost(), (int) $id, $this->konteks(['saluran' => 'web'])), (int) $id);
    }

    /** @param int|string $id */
    public function hapus($id): RedirectResponse
    {
        $r = (new Lib())->hapus((int) $id, $this->konteks(['saluran' => 'web']));

        return $this->ke('admin/pkl/alasan', $r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    /** @param int|string $id */
    public function naik($id): RedirectResponse
    {
        return $this->geser($id, 'naik');
    }

    /** @param int|string $id */
    public function turun($id): RedirectResponse
    {
        return $this->geser($id, 'turun');
    }

    /**
     * @param int|string $id
     */
    private function geser($id, string $arah): RedirectResponse
    {
        $lib = new Lib();
        if (! Lib::bolehKelola($this->peranSaya())) {
            return $this->ke('admin/pkl/alasan', 'error', 'Akun ' . HakAkses::label($this->peranSaya()) . ' tidak punya hak mengelola template alasan.');
        }
        $r = $lib->geser((int) $id, $arah === 'naik' ? -1 : 1, $this->konteks(['saluran' => 'web']));

        return redirect()->to(site_url('admin/pkl/alasan') . '#alasan-' . (int) $id)->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    /** @param array{ok: bool, http: int, pesan: string, galat?: array<string, string>, id?: int} $r */
    private function hasil(array $r, ?int $id): RedirectResponse
    {
        if ($r['ok']) {
            return redirect()->to(site_url('admin/pkl/alasan') . '#alasan-' . (int) ($r['id'] ?? 0))->with('success', $r['pesan']);
        }

        return redirect()->to(site_url('admin/pkl/alasan') . ($id !== null ? '#alasan-' . $id : '#tambah'))->withInput()
            ->with('error', $r['pesan'])->with('galat_alasan', $r['galat'] ?? [])->with('alasan_ubah', $id ?? 0);
    }
}
