<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\HonorPengaturan as Aturan;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pengaturan Honor Ujian — komponen & tarif, tunjangan panitia per jabatan, nama tanda tangan.
 * Honor adalah data gaji: hak menunya diatur Admin di Sistem → Hak Akses (bawaan HANYA Admin). Penjaga rute menolak peran tanpa hak menu "Pengaturan Honor", dan setiap aksi di sini
 * memeriksa ulang hak itu sebagai pagar kedua (membuka = Lihat, menyimpan = Kelola).
 * Aturan & validasi: Libraries\HonorPengaturan. Perubahan tercatat di Audit Log.
 */
class HonorPengaturan extends BaseController
{
    private const ALAMAT = 'admin/honor/pengaturan';

    public function index()
    {
        if (($tolak = $this->tolakTanpaHak()) !== null) {
            return $tolak;
        }
        $a = new Aturan();
        // Jabatan ditampilkan menurut urutannya di rekap (yang diatur Admin, selain itu bawaan) supaya jelas siapa di atas siapa.
        $panitia = $a->panitia();
        usort($panitia, static fn (array $x, array $y): int => [$x['urutan'] ?? $x['urutan_bawaan'], $x['nama']] <=> [$y['urutan'] ?? $y['urutan_bawaan'], $y['nama']]);

        return view('admin/honor/pengaturan', [
            'title'      => 'Pengaturan Honor Ujian',
            'komponen'   => $a->komponen(),
            'panitia'    => $panitia,
            'ttd'        => $a->tandaTangan(),
            'jenisLabel' => UjianPeriodeModel::JENIS_LABEL,
            'maksKomp'   => Aturan::MAKS_KOMPONEN,
        ]);
    }

    /** POST admin/honor/pengaturan/komponen — simpan semua komponen. */
    public function simpanKomponen()
    {
        if (($tolak = $this->tolakTanpaHak()) !== null) {
            return $tolak;
        }
        $hasil = (new Aturan())->simpanKomponen((array) $this->request->getPost('k'));

        return $this->balas($hasil, '#komponen', ! $hasil['ok']);
    }

    /** POST admin/honor/pengaturan/komponen/tambah */
    public function tambahKomponen()
    {
        if (($tolak = $this->tolakTanpaHak()) !== null) {
            return $tolak;
        }
        $hasil = (new Aturan())->tambahKomponen([
            'nama'   => $this->request->getPost('nama'),
            'tipe'   => $this->request->getPost('tipe'),
            'tarif'  => $this->request->getPost('tarif'),
            'satuan' => $this->request->getPost('satuan'),
        ]);

        return $this->balas($hasil, '#komponen', ! $hasil['ok']);
    }

    /** POST admin/honor/pengaturan/komponen/(:num)/hapus */
    public function hapusKomponen($id)
    {
        if (($tolak = $this->tolakTanpaHak()) !== null) {
            return $tolak;
        }

        return $this->balas((new Aturan())->hapusKomponen((int) $id), '#komponen');
    }

    /** POST admin/honor/pengaturan/panitia */
    public function simpanPanitia()
    {
        if (($tolak = $this->tolakTanpaHak()) !== null) {
            return $tolak;
        }

        // Nominal dan urutan disimpan sekaligus: salah satu ditolak = tak ada yang tersimpan.
        $a  = new Aturan();
        $db = db_connect();
        $db->transBegin();
        $hasil = $a->simpanUrutan((array) $this->request->getPost('urutan'));
        if ($hasil['ok']) {
            $nom = $a->simpanPanitia((array) $this->request->getPost('nominal'));
            if (! $nom['ok']) {
                $hasil = $nom;
            } elseif (($hasil['jumlah'] ?? 0) > 0) {
                $hasil['pesan'] = $nom['pesan'] === 'Tidak ada perubahan.' ? $hasil['pesan'] : $nom['pesan'] . ' ' . $hasil['pesan'];
            } else {
                $hasil = $nom;
            }
        }
        $hasil['ok'] ? $db->transCommit() : $db->transRollback();

        return $this->balas($hasil, '#panitia', ! $hasil['ok']);
    }

    /** POST admin/honor/pengaturan/tanda-tangan */
    public function simpanTandaTangan()
    {
        if (($tolak = $this->tolakTanpaHak()) !== null) {
            return $tolak;
        }
        $hasil = (new Aturan())->simpanTandaTangan(
            (string) $this->request->getPost('ketua_nama'),
            (string) $this->request->getPost('bendahara_nama')
        );

        return $this->balas($hasil, '#tanda-tangan', ! $hasil['ok']);
    }

    // -----------------------------------------------------------------

    /**
     * Pagar kedua: menu "Pengaturan Honor" (diatur Admin di Sistem → Hak Akses; bawaan hanya Admin). Membuka = level Lihat,
     * menyimpan = level Kelola.
     */
    private function tolakTanpaHak(): ?RedirectResponse
    {
        $peran = (string) (session('admin')['role'] ?? '');
        if (HakAkses::boleh($peran, self::ALAMAT, $this->request->getMethod())) {
            return null;
        }
        [$ke, $pesan] = HakAkses::tolak($peran, self::ALAMAT, 'Pengaturan Honor');

        return redirect()->to(site_url($ke))->with('error', $pesan);
    }

    /** Kembali ke halaman pengaturan dengan pesan; $simpanIsian: bawa isian form agar tidak diketik ulang. */
    private function balas(array $hasil, string $jangkar, bool $simpanIsian = false): RedirectResponse
    {
        $r = redirect()->to(site_url(self::ALAMAT) . $jangkar)->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);

        return $simpanIsian ? $r->withInput() : $r;
    }
}
