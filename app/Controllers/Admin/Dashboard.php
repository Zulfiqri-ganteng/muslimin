<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\SuratSekolah;
use App\Libraries\UjianReport;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\AbsensiKerjaModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use App\Models\SubmissionModel;
use App\Models\UjianPeriodeModel;

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
            'tren'      => $this->trenKehadiran(),
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

    /**
     * Tren kehadiran guru 180 hari terakhir — hanya hari yang absensinya dicatat (grafik memilih 7/30/90 hari dan
     * membandingkannya dengan rentang sebelumnya). Status harian per guru = status TERBURUK dari semua sesinya + kehadiran
     * kerja: aturan yang sama dengan Absensi::ringkasHarian (angka "Kehadiran guru hari ini"). Daftar guru per hari diambil
     * dari salinan sesi saat absensi dicatat (absensi_snapshot), jadi tidak berubah walau jadwal diganti kemudian.
     *
     * @return list<array{t:string, n:int, hadir:int, telat:int, izin:int, sakit:int, alpa:int}>
     */
    private function trenKehadiran(): array
    {
        $db = db_connect();
        foreach (['absensi_hari', 'absensi_snapshot', 'absensi_guru', 'absensi_kerja'] as $tabel) {
            if (! $db->tableExists($tabel)) {
                return [];
            }
        }

        $sampai  = date('Y-m-d');
        $dari    = date('Y-m-d', strtotime('-179 days'));
        $tanggal = (new AbsensiHariModel())->datesInRange($dari, $sampai);
        if ($tanggal === []) {
            return [];
        }
        // Guru per hari dari salinan sesi — cukup pasangan unik (tanggal, guru), bukan seluruh sesi (±300 baris/hari).
        $snapshot = [];
        foreach ($db->table('absensi_snapshot')->distinct()->select('tanggal, guru_id')
            ->where('tanggal >=', $dari)->where('tanggal <=', $sampai)->get()->getResultArray() as $r) {
            $snapshot[(string) $r['tanggal']][(int) $r['guru_id']] = true;
        }

        // Pengecualian sesi & kehadiran kerja: [guru][tanggal] → [tanggal][guru] (status terburuk).
        $perHari = [];
        foreach ([(new AbsensiKerjaModel())->dayStatusPerGuru($dari, $sampai), (new AbsensiGuruModel())->dayStatusPerGuru($dari, $sampai)] as $peta) {
            foreach ($peta as $gid => $hari) {
                foreach ($hari as $tgl => $st) {
                    $perHari[$tgl][$gid] = AbsensiGuruModel::worst($perHari[$tgl][$gid] ?? 'hadir', (string) $st);
                }
            }
        }

        // Hari tercatat tanpa salinan (dicatat sebelum fitur salinan ada): pakai jadwal yang berlaku untuk hari itu —
        // sama dengan cadangan di AbsensiLaporan::matriks (rekap absensi).
        $jadwalHari = [];
        $guruJadwal = static function (string $tgl) use (&$jadwalHari): array {
            $n = (int) date('N', strtotime($tgl));
            if (! array_key_exists($n, $jadwalHari)) {
                $hari            = (new HariModel())->byWeekday($n);
                $jadwalHari[$n]  = $hari && (int) $hari['aktif'] === 1
                    ? array_fill_keys(array_map(static fn (array $s): int => (int) $s['guru_id'], (new JadwalModel())->sessionsForHari((int) $hari['id'])), true)
                    : [];
            }

            return $jadwalHari[$n];
        };

        $out = [];
        foreach ($tanggal as $tgl) {
            $guru = [];
            foreach (array_keys($snapshot[$tgl] ?? $guruJadwal((string) $tgl)) as $gid) {
                $guru[$gid] = 'hadir';
            }
            foreach ($perHari[$tgl] ?? [] as $gid => $st) {
                $guru[$gid] = AbsensiGuruModel::worst($guru[$gid] ?? 'hadir', $st);
            }
            if ($guru === []) {
                continue;
            }
            $n = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
            foreach ($guru as $st) {
                $n[$st] = ($n[$st] ?? 0) + 1;
            }
            $out[] = ['t' => (string) $tgl, 'n' => count($guru)] + $n;
        }

        return $out;
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
