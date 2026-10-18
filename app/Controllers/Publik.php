<?php

namespace App\Controllers;

use App\Libraries\LayananPublik;
use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\FotoSekolahModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\PengumumanModel;
use App\Models\SettingModel;
use App\Models\SiswaModel;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Halaman publik (tanpa login): beranda, cek jadwal kelas/guru, cetak PDF.
 * Jadwal hanya tampil bila admin mengaktifkan "Tampilkan jadwal ke publik".
 */
class Publik extends BaseController
{
    protected SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    /** Beranda. */
    public function home()
    {
        $setting = $this->settings->get();

        return view('public/home', [
            'title'      => 'Beranda',
            'setting'    => $setting,
            'stats'      => $this->statistik(),
            // Agregat jumlah siswa untuk grafik. Hanya angka — TIDAK pernah
            // memuat identitas siswa (data pribadi anak jangan tampil publik).
            'siswaStat'  => (new SiswaModel())->statistik(),
            'kelasOpts'  => $this->jadwalPublik($setting) ? (new KelasModel())->options() : [],
            'pengumuman' => (new PengumumanModel())->aktif(5),
            'foto'       => $this->fotoSekolah(),
            'now'        => $this->nowContext(),
            'jadwalOn'   => $this->jadwalPublik($setting),
        ]);
    }

    /** Cek jadwal per kelas. */
    public function jadwalKelas()
    {
        $setting = $this->settings->get();
        if (! $this->jadwalPublik($setting)) {
            return $this->blokir($setting);
        }

        $kelasModel = new KelasModel();
        $kelasOpts  = $kelasModel->options();
        $kelasId    = (int) $this->request->getGet('kelas_id');
        $kelas      = $kelasId ? $kelasModel->find($kelasId) : null;
        $shift      = $kelas['shift'] ?? 'pagi';

        $hari = $jam = $grid = [];
        if ($kelas) {
            $hari = (new HariModel())->aktifUrut();
            $jam  = (new JamPelajaranModel())->where('shift', $shift)->orderBy('waktu_mulai', 'ASC')->findAll();
            $grid = (new JadwalModel())->gridForKelas($kelasId);
        }

        return view('public/jadwal_kelas', [
            'title' => 'Jadwal Kelas', 'setting' => $setting,
            'kelasOpts' => $kelasOpts, 'kelasId' => $kelasId, 'kelas' => $kelas,
            'hari' => $hari, 'jam' => $jam, 'grid' => $grid, 'now' => $this->nowContext(),
        ]);
    }

    /** Cek jadwal mengajar per guru. */
    public function jadwalGuru()
    {
        $setting = $this->settings->get();
        if (! $this->jadwalPublik($setting)) {
            return $this->blokir($setting);
        }

        $guruOpts = [];
        foreach ((new GuruModel())->select('id, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $guruOpts[$g['id']] = $g['nama'];
        }
        $guruId = (int) $this->request->getGet('guru_id');
        $guru   = $guruId ? (new GuruModel())->find($guruId) : null;

        $hari = $jam = $grid = [];
        if ($guru) {
            $hari = (new HariModel())->aktifUrut();
            // dua shift + istirahat (urut waktu → istirahat jatuh di tengah tiap shift)
            $jam  = (new JamPelajaranModel())->orderBy('shift', 'ASC')->orderBy('waktu_mulai', 'ASC')->findAll();
            $grid = (new JadwalModel())->gridForGuru($guruId);
        }

        return view('public/jadwal_guru', [
            'title' => 'Jadwal Guru', 'setting' => $setting,
            'guruOpts' => $guruOpts, 'guruId' => $guruId, 'guru' => $guru,
            'hari' => $hari, 'jam' => $jam, 'grid' => $grid, 'now' => $this->nowContext(),
        ]);
    }

    /** Absensi guru harian (publik). Pilih tanggal → status kehadiran per sesi. */
    public function absensi()
    {
        $setting = $this->settings->get();
        if ((int) ($setting['absensi_publik'] ?? 1) !== 1) {
            return $this->blokir($setting, 'Kehadiran guru');
        }

        $raw     = trim((string) $this->request->getGet('tanggal'));
        $ts      = $raw !== '' ? strtotime($raw) : time();
        $tanggal = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
        $ts      = strtotime($tanggal);

        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $hariNama  = $hari['nama'] ?? '';
        $hariAktif = $hari && (int) $hari['aktif'] === 1;

        // Hanya tampilkan bila hari ini SUDAH diabsen admin (tercatat).
        // Kalau belum, jangan tampilkan daftar "hadir" (belum divalidasi).
        $recorded = (new AbsensiHariModel())->isRecorded($tanggal);
        $sessions = ($hariAktif && $recorded) ? (new JadwalModel())->sessionsForHari((int) $hari['id']) : [];
        $absen    = (new AbsensiGuruModel())->forDate($tanggal);

        $grup    = [];
        $ringkas = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($sessions as $s) {
            $ex              = $absen[$s['kelas_id'] . '-' . $s['jam_id']] ?? null;
            $s['status']     = $ex['status'] ?? 'hadir';
            $s['jam_masuk']  = $ex && $ex['jam_masuk'] ? substr($ex['jam_masuk'], 0, 5) : '';
            $s['keterangan'] = $ex['keterangan'] ?? '';
            $ringkas[$s['status']] = ($ringkas[$s['status']] ?? 0) + 1;

            $gid = (int) $s['guru_id'];
            if (! isset($grup[$gid])) {
                $grup[$gid] = ['nama' => $s['guru_nama'], 'sesi' => []];
            }
            $grup[$gid]['sesi'][] = $s;
        }

        // Kehadiran kerja (di luar jadwal) — hanya tampil bila hari sudah tercatat.
        $kerja = $recorded ? (new \App\Models\AbsensiKerjaModel())->forDate($tanggal) : [];

        return view('public/absensi', [
            'title'     => 'Absensi Guru', 'setting' => $setting,
            'tanggal'   => $tanggal, 'hariNama' => $hariNama, 'hariAktif' => $hariAktif,
            'grup'      => array_values($grup), 'ringkas' => $ringkas, 'total' => count($sessions),
            'recorded'  => $recorded, 'kerja' => $kerja,
        ]);
    }

    /**
     * Pencarian portal: layanan & halaman, kelas (→ jadwal kelas) dan guru (→ jadwal mengajar; hanya bila jadwal dibuka untuk umum —
     * nama yang memang sudah tampil di halaman jadwal). Tanpa data siswa.
     */
    public function cari()
    {
        $setting = $this->settings->get();
        $q       = mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $this->request->getGet('q'))), 0, 100);
        $hasil   = ['layanan' => [], 'kelas' => [], 'guru' => []];

        if (mb_strlen($q) >= 2) {
            $hasil['layanan'] = LayananPublik::cari($q, $setting);
            if ($this->jadwalPublik($setting)) {
                $kata = array_values(array_filter(explode(' ', LayananPublik::normal($q))));
                foreach ((new KelasModel())->options() as $id => $nama) {
                    $teks = LayananPublik::normal((string) $nama);
                    if ($kata !== [] && count(array_filter($kata, static fn (string $k): bool => str_contains($teks, $k))) === count($kata)) {
                        $hasil['kelas'][] = ['id' => (int) $id, 'nama' => (string) $nama];
                    }
                }
                $hasil['kelas'] = array_slice($hasil['kelas'], 0, 12);
                $hasil['guru']  = (new GuruModel())->select('id, nama')->like('nama', $q)->orderBy('nama', 'ASC')->findAll(12);
            }
        }

        return view('public/cari', [
            'title'   => $q !== '' ? 'Cari "' . $q . '"' : 'Pencarian',
            'setting' => $setting,
            'q'       => $q,
            'hasil'   => $hasil,
            'jumlah'  => array_sum(array_map('count', $hasil)),
            'saran'   => LayananPublik::unggulan($setting),
        ]);
    }

    /** Halaman layanan per pengguna: /siswa, /guru, /orang-tua. */
    public function kelompok(string $kunci)
    {
        $setting  = $this->settings->get();
        $kelompok = LayananPublik::kelompok();
        if (! isset($kelompok[$kunci]) || $kunci === 'sekolah') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('public/kelompok', [
            'title'     => $kelompok[$kunci]['label'],
            'setting'   => $setting,
            'g'         => $kelompok[$kunci],
            'bagian'    => LayananPublik::untukKelompok($kunci, $setting),
            'deskripsi' => $kelompok[$kunci]['ket'],
        ]);
    }

    /** Profil sekolah: identitas (Pengaturan Sekolah) + angka agregat + kontak. */
    public function profil()
    {
        $setting = $this->settings->get();

        return view('public/profil', [
            'title'     => 'Profil Sekolah',
            'setting'   => $setting,
            'stats'     => $this->statistik(),
            'siswaStat' => (new SiswaModel())->statistik(),
            'foto'      => $this->fotoSekolah(),
        ]);
    }

    /** Daftar pengumuman aktif (9 per halaman). */
    public function pengumuman()
    {
        $setting = $this->settings->get();
        $model   = new PengumumanModel();

        return view('public/pengumuman', [
            'title'   => 'Pengumuman',
            'setting' => $setting,
            'rows'    => $model->where('is_aktif', 1)->orderBy('id', 'DESC')->paginate(9, 'pg'),
            'pager'   => $model->pager,
            'logo'    => $this->logoUrl($setting),
        ]);
    }

    /** Isi satu pengumuman aktif + pengumuman lain. */
    public function pengumumanIsi($id)
    {
        $setting = $this->settings->get();
        $model   = new PengumumanModel();
        $p       = $model->where('is_aktif', 1)->find((int) $id);
        if (! $p) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('public/pengumuman_isi', [
            'title'     => (string) $p['judul'],
            'setting'   => $setting,
            'p'         => $p,
            'lain'      => (new PengumumanModel())->where('is_aktif', 1)->where('id !=', (int) $p['id'])->orderBy('id', 'DESC')->findAll(3),
            'logo'      => $this->logoUrl($setting),
            'deskripsi' => mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', (string) $p['isi'])), 0, 160, '…'),
        ]);
    }

    /** Bantuan: tanya-jawab + kontak sekolah. */
    public function bantuan()
    {
        $setting = $this->settings->get();

        return view('public/bantuan', [
            'title'     => 'Bantuan',
            'setting'   => $setting,
            'tanya'     => LayananPublik::tanyaJawab($setting),
            'deskripsi' => 'Pertanyaan yang sering ditanyakan dan kontak ' . ($setting['school_name'] ?? 'sekolah') . '.',
        ]);
    }

    // ===================== CETAK PDF PUBLIK =====================
    public function cetakKelas($id)
    {
        $setting = $this->settings->get();
        if (! $this->jadwalPublik($setting)) {
            return redirect()->to(site_url('/'));
        }
        $kelas = (new KelasModel())->find((int) $id);
        if (! $kelas) {
            return redirect()->to(site_url('jadwal-kelas'));
        }
        $hari = (new HariModel())->aktifUrut();
        $jam  = (new JamPelajaranModel())->where('shift', $kelas['shift'])->orderBy('waktu_mulai', 'ASC')->findAll();
        $grid = (new JadwalModel())->gridForKelas((int) $id);

        $this->streamPdf(view('pdf/jadwal_grid', [
            'title' => 'JADWAL PELAJARAN', 'label' => $kelas['nama_kelas'], 'setting' => $setting,
            'hari' => $hari, 'jam' => $jam, 'grid' => $grid, 'mode' => 'kelas',
        ]), 'Jadwal-' . $this->slug($kelas['nama_kelas']));
    }

    public function cetakGuru($id)
    {
        $setting = $this->settings->get();
        if (! $this->jadwalPublik($setting)) {
            return redirect()->to(site_url('/'));
        }
        $guru = (new GuruModel())->find((int) $id);
        if (! $guru) {
            return redirect()->to(site_url('jadwal-guru'));
        }
        $hari = (new HariModel())->aktifUrut();
        $jam  = (new JamPelajaranModel())->orderBy('shift', 'ASC')->orderBy('waktu_mulai', 'ASC')->findAll();
        $grid = (new JadwalModel())->gridForGuru((int) $id);

        $this->streamPdf(view('pdf/jadwal_grid', [
            'title' => 'JADWAL MENGAJAR — ' . $guru['kode_guru'] . ' · ' . $guru['nama'], 'setting' => $setting,
            'hari' => $hari, 'jam' => $jam, 'grid' => $grid, 'mode' => 'guru',
        ]), 'Jadwal-Guru-' . $this->slug($guru['nama']));
    }

    // ===================== HELPER =====================
    /** Angka agregat sekolah (beranda & profil), di-cache 30 menit. "v2" = penanda bentuk cache. */
    private function statistik(): array
    {
        $stats = cache('publik_stats_v2');
        if (! $stats) {
            $stats = [
                'guru'  => (new GuruModel())->where('bukan_pengajar', 0)->countAllResults(),
                'kelas' => (new KelasModel())->countAllResults(),
                'mapel' => (new MataPelajaranModel())->countAllResults(),
                'siswa' => (new SiswaModel())->where('status', 'aktif')->countAllResults(),
            ];
            cache()->save('publik_stats_v2', $stats, 1800);
        }

        return $stats;
    }

    /**
     * Foto sekolah aktif yang berkasnya ada (+ 'url') — banner beranda, Tentang sekolah, galeri Profil. Tabel belum ada (kode sudah
     * ditarik di server tetapi `spark migrate` belum dijalankan) → kosong, beranda tetap tampil.
     */
    private function fotoSekolah(): array
    {
        try {
            $rows = (new FotoSekolahModel())->untukPublik();
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException) {
            return [];
        }
        $hasil = [];
        foreach ($rows as $f) {
            $url = LayananPublik::gambar(FotoSekolahModel::FOLDER, $f['berkas']);
            if ($url !== null) {
                $hasil[] = $f + ['url' => $url];
            }
        }

        return $hasil;
    }

    /** URL logo sekolah bila berkasnya ada (pengganti gambar di kartu pengumuman). */
    private function logoUrl(array $setting): ?string
    {
        $logo = (string) ($setting['logo'] ?? '');

        return $logo !== '' && is_file(FCPATH . 'uploads/' . $logo) ? base_url('uploads/' . rawurlencode($logo)) : null;
    }

    private function jadwalPublik(array $setting): bool
    {
        return (int) ($setting['jadwal_publik'] ?? 1) === 1;
    }

    /** Halaman informasi yang saklar privasinya dimatikan di Pengaturan Sekolah (jadwal / kehadiran guru). */
    private function blokir(array $setting, string $halaman = 'Jadwal pelajaran')
    {
        return view('public/jadwal_off', ['title' => $halaman, 'setting' => $setting, 'halaman' => $halaman]);
    }

    /** Konteks "sekarang": hari & jam yang sedang berlangsung. */
    private function nowContext(): array
    {
        $names  = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $today  = $names[(int) date('N')] ?? '';
        $hari   = (new HariModel())->where('nama', $today)->where('aktif', 1)->first();
        $hariId = $hari ? (int) $hari['id'] : 0;

        $now    = date('H:i:s');
        $jamIds = [];
        $label  = null;
        if ($hariId) {
            foreach ((new JamPelajaranModel())->where('is_istirahat', 0)->findAll() as $j) {
                if ($now >= $j['waktu_mulai'] && $now <= $j['waktu_selesai']) {
                    $jamIds[] = (int) $j['id'];
                    $label    = 'Jam ke-' . $j['jam_ke'] . ' (' . substr($j['waktu_mulai'], 0, 5) . '–' . substr($j['waktu_selesai'], 0, 5) . ')';
                }
            }
        }
        return ['hariId' => $hariId, 'hariNama' => $today, 'jamIds' => $jamIds, 'label' => $label];
    }

    private function streamPdf(string $html, string $filename): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf', ['Attachment' => false]);
        exit;
    }

    private function slug(string $text): string
    {
        return preg_replace('/[^a-z0-9]+/i', '-', trim($text));
    }
}
