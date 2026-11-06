<?php

namespace App\Controllers\Api;

use App\Libraries\DashboardData;
use App\Models\SubmissionModel;

/**
 * Dashboard admin (butuh token). Cermin App\Controllers\Admin\Dashboard — sumber data SAMA (Libraries\DashboardData), jadi angka
 * di aplikasi sama persis dengan web.
 *
 * 2026-11-07 (Dashboard Android = web): selain kolom lama (school, admin, absensi, kesediaan, penjadwalan, beban_chart, recent —
 * tidak berubah, aplikasi lama tetap jalan) kini juga: `siswa`, `jp_tugas`, `tindakan`, `aktivitas`, `tren`, `ujian`, `modul`.
 * Kontrak: docs/API-DASHBOARD.md.
 */
class Dashboard extends BaseApiController
{
    /** GET /api/v1/admin/dashboard */
    public function index()
    {
        $admin = $this->admin() ?? [];
        $data  = new DashboardData();
        $d     = $data->semua((string) ($admin['role'] ?? ''));
        $stats = $d['stats'];
        $kur   = $d['kur'];

        $recent = array_map(static fn ($r) => [
            'id'                 => (int) $r['id'],
            'nama_lengkap'       => $r['nama_lengkap'],
            'guru_mapel'         => $r['guru_mapel'] ?? null,
            'status_kepegawaian' => $r['status_kepegawaian'] ?? null,
            'status'             => $r['status'],
            'bersedia'           => (int) ($r['bersedia_mengajar'] ?? 0) === 1,
            'created_at'         => $r['created_at'] ?? null,
        ], (new SubmissionModel())->orderBy('created_at', 'DESC')->findAll(5));

        $chart = array_map(static fn ($b) => [
            'id'        => (int) $b['id'],
            'kode_guru' => $b['kode_guru'] ?? null,
            'nama'      => $b['nama'],
            'max_beban' => (int) $b['max_beban'],
            'total_jp'  => (int) $b['total_jp'],
            'jml_tugas' => (int) $b['jml_tugas'],
        ], $kur['chart'] ?? []);

        $setting = $d['setting'];
        $siswa   = $d['siswa'];

        return $this->ok([
            'school' => [
                'name'          => $setting['school_name'] ?? '',
                'academic_year' => $setting['academic_year'] ?? null,
                'logo'          => $this->uploadUrl($setting['logo'] ?? null),
            ],
            'admin' => [
                'full_name' => $admin['full_name'] ?? '',
                'photo'     => $this->uploadUrl($admin['photo'] ?? null),
            ],
            // Highlight utama dashboard: absensi guru hari ini.
            'absensi' => $d['absensi'],
            'kesediaan' => [
                'total'     => (int) $stats['total'],
                'today'     => (int) $stats['today'],
                'by_status' => array_map('intval', $stats['byStatus']),
                'dibuka'    => ! empty($setting['form_open']),
            ],
            'penjadwalan' => [
                'guru'         => (int) $kur['guru'],
                'kelas'        => (int) $kur['kelas'],
                'mapel'        => (int) $kur['mapel'],
                'jp_terjadwal' => (int) $kur['jpTerjadwal'],
                'bentrok'      => (int) $kur['bentrok'],
                'kurang'       => (int) $kur['kurang'],
                'lebih'        => (int) $kur['lebih'],
            ],
            'beban_chart' => $chart,
            'recent'      => $recent,

            // ── 2026-11-07: bagian dashboard web yang dulu belum ada di aplikasi ──
            // Siswa aktif per jurusan & tingkat (+ tanpa kelas). Cache 5 menit (sama dengan web).
            'siswa' => [
                'aktif'       => (int) $siswa['aktif'],
                'tanpa_kelas' => (int) $siswa['tanpa_kelas'],
                'jurusan'     => array_map(static fn (array $j): array => ['label' => (string) $j['label'], 'nama' => (string) $j['nama'], 'n' => (int) $j['n']], $siswa['jurusan']),
                'tingkat'     => array_map(static fn (array $t): array => ['label' => (string) $t['label'], 'n' => (int) $t['n']], $siswa['tingkat']),
            ],
            // Total JP penugasan guru pengajar — pembanding "Jadwal KBM tersusun" (jp_terjadwal ÷ jp_tugas).
            'jp_tugas' => (int) $d['jpTugas'],
            // "Tugas hari ini": hanya yang > 0, urut paling mendesak. `alamat` = halaman web (aplikasi memetakan ke layarnya).
            'tindakan' => array_map(static fn (array $t): array => [
                'tingkat' => (string) $t['tingkat'], // kritis | awas | info
                'n'       => (int) $t['n'],
                'teks'    => (string) $t['teks'],
                'alamat'  => (string) $t['alamat'],
            ], $d['tindakan']),
            // 8 kegiatan staf terbaru (Audit Log).
            'aktivitas' => array_map(static fn (array $a): array => [
                'nama'      => (string) ($a['nama'] ?: (! empty($a['admin_id']) ? 'Akun terhapus' : 'Sistem')),
                'aksi'      => (string) $a['aksi'],
                'deskripsi' => (string) $a['deskripsi'],
                'waktu'     => (string) $a['waktu'],
            ], $d['aktivitas']),
            // Tren kehadiran guru 180 hari (hanya hari yang absensinya dicatat). Persen = (hadir + telat) ÷ n.
            'tren' => $d['tren'],
            // Ujian per gelombang tahun berjalan.
            'ujian' => [
                'tahun' => (string) $d['ujian']['tahun'],
                'kartu' => array_map(static fn (array $u): array => [
                    'jenis'     => (string) $u['jenis'],
                    'label'     => (string) $u['label'],
                    'slug'      => (string) $u['slug'],
                    'status'    => (string) $u['status'], // draft | berjalan | selesai
                    'jadwal'    => (int) $u['jadwal'],
                    'tak_hadir' => (int) $u['takHadir'],
                    'belum'     => (int) $u['belum'],
                ], $d['ujian']['kartu']),
            ],
            // Ringkasan modul — kalimat siap tampil, sama dengan web.
            'modul' => array_map(static fn (array $b): array => [
                'judul'  => $b[0],
                'ikon'   => $b[1],
                'ket'    => $b[2],
                'ket2'   => $b[3],
                'alamat' => $b[4],
            ], $data->barisModul($d['modul'])),
        ]);
    }
}
