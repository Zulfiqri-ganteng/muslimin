<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalPiketModel;

/**
 * Jadwal guru piket (API) — cermin App\Controllers\Admin\AbsensiPiket.
 *
 *   GET  /api/v1/admin/absensi/piket → {hari[{id,nama}], grid{hari_id:{pagi[],siang[]}}, guru[{id,nama}]}
 *   POST /api/v1/admin/absensi/piket   Body: {grid:{hari_id:{pagi:[guru_id…], siang:[…]}}}
 */
class AbsensiPiket extends BaseApiController
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

        $grid = [];
        foreach ((new JadwalPiketModel())->grid() as $hid => $perShift) {
            $grid[(string) $hid] = ['pagi' => $perShift['pagi'] ?? [], 'siang' => $perShift['siang'] ?? []];
        }

        return $this->ok([
            'hari' => array_map(static fn ($h) => ['id' => (int) $h['id'], 'nama' => $h['nama']], (new HariModel())->aktifUrut()),
            'grid' => (object) $grid,
            'guru' => $guru,
        ]);
    }

    public function save()
    {
        $grid = $this->body()['grid'] ?? [];
        (new JadwalPiketModel())->simpanGrid(is_array($grid) ? $grid : []);
        (new AuditModel())->record('update', 'jadwal_piket', null, 'Simpan jadwal guru piket (via mobile)');

        return $this->ok(null, 'Jadwal guru piket disimpan.');
    }
}
