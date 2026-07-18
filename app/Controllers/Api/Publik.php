<?php

namespace App\Controllers\Api;

use App\Models\AbsensiGuruModel;
use App\Models\AbsensiHariModel;
use App\Models\GuruModel;
use App\Models\HariModel;
use App\Models\JadwalModel;
use App\Models\JamPelajaranModel;
use App\Models\KelasModel;
use App\Models\MataPelajaranModel;
use App\Models\PengumumanModel;
use App\Models\SettingModel;
use App\Models\SiswaModel;

/**
 * Endpoint publik (tanpa token) — cermin dari App\Controllers\Publik untuk web,
 * tetapi menghasilkan JSON. Logika bisnis memakai method model yang sama persis.
 */
class Publik extends BaseApiController
{
    /** GET /api/v1/home — data beranda: identitas sekolah, statistik, pengumuman, konteks "sekarang". */
    public function home()
    {
        $setting = (new SettingModel())->get();

        // Kunci cache SAMA dengan web (Publik::home) supaya angka di aplikasi
        // dan di situs tidak pernah berbeda, dan sekali invalidasi cukup.
        $stats = cache('publik_stats_v2');
        if (! $stats) {
            $stats = [
                'guru'  => (new GuruModel())->countAllResults(),
                'kelas' => (new KelasModel())->countAllResults(),
                'mapel' => (new MataPelajaranModel())->countAllResults(),
                'siswa' => (new SiswaModel())->where('status', 'aktif')->countAllResults(),
            ];
            cache()->save('publik_stats_v2', $stats, 1800);
        }

        return $this->ok([
            'school'        => $this->schoolInfo($setting),
            'stats'         => $stats,
            'flags'         => [
                'form_open'      => (int) ($setting['form_open'] ?? 1) === 1,
                'jadwal_publik'  => $this->jadwalOn($setting),
                'absensi_publik' => (int) ($setting['absensi_publik'] ?? 1) === 1,
            ],
            'form_intro'    => $setting['form_intro'] ?? null,
            'announcements' => array_map(static fn ($p) => [
                'id'         => (int) $p['id'],
                'judul'      => $p['judul'],
                'isi'        => $p['isi'],
                'created_at' => $p['created_at'] ?? null,
            ], (new PengumumanModel())->aktif(5)),
            'now'           => $this->nowContext(),
        ]);
    }

    /** GET /api/v1/jadwal/kelas-options — daftar kelas untuk dropdown. */
    public function kelasOptions()
    {
        if (! $this->jadwalOn((new SettingModel())->get())) {
            return $this->forbidden('Jadwal sedang tidak ditampilkan untuk publik.');
        }
        $out = [];
        foreach ((new KelasModel())->options() as $id => $nama) {
            $out[] = ['id' => (int) $id, 'nama_kelas' => $nama];
        }
        return $this->ok($out);
    }

    /** GET /api/v1/jadwal/guru-options — daftar guru untuk dropdown. */
    public function guruOptions()
    {
        if (! $this->jadwalOn((new SettingModel())->get())) {
            return $this->forbidden('Jadwal sedang tidak ditampilkan untuk publik.');
        }
        $out = [];
        foreach ((new GuruModel())->select('id, kode_guru, nama')->orderBy('nama', 'ASC')->findAll() as $g) {
            $out[] = ['id' => (int) $g['id'], 'kode_guru' => $g['kode_guru'] ?? null, 'nama' => $g['nama']];
        }
        return $this->ok($out);
    }

    /** GET /api/v1/jadwal/kelas/{id} — grid jadwal satu kelas. */
    public function jadwalKelas($id = null)
    {
        $setting = (new SettingModel())->get();
        if (! $this->jadwalOn($setting)) {
            return $this->forbidden('Jadwal sedang tidak ditampilkan untuk publik.');
        }

        $kelas = (new KelasModel())->find((int) $id);
        if (! $kelas) {
            return $this->missing('Kelas tidak ditemukan.');
        }

        $shift = $kelas['shift'] ?? 'pagi';
        $hari  = (new HariModel())->aktifUrut();
        $jam   = (new JamPelajaranModel())->where('shift', $shift)->orderBy('waktu_mulai', 'ASC')->findAll();
        $grid  = (new JadwalModel())->gridForKelas((int) $id);

        return $this->ok([
            'kelas' => ['id' => (int) $kelas['id'], 'nama_kelas' => $kelas['nama_kelas'], 'shift' => $shift],
            'hari'  => $this->mapHari($hari),
            'jam'   => $this->mapJam($jam),
            'cells' => $this->mapCells($grid, 'kelas'),
        ]);
    }

    /** GET /api/v1/jadwal/guru/{id} — grid jadwal mengajar satu guru. */
    public function jadwalGuru($id = null)
    {
        $setting = (new SettingModel())->get();
        if (! $this->jadwalOn($setting)) {
            return $this->forbidden('Jadwal sedang tidak ditampilkan untuk publik.');
        }

        $guru = (new GuruModel())->find((int) $id);
        if (! $guru) {
            return $this->missing('Guru tidak ditemukan.');
        }

        $hari = (new HariModel())->aktifUrut();
        $jam  = (new JamPelajaranModel())->orderBy('shift', 'ASC')->orderBy('waktu_mulai', 'ASC')->findAll();
        $grid = (new JadwalModel())->gridForGuru((int) $id);

        return $this->ok([
            'guru'  => ['id' => (int) $guru['id'], 'kode_guru' => $guru['kode_guru'] ?? null, 'nama' => $guru['nama']],
            'hari'  => $this->mapHari($hari),
            'jam'   => $this->mapJam($jam),
            'cells' => $this->mapCells($grid, 'guru'),
        ]);
    }

    /** GET /api/v1/absensi?tanggal=YYYY-MM-DD — absensi guru harian (publik). */
    public function absensi()
    {
        $setting = (new SettingModel())->get();
        if ((int) ($setting['absensi_publik'] ?? 1) !== 1) {
            return $this->forbidden('Absensi sedang tidak ditampilkan untuk publik.');
        }

        $raw     = trim((string) $this->request->getGet('tanggal'));
        $ts      = $raw !== '' ? strtotime($raw) : time();
        $tanggal = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
        $ts      = strtotime($tanggal);

        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $hariNama  = $hari['nama'] ?? '';
        $hariAktif = $hari && (int) $hari['aktif'] === 1;

        $recorded = (new AbsensiHariModel())->isRecorded($tanggal);
        $sessions = ($hariAktif && $recorded) ? (new JadwalModel())->sessionsForHari((int) $hari['id']) : [];
        $absen    = (new AbsensiGuruModel())->forDate($tanggal);

        $grup    = [];
        foreach ($sessions as $s) {
            $ex     = $absen[$s['kelas_id'] . '-' . $s['jam_id']] ?? null;
            $status = $ex['status'] ?? 'hadir';

            $gid = (int) $s['guru_id'];
            if (! isset($grup[$gid])) {
                $grup[$gid] = ['guru_id' => $gid, 'nama' => $s['guru_nama'], 'kode_guru' => $s['kode_guru'] ?? null, 'sesi' => []];
            }
            $grup[$gid]['sesi'][] = [
                'kelas'         => $s['nama_kelas'],
                'mapel'         => $s['nama_mapel'],
                'jam_ke'        => (int) $s['jam_ke'],
                'waktu_mulai'   => substr((string) $s['waktu_mulai'], 0, 5),
                'waktu_selesai' => substr((string) $s['waktu_selesai'], 0, 5),
                'status'        => $status,
                'jam_masuk'     => $ex && $ex['jam_masuk'] ? substr($ex['jam_masuk'], 0, 5) : null,
                'keterangan'    => $ex['keterangan'] ?? null,
            ];
        }

        // Ringkasan dihitung PER GURU (status harian = status terburuk di
        // antara sesi-sesinya), bukan per sesi — sesuai kebutuhan laporan.
        $ringkas = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($grup as &$g) {
            $harian = 'hadir';
            foreach ($g['sesi'] as $s) {
                $harian = AbsensiGuruModel::worst($harian, $s['status']);
            }
            $g['status_harian'] = $harian;
            $ringkas[$harian]   = ($ringkas[$harian] ?? 0) + 1;
        }
        unset($g);

        // Kehadiran kerja (di luar jadwal) — hanya bila hari sudah tercatat.
        $kerja = $recorded ? (new \App\Models\AbsensiKerjaModel())->forDate($tanggal) : [];

        return $this->ok([
            'tanggal'         => $tanggal,
            'hari_nama'       => $hariNama,
            'hari_aktif'      => $hariAktif,
            'recorded'        => $recorded,
            'total'           => count($sessions),
            'total_guru'      => count($grup),
            'ringkas'         => $ringkas,
            'guru'            => array_values($grup),
            'kehadiran_kerja' => $kerja,
        ]);
    }

    // ===================== HELPER =====================

    private function jadwalOn(array $setting): bool
    {
        return (int) ($setting['jadwal_publik'] ?? 1) === 1;
    }

    private function schoolInfo(array $s): array
    {
        return [
            'name'          => $s['school_name'] ?? '',
            'level'         => $s['school_level'] ?? null,
            'city'          => $s['city'] ?? null,
            'academic_year' => $s['academic_year'] ?? null,
            'address'       => $s['address'] ?? null,
            'phone'         => $s['phone'] ?? null,
            'email'         => $s['email'] ?? null,
            'website'       => $s['website'] ?? null,
            'logo'          => $this->uploadUrl($s['logo'] ?? null),
        ];
    }

    private function mapHari(array $hari): array
    {
        return array_map(static fn ($h) => [
            'id'     => (int) $h['id'],
            'nama'   => $h['nama'],
            'urutan' => (int) ($h['urutan'] ?? 0),
        ], $hari);
    }

    private function mapJam(array $jam): array
    {
        return array_map(static fn ($j) => [
            'id'            => (int) $j['id'],
            'jam_ke'        => (int) $j['jam_ke'],
            'shift'         => $j['shift'] ?? null,
            'waktu_mulai'   => substr((string) $j['waktu_mulai'], 0, 5),
            'waktu_selesai' => substr((string) $j['waktu_selesai'], 0, 5),
            'is_istirahat'  => (int) ($j['is_istirahat'] ?? 0) === 1,
        ], $jam);
    }

    /** Ubah map grid ("hariId-jamId" => sel) menjadi daftar sel untuk Flutter. */
    private function mapCells(array $grid, string $mode): array
    {
        $cells = [];
        foreach ($grid as $key => $r) {
            [$hariId, $jamId] = array_map('intval', explode('-', $key));
            $cell = [
                'hari_id'    => $hariId,
                'jam_id'     => $jamId,
                'kode_mapel' => $r['kode_mapel'] ?? null,
                'nama_mapel' => $r['nama_mapel'] ?? null,
            ];
            if ($mode === 'kelas') {
                $cell['guru_nama'] = $r['guru_nama'] ?? null;
                $cell['kode_guru'] = $r['kode_guru'] ?? null;
            } else {
                $cell['nama_kelas'] = $r['nama_kelas'] ?? null;
            }
            $cells[] = $cell;
        }
        return $cells;
    }

    /** Konteks "sekarang": hari & jam berjalan (sama dengan web). */
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
        return ['hari_id' => $hariId, 'hari_nama' => $today, 'jam_ids' => $jamIds, 'label' => $label];
    }

    /**
     * GET /api/v1/statistik/siswa — jumlah siswa untuk grafik di aplikasi.
     *
     * Hanya AGREGAT (total, per tingkat, per jurusan, jenis kelamin, per tahun
     * masuk). Identitas siswa — nama, alamat, tanggal lahir — TIDAK PERNAH
     * dikirim lewat endpoint publik ini; itu data pribadi anak.
     */
    public function statistikSiswa()
    {
        return $this->ok((new SiswaModel())->statistik());
    }
}
