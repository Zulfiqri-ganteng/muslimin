<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\SuratSekolah;
use App\Libraries\UjianReport;
use App\Models\PklPengajuanModel;
use App\Models\SettingModel;
use App\Models\SubmissionModel;
use App\Models\UjianPeriodeModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $model   = new SubmissionModel();
        $kur     = Kurikulum::dashboardData();          // statistik penjadwalan (cache 30 menit)
        $absensi = Absensi::ringkasHarian(date('Y-m-d')); // highlight hari ini
        // Ringkasan 4 gelombang ujian; hanya membaca, tidak membuat periode.
        $ujian   = UjianReport::dashboard((new UjianPeriodeModel())->tahunBerjalan());
        $siswa   = $this->siswa();

        return view('admin/dashboard', [
            'title'    => 'Dashboard',
            'kur'      => $kur,
            'stats'    => $model->getStats(),                  // statistik kesediaan
            'setting'  => (new SettingModel())->get(),
            'recent'   => $model->orderBy('created_at', 'DESC')->findAll(5),
            'absensi'  => $absensi,
            'ujian'    => $ujian,
            'siswa'    => $siswa,
            'jpTugas'  => $this->jpPenugasan(),
            'tindakan' => $this->tindakan($kur, $absensi, $ujian, $siswa),
            'nama'     => (string) (session('admin')['full_name'] ?? 'Admin'),
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
     * Daftar "Perlu tindakan": hanya yang jumlahnya > 0, urut dari yang paling mendesak.
     * Angka antrean (PKL, surat) dihitung langsung — tidak di-cache — supaya langsung hilang setelah ditindak.
     *
     * @return list<array{tingkat:string, teks:string, n:int, url:string}>
     */
    private function tindakan(array $kur, array $absensi, array $ujian, array $siswa): array
    {
        $db  = db_connect();
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
        if ($db->tableExists('pkl_pengajuan')) {
            $tambah('awas', (int) ((new PklPengajuanModel())->hitungStatus()['menunggu'] ?? 0), 'ajuan PKL menunggu ACC', 'admin/pkl/daftar/menunggu');
        }
        if ($db->tableExists('surat_sekolah')) {
            $tambah('awas', (int) ((new SuratSekolah())->hitungStatus()['menunggu'] ?? 0), 'surat sekolah menunggu ACC', 'admin/surat?status=menunggu');
        }
        foreach ($ujian['kartu'] as $u) {
            $tambah('awas', (int) $u['belum'], 'susulan ' . $u['label'] . ' belum dijadwalkan', 'admin/ujian/' . $u['slug']);
        }
        $tambah('info', (int) $siswa['tanpa_kelas'], 'siswa aktif belum punya kelas', 'admin/master/siswa');
        $tambah('info', (int) $kur['lebih'], 'guru melebihi batas jam mengajar', 'admin/kurikulum/rekap');
        $tambah('info', (int) $kur['kurang'], 'guru belum memenuhi batas jam', 'admin/kurikulum/rekap');

        return $out;
    }
}
