<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — penugasan PEMBUAT SOAL per jadwal ujian (Fase 3).
 *
 * Satu baris = satu guru membuat satu set soal untuk satu sesi/jadwal ujian. Jumlah baris per guru dalam sebuah
 * periode = jumlah "Pembuatan Soal" otomatis di honor (masih bisa diubah manual di grid honor).
 * Soft delete jadwal/guru tidak memicu FK, jadi pembersihan dilakukan manual di Admin\Ujian::hapusJadwal dan
 * Admin\Master\Guru::cleanupRelations. Idempoten. Rancangan: docs/DESAIN-HONOR.md.
 */
class HonorPembuatSoal extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('ujian_pembuat_soal')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'jadwal_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['jadwal_id', 'guru_id'], 'pembuat_soal_unik');
            $this->forge->addKey('guru_id');
            $this->forge->addForeignKey('jadwal_id', 'ujian_jadwal', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
            $this->forge->createTable('ujian_pembuat_soal', true, $this->attr);
        }
    }

    public function down()
    {
        $this->forge->dropTable('ujian_pembuat_soal', true);
    }
}
