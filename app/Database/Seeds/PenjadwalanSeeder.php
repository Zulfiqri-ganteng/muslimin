<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * F0 — Data dasar modul Penjadwalan:
 * hari (Senin-Jumat), jam pelajaran (pagi & siang), jurusan, tahun ajaran aktif.
 * Idempoten: pakai ignore(true) / cek existing agar aman dijalankan ulang.
 */
class PenjadwalanSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // ---- HARI ----
        $hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        foreach ($hari as $i => $nama) {
            $this->db->table('hari')->ignore(true)->insert([
                'nama'   => $nama,
                'urutan' => $i + 1,
                'aktif'  => 1,
            ]);
        }

        // ---- JURUSAN ----
        $jurusan = [
            ['kode' => 'TKJT', 'nama' => 'Teknik Jaringan Komputer dan Telekomunikasi'],
            ['kode' => 'MPLB', 'nama' => 'Manajemen Perkantoran dan Layanan Bisnis'],
            ['kode' => 'AKL',  'nama' => 'Akuntansi dan Keuangan Lembaga'],
        ];
        foreach ($jurusan as $j) {
            $this->db->table('jurusan')->ignore(true)->insert($j + [
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ---- TAHUN AJARAN aktif ----
        $existsTa = $this->db->table('tahun_ajaran')->where('tahun', '2026/2027')->where('semester', 'Ganjil')->get()->getRow();
        if (! $existsTa) {
            $this->db->table('tahun_ajaran')->insert([
                'tahun' => '2026/2027', 'semester' => 'Ganjil', 'is_aktif' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ---- JAM PELAJARAN PAGI (durasi 35 menit, sesuai Excel) ----
        // Jam 1-4, ISTIRAHAT, Jam 5-8.
        $pagi = [
            [1, '07:00', '07:35'], [2, '07:35', '08:10'], [3, '08:10', '08:45'], [4, '08:45', '09:20'],
            ['ist', '09:20', '09:40'],
            [5, '09:40', '10:15'], [6, '10:15', '10:50'], [7, '10:50', '11:25'], [8, '11:25', '12:00'],
        ];
        $this->seedJam('pagi', $pagi);

        // ---- JAM PELAJARAN SIANG (durasi 30 menit, sesuai Excel) ----
        $siang = [
            [1, '13:00', '13:30'], [2, '13:30', '14:00'], [3, '14:00', '14:30'], [4, '14:30', '15:00'],
            ['ist', '15:00', '15:20'],
            [5, '15:20', '15:45'], [6, '15:45', '16:10'], [7, '16:10', '16:35'], [8, '16:35', '17:00'],
        ];
        $this->seedJam('siang', $siang);
    }

    /** Sisipkan baris jam_pelajaran satu shift (idempoten via cek shift+jam_ke). */
    private function seedJam(string $shift, array $rows): void
    {
        $now = date('Y-m-d H:i:s');
        $ke  = 0;
        foreach ($rows as $r) {
            $isIstirahat = ($r[0] === 'ist');
            // jam_ke unik per shift: istirahat diberi nomor lanjut agar tetap unik
            $jamKe = $isIstirahat ? (90 + (++$ke)) : (int) $r[0];

            $exists = $this->db->table('jam_pelajaran')
                ->where('shift', $shift)->where('jam_ke', $jamKe)->get()->getRow();
            if ($exists) {
                continue;
            }

            $mulai  = $r[1] . ':00';
            $selesai = $r[2] . ':00';
            $durasi = (strtotime($selesai) - strtotime($mulai)) / 60;

            $this->db->table('jam_pelajaran')->insert([
                'shift'         => $shift,
                'jam_ke'        => $jamKe,
                'waktu_mulai'   => $mulai,
                'waktu_selesai' => $selesai,
                'durasi'        => (int) $durasi,
                'is_istirahat'  => $isIstirahat ? 1 : 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}
