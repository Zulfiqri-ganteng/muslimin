<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Akun staf bertingkat peran (Admin, Operator Sekolah, Waka Hubin, ...).
 *
 * Kolom `admins.role` sudah ada sejak awal tetapi tidak pernah dipakai; migrasi
 * ini menambah 3 kolom pendukung agar akun bisa dikelola dengan aman:
 *
 *   aktif              — nonaktifkan tanpa menghapus (riwayat audit tetap utuh).
 *                        Default 1, jadi SEMUA akun lama tetap aktif.
 *   wajib_ganti_sandi  — 1 = sandi sementara dari admin; pemilik akun dipaksa
 *                        menggantinya saat login pertama. Default 0.
 *   last_login_at      — kapan terakhir masuk (untuk mengenali akun terbengkalai).
 *
 * Idempoten: aman dijalankan ulang bila sempat gagal di tengah jalan.
 * Lihat docs/DESAIN-PKL.md (Tahap 1).
 */
class AddAkunStaf extends Migration
{
    /** @return array<string, array<string, mixed>> */
    private function kolom(): array
    {
        return [
            'aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'role',
            ],
            'wajib_ganti_sandi' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'aktif',
            ],
            'last_login_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'wajib_ganti_sandi',
            ],
        ];
    }

    public function up()
    {
        foreach ($this->kolom() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'admins')) {
                $this->forge->addColumn('admins', [$nama => $definisi]);
            }
        }
    }

    public function down()
    {
        foreach (array_reverse(array_keys($this->kolom())) as $nama) {
            if ($this->db->fieldExists($nama, 'admins')) {
                $this->forge->dropColumn('admins', $nama);
            }
        }
    }
}
