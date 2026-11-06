<?php

namespace App\Libraries;

use App\Controllers\Admin\Absensi;
use App\Controllers\Admin\Kurikulum;
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
 * Data Dashboard Admin — dipakai halaman web (Controllers\Admin\Dashboard) DAN API aplikasi (Controllers\Api\Dashboard,
 * 2026-11-07) supaya angka di HP sama persis dengan web. Isi method dipindah utuh dari controller web (tanpa perubahan rumus);
 * satu-satunya tambahan: tindakan() menerima peran sebagai parameter dan mengirim `alamat` (relatif) di samping `url`.
 * Hanya membaca; setiap modul dijaga tableExists() agar hosting yang belum migrasi tetap aman.
 */
class DashboardData
{
    /**
     * Semua bagian dashboard sekaligus (bahan halaman web & API).
     *
     * @return array<string, mixed>
     */
    public function semua(string $peran): array
    {
        $model   = new SubmissionModel();
        $setting = (new SettingModel())->get();
        $kur     = Kurikulum::dashboardData();
        $absensi = Absensi::ringkasHarian(date('Y-m-d'));
        $ujian   = UjianReport::dashboard((new UjianPeriodeModel())->tahunBerjalan());
        $siswa   = $this->siswa();
        $stats   = $model->getStats();
        $modul   = $this->modul($setting, $stats);

        return [
            'kur'       => $kur,
            'stats'     => $stats,
            'setting'   => $setting,
            'absensi'   => $absensi,
            'ujian'     => $ujian,
            'siswa'     => $siswa,
            'jpTugas'   => $this->jpPenugasan(),
            'modul'     => $modul,
            'tindakan'  => $this->tindakan($kur, $absensi, $ujian, $siswa, $modul, $peran),
            'aktivitas' => $this->aktivitas(),
            'tren'      => $this->trenKehadiran(),
        ];
    }

    /**
     * Siswa aktif per jurusan & per tingkat (+ yang belum punya kelas). Cache 5 menit.
     *
     * @return array{aktif:int, tanpa_kelas:int, jurusan:list<array{label:string,nama:string,n:int}>, tingkat:list<array{label:string,n:int}>}
     */
    public function siswa(): array
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
    public function trenKehadiran(): array
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
    public function jpPenugasan(): int
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
    public function modul(array $setting, array $stats): array
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
    public function tindakan(array $kur, array $absensi, array $ujian, array $siswa, array $modul, string $peran = ''): array
    {
        $out = [];
        $tambah = static function (string $tingkat, int $n, string $teks, string $url) use (&$out): void {
            if ($n > 0) {
                $out[] = ['tingkat' => $tingkat, 'n' => $n, 'teks' => $teks, 'url' => site_url($url), 'alamat' => $url];
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

        // Periksa Data Siswa (2026-10-29): data ganda / tidak sesuai / tidak aktif / terhapus lama — hanya bagi yang boleh membuka Master Siswa.
        if (HakAkses::boleh($peran, 'admin/master/siswa')) {
            $j = PeriksaSiswa::hasil()['jumlah'];
            $tambah('awas', $j['ganda'], 'kemungkinan data siswa ganda (satu siswa tercatat dua kali)', 'admin/master/siswa/periksa#ganda');
            $tambah('awas', $j['tidak_bisa_masuk'], 'siswa tidak bisa masuk Layanan Siswa (NISN & tanggal lahir belum benar)', 'admin/master/siswa/periksa#tidak-sesuai');
            $tambah('info', $j['tidak_sesuai'] - $j['tidak_bisa_masuk'], 'data siswa perlu dilengkapi (NIS/NISN tidak wajar atau tanggal lahir kosong)', 'admin/master/siswa/periksa#tidak-sesuai');
            $tambah('info', (int) $siswa['tanpa_kelas'], 'siswa aktif belum punya kelas', 'admin/master/siswa?kelas_id=tanpa');
            $tambah('info', $j['penempatan'], 'siswa dengan kelas hasil penempatan otomatis — perlu dicek', 'admin/master/siswa/periksa#penempatan');
            $tambah('info', $j['terhapus'], 'data siswa terhapus (lama) belum dibereskan — pulihkan atau hapus permanen', 'admin/master/siswa?status=terhapus');
            $tambah('info', $j['tidak_aktif'], 'siswa tidak aktif (lulus / pindah / keluar)', 'admin/master/siswa?status=nonaktif');
        }

        $tambah('info', (int) ($modul['lab']['kerusakan']['dilaporkan'] ?? 0), 'laporan kerusakan lab belum diproses', 'admin/kerusakan');
        $tambah('info', (int) $kur['lebih'], 'guru melebihi batas jam mengajar', 'admin/kurikulum/rekap');
        $tambah('info', (int) $kur['kurang'], 'guru belum memenuhi batas jam', 'admin/kurikulum/rekap');

        return $out;
    }

    /**
     * 8 aktivitas staf terbaru dari Audit Log (siapa, apa, kapan).
     *
     * @return list<array{nama:?string, aksi:string, deskripsi:string, waktu:string}>
     */
    public function aktivitas(): array
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

    /**
     * Baris "Ringkasan modul" (dipindah utuh dari view dashboard, 2026-11-07): [judul, ikon, keterangan, keterangan 2 | null,
     * alamat]. Dipakai view web dan API aplikasi supaya kalimatnya sama persis.
     *
     * @return list<array{0:string, 1:string, 2:string, 3:?string, 4:string}>
     */
    public function barisModul(array $modul): array
    {
        $angka = static fn ($n): string => number_format((int) $n, 0, ',', '.');
        $baris = [];
        if ($m = $modul['pkl']) {
            $s = $m['status'];
            $baris[] = ['PKL / Prakerin', 'briefcase', $angka($s['menunggu'] ?? 0) . ' menunggu ACC · ' . $angka($s['perbaikan'] ?? 0) . ' perbaikan · ' . $angka($s['disetujui'] ?? 0) . ' disetujui · ' . $angka($s['ditolak'] ?? 0) . ' ditolak',
                'Siswa sudah PKL ' . $angka($m['siswa']['sudah_pkl'] ?? 0) . ' dari ' . $angka($m['siswa']['total'] ?? 0) . ' (' . (int) ($m['siswa']['persen_pkl'] ?? 0) . '%)', 'admin/pkl'];
        }
        if ($m = $modul['surat']) {
            $baris[] = ['Surat sekolah', 'envelope', $angka($m['menunggu'] ?? 0) . ' menunggu ACC · ' . $angka($m['belum_unduh'] ?? 0) . ' belum diunduh · ' . $angka($m['dikembalikan'] ?? 0) . ' dikembalikan',
                $angka($m['semua'] ?? 0) . ' surat tercatat', 'admin/surat'];
        }
        if ($m = $modul['ukk']) {
            $p = $m['peserta'];
            $baris[] = ['Uji kompetensi (UKK)', 'award', $angka(array_sum($p)) . ' peserta · ' . $angka($p['lulus'] ?? 0) . ' lulus · ' . $angka($p['tidak_lulus'] ?? 0) . ' tidak lulus',
                $angka($m['jadwal']) . ' jadwal uji', 'admin/peserta-ukk'];
        }
        if ($m = $modul['lab']) {
            $baris[] = ['Laboratorium', 'desktop-pc', $angka(array_sum($m['pinjam'])) . ' sedang dipinjam' . (($m['pinjam']['terlambat'] ?? 0) > 0 ? ' (' . $angka($m['pinjam']['terlambat']) . ' terlambat)' : '') . ' · ' . $angka(array_sum($m['kerusakan'])) . ' kerusakan terbuka',
                null, 'admin/peminjaman'];
        }
        if ($m = $modul['dokumen']) {
            $mb = static fn (int $b): string => number_format($b / 1048576, $b < 10 * 1048576 ? 1 : 0, ',', '.') . ' MB';
            $baris[] = ['Arsip dokumen', 'folder-open', $angka($m['jumlah']) . ' dokumen · ' . $mb($m['byte']) . ' dari ' . $mb($m['kuota']) . ' terpakai', null, 'admin/dokumen'];
        }
        if (($m = $modul['biodata']) !== null) {
            $baris[] = ['Isian biodata siswa', 'profile-card', $angka($m['menunggu'] ?? 0) . ' menunggu · ' . $angka($m['perbaikan'] ?? 0) . ' perbaikan · ' . $angka($m['disetujui'] ?? 0) . ' disetujui', null, 'admin/biodata'];
        }
        $k = $modul['kesediaan'];
        $baris[] = ['Kesediaan guru mengajar', 'clipboard-list', $angka($k['total']) . ' isian · ' . $angka($k['hari_ini']) . ' masuk hari ini · formulir ' . ($k['dibuka'] ? 'dibuka' : 'ditutup'), null, 'admin/submissions'];

        return $baris;
    }
}
