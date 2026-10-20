<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\PklHak;
use App\Libraries\PklLapor;
use App\Libraries\SuratJenis;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Kotak "Lapor Diterima PKL" — laporan siswa (sudah diterima perusahaan + periode PKL) yang diproses staf menjadi Surat Balasan
 * (docs/DESAIN-HAK-AKSES.md bagian 4). Terbuka bagi pemegang hak Buat ATAU ACC Surat Balasan (diatur Admin di Sistem → Hak Akses).
 *
 * Alamat: GET admin/surat/balasan/laporan?status=baru|dikembalikan|diproses · POST …/{id}/setujui ("Setujui & buat": surat langsung
 * disetujui atas nama pemroses) · POST …/{id}/buat (surat masuk antrean ACC) · POST …/{id}/kembalikan (alasan wajib).
 * Aturan & penyimpanan: Libraries\PklLapor (dipakai juga oleh API).
 */
class SuratLapor extends SuratDasar
{
    public function index()
    {
        $peran = $this->peranSaya();
        $hak   = PklLapor::hak($peran, $this->p);
        if (! $hak['lihat']) {
            return $this->ke('admin/surat', 'error', 'Akun ' . HakAkses::label($peran) . ' tidak punya hak memproses laporan Surat Balasan. Admin bisa mengaturnya di Sistem → Hak Akses.');
        }
        if (! PklLapor::tabelAda()) {
            return $this->ke('admin/surat', 'error', 'Fitur lapor diterima belum aktif di server ini: jalankan "php spark migrate".');
        }
        $status = (string) $this->request->getGet('status');
        if (! in_array($status, PklLapor::STATUS, true)) {
            $status = 'baru';
        }
        $lib = new PklLapor();

        return view('admin/surat/lapor', $this->dasarSurat('Lapor Diterima PKL', 'daftar') + [
            'status'       => $status,
            'rows'         => $lib->daftar($status, 200, $this->p),
            'hitungLapor'  => $lib->hitung(),
            'hak'          => $hak,
            'bolehForm'    => PklHak::bolehSurat($peran, 'buat', SuratJenis::BALASAN),
            'tautanLapor'  => config('Pkl')->tautanLapor(),
            'laporBuka'    => PklLapor::terbuka($this->p),
            'bolehAtur'    => HakAkses::boleh($peran, 'admin/pkl/pengaturan'),
            'pemegangAcc'  => PklHak::teksPemegang(PklHak::kunciSurat('acc', SuratJenis::BALASAN)),
            // Template alasan "laporan dikembalikan" (revisi 2026-10-20) + batas huruf alasan.
            'alasanLapor'  => (new \App\Libraries\PklAlasan())->untukDialog('lapor'),
            'kelolaAlasan' => \App\Libraries\PklAlasan::bolehKelola($peran),
            'maksAlasan'   => \App\Libraries\PklAjuan::maksCatatan(),
        ]);
    }

    /** @param int|string $id */
    public function setujui($id): RedirectResponse
    {
        return $this->proses((int) $id, true);
    }

    /** @param int|string $id */
    public function buat($id): RedirectResponse
    {
        return $this->proses((int) $id, false);
    }

    /** @param int|string $id */
    public function kembalikan($id): RedirectResponse
    {
        $r = (new PklLapor())->kembalikan((int) $id, (string) $this->request->getPost('alasan'), $this->konteks(['saluran' => 'web']));

        return $this->ke('admin/surat/balasan/laporan', $r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    private function proses(int $id, bool $setujui): RedirectResponse
    {
        $r = (new PklLapor())->proses($id, $setujui, $this->konteks(['saluran' => 'web']), $this->p);
        // Berhasil → langsung ke halaman suratnya (tombol Unduh ada di sana bagi yang berhak).
        if ($r['ok'] && ! empty($r['surat_id'])) {
            return $this->ke('admin/surat/' . (int) $r['surat_id'], 'success', $r['pesan']);
        }

        return $this->ke('admin/surat/balasan/laporan', $r['ok'] ? 'success' : 'error', $r['pesan']);
    }
}
