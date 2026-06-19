<?php

namespace App\Controllers;

use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\SettingModel;

/**
 * Halaman publik (tanpa login): beranda, cek jadwal kelas, cek jadwal guru.
 * Hanya menampilkan data yang aman untuk umum (jadwal & statistik agregat).
 */
class Publik extends BaseController
{
    protected SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    /** Beranda: identitas sekolah + statistik ringkas + pencarian jadwal. */
    public function home()
    {
        $stats = cache('publik_stats');
        if (! $stats) {
            $stats = [
                'guru'  => (new GuruModel())->countAllResults(),
                'kelas' => (new KelasModel())->countAllResults(),
                'mapel' => (new MataPelajaranModel())->countAllResults(),
            ];
            cache()->save('publik_stats', $stats, 1800);
        }

        return view('public/home', [
            'title'     => 'Beranda',
            'setting'   => $this->settings->get(),
            'stats'     => $stats,
            'kelasOpts' => (new KelasModel())->options(),
        ]);
    }

    /** Cek jadwal per kelas. */
    public function jadwalKelas()
    {
        $kelasModel = new KelasModel();
        $kelasOpts  = $kelasModel->options();

        $kelasId = (int) $this->request->getGet('kelas_id');
        $kelas = $kelasId ? $kelasModel->find($kelasId) : null;
        $shift = $kelas['shift'] ?? 'pagi';

        $hari = $jam = $grid = [];
        if ($kelas) {
            $hari = (new HariModel())->aktifUrut();
            $jam  = (new JamPelajaranModel())->where('shift', $shift)->orderBy('jam_ke', 'ASC')->findAll();
            $grid = (new JadwalModel())->gridForKelas($kelasId);
        }

        return view('public/jadwal_kelas', [
            'title'     => 'Jadwal Kelas',
            'setting'   => $this->settings->get(),
            'kelasOpts' => $kelasOpts,
            'kelasId'   => $kelasId,
            'kelas'     => $kelas,
            'hari'      => $hari,
            'jam'       => $jam,
            'grid'      => $grid,
        ]);
    }

    /** Cek jadwal mengajar per guru. */
    public function jadwalGuru()
    {
        // opsi guru (nama saja, tanpa NIP untuk publik)
        $guruOpts = [];
        foreach ((new GuruModel())->select('id, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $guruOpts[$g['id']] = $g['nama'];
        }

        $guruId = (int) $this->request->getGet('guru_id');
        $guru   = $guruId ? (new GuruModel())->find($guruId) : null;

        $hari = $jam = $grid = [];
        if ($guru) {
            $hari = (new HariModel())->aktifUrut();
            $jam  = (new JamPelajaranModel())->where('is_istirahat', 0)
                ->orderBy('shift', 'ASC')->orderBy('jam_ke', 'ASC')->findAll();
            $grid = (new JadwalModel())->gridForGuru($guruId);
        }

        return view('public/jadwal_guru', [
            'title'    => 'Jadwal Guru',
            'setting'  => $this->settings->get(),
            'guruOpts' => $guruOpts,
            'guruId'   => $guruId,
            'guru'     => $guru,
            'hari'     => $hari,
            'jam'      => $jam,
            'grid'     => $grid,
        ]);
    }
}
