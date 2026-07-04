<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\GuruModel;
use App\Models\JurusanModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;

/**
 * Sumber data dropdown untuk form master (guru, mapel, kelas, jurusan).
 * Satu endpoint agar Flutter cukup satu request menyiapkan seluruh pilihan.
 *
 *   GET /api/v1/admin/master/options?types=guru,mapel,kelas,jurusan
 * (tanpa parameter → kembalikan semuanya)
 */
class Options extends BaseApiController
{
    public function index()
    {
        $req  = trim((string) $this->request->getGet('types'));
        $want = $req !== '' ? array_map('trim', explode(',', $req)) : ['guru', 'mapel', 'kelas', 'jurusan'];

        $out = [];
        if (in_array('guru', $want, true)) {
            $out['guru'] = $this->pairs((new GuruModel())->options());
        }
        if (in_array('mapel', $want, true)) {
            $out['mapel'] = $this->pairs((new MataPelajaranModel())->options());
        }
        if (in_array('kelas', $want, true)) {
            $out['kelas'] = $this->pairs((new KelasModel())->options());
        }
        if (in_array('jurusan', $want, true)) {
            $out['jurusan'] = $this->pairs((new JurusanModel())->options());
        }

        return $this->ok($out);
    }

    /** Ubah map id=>label menjadi list [{id,label}] yang stabil untuk klien. */
    private function pairs(array $map): array
    {
        $list = [];
        foreach ($map as $id => $label) {
            $list[] = ['id' => (int) $id, 'label' => $label];
        }
        return $list;
    }
}
