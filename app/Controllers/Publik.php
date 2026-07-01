<?php

namespace App\Controllers;

use App\Models\AbsensiGuruModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\PengumumanModel;
use App\Models\SettingModel;
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
            'title'      => 'Beranda',
            'setting'    => $setting,
            'stats'      => $stats,
            'kelasOpts'  => $this->jadwalPublik($setting) ? (new KelasModel())->options() : [],
            'pengumuman' => (new PengumumanModel())->aktif(5),
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
            return $this->blokir($setting);
        }

        $raw     = trim((string) $this->request->getGet('tanggal'));
        $ts      = $raw !== '' ? strtotime($raw) : time();
        $tanggal = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
        $ts      = strtotime($tanggal);

        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $hariNama  = $hari['nama'] ?? '';
        $hariAktif = $hari && (int) $hari['aktif'] === 1;

        $sessions = $hariAktif ? (new JadwalModel())->sessionsForHari((int) $hari['id']) : [];
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

        return view('public/absensi', [
            'title'     => 'Absensi Guru', 'setting' => $setting,
            'tanggal'   => $tanggal, 'hariNama' => $hariNama, 'hariAktif' => $hariAktif,
            'grup'      => array_values($grup), 'ringkas' => $ringkas, 'total' => count($sessions),
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
            'title' => 'JADWAL KBM — ' . $kelas['nama_kelas'], 'setting' => $setting,
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
            'title' => 'JADWAL MENGAJAR — ' . $guru['nama'], 'setting' => $setting,
            'hari' => $hari, 'jam' => $jam, 'grid' => $grid, 'mode' => 'guru',
        ]), 'Jadwal-Guru-' . $this->slug($guru['nama']));
    }

    // ===================== HELPER =====================
    private function jadwalPublik(array $setting): bool
    {
        return (int) ($setting['jadwal_publik'] ?? 1) === 1;
    }

    private function blokir(array $setting)
    {
        return view('public/jadwal_off', ['title' => 'Jadwal', 'setting' => $setting]);
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
