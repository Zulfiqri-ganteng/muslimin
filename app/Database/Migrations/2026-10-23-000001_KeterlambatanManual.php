<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catat Keterlambatan — siswa yang BELUM TERDAFTAR di Data Siswa bisa dicatat manual (keluhan klien 2026-10-19: guru piket tidak bisa
 * memilih anak yang namanya belum ada di sistem):
 *   - keterlambatan_siswa.manual  — 1 = nama & kelas diketik guru piket (siswa_id kosong). Beda dengan siswa_id kosong karena data
 *     siswanya DIHAPUS kemudian (manual = 0). Setelah anak itu dimasukkan ke Data Siswa, catatannya bisa "ditautkan" (manual → 0).
 *   - keterlambatan_siswa.hp_ortu — nomor HP orang tua untuk siswa manual (tidak ada Data Siswa tempat menyimpannya); dipakai tombol
 *     WhatsApp "Kabari ortu". Siswa terdaftar tetap memakai nomor di Data Siswa.
 * Idempoten.
 */
class KeterlambatanManual extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('keterlambatan_siswa')) {
            return;
        }
        if (! $this->db->fieldExists('manual', 'keterlambatan_siswa')) {
            $this->forge->addColumn('keterlambatan_siswa', [
                'manual' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0, 'after' => 'siswa_id'],
            ]);
        }
        if (! $this->db->fieldExists('hp_ortu', 'keterlambatan_siswa')) {
            $this->forge->addColumn('keterlambatan_siswa', [
                'hp_ortu' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'keterangan'],
            ]);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('keterlambatan_siswa')) {
            return;
        }
        foreach (['manual', 'hp_ortu'] as $kolom) {
            if ($this->db->fieldExists($kolom, 'keterlambatan_siswa')) {
                $this->forge->dropColumn('keterlambatan_siswa', $kolom);
            }
        }
    }
}
