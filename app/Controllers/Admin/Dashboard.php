<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\DashboardData;

/**
 * Dashboard Admin — pola halaman muka template Flowbite Admin Dashboard + grafik ApexCharts (rombak tampilan F4): angka utama,
 * tren kehadiran guru, "Perlu tindakan" & aktivitas staf, siswa per jurusan, absensi hari ini, kesediaan, beban mengajar,
 * ringkasan SEMUA modul (PKL, Surat Sekolah, UKK, Laboratorium, Arsip Dokumen, Biodata, Kesediaan) dan ujian per gelombang.
 * Hanya membaca; setiap modul dijaga tableExists() agar hosting yang belum migrasi tetap aman.
 */
class Dashboard extends BaseController
{
    public function index()
    {
        $d = (new DashboardData())->semua((string) (session('admin')['role'] ?? ''));

        return view('admin/dashboard', [
            'title'     => 'Dashboard',
            'kur'       => $d['kur'],
            'stats'     => $d['stats'],
            'setting'   => $d['setting'],
            'absensi'   => $d['absensi'],
            'ujian'     => $d['ujian'],
            'siswa'     => $d['siswa'],
            'jpTugas'   => $d['jpTugas'],
            'modul'     => $d['modul'],
            'tindakan'  => $d['tindakan'],
            'aktivitas' => $d['aktivitas'],
            'tren'      => $d['tren'],
            'nama'      => (string) (session('admin')['full_name'] ?? 'Admin'),
        ]);
    }
}
