<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AbsensiGuruModel;
use App\Models\AuditModel;
use App\Models\HariModel;
use App\Models\JadwalModel;

/**
 * Absensi manual guru per sesi mengajar (berbasis jadwal KBM).
 * Pilih tanggal → sistem tampilkan sesi hari itu (default HADIR) → admin
 * tandai yang telat/izin/sakit/alpa → simpan. Hanya pengecualian disimpan.
 */
class Absensi extends BaseController
{
    /** date('N') 1..7 → nama hari (cocok dengan master 'hari'). */
    private const HARI_NAMA = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    public function index()
    {
        $tanggal = $this->normalTanggal($this->request->getGet('tanggal'));
        $ts      = strtotime($tanggal);

        $hari      = (new HariModel())->byWeekday((int) date('N', $ts));
        $namaHari  = $hari['nama'] ?? (self::HARI_NAMA[(int) date('N', $ts)] ?? '');
        $hariAktif = $hari && (int) $hari['aktif'] === 1;

        $sessions = $hariAktif
            ? (new JadwalModel())->sessionsForHari((int) $hari['id'])
            : [];
        $absen = (new AbsensiGuruModel())->forDate($tanggal);

        // Kelompokkan per guru + gabungkan status yang tersimpan; hitung ringkasan.
        $grup    = [];
        $ringkas = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($sessions as $s) {
            $ex             = $absen[$s['kelas_id'] . '-' . $s['jam_id']] ?? null;
            $s['status']    = $ex['status'] ?? 'hadir';
            $s['jam_masuk'] = $ex && $ex['jam_masuk'] ? substr($ex['jam_masuk'], 0, 5) : '';
            $s['keterangan'] = $ex['keterangan'] ?? '';
            $ringkas[$s['status']] = ($ringkas[$s['status']] ?? 0) + 1;

            $gid = (int) $s['guru_id'];
            if (! isset($grup[$gid])) {
                $grup[$gid] = ['guru_id' => $gid, 'nama' => $s['guru_nama'], 'kode' => $s['kode_guru'], 'sesi' => []];
            }
            $grup[$gid]['sesi'][] = $s;
        }

        return view('admin/absensi/index', [
            'title'     => 'Absensi Guru',
            'tanggal'   => $tanggal,
            'namaHari'  => $namaHari,
            'hariAktif' => $hariAktif,
            'grup'      => array_values($grup),
            'ringkas'   => $ringkas,
            'total'     => count($sessions),
        ]);
    }

    public function save()
    {
        $tanggal = $this->normalTanggal($this->request->getPost('tanggal'));
        $rows    = $this->request->getPost('rows');
        $rows    = is_array($rows) ? $rows : [];

        (new AbsensiGuruModel())->syncDate($tanggal, $rows, session('admin')['id'] ?? null);
        (new AuditModel())->record('update', 'absensi_guru', null, 'Simpan absensi ' . $tanggal);

        return redirect()->to(site_url('admin/absensi') . '?tanggal=' . $tanggal)
            ->with('success', 'Absensi tanggal ' . $tanggal . ' berhasil disimpan.');
    }

    /** Validasi/normalisasi tanggal → Y-m-d; fallback hari ini. */
    private function normalTanggal(?string $raw): string
    {
        $raw = trim((string) $raw);
        $ts  = $raw !== '' ? strtotime($raw) : false;
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
