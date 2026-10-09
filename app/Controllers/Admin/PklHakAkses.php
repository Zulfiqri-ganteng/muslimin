<?php

namespace App\Controllers\Admin;

use App\Libraries\PklHak;
use App\Libraries\SuratJenis;

/**
 * PKL → Hak Akses: Admin menentukan siapa (Waka Hubin / Operator) boleh melakukan apa di modul PKL.
 * KHUSUS ADMIN — dijaga filter rute (HakAkses: alamat 'admin/pkl/hak-akses' = khusus_admin) dan diperiksa lagi di sini,
 * karena kalau Operator/Hubin bisa mengubahnya mereka dapat memberi diri sendiri hak ACC atau unduh surat.
 * Aturan dan hak bawaan: Libraries\PklHak. Perubahan berlaku langsung dan tercatat di Audit Log.
 */
class PklHakAkses extends PklDasar
{
    public function index()
    {
        if ($this->peranSaya() !== 'admin') {
            return $this->ke('admin/pkl', 'error', 'Hak Akses PKL hanya bisa diatur Admin.');
        }

        return view('admin/pkl/hak_akses', $this->dasar('Hak Akses PKL', 'hak_akses') + [
            'matriks'    => PklHak::matriks(),
            'hakDaftar'  => PklHak::HAK,
            'peringatan' => PklHak::peringatan(),
            'bawaan'     => PklHak::BAWAAN,
            'accSurat'   => SuratJenis::petaAcc($this->p),
        ]);
    }

    /** POST admin/pkl/hak-akses/surat-acc — jenis Surat Sekolah mana yang wajib ACC (khusus Admin). */
    public function simpanAccSurat()
    {
        if ($this->peranSaya() !== 'admin') {
            return $this->ke('admin/pkl', 'error', 'Hak Akses PKL hanya bisa diatur Admin.');
        }
        $hasil = SuratJenis::simpanAcc((array) $this->request->getPost('acc'), $this->konteks());

        return $this->ke('admin/pkl/hak-akses', 'success', $hasil['pesan']);
    }

    public function simpan()
    {
        if ($this->peranSaya() !== 'admin') {
            return $this->ke('admin/pkl', 'error', 'Hak Akses PKL hanya bisa diatur Admin.');
        }
        $hasil = PklHak::simpan((array) $this->request->getPost('hak'), $this->konteks());
        if (! $hasil['ok']) {
            return $this->ke('admin/pkl/hak-akses', 'error', $hasil['pesan']);
        }
        $r = $this->ke('admin/pkl/hak-akses', 'success', $hasil['pesan']);

        return $hasil['peringatan'] !== [] ? $r->with('peringatan_hak', $hasil['peringatan']) : $r;
    }

    /** POST admin/pkl/hak-akses/bawaan — kembalikan ke hak bawaan sekolah. */
    public function bawaan()
    {
        if ($this->peranSaya() !== 'admin') {
            return $this->ke('admin/pkl', 'error', 'Hak Akses PKL hanya bisa diatur Admin.');
        }
        $kirim = [];
        foreach (PklHak::BAWAAN as $peran => $daftar) {
            $kirim[$peran] = $daftar;
        }
        $hasil = PklHak::simpan($kirim, $this->konteks());

        return $this->ke('admin/pkl/hak-akses', $hasil['ok'] ? 'success' : 'error', $hasil['ok'] ? 'Hak dikembalikan ke bawaan sekolah.' : $hasil['pesan']);
    }
}
