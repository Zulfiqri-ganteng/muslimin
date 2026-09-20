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
use App\Models\UjianJadwalModel;
use App\Models\UjianPengawasModel;
use App\Models\UjianPeriodeModel;
use App\Models\UjianSusulanModel;

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

        // ---- Menu Ujian: daftar nilai tetap, supaya klien tidak hard-code ----
        if (in_array('ujian_tingkat', $want, true)) {
            $out['ujian_tingkat'] = $this->nilai(UjianJadwalModel::TINGKAT);
        }
        if (in_array('ujian_shift', $want, true)) {
            $out['ujian_shift'] = $this->nilai(UjianJadwalModel::SHIFT, [
                'pagi' => 'Pagi', 'siang' => 'Siang', 'semua' => 'Pagi & Siang',
            ]);
        }
        if (in_array('ujian_alasan', $want, true)) {
            $out['ujian_alasan'] = $this->nilai(UjianSusulanModel::ALASAN, [
                'sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'lainnya' => 'Lainnya',
            ]);
        }
        if (in_array('ujian_status', $want, true)) {
            $out['ujian_status'] = $this->nilai(UjianSusulanModel::STATUS, UjianSusulanModel::STATUS_LABEL);
        }
        if (in_array('ujian_peran_pengawas', $want, true)) {
            $out['ujian_peran_pengawas'] = $this->nilai(UjianPengawasModel::PERAN, [
                'pengawas' => 'Pengawas', 'cadangan' => 'Cadangan',
            ]);
        }
        if (in_array('ujian_jenis', $want, true)) {
            $out['ujian_jenis'] = array_map(static fn ($j) => [
                'value' => $j,
                'slug'  => UjianPeriodeModel::keSlug($j),
                'label' => UjianPeriodeModel::JENIS_LABEL[$j] ?? $j,
                'nama'  => UjianPeriodeModel::JENIS_PANJANG[$j] ?? $j,
            ], UjianPeriodeModel::JENIS);
        }

        return $this->ok($out);
    }

    /**
     * Daftar nilai tetap (enum) jadi list [{value,label}].
     *
     * @param array<int,string>    $values
     * @param array<string,string> $labels label khusus; selain itu dipakai
     *                                     nilainya sendiri dengan huruf besar di depan
     */
    private function nilai(array $values, array $labels = []): array
    {
        return array_map(static fn ($v) => [
            'value' => $v,
            'label' => $labels[$v] ?? ucfirst(str_replace('_', ' ', $v)),
        ], $values);
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
