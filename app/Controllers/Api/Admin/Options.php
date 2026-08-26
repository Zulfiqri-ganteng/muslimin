<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AsetModel;
use App\Models\FaseModel;
use App\Models\GuruModel;
use App\Models\JabatanModel;
use App\Models\JurusanModel;
use App\Models\KelasModel;
use App\Models\LabModel;
use App\Models\MataPelajaranModel;
use App\Models\SparepartModel;
use App\Models\TeknisiModel;

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
        $want = $req !== '' ? array_map('trim', explode(',', $req)) : ['guru', 'mapel', 'kelas', 'jurusan', 'jabatan', 'fase'];

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
        if (in_array('fase', $want, true)) {
            $out['fase'] = $this->pairs((new FaseModel())->options());
        }
        // ---- Laboratorium & inventaris ----
        if (in_array('lab', $want, true)) {
            $out['lab'] = $this->pairs((new LabModel())->options());
        }
        if (in_array('teknisi', $want, true)) {
            $out['teknisi'] = $this->pairs((new TeknisiModel())->options());
        }
        if (in_array('aset', $want, true)) {
            $out['aset'] = $this->pairs((new AsetModel())->options());
        }
        if (in_array('aset_tersedia', $want, true)) {
            $out['aset_tersedia'] = $this->pairs((new AsetModel())->optionsTersedia());
        }
        if (in_array('sparepart', $want, true)) {
            $out['sparepart'] = $this->pairs((new SparepartModel())->options());
        }
        if (in_array('jabatan', $want, true)) {
            // Jabatan membawa penanda struktural agar klien bisa menandainya
            // (penyandangnya wajib hadir walau tanpa jadwal mengajar).
            $out['jabatan'] = array_map(static fn ($r) => [
                'id'            => (int) $r['id'],
                'label'         => $r['nama'],
                'is_struktural' => (bool) $r['is_struktural'],
            ], (new JabatanModel())->select('id, nama, is_struktural')
                ->orderBy('level', 'ASC')->orderBy('nama', 'ASC')->findAll());
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
