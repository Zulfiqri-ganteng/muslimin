<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Urutan kolom jurusan di cetakan Jadwal Ujian KHUSUS shift siang (audit 2026-11-23: contoh sekolah memakai urutan berbeda per
 * shift — pagi "XII AKL, XII TKJ", siang "XI TKJ, XI AKL" — sehingga satu urutan untuk semua shift mustahil menyamai keduanya).
 *   - jurusan.urutan_ujian       : tetap = urutan PAGI (dan siang bila kolom di bawah kosong).
 *   - jurusan.urutan_ujian_siang : urutan SIANG; kosong = ikut urutan_ujian.
 * HANYA MENAMBAH kolom (project live), idempoten.
 */
class UrutanUjianSiang extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('urutan_ujian_siang', 'jurusan')) {
            $this->forge->addColumn('jurusan', [
                'urutan_ujian_siang' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'urutan_ujian'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('urutan_ujian_siang', 'jurusan')) {
            $this->forge->dropColumn('jurusan', 'urutan_ujian_siang');
        }
    }
}
