<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pencatat "hari yang sudah diabsen". Karena absensi memakai pola exception-only
 * (hari yang semua hadir tak meninggalkan baris), kita butuh registry tanggal
 * agar rekap TAHU hari mana yang benar-benar sudah dikelola admin.
 *
 * Rekap hanya menghitung tanggal yang ada di sini → hari libur / belum dipakai
 * otomatis TIDAK dihitung (valid untuk penggajian).
 */
class CreateAbsensiHari extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tanggal'    => ['type' => 'DATE'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('tanggal');
        $this->forge->addForeignKey('created_by', 'admins', 'id', '', 'SET NULL');
        $this->forge->createTable('absensi_hari', true, $this->attr);
    }

    public function down()
    {
        $this->forge->dropTable('absensi_hari', true);
    }
}
