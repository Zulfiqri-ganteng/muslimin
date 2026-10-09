<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HonorPengaturan as Aturan;
use App\Models\UjianPeriodeModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pengaturan Honor Ujian — komponen & tarif, tunjangan panitia per jabatan, nama tanda tangan.
 * KHUSUS ADMIN: honor adalah data gaji. Operator & Waka Hubin ditolak filter rute (tidak ada di daftar hak mereka),
 * dan setiap aksi di sini memeriksa ulang peran Admin sebagai pagar kedua.
 * Aturan & validasi: Libraries\HonorPengaturan. Perubahan tercatat di Audit Log.
 */
class HonorPengaturan extends BaseController
{
    private const ALAMAT = 'admin/honor/pengaturan';

    public function index()
    {
        if (($tolak = $this->tolakBukanAdmin()) !== null) {
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
        if (($tolak = $this->tolakBukanAdmin()) !== null) {
            return $tolak;
        }
        $hasil = (new Aturan())->simpanKomponen((array) $this->request->getPost('k'));

        return $this->balas($hasil, '#komponen', ! $hasil['ok']);
    }

    /** POST admin/honor/pengaturan/komponen/tambah */
    public function tambahKomponen()
    {
        if (($tolak = $this->tolakBukanAdmin()) !== null) {
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
        if (($tolak = $this->tolakBukanAdmin()) !== null) {
            return $tolak;
        }

        return $this->balas((new Aturan())->hapusKomponen((int) $id), '#komponen');
    }

    /** POST admin/honor/pengaturan/panitia */
    public function simpanPanitia()
    {
        if (($tolak = $this->tolakBukanAdmin()) !== null) {
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
        if (($tolak = $this->tolakBukanAdmin()) !== null) {
            return $tolak;
        }
        $hasil = (new Aturan())->simpanTandaTangan(
            (string) $this->request->getPost('ketua_nama'),
            (string) $this->request->getPost('bendahara_nama')
        );

        return $this->balas($hasil, '#tanda-tangan', ! $hasil['ok']);
    }

    // -----------------------------------------------------------------

    private function tolakBukanAdmin(): ?RedirectResponse
    {
        if ((string) (session('admin')['role'] ?? '') !== 'admin') {
            return redirect()->to(site_url('admin/dashboard'))->with('error', 'Pengaturan Honor hanya bisa dibuka Admin.');
        }

        return null;
    }

    /** Kembali ke halaman pengaturan dengan pesan; $simpanIsian: bawa isian form agar tidak diketik ulang. */
    private function balas(array $hasil, string $jangkar, bool $simpanIsian = false): RedirectResponse
    {
        $r = redirect()->to(site_url(self::ALAMAT) . $jangkar)->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);

        return $simpanIsian ? $r->withInput() : $r;
    }
}
