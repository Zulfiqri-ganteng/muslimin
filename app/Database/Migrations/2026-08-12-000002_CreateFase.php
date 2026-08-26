<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fase Kurikulum Merdeka (A-F) — lookup dasar modul Kurikulum & Pembelajaran
 * (Capaian Pembelajaran dikelompokkan per fase+mapel). Semua 6 fase resmi
 * diisi meski sekolah ini (SMK) praktiknya hanya memakai E (kelas X) & F
 * (kelas XI-XII) — lihat migrasi berikutnya yang menambah kelas.fase_id.
 */
class CreateFase extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode'       => ['type' => 'VARCHAR', 'constraint' => 2],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'urutan'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'deskripsi'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->createTable('fase', true, $this->attr);

        $this->db->table('fase')->insertBatch([
            ['kode' => 'A', 'nama' => 'Fase A', 'urutan' => 1, 'deskripsi' => 'Umumnya kelas I-II SD/sederajat'],
            ['kode' => 'B', 'nama' => 'Fase B', 'urutan' => 2, 'deskripsi' => 'Umumnya kelas III-IV SD/sederajat'],
            ['kode' => 'C', 'nama' => 'Fase C', 'urutan' => 3, 'deskripsi' => 'Umumnya kelas V-VI SD/sederajat'],
            ['kode' => 'D', 'nama' => 'Fase D', 'urutan' => 4, 'deskripsi' => 'Umumnya kelas VII-IX SMP/sederajat'],
            ['kode' => 'E', 'nama' => 'Fase E', 'urutan' => 5, 'deskripsi' => 'Umumnya kelas X SMA/SMK/sederajat'],
            ['kode' => 'F', 'nama' => 'Fase F', 'urutan' => 6, 'deskripsi' => 'Umumnya kelas XI-XII SMA/SMK/sederajat'],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('fase', true);
    }
}
