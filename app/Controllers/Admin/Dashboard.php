<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\SuratSekolah;
use App\Libraries\UjianReport;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use App\Models\SubmissionModel;
use App\Models\UjianPeriodeModel;

/**
 * Dashboard Admin — versi profesional (2026-10-12): angka utama, "Perlu tindakan", beban mengajar, ringkasan SEMUA modul
 * (PKL, Surat Sekolah, UKK, Laboratorium, Arsip Dokumen, Biodata, Kesediaan), ujian per gelombang, data siswa, kesediaan,
 * dan aktivitas staf terbaru. Hanya membaca; setiap modul dijaga tableExists() agar hosting yang belum migrasi tetap aman.
 */
class Dashboard extends BaseController
{
    public function index()
    {
        $model   = new SubmissionModel();
        $setting = (new SettingModel())->get();
        $kur     = Kurikulum::dashboardData();            // statistik penjadwalan (cache 30 menit)
        $absensi = Absensi::ringkasHarian(date('Y-m-d')); // kehadiran hari ini
        // Ringkasan 4 gelombang ujian; hanya membaca, tidak membuat periode.
        $ujian   = UjianReport::dashboard((new UjianPeriodeModel())->tahunBerjalan());
        $siswa   = $this->siswa();
        $stats   = $model->getStats();
        $modul   = $this->modul($setting, $stats);

        return view('admin/dashboard', [
            'title'     => 'Dashboard',
            'kur'       => $kur,
            'stats'     => $stats,
            'setting'   => $setting,
            'absensi'   => $absensi,
            'ujian'     => $ujian,
            'siswa'     => $siswa,
            'jpTugas'   => $this->jpPenugasan(),
            'modul'     => $modul,
            'tindakan'  => $this->tindakan($kur, $absensi, $ujian, $siswa, $modul),
            'aktivitas' => $this->aktivitas(),
            'nama'      => (string) (session('admin')['full_name'] ?? 'Admin'),
        ]);
    }

    /**
     * Siswa aktif per jurusan & per tingkat (+ yang belum punya kelas). Cache 5 menit.
     *
     * @return array{aktif:int, tanpa_kelas:int, jurusan:list<array{label:string,nama:string,n:int}>, tingkat:list<array{label:string,n:int}>}
     */
    private function siswa(): array
    {
        $data = cache('dash_siswa');
        if (is_array($data)) {
            return $data;
        }

        $db   = db_connect();
        $data = [
            'aktif'       => (int) $db->query("SELECT COUNT(*) n FROM siswa WHERE status = 'aktif' AND deleted_at IS NULL")->getRow()->n,
            'tanpa_kelas' => (int) $db->query("SELECT COUNT(*) n FROM siswa WHERE status = 'aktif' AND deleted_at IS NULL AND kelas_id IS NULL")->getRow()->n,
            'jurusan'     => $db->query(
                "SELECT COALESCE(NULLIF(j.kode, ''), j.nama) AS label, j.nama, COUNT(*) AS n
                   FROM siswa s JOIN kelas k ON k.id = s.kelas_id JOIN jurusan j ON j.id = k.jurusan_id
                  WHERE s.status = 'aktif' AND s.deleted_at IS NULL
                  GROUP BY j.id ORDER BY n DESC"
            )->getResultArray(),
            'tingkat'     => $db->query(
                "SELECT k.tingkat AS label, COUNT(*) AS n
                   FROM siswa s JOIN kelas k ON k.id = s.kelas_id
                  WHERE s.status = 'aktif' AND s.deleted_at IS NULL AND k.tingkat IS NOT NULL AND k.tingkat <> ''
                  GROUP BY k.tingkat ORDER BY FIELD(k.tingkat, 'X', 'XI', 'XII', 'XIII'), k.tingkat"
            )->getResultArray(),
        ];
        cache()->save('dash_siswa', $data, 300);

        return $data;
    }

    /** Total JP dari seluruh penugasan guru pengajar — pembanding "JP terjadwal". */
    private function jpPenugasan(): int
    {
        return (int) db_connect()->query(
            'SELECT COALESCE(SUM(p.jp), 0) n FROM pengampu p
               JOIN guru g ON g.id = p.guru_id AND g.deleted_at IS NULL AND g.bukan_pengajar = 0
              WHERE p.deleted_at IS NULL'
        )->getRow()->n;
    }

    /**
     * Ringkasan tiap modul (angka langsung, tidak di-cache — dipakai juga untuk "Perlu tindakan"). Modul yang tabelnya
     * belum ada bernilai null dan tidak ditampilkan.
     *
     * @return array<string, array<string, mixed>|null>
     */
    private function modul(array $setting, array $stats): array
    {
        $db    = db_connect();
        $ada   = static fn (string $t): bool => $db->tableExists($t);
        $hitung = static function (string $sql) use ($db): array {
            $out = [];
            foreach ($db->query($sql)->getResultArray() as $r) {
                $out[(string) $r['k']] = (int) $r['n'];
            }

            return $out;
        };
        $out = [];

        // PKL / Prakerin
        $out['pkl'] = null;
        if ($ada('pkl_pengajuan')) {
            $p = (new PklPengaturanModel())->ambil();
            $out['pkl'] = [
                'status' => (new PklPengajuanModel())->hitungStatus(),
                'siswa'  => (new PklPengajuanModel())->ringkasanSiswa(PklPengaturanModel::tingkatBoleh($p)),
            ];
        }

        // Surat Sekolah
        $out['surat'] = null;
        if ($ada('surat_sekolah')) {
            $out['surat'] = (new SuratSekolah())->hitungStatus() + [
                'belum_unduh' => (int) $db->query("SELECT COUNT(*) n FROM surat_sekolah WHERE status = 'disetujui' AND cetak_ke = 0")->getRow()->n,
            ];
        }

        // UKK
        $out['ukk'] = null;
        if ($ada('peserta_ukk')) {
            $out['ukk'] = [
                'peserta' => $hitung("SELECT status k, COUNT(*) n FROM peserta_ukk WHERE deleted_at IS NULL GROUP BY status"),
                'jadwal'  => $ada('jadwal_ukk') ? (int) $db->query('SELECT COUNT(*) n FROM jadwal_ukk WHERE deleted_at IS NULL')->getRow()->n : 0,
            ];
        }

        // Laboratorium
        $out['lab'] = null;
        if ($ada('peminjaman') && $ada('kerusakan')) {
            $out['lab'] = [
                'pinjam'    => $hitung("SELECT status k, COUNT(*) n FROM peminjaman WHERE deleted_at IS NULL AND status IN ('dipinjam', 'terlambat') GROUP BY status"),
                'kerusakan' => $hitung("SELECT status k, COUNT(*) n FROM kerusakan WHERE deleted_at IS NULL AND status IN ('dilaporkan', 'diproses') GROUP BY status"),
            ];
        }

        // Arsip Dokumen
        $out['dokumen'] = null;
        if ($ada('dokumen')) {
            $r = $db->query("SELECT COUNT(*) n, COALESCE(SUM(ukuran), 0) b FROM dokumen WHERE deleted_at IS NULL AND tipe = 'berkas'")->getRow();
            $out['dokumen'] = [
                'jumlah' => (int) $db->query('SELECT COUNT(*) n FROM dokumen WHERE deleted_at IS NULL')->getRow()->n,
                'berkas' => (int) $r->n,
                'byte'   => (int) $r->b,
                'kuota'  => max(1, (int) ($setting['dok_kuota_mb'] ?? 2048)) * 1024 * 1024,
            ];
        }

        // Biodata siswa online
        $out['biodata'] = $ada('biodata_isian') ? $hitung('SELECT status k, COUNT(*) n FROM biodata_isian GROUP BY status') : null;

        // Kesediaan guru mengajar
        $out['kesediaan'] = ['total' => (int) $stats['total'], 'hari_ini' => (int) $stats['today'], 'dibuka' => ! empty($setting['form_open'])];

        return $out;
    }

    /**
     * "Perlu tindakan": hanya yang jumlahnya > 0, dari yang paling mendesak.
     *
     * @return list<array{tingkat:string, teks:string, n:int, url:string}>
     */
    private function tindakan(array $kur, array $absensi, array $ujian, array $siswa, array $modul): array
    {
        $out = [];
        $tambah = static function (string $tingkat, int $n, string $teks, string $url) use (&$out): void {
            if ($n > 0) {
                $out[] = ['tingkat' => $tingkat, 'n' => $n, 'teks' => $teks, 'url' => site_url($url)];
            }
        };

        $tambah('kritis', (int) $kur['bentrok'], 'bentrok jadwal perlu diperiksa', 'admin/kurikulum/bentrok');
        if ($absensi['hari_aktif'] && ! $absensi['recorded'] && (int) $absensi['total_guru'] > 0) {
            $tambah('awas', (int) $absensi['total_guru'], 'guru mengajar hari ini, absensi belum dicatat', 'admin/absensi?tanggal=' . $absensi['tanggal']);
        }
        $tambah('awas', (int) ($modul['pkl']['status']['menunggu'] ?? 0), 'ajuan PKL menunggu ACC', 'admin/pkl/daftar/menunggu');
        $tambah('awas', (int) ($modul['surat']['menunggu'] ?? 0), 'surat sekolah menunggu ACC', 'admin/surat?status=menunggu');
        $tambah('awas', (int) ($modul['biodata']['menunggu'] ?? 0), 'isian biodata siswa menunggu diperiksa', 'admin/biodata');
        foreach ($ujian['kartu'] as $u) {
            $tambah('awas', (int) $u['belum'], 'susulan ' . $u['label'] . ' belum dijadwalkan', 'admin/ujian/' . $u['slug']);
        }
        $tambah('awas', (int) ($modul['lab']['pinjam']['terlambat'] ?? 0), 'peminjaman lab terlambat dikembalikan', 'admin/peminjaman');
        $tambah('info', (int) ($modul['lab']['kerusakan']['dilaporkan'] ?? 0), 'laporan kerusakan lab belum diproses', 'admin/kerusakan');
        $tambah('info', (int) $siswa['tanpa_kelas'], 'siswa aktif belum punya kelas', 'admin/master/siswa');
        $tambah('info', (int) $kur['lebih'], 'guru melebihi batas jam mengajar', 'admin/kurikulum/rekap');
        $tambah('info', (int) $kur['kurang'], 'guru belum memenuhi batas jam', 'admin/kurikulum/rekap');

        return $out;
    }

    /**
     * 8 aktivitas staf terbaru dari Audit Log (siapa, apa, kapan).
     *
     * @return list<array{nama:?string, aksi:string, deskripsi:string, waktu:string}>
     */
    private function aktivitas(): array
    {
        $db = db_connect();
        if (! $db->tableExists('audit_log')) {
            return [];
        }

        return $db->table('audit_log l')
            ->select('a.full_name AS nama, l.admin_id, l.aksi, l.deskripsi, l.created_at AS waktu')
            ->join('admins a', 'a.id = l.admin_id', 'left')
            ->orderBy('l.id', 'DESC')->limit(8)
            ->get()->getResultArray();
    }
}
