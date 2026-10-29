<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\SesiAktif;
use App\Models\AuditModel;

/**
 * Sistem → Sesi Aktif (khusus Admin — MenuAdmin entri 'sesi_aktif'; docs/DESAIN-ALAT-SISTEM.md F5): siapa sedang masuk di web &
 * aplikasi Android, keluarkan satu sesi atau semua kecuali diri sendiri. Dicatat di Audit Log.
 */
class Sesi extends BaseController
{
    public function index()
    {
        return view('admin/sistem/sesi', [
            'title' => 'Sesi Aktif',
            'sesi'  => SesiAktif::daftar(SesiAktif::sidik()),
        ]);
    }

    /** POST admin/sistem/sesi/{web|android}/{id}/keluarkan */
    public function keluarkan(string $jenis, string $id)
    {
        $jenis = $jenis === 'android' ? 'android' : 'web';
        $baris = $this->cari($jenis, (int) $id);
        if ($baris === null) {
            return $this->kembali('error', 'Sesi tidak ditemukan atau sudah berakhir.');
        }
        if ($baris['ini']) {
            return $this->kembali('error', 'Ini sesi Anda sendiri — pakai tombol Keluar di menu akun.');
        }
        SesiAktif::keluarkan($jenis, (int) $id, $this->oleh());
        (new AuditModel())->record('update', 'sesi', (int) $id, 'Sesi ' . ($jenis === 'web' ? 'web' : 'Android') . ' ' . $baris['nama'] . ' (' . $baris['perangkat'] . ') dikeluarkan oleh ' . $this->oleh());

        return $this->kembali('success', $baris['nama'] . ' dikeluarkan dari ' . ($jenis === 'web' ? 'web' : 'aplikasi Android') . '.');
    }

    /** POST admin/sistem/sesi/keluarkan-semua — semua sesi web & Android selain akun sendiri. */
    public function keluarkanSemua()
    {
        $n = SesiAktif::keluarkanSemua($this->oleh(), (int) (((array) session('admin'))['id'] ?? 0));
        (new AuditModel())->record('update', 'sesi', null, 'Semua sesi dikeluarkan (kecuali akun sendiri) oleh ' . $this->oleh() . ': ' . $n['web'] . ' web, ' . $n['android'] . ' Android');

        return $this->kembali('success', 'Dikeluarkan: ' . $n['web'] . ' sesi web dan ' . $n['android'] . ' sesi Android.');
    }

    private function cari(string $jenis, int $id): ?array
    {
        foreach (SesiAktif::daftar(SesiAktif::sidik())[$jenis] as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }

        return null;
    }

    private function kembali(string $jenis, string $pesan)
    {
        return redirect()->to(site_url('admin/sistem/sesi'))->with($jenis, $pesan);
    }

    private function oleh(): string
    {
        $a = (array) session('admin');

        return (string) (($a['full_name'] ?? '') ?: ($a['username'] ?? 'Admin'));
    }
}
