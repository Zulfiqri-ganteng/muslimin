<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — URUTAN jabatan di rekap, bisa diatur Admin (Pengaturan Honor).
 *
 * Satu baris = satu jabatan yang urutannya diatur sendiri (angka kecil tampil di atas). Jabatan tanpa baris memakai
 * urutan bawaan (kode jabatan baku → nama jabatan → 500 + level), jadi tabel boleh kosong. Tabel terpisah dari
 * honor_panitia_jabatan karena baris di sana dihapus bila nominalnya 0. Tidak ada nama orang atau nominal di migrasi
 * ini (repo publik). Idempoten. Rancangan: docs/DESAIN-HONOR.md bagian 11.
 */
class HonorUrutanJabatan extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('honor_jabatan_urutan')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'jabatan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'urutan'     => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('jabatan_id', 'honor_urutan_satu_per_jabatan');
            $this->forge->addForeignKey('jabatan_id', 'jabatan', 'id', '', 'CASCADE');
            $this->forge->createTable('honor_jabatan_urutan', true, $this->attr);
        }
    }

    public function down()
    {
        $this->forge->dropTable('honor_jabatan_urutan', true);
    }
}
