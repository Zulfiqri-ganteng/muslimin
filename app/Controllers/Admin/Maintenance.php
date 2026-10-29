<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\ModeMaintenance;

/**
 * Sistem → Mode Maintenance (khusus Admin — MenuAdmin entri 'mode_maintenance'; docs/DESAIN-ALAT-SISTEM.md F2).
 */
class Maintenance extends BaseController
{
    public function index()
    {
        return view('admin/sistem/maintenance', [
            'title' => 'Mode Maintenance',
            's'     => ModeMaintenance::status(),
        ]);
    }

    /** POST admin/sistem/maintenance/nyalakan — aktifkan, atau perbarui pesan/perkiraan selesai bila sudah aktif. */
    public function nyalakan()
    {
        $r = ModeMaintenance::simpan(true, (string) $this->request->getPost('pesan'), (string) $this->request->getPost('selesai'), $this->oleh());

        return $r['ok'] ? redirect()->to(site_url('admin/sistem/maintenance'))->with('success', $r['pesan'])
            : redirect()->back()->withInput()->with('error', $r['pesan']);
    }

    /** POST admin/sistem/maintenance/matikan */
    public function matikan()
    {
        $r = ModeMaintenance::simpan(false, '', '', $this->oleh());

        return redirect()->to(site_url('admin/sistem/maintenance'))->with('success', $r['pesan']);
    }

    /** GET admin/sistem/maintenance/pratinjau — tampilan yang dilihat pengunjung (memakai pesan tersimpan). */
    public function pratinjau()
    {
        $s = ModeMaintenance::status();

        return $this->response->setHeader('Cache-Control', 'no-store')
            ->setBody(ModeMaintenance::halaman(['pesan' => $s['pesan'], 'selesai_label' => ModeMaintenance::labelWaktu($s['selesai'])]));
    }

    private function oleh(): string
    {
        $a = (array) session('admin');

        return (string) (($a['full_name'] ?? '') ?: ($a['username'] ?? 'Admin'));
    }
}
