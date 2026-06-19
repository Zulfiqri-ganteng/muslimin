<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GuruModel;
use App\Models\JadwalModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\PengampuModel;

class Kurikulum extends BaseController
{
    // ===================== DASHBOARD KURIKULUM =====================
    // Dashboard kurikulum kini disatukan ke dashboard utama (admin/dashboard).
    public function dashboard()
    {
        return redirect()->to(site_url('admin/dashboard'));
    }

    /** Data statistik kurikulum (dipakai dashboard terpadu), di-cache 30 menit. */
    public static function dashboardData(): array
    {
        $data = cache('dash_kurikulum');
        if (! $data) {
            $jadwal   = new JadwalModel();
            $pengampu = new PengampuModel();
            $beban    = $pengampu->bebanPerGuruDetail();

            $kurang = 0; $lebih = 0;
            foreach ($beban as $b) {
                if ((int) $b['total_jp'] < (int) $b['max_beban']) {
                    $kurang++;
                } elseif ((int) $b['total_jp'] > (int) $b['max_beban']) {
                    $lebih++;
                }
            }

            $bentrok = count($jadwal->conflictsGuru())
                     + count($jadwal->conflictsKelas())
                     + count($jadwal->ketViolations());

            $data = [
                'guru'        => (new GuruModel())->countAllResults(),
                'kelas'       => (new KelasModel())->countAllResults(),
                'mapel'       => (new MataPelajaranModel())->countAllResults(),
                'jpTerjadwal' => $jadwal->countAllResults(),
                'bentrok'     => $bentrok,
                'kurang'      => $kurang,
                'lebih'       => $lebih,
                'chart'       => array_slice($beban, 0, 12),
            ];
            cache()->save('dash_kurikulum', $data, 1800);
        }
        return $data;
    }

    // ===================== REKAP BEBAN MENGAJAR =====================
    public function rekap()
    {
        $grouped = cache('rekap_beban');
        if (! $grouped) {
            $pengampu = new PengampuModel();
            $rows     = $pengampu->rekapRows();

            $grouped = [];
            foreach ($rows as $r) {
                $gid = (int) $r['guru_id'];
                if (! isset($grouped[$gid])) {
                    $grouped[$gid] = [
                        'guru_id'   => $gid,
                        'kode_guru' => $r['kode_guru'],
                        'guru_nama' => $r['guru_nama'],
                        'max_beban' => (int) $r['max_beban'],
                        'items'     => [],
                        'total'     => 0,
                    ];
                }
                $grouped[$gid]['items'][] = [
                    'mapel' => $r['nama_mapel'],
                    'kelas' => $r['nama_kelas'],
                    'jp'    => (int) $r['jp'],
                ];
                $grouped[$gid]['total'] += (int) $r['jp'];
            }
            $grouped = array_values($grouped);
            cache()->save('rekap_beban', $grouped, 3600); // 1 jam
        }

        return view('admin/kurikulum/rekap', [
            'title'   => 'Rekap Beban Mengajar',
            'grouped' => $grouped,
        ]);
    }

    // ===================== DETEKSI BENTROK =====================
    public function bentrok()
    {
        // sengaja TIDAK di-cache: ini alat pemeriksaan, harus selalu real-time.
        $jadwal = new JadwalModel();

        return view('admin/kurikulum/bentrok', [
            'title'        => 'Deteksi Bentrok',
            'bentrokGuru'  => $jadwal->conflictsGuru(),
            'bentrokKelas' => $jadwal->conflictsKelas(),
            'ketViolations'=> $jadwal->ketViolations(),
            'jpMismatch'   => $jadwal->jpMismatch(),
        ]);
    }
}
