<?php

namespace App\Controllers;

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
            $jam  = (new JamPelajaranModel())->where('shift', $shift)->orderBy('jam_ke', 'ASC')->findAll();
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
            $jam  = (new JamPelajaranModel())->where('is_istirahat', 0)
                ->orderBy('shift', 'ASC')->orderBy('jam_ke', 'ASC')->findAll();
            $grid = (new JadwalModel())->gridForGuru($guruId);
        }

        return view('public/jadwal_guru', [
            'title' => 'Jadwal Guru', 'setting' => $setting,
            'guruOpts' => $guruOpts, 'guruId' => $guruId, 'guru' => $guru,
            'hari' => $hari, 'jam' => $jam, 'grid' => $grid, 'now' => $this->nowContext(),
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
        $jam  = (new JamPelajaranModel())->where('shift', $kelas['shift'])->orderBy('jam_ke', 'ASC')->findAll();
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
        $jam  = (new JamPelajaranModel())->where('is_istirahat', 0)->orderBy('shift', 'ASC')->orderBy('jam_ke', 'ASC')->findAll();
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
