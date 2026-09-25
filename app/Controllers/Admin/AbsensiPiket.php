<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalPiketModel;

/**
 * Jadwal guru piket bergilir (hari × shift pagi/siang). Guru piket hari itu
 * otomatis disarankan ke Kehadiran Kerja & tampil di bagian "Guru piket" pada
 * pesan WhatsApp absensi.
 */
class AbsensiPiket extends BaseController
{
    public function index()
    {
        $peta   = GuruModel::petaOrang();
        $keluar = GuruModel::tidakIkutAbsensi();
        $guru   = [];
        foreach ((new GuruModel())->select('id, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $id = (int) $g['id'];
            if (! isset($peta[$id]) && ! isset($keluar[$id])) {
                $guru[] = ['id' => $id, 'nama' => $g['nama']];
            }
        }

        return view('admin/absensi/piket', [
            'title' => 'Jadwal Guru Piket',
            'hari'  => (new HariModel())->aktifUrut(),
            'grid'  => (new JadwalPiketModel())->grid(),
            'guru'  => $guru,
        ]);
    }

    public function save()
    {
        $grid = json_decode((string) $this->request->getPost('grid_json'), true);
        (new JadwalPiketModel())->simpanGrid(is_array($grid) ? $grid : []);
        (new AuditModel())->record('update', 'jadwal_piket', null, 'Simpan jadwal guru piket');

        return redirect()->to(site_url('admin/absensi/piket'))->with('success', 'Jadwal guru piket disimpan.');
    }
}
