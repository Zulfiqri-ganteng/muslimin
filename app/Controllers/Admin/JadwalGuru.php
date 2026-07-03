<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;

/**
 * Jadwal mengajar per guru (admin) — untuk dibagikan ke tiap guru
 * dalam bentuk PDF/Excel (tombol cetak memakai Admin\Cetak::jadwalGuru).
 */
class JadwalGuru extends BaseController
{
    public function index()
    {
        $guruOpts = (new GuruModel())->options();

        // guru terpilih — default KOSONG; user memilih sendiri dari dropdown
        $guruId = (int) $this->request->getGet('guru_id');
        if ($guruId !== 0 && ! isset($guruOpts[$guruId])) {
            $guruId = 0;
        }
        $guru = $guruId ? (new GuruModel())->find($guruId) : null;

        // hari aktif & seluruh jam (kedua shift, urut waktu → istirahat di posisi benar)
        $hari = master_cache('hari', 'aktif_urut', 3600, static fn () => (new HariModel())->aktifUrut());
        $jam  = master_cache('jam', 'all_urut', 3600, static fn () => (new JamPelajaranModel())
            ->orderBy('shift', 'ASC')->orderBy('waktu_mulai', 'ASC')->findAll());

        return view('admin/jadwal/guru', [
            'title'    => 'Jadwal Guru',
            'guruOpts' => $guruOpts,
            'guruId'   => $guruId,
            'guru'     => $guru,
            'hari'     => $hari,
            'jam'      => $jam,
            'grid'     => $guruId ? (new JadwalModel())->gridForGuru($guruId) : [],
        ]);
    }
}
